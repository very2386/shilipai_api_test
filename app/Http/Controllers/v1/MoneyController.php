<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Models\BuyMode;
use App\Models\Company;
use App\Models\DirectSalesPointLog;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\User;
use App\Models\SystemNotice;
use App\Models\PermissionMenu;
use App\Models\ShopPayType;

class MoneyController extends Controller
{
    // 選擇版本
    public function get_buy_mode($shop_id)
    {
        // $buy_modes = BuyMode::where('status','published')->where('id','<',50)->get();

        $basic = BuyMode::whereIn('id',[0,15])->get();
        $plus  = BuyMode::where('id','!=',0)->whereIn('id',[1,2])->where('status','published')->where('id','<',50)->get();
        $pro   = BuyMode::where('id','!=',0)->whereIn('id',[5,6])->where('status', 'published')->where('id', '<', 50)->get();

        $shop_info = Shop::find($shop_id);

        if( $shop_info->buy_mode_id == 0 ){
            if( $pro->count() != 0 ){
                $buy_modes = [
                    ['name' => '基本版 Basic', 'modes' => $basic],
                    ['name' => '進階版 Plus', 'modes' => $plus],
                    ['name' => '專業版 Pro', 'modes' => $pro],
                ];
            }else{
                $buy_modes = [
                    ['name' => '基本版 Basic', 'modes' => $basic],
                    ['name' => '進階版 Plus', 'modes' => $plus],
                ];
            }
        }else{
            $buy_modes = [
                [ 'name' => '基本版 Basic' , 'modes' => $basic ],
                [ 'name' => '進階版 Plus' , 'modes' => $plus ],
            ];
        }

        return response()->json(['status'=>true,'data'=>$buy_modes]);
    }

    // 確認付款建立訂單回傳藍新設定值
    public function make_order($shop_id)
    {
    	// 驗證欄位資料
        $rules     = ['id' => 'required' , 'pay_type' => 'required'];

        $messages = [
            'id.required'       => '請選擇要購買的項目',
            'pay_type.required' => '請選擇付款方式'
        ];

        $validator = Validator::make(request()->all(), $rules,$messages);

        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

    	$HashKey = config('services.newebpay.HashKey');
        $HashIV  = config('services.newebpay.HashIV');

        $oid = strtotime(date('Y-m-d H:i:s')) . rand(0,9) . rand(0,9) . rand(0,9) . rand(0,9);

        $buy_mode_info = BuyMode::find(request('id'));

        $mdata = [
            'MerchantID'      => config('services.newebpay.MerchantID'),                        // 商店代號   
            'RespondType'     => 'JSON',                                                        // 回傳格式
            'TimeStamp'       => time(),                                                        // 時間戳記
            'Version'         => "1.5",                                                         // 串接程式版本
            'MerchantOrderNo' => $oid,                                                          // 商店訂單編號
            'Amt'             => $buy_mode_info->price,                                         // 訂單金額
            'ItemDesc'        => $buy_mode_info->title,                                         // 商品資訊
            'ExpireDate'      => date('Y-m-d' , strtotime("+".env('PAYENDDAY')." day",time())), // 繳費期限
            'LoginType'       => 0,//藍新會員
            'TradeLimit'      => 900,//交易限制秒數
            'ClientBackURL'   => env('DOMAIN_NAME').'/storeData/contract',
            'ReturnURL'       => url('/api/newebpay/pay/return'),
            'NotifyURL'       => url('/api/newebpay/notify/pay/return'),
            'CREDIT'          => request('pay_type') == 'CREDIT' ? 1 : 0,
            'VACC'            => request('pay_type') == 'VACC' ? 1 : 0,
        ];

        $TradeInfo = Self::create_mpg_aes_encrypt($mdata, $HashKey, $HashIV); 
        $TradeSha  = strtoupper(hash("sha256","HashKey=".$HashKey."&".$TradeInfo."&HashIV=".$HashIV)) ;

        $newebpay_info = [
        	'action'     => config('services.newebpay.NEWBPAY'),
        	'MerchantID' => config('services.newebpay.MerchantID'),
        	'TradeInfo'  => $TradeInfo,
        	'TradeSha'   => $TradeSha,
        	'Version'    => "1.5",
        ];

        // 寫入訂單資訊
        $shop_info = Shop::find($shop_id);
        $user_id   = Permission::where('company_id',$shop_info->company_info->id)->where('shop_id',NULL)->where('shop_staff_id',NULL)->first()->user_id;

        $code = '';
        if( $shop_info->buy_mode_id == 0 ){
            // 初次註冊購買需判斷是否有推薦人
            $user_info = auth()->getUser();
            if( $user_info->code ) $code = $user_info->code;
        }else{
            $code = request('phone') ? User::where('phone',request('phone'))->value('id') : NULL; 
        }
        
        $order = new Order;
        $order->oid         = $oid;
        $order->user_id     = $user_id;
        $order->company_id  = $shop_info->company_info->id;
        $order->shop_id     = $shop_info->id;
        $order->buy_mode_id = $buy_mode_info->id;
        $order->code        = $user_id == $code ? NULL : $code;
        $order->price       = $buy_mode_info->price;
        $order->pay_type    = request('pay_type');
        $order->note        = $buy_mode_info->title;
        $order->save();

        return ['status'=>true,'data'=>$newebpay_info];
    }

