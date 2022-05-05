<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Validator;
use Image;
use App\Models\BuyMode;
use App\Models\MessageLog;
use App\Models\SystemNotice;
use App\Models\Shop;
use App\Models\ShopCoupon;
use App\Models\ShopManagement;
use App\Models\ShopCustomer;
use App\Models\ShopManagementCustomerList;
use App\Models\ShopManagementRefuse;
use App\Models\TransformUrl;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // 顧客經營預估簡訊數量
    public function management_calculate_message($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'message' => 'required', 
        ];

        $messages = [
            'message.required' => '缺少發送通知內容',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_info = Shop::find($shop_id);

        $message = request('message');
        $coupon  = ShopCoupon::find(request('coupon'));

        $message = str_replace('「"商家名稱"」' , $shop_info->name, $message);
        $message = str_replace('「"會員名稱"」' , 'OOOO', $message);
        $message = str_replace('「"下個月份"」' , (string)(date('n')+1).'月', $message);


        if( request('link') ){
            $message = str_replace('「"連結"」' , request('link'), $message);
        }else{
            $message = str_replace('「"連結"」' , '', $message);
        }

        if( $coupon ){
            // 建立縮短網址
            $url = '/store/' . $shop_info->alias . '/member/coupon?select=2';
            $transform_url_code = Controller::get_transform_url_code($url);
            $message = str_replace('「"優惠券"」', ' ' . env('SHILIPAI_WEB') . '/T/' . $transform_url_code . ' ', $message);
        }else{
            $message = str_replace('「"優惠券"」' , '', $message);
        }

        // 訊息通知替換內容
        $message = str_replace('「"服務名稱"」' , $shop_info->name.'-服務名稱', $message);
        
        $message = str_replace('「"預約時間"」' , date('m-d H:i'), $message);
        $message = str_replace('「"預約日期"」' , date('m-d'), $message);
        $message = str_replace('「"隔月"」' , ( date('n')+1 == 13 ? '01' : (string)date('n')+1 ), $message);
        $message = str_replace('「"當月"」' , (string)(date('n')).'月', $message);

        // 問卷模組
        $url = '/s/'.$shop_info->alias.'/c/xxx/q/ooo';
        $transform_url_code = Controller::get_transform_url_code($url); 
        $message = str_replace('「"問卷模組"」' , env('SHILIPAI_WEB'). '/T/'.$transform_url_code.' ', $message);

        if( request('evaluate') && request('evaluate') == 'Y' ){
            $url = '/s/'.$shop_info->alias.'/e/xxx';
            $transform_url_code = Controller::get_transform_url_code($url); 
            $message .= ' ' . env('SHILIPAI_WEB'). '/T/'.$transform_url_code;
        }

        // 再次預約連結
        $url = '/store/'.$shop_info->alias.'/reservation/again/xxxx';
        $transform_url_code = Controller::get_transform_url_code($url); 
        $message = str_replace('「"再次預約的連結"」' , env('SHILIPAI_WEB'). '/T/'.$transform_url_code.' ', $message);

        $count = ceil( mb_strlen($message) / 70 );

        return response()->json(['status' => true , 'message' => $message , 'count' => $count , 'str_length' => mb_strlen($message) ]);
    }

    // 顧客經營發送測試簡訊
    public function management_send_message($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'phone'   => 'required',
            'message' => 'required', 
        ];

        $messages = [
            'phone.required'   => '請填寫手機號碼',
            'message.required' => '缺少發送通知內容',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_info = Shop::find($shop_id);

        $res = Self::send_phone_message(request('phone'),request('message'),$shop_info);

        if( $res['status'] == false){
            return response()->json(['status' => false, 'errors'=>['message'=>$res['msg']] ]);
        }else{
            return response()->json(['status' => true ]);
        }
    }

    // 單次/自動推廣/訊息通知重新發送訊息
    public function management_resend_message($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'log_id' => 'required',
        ];

        $validator = Validator::make(request()->all(), $rules);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $management_log = ShopManagementCustomerList::find(request('log_id'));
        if( !$management_log ) return ['status'=>false , 'errors'=>['message' => '找不到對應發送資料']];

        $shop_customer   = ShopCustomer::find($management_log->shop_customer_id);
        $shop_info       = Shop::find($shop_id);
        $shop_management = ShopManagement::find($management_log->shop_management_id);

        switch ($shop_management->send_type) {
            //1手機與line 2手機 3line 4line優先
            case 1:
                // 手機 
                if( $shop_customer->customer_info && $shop_customer->customer_info->phone ){
                    $res = Self::send_phone_message($shop_customer->customer_info->phone,$management_log->message,$shop_info);
                    if( $res['use'] != 0 ){
                        // 將狀態改為已發送
                        $management_log->status = 'Y';
                        $management_log->sms    = 'Y';
                        $management_log->save();
                    }

                    if( $res['status'] == false ){
                        return response()->json(['status' => false, 'errors'=>['message'=>$res['msg']] ]);
                    }else{
                        return response()->json(['status' => true ]);
                    }
                }                

                // line

                break;
            case 2:
                // 僅手機
                if( $shop_customer->customer_info && $shop_customer->customer_info->phone ){
                    $res = Self::send_phone_message($shop_customer->customer_info->phone,$management_log->message,$shop_info);
                    if( $res['use'] != 0 ){
                        // 將狀態改為已發送
                        $management_log->status = 'Y';
                        $management_log->sms    = 'Y';
                        $management_log->save();
                    }

                    if( $res['status'] == false ){
                        return response()->json(['status' => false, 'errors'=>['message'=>$res['msg']] ]);
                    }else{
                        return response()->json(['status' => true ]);
                    }
                }
                break;
            case 3:
                // 僅line
                break;
            case 4:
                // line 先

                // 手機
                if( $shop_customer->customer_info && $shop_customer->customer_info->phone ){
                    $res = Self::send_phone_message($shop_customer->customer_info->phone,$management_log->message,$shop_info);
                    if( $res['use'] != 0 ){
                        // 將狀態改為已發送
                        $management_log->status = 'Y';
                        $management_log->sms    = 'Y';
                        $management_log->save();
                    }
                    
                    if( $res['status'] == false ){
                        return response()->json(['status' => false, 'errors'=>['message'=>$res['msg']] ]);
                    }else{
                        return response()->json(['status' => true ]);
                    }
                }
                break;
        }
    }

    // 發送驗證碼
    static public function send_verification_code($shop=[])
    {
        // 檢查簡訊格式是否正確
        if(!preg_match("/^09[0-9]{8}$/", request('phone'))) {
            return ['status'=>false , 'errors'=>['message' => '請檢查手機號碼是否輸入正確']];
        }

        $random   = rand(1, 9) . rand(1, 9) .  rand(1, 9) .  rand(1, 9) .  rand(1, 9) .  rand(1, 9);
        $sendword = "「實力派」通知\n 您的驗證碼為：".$random;

        $data = \DB::table('phone_check')->where('phone',request('phone'))->first();
        if( $data ){
            // 需檢查時間，需等待30秒後才可在發送
            if( strtotime(date('Y-m-d H:i:s') ) - strtotime($data->updated_at) < 30 ){
                $wait = 30 - (strtotime(date('Y-m-d H:i:s') ) - strtotime($data->updated_at));
                return ['status'=>false , 'errors'=>['message' => '請等待'.$wait.'秒後才可再次發送簡訊驗證碼']];
            }

            \DB::table('phone_check')
                ->where('phone',request('phone'))
                ->update([ 'phone_check' => $random , 'updated_at' => date('Y-m-d H:i:s') ]);
        }else{
            \DB::table('phone_check')
                ->insert([ 'phone' => request('phone') , 'phone_check' => $random , 'created_at' => date('Y-m-d H:i:s') , 'updated_at' => date('Y-m-d H:i:s') ]);
        }

        $res = Self::send_phone_message(request('phone'),$sendword,$shop);

        if( $res['use'] == 0 ){
            // 發送失敗
            return ['status'=>false ,'errors'=>['message' => 'Send Error']];
        }else{
            // 發送成功
            return ['status'=>true];
        }
    }

    // 簡訊方案
    public function sms_mode($shop_id)
    {
        $models = BuyMode::whereIn('id',[50,51,52,53,54])->get();

        $shop_info = Shop::find($shop_id);

        $sms_logs = MessageLog::where('shop_id',$shop_id)->get();

        $month = round($sms_logs->count() == 0 ? 0 : (time() - strtotime($sms_logs->first()->created_at))/(86400*30)) ;

        $average = $sms_logs->count() == 0 ? 0 : $sms_logs->sum('use') / $month;

        $data = [
            'status'  => true,
            'last'    => $shop_info->company_info->gift_sms+$shop_info->company_info->buy_sms,
            'average' => round($average),
            'data'    => $models,
        ];

        return response()->json($data);
    }

    // 項目排序
    public function item_sort()
    {
    	// 驗證欄位資料
        $rules     = [ 'type' => 'required' , 'sequence' => 'required' ];

        $messages = [
            'type.required'     => '缺少項目',
            'sequence.required' => '缺少排序資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }
        
        $class = 'App\\Models\\'.request('type');
        foreach (request('sequence') as $key => $data) {
            $model = $class::find( $data['id'] );
            $model->sequence = $key+1;
            $model->save();
        }

        return response()->json(['status'=>true]);
    }

    // 發送簡訊通知
    static public function send_phone_message($phone_number='',$sendword='',$shop=[])
    {
        // 先確認商家是否有足夠的簡訊發送數量
        $message_count = (int)ceil(mb_strlen($sendword,'utf-8')/70);
        if( !empty($shop) ){
            $company = $shop->company_info;
            if( $shop->gift_sms + $shop->buy_sms < $message_count ){
                // 簡訊發送記錄
                $log = new MessageLog;
                $log->company_id = $company->id;
                $log->shop_id    = $shop->id;
                $log->phone      = $phone_number;
                $log->content    = $sendword;
                $log->use        = 0;
                $log->save();

                // 右上角提示訊息
                if( $shop->sms_notice == 0 ){
                    $url_data = [
                        [
                            'text' => '購買簡訊 GO>',
                            'url'  => '/storeData/contract',
                        ],
                    ];
                    $notice = new SystemNotice;
                    $notice->company_id = $company->id;
                    $notice->shop_id    = $shop->id;
                    $notice->content    = '簡訊剩餘不足囉';
                    $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
                    $notice->save();

                    $shop->sms_notice = 2;
                    $shop->save();
                }
               
                return ['status'=>false,'use' => 0,'msg'=>'請檢查簡訊餘額是否充足'];
                exit;
            }
        }

        // 先取得剩餘簡訊數
        $url   = 'http://smsapi.mitake.com.tw/api/mtk/SmQuery'; 
        $data  = 'username='.config('services.phone.username');
        $data .= '&password='.config('services.phone.password');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);
        $before = (int)explode("=",$output)[1];
        
        // url
        $url  = 'http://smsapi.mitake.com.tw/api/mtk/SmSend'; 
        $url .= '?CharsetURL=UTF-8';
        
        // parameters
        $data  = 'username='.config('services.phone.username'); 
        $data .= '&password='.config('services.phone.password');
        $data .= '&dstaddr='.$phone_number; 
        $data .= '&smbody='.$sendword;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);

        // 先取得剩餘簡訊數
        $url  = 'http://smsapi.mitake.com.tw/api/mtk/SmQuery'; 
        $data = 'username='.config('services.phone.username');
        $data .= '&password='.config('services.phone.password');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);

        $after = (int)explode("=",$output)[1];

        $use   = $before - $after;

        if( $use == 0 ) return ['status'=>false,'use' => 0,'msg'=>'請檢查手機號碼或發送內容是否都有填寫'];

        if( !empty($shop) ){
            $company = $shop->company_info;
            // 扣除簡訊
            if( $shop->gift_sms >= $use ){
                $shop->gift_sms = $shop->gift_sms - $use;
            }else{
                // 先將贈送的簡訊扣光
                $used = $use - $shop->gift_sms;
                if( $shop->gift_sms != 0 ){
                    $shop->gift_sms = 0 ;    
                }

                $shop->buy_sms = $shop->buy_sms - $used;
            }
            $shop->save();

            // 簡訊不足50 20則通知
            if( $shop->buy_sms + $shop->gift_sms < 50 || $shop->buy_sms + $shop->gift_sms < 20 ){
                // 將shop的sms_notice狀態修改
                $sys_notice = false;
                if( $shop->buy_sms + $shop->gift_sms < 50 
                        && $shop->buy_sms + $shop->gift_sms >= 20 
                        && $shop->sms_notice == 0 ){
                    $shop->sms_notice = 1;
                    $shop->save(); 
                    $sys_notice = true;  
                }elseif( $shop->buy_sms + $shop->gift_sms < 20 && $shop->sms_notice != 0 ){
                    $shop->sms_notice = 2;
                    $shop->save(); 
                    $sys_notice = true;  
                }

                if( $sys_notice ){
                    $url_data = [
                        [
                            'text' => '購買簡訊 GO>',
                            'url'  => '/storeData/contract',
                        ],
                    ];
                    $notice = new SystemNotice;
                    $notice->company_id = $company->id;
                    $notice->shop_id    = $shop->id;
                    $notice->content    = '簡訊剩餘不足「'.( $shop->buy_sms + $shop->gift_sms < 20 ? 20 : 50 ).'則」囉！';
                    $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
                    $notice->save();
                }
            }

            // 簡訊發送記錄
            $log = new MessageLog;
            $log->company_id = $company->id;
            $log->shop_id    = $shop->id;
            $log->phone      = $phone_number;
            $log->content    = $sendword;
            $log->use        = $use;
            $log->save();

            
        }

        return ['status'=>true,'use' => ( $before - $after )];
    }

    // 中英文字擷取
    static public function cut_str($string,$start,$strlen)
    {
        //把'&nbsp;'先轉成空白
        $str = str_replace('&nbsp;', ' ', $string);
     
        $output_str_len = 0;  // 累計要輸出的擷取字串長度
        $output_str     = ''; // 要輸出的擷取字串
        $now_len        = 0;  // 記錄目前文字長度
         
        //逐一讀出原始字串每一個字元
        for($i=$start; $i<strlen($str); $i++)  {
            //擷取字數已達到要擷取的字串長度，跳出回圈
            if($output_str_len >= $strlen){
                break;
            }

            //取得目前字元的ASCII碼
            $str_bit = ord(substr($str, $i, 1));
    
            if( $now_len <= $strlen ){
                if($str_bit  <  128)  {
                    //ASCII碼小於 128 為英文或數字字符
                    $output_str_len += 1; //累計要輸出的擷取字串長度，英文字母算一個字數
                    $output_str .= substr($str, $i, 1); //要輸出的擷取字串

                    $now_len += 2;
        
                }elseif($str_bit  >  191  &&  $str_bit  <  224)  {
                    //第一字節為落於192~223的utf8的中文字(表示該中文為由2個字節所組成utf8中文字)
                    $output_str_len += 2; //累計要輸出的擷取字串長度，中文字需算二個字數
                    $output_str .= substr($str, $i, 2); //要輸出的擷取字串
                    $i++;

                    $now_len += 1;
        
                }elseif($str_bit  >  223  &&  $str_bit  <  240)  {
                    if( $i >= $strlen || $strlen == 1 ){
                        break;
                    }

                    //第一字節為落於223~239的utf8的中文字(表示該中文為由3個字節所組成的utf8中文字)
                    $output_str_len += 2; //累計要輸出的擷取字串長度，中文字需算二個字數


                    $output_str .= substr($str, $i, 3); //要輸出的擷取字串
                    $i+=2;

                    $now_len += 1;

        
                }elseif($str_bit  >  239  &&  $str_bit  <  248)  {

                    //第一字節為落於240~247的utf8的中文字(表示該中文為由4個字節所組成的utf8中文字)
                    $output_str_len += 2; //累計要輸出的擷取字串長度，中文字需算二個字數
                    $output_str .= substr($str, $i, 4); //要輸出的擷取字串
                    $i+=3;
                    $now_len += 1;
                }
            }
        }
         
        //要輸出的擷取字串為空白時，輸出原始字串
        return ($output_str == '') ? '' : $output_str;

    }

    // 建立並取得轉址資料
    static public function get_transform_url_code($url)
    {
        // 先檢查是否已經有一樣的轉址資料
        $transform = TransformUrl::where('redirect_url',$url)->first();
        if( !$transform ){
            // 建立新的轉址資料
            // 檢查是否重複ID
            do{
                $pattern = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                $key = '';
                for($i=0;$i<4;$i++)   
                {   
                    $key .= $pattern[mt_rand(0,61)];    //生成php隨機數   
                }

                // 隨機20碼檢查
                $check_id = TransformUrl::where('code',$key)->first();
            }while($check_id);

            $transform = new TransformUrl ;
            $transform->code         = $key;
            $transform->redirect_url = $url;
            $transform->save();
        }

        return $transform->code;
    }
    	
}