    // 藍新金流回傳
    public function newebpay_pay_return()
    {
        // 需判斷是第一次購買plus以上的版本後，導頁至對應頁面
        $HashKey = config('services.newebpay.HashKey');
    	$HashIV  = config('services.newebpay.HashIV');

    	$return_data = request()->all() ;

	    // 信用卡一次付清、銀行轉帳
	    $data       = json_decode(Self::create_aes_decrypt($return_data['TradeInfo'], $HashKey , $HashIV),JSON_UNESCAPED_UNICODE) ; 
	    $oid        = $data['Result']['MerchantOrderNo'] ; 
	    $pay_status = $data['Status'] == 'SUCCESS' ? 'Y' : 'N';

        if( $pay_status == 'N' ){
            return redirect( env('DOMAIN_NAME').'/storeData/contract' );
        }
	    
	    $order = Order::where('oid',$oid)->first();
        
        $same_mode = Order::where('shop_id',$order->shop_id)->where('buy_mode_id',$order->buy_mode_id)->where('pay_status','Y')->get();
        if( $same_mode->count() >= 2 ){
            // 此方案購買第二次
            $redirect_url = env('DOMAIN_NAME').'/storeData/contract';
        }else{
            // 第一次購買此方案
            $redirect_url = env('DOMAIN_NAME').'/userintro/guide';
        }

        // return redirect( env('DOMAIN_NAME').'/storeData/contract' );
    	return redirect( $redirect_url );
    }

    // 藍新金流回傳(背景回傳)
    public function newebpay_notify_pay_return()
    {
    	$HashKey = config('services.newebpay.HashKey');
    	$HashIV  = config('services.newebpay.HashIV');

    	$return_data = request()->all() ;

	    // 信用卡一次付清、銀行轉帳
	    $data       = json_decode(Self::create_aes_decrypt($return_data['TradeInfo'], $HashKey , $HashIV),JSON_UNESCAPED_UNICODE) ; 
	    $oid        = $data['Result']['MerchantOrderNo'] ; 
	    $pay_status = $data['Status'] == 'SUCCESS' ? 'Y' : 'N';

	    $order = Order::where('oid',$oid)->first();

	    if( $pay_status == 'N' ){
	        $order->pay_status = 'N';
	        $order->pay_date   = $data['Result']['PayTime'];
	        $order->message    = $data['Message'];
	        $order->save();

	        return response()->json([ 'status' => false , 'msg' => '購買失敗！' ]); 
	    }

	    if( $order && $pay_status == 'Y' ){
	        $pay_date = $data['Result']['PayTime'];

	        // 商家購買方案，需壓上開通日期
	        $company   = Company::where('id',$order->company_id)->first();
	        $buy_mode  = BuyMode::where('id',$order->buy_mode_id)->first();
            $shop_info = Shop::find($order->shop_id);

            $old_buy_mode = $shop_info->buy_mode_id;
	        
	        if( in_array( $buy_mode->id ,[50,51,52,53,54] ) ){
	            // 商家購買簡訊
	            $shop_info->buy_sms    = $shop_info->buy_sms + $buy_mode->free_SMS;
	            $shop_info->sms_notice = 0; // 1提醒簡訊數量不夠0數量充足
	            $shop_info->save(); 
	        }else{
	        	// 方案變更/方案續費
	            $days = ($buy_mode->during/12)*365;

                // 商家記錄期限與購買方案
                $shop_info->buy_mode_id = $buy_mode->id;
                if( $shop_info->deadline == NULL ){
                    // 沒有使用期限
	                $shop_info->deadline = date("Y-m-d 23:59:59",strtotime("+".$days." day",strtotime($data['Result']['PayTime'])));
	            }elseif( $data['Result']['PayTime'] > $shop_info->deadline ){
                    // 付款日期比期限大
	                $shop_info->deadline = date("Y-m-d 23:59:59",strtotime("+".$days." day",strtotime($data['Result']['PayTime'])));
	            }else{
                    // 使用期限比付款日期大
	                $shop_info->deadline = date("Y-m-d 23:59:59",strtotime("+".$days." day",strtotime($shop_info->deadline)));
	            }
                $shop_info->sms_notice = 0;
                $shop_info->gift_sms   = $shop_info->gift_sms + $buy_mode->free_SMS;
                $shop_info->save();

                // 權限變更
                switch ($shop_info->buy_mode_id) {
                    case 1: // plus年繳單人版
                    case 3: // plus月繳單人版
                    case 11:// plus兩年繳單人版
                        $permission = implode(',',PermissionMenu::where('plus',1)->pluck('value')->toArray());
                        break;
                    case 2:  // plus年繳多人版
                    case 4:  // plus月繳多人版
                    case 12: // plus兩年多人版
                        $permission = implode(',',PermissionMenu::where('plus_m',1)->pluck('value')->toArray());
                        break;
                    case 5: // pro年繳單人版
                        $permission = implode(',',PermissionMenu::where('pro',1)->pluck('value')->toArray());
                        break;
                    case 6: // pro年繳多人版
                        $permission = implode(',',PermissionMenu::where('pro_m',1)->pluck('value')->toArray());
                        break;
                }

                // 更換分店權限
                $shop_permission = Permission::where('company_id',$company->id)->where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->first();
                $shop_permission->buy_mode_id = $buy_mode->id;
                $shop_permission->permission  = $permission;
                $shop_permission->save();

                // 更改商家員工的購買方案
                switch ($buy_mode->id) {
                    case 0: // 基本版
                        $pn = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->where('basic', 1)->pluck('value')->toArray());
                        break;
                    case 1: // 進階單人年繳
                    case 2: // 進階多人年繳
                        $pn = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->where('plus', 1)->pluck('value')->toArray());
                        break;
                    case 5: // 專業單人年繳
                    case 6: // 專業多人年繳
                        $pn = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->where('pro', 1)->pluck('value')->toArray());
                        break;
                }

                $shop_staff_permission = Permission::where('company_id',$company->id)
                                                   ->where('shop_id',$shop_info->id)
                                                   ->where('shop_staff_id','!=',NULL)
                                                   ->update(['buy_mode_id' => $buy_mode->id, 'permission' => $pn]);

                // 若是專業版，需補上預設付款方式
                if ($buy_mode->id == 5 || $buy_mode->id == 6) {
                    $pay_type = ['無收現', '現金', 'Line Pay', '街口支付'];
                    foreach ($pay_type as $type) {
                        $data = ShopPayType::where('shop_id', $shop_info->id)->where('name', $type)->first();
                        if (!$data) {
                            $data = new ShopPayType;
                            $data->shop_id = $shop_info->id;
                            $data->name    = $type;
                            $data->save();
                        }
                    }
                }                 
	        }
	       
	        $order->pay_status = $pay_status;
	        $order->pay_date   = $data['Result']['PayTime'];
	        $order->pay_type   = $data['Result']['PaymentType'];
	        $order->pay_return = json_encode($data);
	        $order->save();

	        // 檢查是否有推薦人，若是第一次開通，則需要為推薦人增加點數
	        if( $order->code ){
	            $user_code = User::where('id',$order->code)->first();
                if( $user_code ){
                    $user_code->direct_sales_point += $buy_mode->during/12;
                    $user_code->save();
                }

                // 點數活動紀錄
                $point          = new DirectSalesPointLog;
                $point->user_id = $order->code;
                $point->type    = 'in';
                $point->point   = $buy_mode->during/12;
                $point->content = '推薦 ' . $company->name . ' 商家成功，獲得點數 '.($buy_mode->during/12).' 點';
                $point->save();
	        }

            $permission = Permission::where('company_id',$company->id)->where('shop_id','!=',NULL)->first();

            if( $old_buy_mode == 0 ){
                // 系統訊息
                $url_data = [
                    [
                        'text' => 'Line按鈕款式 GO>',
                        'url'  => '/storeData/lineBtn',
                    ],
                    [
                        'text' => '員工 GO>',
                        'url'  => '/chargeStaff/staffList',
                    ],
                    [
                        'text' => '加值服務 GO>',
                        'url'  => '/chargeService/extra',
                    ],
                    [
                        'text' => '預約設定 GO>',
                        'url'  => '/reservation/setting/ruleSet',
                    ],
                    [
                        'text' => '營業時間 GO>',
                        'url'  => '/storeData/basicData/opentime',
                    ],
                ];

                $content = "";
                switch ($order->buy_mode_id) {
                    case '1':
                        $content = '恭喜您升級成「進階版Plus」方案；啟用「預約功能」需要完成下列5個功能設定；另外，還成功解鎖「優惠活動」、「貼文」、「作品集」功能，讓行銷推廣更靈活囉！';
                        break;
                }
                $notice = new SystemNotice;
                $notice->company_id = $company->id;
                $notice->shop_id    = $permission->shop_id;
                $notice->content    = $content;
                $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
                $notice->save();

            }

            return response()->json([ 'status' => true , 'msg' => '購買成功！' ]) ;
 
        }else{
            return response()->json([ 'status' => false , 'msg' => '購買失敗！' ]) ;
        }
    	
    }

    // 交易資料 AES 加密
    public static function create_mpg_aes_encrypt ($parameter = "" , $key = "", $iv = "") 
    {
        $return_str = '';
        if (!empty($parameter)) {
            //將參數經過 URL ENCODED QUERY STRING
            $return_str = http_build_query($parameter);
        }
        $len = strlen($return_str);
        $pad = 32 - ($len % 32);
        $return_str .= str_repeat(chr($pad), $pad);
        return trim(bin2hex(openssl_encrypt($return_str, 'aes-256-cbc', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv)));
    }

    // 交易資料 AES 解密
    public static function create_aes_decrypt($parameter = "", $key = "", $iv = "")
    {
        $string = openssl_decrypt(hex2bin($parameter),'AES-256-CBC', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv) ; 
        $slast = ord(substr($string, -1));
        $slastc = chr($slast);
        $pcheck = substr($string, -$slast);
        if (preg_match("/$slastc{" . $slast . "}/", $string)) {
            $string = substr($string, 0, strlen($string) - $slast);
            return $string;
        } else {
            return false;
        }
    }
}
