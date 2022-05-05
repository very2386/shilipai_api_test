<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Models\CustomerLoyaltyCardPoint;
use App\Models\CompanyLoyaltyCard;
use App\Models\CompanyLoyaltyCardLimit;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\ShopLoyaltyCard;
use App\Models\ShopLoyaltyCardLimit;

class ShopLoyaltyCardController extends Controller
{
    // 取得商家全部集點卡
    public function shop_loyaltyCard($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_loyaltyCards',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $user_shop    = Shop::find($shop_id);
        $company_info = $user_shop->company_info;

        $loyaltyCards = ShopLoyaltyCard::where('shop_id',$shop_id)->get();
        $card_list = [];
        foreach( $loyaltyCards as $loyaltyCard ){
        
        	$get_count        = $loyaltyCard->customers->count();
        	$used_count       = $loyaltyCard->customers->where('status','Y')->count();
        	$used_percentage  = $get_count == 0 ? 0 : round($used_count/$get_count*100 ,2);

            // 集點率
            $customer_card_ids = $loyaltyCard->customers->pluck('id')->toArray();
            $total_points = CustomerLoyaltyCardPoint::whereIn('customer_loyalty_card_id',$customer_card_ids)->sum('point'); 
            $point_percentage = $get_count == 0 ? 0 : round( ($total_points/($loyaltyCard->full_point*$get_count))*100,2);

            $edit   = true;
            $copy   = true;
            $delete = true;
            
            if( ($loyaltyCard->second_type == 1 && !$loyaltyCard->product_info)  || ($loyaltyCard->second_type == 3 && !$loyaltyCard->service_info) ){
                $status = '已失效';
            }else{
                if( $loyaltyCard->status == 'published' ){
                    if( $loyaltyCard->deadline_type == 4 && $loyaltyCard->end_date && date('Y-m-d') > $loyaltyCard->end_date ){
                        $status = '已過期';
                    }elseif( $loyaltyCard->deadline_type == 4 && date('Y-m-d') < $loyaltyCard->start_date ){
                        $status = '尚未開始'; 
                    }else{
                        $status = '活動中';
                    }
                }else{
                    $status = '暫存';
                }
            }

            $copy   = $copy   ? (in_array('shop_loyaltyCard_copy_btn',$user_shop_permission['permission']) ? true : false) : false;
            $edit   = $edit   ? (in_array('shop_loyaltyCard_edit_btn',$user_shop_permission['permission']) ? true : false) : false;
            $delete = $delete ? (in_array('shop_loyaltyCard_delete_btn',$user_shop_permission['permission']) ? true : false) : false;

            $card_list[] = [
                'id'                => $loyaltyCard->id,
                'name'              => $loyaltyCard->name,
                'view'              => $loyaltyCard->view,
                'get_count'         => $get_count,
                'used_count'        => $used_count,
                'point_percentage'  => $point_percentage,
                'used_percentage'   => $used_percentage,
                'status'            => $status,
                'edit_permission'   => true,//$edit,
                'copy_permission'   => $copy,
                'delete_permission' => $delete,
            ];
        }

        $data = [
            'status'            => true,
            'permission'        => true,
            'create_permission' => in_array('shop_loyaltyCard_create_btn',$user_shop_permission['permission']) ? true : false,
            'edit_permission'   => in_array('shop_loyaltyCard_edit_btn',$user_shop_permission['permission']) ? true : false,
            'delete_permission' => in_array('shop_loyaltyCard_delete_btn',$user_shop_permission['permission']) ? true : false,
            'copy_permission'   => in_array('shop_loyaltyCard_copy_btn',$user_shop_permission['permission']) ? true : false,
            'status_permission' => in_array('shop_loyaltyCard_status_btn',$user_shop_permission['permission']) ? true : false,
            'give_permission'   => in_array('shop_loyaltyCard_give_btn',$user_shop_permission['permission']) ? true : false,
            'data'              => $card_list,
        ];
        
        return response()->json($data);
    }

    // 取得商家指定集點卡
    public function shop_loyaltyCard_info($shop_id,$shop_loyalty_card_id="",$mode="")
    {
        if( $shop_loyalty_card_id ){
            $shop_loyaltyCard_info = ShopLoyaltyCard::find($shop_loyalty_card_id);
            if( !$shop_loyaltyCard_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到集點卡資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_loyaltyCard_info = new ShopLoyaltyCard;
            $type                  = 'create';
        }

        $default_content = "· 無法與其他優惠券、折扣等並用。\n· 部分商品或服務不適用集點卡內容。\n· 處於「已兌換」狀態的集點卡無法再次使用（因誤按而變為「已兌換」狀態的集點卡也無法使用）。";

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_info->id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_loyaltyCard_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $edit = true;
        if( $type == 'edit' && $mode == "" ){
            if (($shop_loyaltyCard_info->second_type == 1 && !$shop_loyaltyCard_info->product_info)
                || ($shop_loyaltyCard_info->second_type == 3 && !$shop_loyaltyCard_info->service_info) ) {
                // 已失效
                $edit = false;
            } else {
                if ($shop_loyaltyCard_info->status == 'published') {
                    if ($shop_loyaltyCard_info->deadline_type == 4 && $shop_loyaltyCard_info->end_date && date('Y-m-d') > $shop_loyaltyCard_info->end_date) {
                        // 已過期
                        $edit = false;
                    } elseif ($shop_loyaltyCard_info->deadline_type == 4 && date('Y-m-d') < $shop_loyaltyCard_info->start_date) {
                        // 尚未開始
                        $edit = true;
                    } else {
                        // 活動中
                        $edit = false;
                    }
                }else{
                    // 暫存狀態，檢查是否已被會員拿取，若被拿取就不可以編輯
                    if ($shop_loyaltyCard_info->customers->count() != 0) {
                        $edit = false;
                    }
                }
            }
        }
        $edit = $edit ? (in_array('shop_loyaltyCard_edit_btn', $user_shop_permission['permission']) ? true : false) : false;
        
        // 集點卡內容
        $loyalty_card_info = $shop_loyalty_card_id ? $shop_loyaltyCard_info : new CompanyLoyaltyCard;

        // 處理集點卡的使用限制
        $limit_service = [];
        $limit_product = []; //(待補)
        if( $loyalty_card_info->limit == 4 ){
            $loyalty_limits = $loyalty_card_info->limit_commodity;
            // 檢查此限制項目是否有在商家的服務或產品內
            $limit_service = $loyalty_limits->where('type','service')->pluck('commodity_id');
            $limit_product = $loyalty_limits->where('type','product')->pluck('commodity_id');

            $shop_limit_service = ShopService::whereIn('company_service_id',$limit_service->pluck('id')->toArray())->pluck('id')->toArray();
        }

        $watermark_img = $shop_info->logo ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $shop_info->logo : '/static/media/logo.204b0ddf.png';
        if( $loyalty_card_info->watermark_img ){
            $watermark_img = env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $loyalty_card_info->watermark_img;
        }

        $data = [
        	'id'                        => $shop_loyaltyCard_info->id,
        	'name'                      => $loyalty_card_info->name,
        	'name_permission'           => in_array('shop_loyaltyCard_'.$type.'_name',$user_shop_permission['permission']) ? true : false,

        	'condition_type'            => $loyalty_card_info->condition_type,
        	'condition'                 => $loyalty_card_info->condition,
        	'condition_permission'      => $edit ? (in_array('shop_loyaltyCard_'.$type.'_condition',$user_shop_permission['permission']) ? true : false) : false,

        	'full_point'                => $loyalty_card_info->full_point,
        	'full_point_permission'     => $edit ? (in_array('shop_loyaltyCard_'.$type.'_full_point',$user_shop_permission['permission']) ? true : false) : false,

        	'first_point'               => $loyalty_card_info->first_point || $loyalty_card_info->first_point === 0 ? (string)$loyalty_card_info->first_point : '0',
        	'first_point_permission'    => $edit ? (in_array('shop_loyaltyCard_'.$type.'_first_point',$user_shop_permission['permission']) ? true : false) : false,

        	'deadline_type'             => $loyalty_card_info->deadline_type,
        	'year'                    	=> $loyalty_card_info->year,
        	'month'                     => $loyalty_card_info->month,
        	'start_date'                => date('c',strtotime($loyalty_card_info->start_date?:date('Y-m-d H:i:s'))),
        	'end_date'                  => date('c',strtotime($loyalty_card_info->end_date?:date('Y-m-d H:i:s'))),
            'deadline_permission'       => $edit ? (in_array('shop_loyaltyCard_'.$type.'_deadline',$user_shop_permission['permission']) ? true : false) : false,

        	'content'            		=> $type == 'create' ? $default_content : $loyalty_card_info->content,
        	'content_permission' 		=> in_array('shop_loyaltyCard_'.$type.'_content',$user_shop_permission['permission']) ? true : false,

        	'background_type'           => $loyalty_card_info->background_type,
        	'background_color'          => $loyalty_card_info->background_color,
        	'background_img'            => $loyalty_card_info->background_img ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$loyalty_card_info->background_img : NULL,
        	'background_permission'     => $edit ? (in_array('shop_loyaltyCard_'.$type.'_background',$user_shop_permission['permission']) ? true : false) : false,

        	'watermark_type'            => (string)$loyalty_card_info->watermark_type,
        	'watermark_img'             => $watermark_img,
        	'watermark_permission'      => $edit ? (in_array('shop_loyaltyCard_'.$type.'_watermark',$user_shop_permission['permission']) ? true : false) : false,
            'watermark_img_default'     => $shop_info->logo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_info->logo : '/static/media/logo.204b0ddf.png',

        	'type'                      => $loyalty_card_info->type,
        	'second_type'               => $loyalty_card_info->second_type,
        	'commodityId'               => (int)$loyalty_card_info->commodityId,
        	'self_definition'           => $loyalty_card_info->self_definition,
        	'price'                     => $loyalty_card_info->price,
        	'consumption'               => $loyalty_card_info->consumption,
        	'limit'                     => (string)$loyalty_card_info->limit,
            'limit_service'             => $limit_service,
            'limit_product'             => $limit_product,
            'limit_total'               => count($limit_service) + count($limit_product),
            'type_permission'           => $edit ? (in_array('shop_loyaltyCard_'.$type.'_type',$user_shop_permission['permission']) ? true : false) : false,

        	'get_limit'                 => $loyalty_card_info->get_limit,
        	'get_limit_minute'          => $loyalty_card_info->get_limit_minute,
        	'get_limit_permission'      => $edit ? (in_array('shop_loyaltyCard_'.$type.'_get_limit',$user_shop_permission['permission']) ? true : false) : false,

        	'discount_limit_type'       => $loyalty_card_info->discount_limit_type,
        	'discount_limit_month'      => $loyalty_card_info->discount_limit_month,
        	'discount_limit_permission' => $edit ? (in_array('shop_loyaltyCard_'.$type.'_discount_limit',$user_shop_permission['permission']) ? true : false) : false,

        	'notice_day'                => $loyalty_card_info->notice_day,
        	'notice_day_permission'     => $edit ? (in_array('shop_loyaltyCard_'.$type.'_notice',$user_shop_permission['permission']) ? true : false) : false,

        	'pending'                   => $loyalty_card_info->status ? $loyalty_card_info->status : 'pending',
        	'pending_permission'        => $edit ? (in_array('shop_loyaltyCard_'.$type. '_pending',$user_shop_permission['permission']) ? true : false) : false,

            'published'                 => $loyalty_card_info->status ? $loyalty_card_info->status : 'pending',
            'published_permission'      => $edit ? (in_array('shop_loyaltyCard_'.$type. '_published',$user_shop_permission['permission']) ? true : false) : false,

            'status'                    => $loyalty_card_info->status ? $loyalty_card_info->status : 'pending',
            'status_permission'         => in_array('shop_loyaltyCard_'.$type.'_status',$user_shop_permission['permission']) ? true : false,
        ];

        $shop_services = ShopServiceController::shop_service_select($shop_id);
        $shop_products = ShopProductController::shop_product_select($shop_id);

        $data = [
            'status'        => true,
            'permission'    => true,
            'shop_services' => $shop_services,
            'shop_products' => $shop_products,
            'data'          => $data
        ];

        return response()->json($data);
    }

    // 儲存商家集點卡資料
    public function shop_loyaltyCard_save($shop_id,$shop_loyalty_card_id="")
    {
        $rules = [ 
            'status' => 'required',
        ];

        $messages = [
            'status.required'              => '缺少上下架資料',
            'name.required'                => '請填寫規則說明',
            'condition_type.required'      => '請選擇給點條件',
            'full_point.required'          => '請選擇集滿點數',
            'first_point.required'         => '請選擇起始點數',
            'deadline_type.required'       => '請選擇有效期限',
            'background_type.required'     => '請選擇背景圖片',
            'background_img.required'      => '請上傳背景圖片',
            'watermark_type.required'      => '請填寫得點樣式',
            'watermark_type.required'      => '請上傳得點圖片',
            'type.required'                => '請選擇集點卡類型',
            'get_limit.required'           => '請選擇連續得點限制',
            'discount_limit_type.required' => '請選擇使用期限',
            'notice_day.required'          => '請選擇失效提醒',
            'condition.required'           => '請輸入多少元集一點',
            'year.required'                => '請選擇年',
            'month.required'               => '請選擇月',
            'start_date.required'          => '請輸入起始時間',
            'end_date.required'            => '請輸入結束時間',
            'background_color.required'    => '請輸入背景顏色',
            'end_date.required'            => '請輸入結束時間',
            'end_date.required'            => '請輸入結束時間',
            'end_date.required'            => '請輸入結束時間',
            'end_date.required'            => '請輸入結束時間',
            'second_type.required'         => '請選擇類型下的選項',
            'consumption.required'         => '請填寫消費金額',
            'limit.required'               => '請選擇使用限制/可抵扣的項目',
            'commodityId.required'         => '請選擇計有的產品或服務',
            'price.required'               => '請輸入價格/折抵金額',
            'self_definition.required'     => '請輸入自定項目'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 上架才需判別
        if( request('status') == 'published' ){
            // 基本資料檢查
            $rules = [ 
                'status'              => 'required', // 狀態
                'name'                => 'required', // 規則說明
                'condition_type'      => 'required', // 給點條件
                'full_point'          => 'required', // 集滿點數
                'first_point'         => 'required', // 起始點數
                'deadline_type'       => 'required', // 有效期限選項
                'background_type'     => 'required', // 背景樣式選項
                'watermark_type'      => 'required', // 得點樣式選項
                'type'                => 'required', // 類型
                'get_limit'           => 'required', // 連續得點限制選項
                'discount_limit_type' => 'required', // 使用期限選項
                'notice_day'          => 'required', // 失效提醒選項
            ];

            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()){
                return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
            }

            // 給點條件選擇消費金額，需填寫多少錢一點
            if( request('condition_type') == 1 ){
                $rules['condition'] = 'required';
            }

            // 有效期限選擇2,3
            if( in_array( request('deadline_type') , [2,3] ) ){
                $rules['year']  = 'required';
                $rules['month'] = 'required';
            }elseif( request('deadline_type') == 4 ){
                $rules['start_date'] = 'required';
                $rules['end_date']  = 'required';
            }

            // 背景圖片選擇上傳圖片，圖片需必填
            if( request('background_type') == 3 ){
                $rules['background_img'] = 'required';
            }else{
                $rules['background_color'] = 'required';
            }

            // 得點樣式選擇上傳圖片，圖片需必填
            if( request('watermark_type') == 2 ){
                $rules['watermark_img'] = 'required';
            }

            // 根據類型檢查必填
            switch (request('type')) {
                case 'free': // 免費體驗
                case 'gift': // 贈品
                    $rules['second_type'] = 'required';
                    break;
                case 'cash': // 現金券
                    $rules['second_type'] = 'required';
                    $rules['limit']       = 'required';
                    $rules['price']       = 'required';                      
                    break;
            }

            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()){
                return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
            }

            switch (request('type')) {
                case 'free': // 免費體驗
                    if( request('second_type') == 3 ) $rules['commodityId']     = 'required';
                    else                              $rules['self_definition'] = 'required';
                    break;
                case 'gift': // 贈品
                    if( request('second_type') == 1 ) $rules['commodityId']     = 'required';
                    else                              $rules['self_definition'] = 'required';
                    break;
                case 'cash': // 現金券
                    if( request('second_type') == 6 ) {
                        $rules['consumption'] = 'required';
                    }                              
                    break;
            }

            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()){
                return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
            }
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 儲存集團集點卡
        // 需判斷購買方案，若是基本和進階，基本上就是直接編輯集團集點卡，多分店則不能編輯集點卡資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){

            if( $shop_loyalty_card_id ){
                $shop_loyalty_card = ShopLoyaltyCard::find($shop_loyalty_card_id);
                $loyalty_card      = CompanyLoyaltyCard::where('id',$shop_loyalty_card->company_loyalty_card_id)->first();
            }else{
                $loyalty_card             = new CompanyLoyaltyCard;
                $loyalty_card->company_id = $company_info->id;
                $shop_loyalty_card        = new ShopLoyaltyCard;
            }

            $month = 0;
            if( request('discount_limit_type') == 6 ){
                $month = request('discount_limit_month');
            } elseif(request('discount_limit_type') == 2) {
                $month = 1;
            } elseif (request('discount_limit_type') == 3) {
                $month = 3;
            } elseif (request('discount_limit_type') == 4) {
                $month = 6;
            } elseif (request('discount_limit_type') == 5) {
                $month = 12;
            }
            
            $loyalty_card->name                 = request('name');
            $loyalty_card->condition_type       = request('condition_type');
            $loyalty_card->condition            = request('condition');
            $loyalty_card->full_point           = request('full_point');
            $loyalty_card->first_point          = request('first_point');
            $loyalty_card->deadline_type        = request('deadline_type');
            $loyalty_card->year                 = request('year');
            $loyalty_card->month                = request('month');
            $loyalty_card->start_date           = date('Y-m-d H:i:s',strtotime(request('start_date')));
            $loyalty_card->end_date             = date('Y-m-d H:i:s',strtotime(request('end_date')));
            $loyalty_card->content              = request('content');
            $loyalty_card->background_type      = request('background_type');
            $loyalty_card->background_color     = request('background_color');
            $loyalty_card->watermark_type       = request('watermark_type');
            $loyalty_card->type                 = request('type');
            $loyalty_card->get_limit            = request('get_limit');
            $loyalty_card->get_limit_minute     = request('get_limit_minute');
            $loyalty_card->discount_limit_type  = request('discount_limit_type');
            $loyalty_card->discount_limit_month = $month;
            $loyalty_card->notice_day           = request('notice_day');
            $loyalty_card->status               = request('status');

            $shop_loyalty_card->name                 = request('name');
            $shop_loyalty_card->condition_type       = request('condition_type');
            $shop_loyalty_card->condition            = request('condition');
            $shop_loyalty_card->full_point           = request('full_point');
            $shop_loyalty_card->first_point          = request('first_point');
            $shop_loyalty_card->deadline_type        = request('deadline_type');
            $shop_loyalty_card->year                 = request('year');
            $shop_loyalty_card->month                = request('month');
            $shop_loyalty_card->start_date           = date('Y-m-d H:i:s', strtotime(request('start_date')));
            $shop_loyalty_card->end_date             = date('Y-m-d H:i:s', strtotime(request('end_date')));
            $shop_loyalty_card->content              = request('content');
            $shop_loyalty_card->background_type      = request('background_type');
            $shop_loyalty_card->background_color     = request('background_color');
            $shop_loyalty_card->watermark_type       = request('watermark_type');
            $shop_loyalty_card->type                 = request('type');
            $shop_loyalty_card->get_limit            = request('get_limit');
            $shop_loyalty_card->get_limit_minute     = request('get_limit_minute');
            $shop_loyalty_card->discount_limit_type  = request('discount_limit_type');
            $shop_loyalty_card->discount_limit_month = $month;
            $shop_loyalty_card->notice_day           = request('notice_day');
            $shop_loyalty_card->status               = request('status');

            switch (request('type')) {
                case 'free':
                    $loyalty_card->second_type     = request('second_type');
                    $loyalty_card->commodityId     = request('commodityId')?:NULL;
                    $loyalty_card->self_definition = request('commodityId') ? NULL : request('self_definition');
                    $shop_loyalty_card->second_type     = request('second_type');
                    $shop_loyalty_card->commodityId     = request('commodityId') ?: NULL;
                    $shop_loyalty_card->self_definition = request('commodityId') ? NULL : request('self_definition');
                    break;
                case 'gift':
                    $loyalty_card->second_type     = request('second_type');
                    $loyalty_card->commodityId     = request('commodityId')?:NULL;
                    $loyalty_card->self_definition = request('commodityId') ? NULL : request('self_definition');
                    $shop_loyalty_card->second_type     = request('second_type');
                    $shop_loyalty_card->commodityId     = request('commodityId') ?: NULL;
                    $shop_loyalty_card->self_definition = request('commodityId') ? NULL : request('self_definition');
                    break;
                case 'cash':
                    $loyalty_card->second_type = request('second_type');
                    $loyalty_card->consumption = request('consumption');
                    $loyalty_card->price       = request('price');
                    $loyalty_card->limit       = request('limit');
                    $shop_loyalty_card->second_type = request('second_type');
                    $shop_loyalty_card->consumption = request('consumption');
                    $shop_loyalty_card->price       = request('price');
                    $shop_loyalty_card->limit       = request('limit');
                    break;
            }
            $shop_loyalty_card->save();
            $loyalty_card->save();

            if( request('type') == 'cash' ){
                // 處理選擇項目
                if( $loyalty_card->limit == 4 && (request('limit_product') || request('limit_service')) ){
                    CompanyLoyaltyCardLimit::where('company_loyalty_card_id',$loyalty_card->id)->delete();
                    $insert = $insert_shop = [];

                    // 限制產品
                    foreach( request('limit_product') as $product ){
                        $insert[] = [
                            'company_id'              => $company_info->id,
                            'company_loyalty_card_id' => $loyalty_card->id,
                            'type'                    => 'product',
                            'commodity_id'            => $product,
                        ];
                        $insert_shop[] = [
                            'shop_id'                 => $shop_id,
                            'shop_loyalty_card_id'    => $shop_loyalty_card->id,
                            'type'                    => 'product',
                            'commodity_id'            => $product,
                        ];
                    }

                    // 限制服務資料
                    $company_service_ids = ShopService::whereIn('id',request('limit_service'))->pluck('company_service_id');
                    foreach(request('limit_service') as $service_id ){
                        $insert[] = [
                            'company_id'              => $shop_info->company_info->id,
                            'company_loyalty_card_id' => $loyalty_card->id,
                            'type'                    => 'service',
                            'commodity_id'            => $service_id,
                        ];
                        $insert_shop[] = [
                            'shop_id'                 => $shop_id,
                            'shop_loyalty_card_id'    => $shop_loyalty_card->id,
                            'type'                    => 'service',
                            'commodity_id'            => $service_id,
                        ];
                    }
                    CompanyLoyaltyCardLimit::insert($insert);
                    ShopLoyaltyCardLimit::insert($insert_shop);
                }
            }

            if (!$shop_loyalty_card_id) {
                // 複製後儲存
                if (request('background_type') == 3 && request('id') != '') {
                    $copy_card = ShopLoyaltyCard::find(request('id'));
                    $file_path = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $copy_card->background_img;
                    $new_pic_name = sha1(uniqid('', true)) . '.jpg';
                    $new_path  = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $new_pic_name;
                    shell_exec("cp " . $file_path . " " . $new_path);

                    $loyalty_card->background_img      = $new_pic_name;
                    $shop_loyalty_card->background_img = $new_pic_name;
                }
            } 

            // 上傳背景照片處理
            if( request('background_type') == 3 && request('background_img') && preg_match('/base64/i',request('background_img')) ){
                $picName = PhotoController::save_base64_photo($shop_info->alias,request('background_img'),$loyalty_card->background_img);
                $loyalty_card->background_img = $picName;
                $shop_loyalty_card->background_img = $picName;
            }elseif( request('background_type') != 3 ){
                if( $loyalty_card->background_img ){
                    $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$loyalty_card->background_img;
                    if(file_exists($filePath)){
                        unlink($filePath);
                    }
                    $loyalty_card->background_img = NULL;
                    $shop_loyalty_card->background_img = NULL;
                }
            } 

            $loyalty_card->save();
            $shop_loyalty_card->save();

            if (!$shop_loyalty_card_id) {
                // 複製後儲存
                if (request('watermark_type') == 2 && request('id') != '') {
                    $copy_card = ShopLoyaltyCard::find(request('id'));
                    $file_path = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $copy_card->watermark_img;
                    $new_pic_name = sha1(uniqid('', true)) . '.jpg';
                    $new_path  = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $new_pic_name;
                    shell_exec("cp " . $file_path . " " . $new_path);

                    $loyalty_card->watermark_img      = $new_pic_name;
                    $shop_loyalty_card->watermark_img = $new_pic_name;
                }
            } 

            // 上傳得點照片處理
            if( request('watermark_type')==2 && request('watermark_img') && preg_match('/base64/i',request('watermark_img')) ){
                $picName = PhotoController::save_base64_photo($shop_info->alias,request('watermark_img'),$loyalty_card->watermark_img);
                $loyalty_card->watermark_img = $picName;
                $shop_loyalty_card->watermark_img = $picName;
            }elseif( request('watermark_type') != 2 ){
                if( $loyalty_card->watermark_img ){
                    $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$loyalty_card->watermark_img;
                    if(file_exists($filePath)){
                        unlink($filePath);
                    }
                    $loyalty_card->watermark_img = NULL;
                    $shop_loyalty_card->watermark_img = NULL;
                }
            } 
 
            $loyalty_card->save();
            $shop_loyalty_card->save();

            if( !$shop_loyalty_card_id ){
                $shop_loyalty_card->shop_id                 = $shop_id;
                $shop_loyalty_card->company_loyalty_card_id = $loyalty_card->id;
                $shop_loyalty_card->status                  = request('status');
                $shop_loyalty_card->save();
            }else{
                $shop_loyalty_card->status = request('status');
                $shop_loyalty_card->save();
            }
        }
        
        return response()->json(['status'=>true,'data'=>$loyalty_card]);
    }

    // 刪除商家集點卡資料
    public function shop_loyaltyCard_delete($shop_id,$shop_loyalty_card_id)
    {
        $shop_info = Shop::find($shop_id);

        $shop_loyalty_card= ShopLoyaltyCard::find($shop_loyalty_card_id);
        if( !$shop_loyalty_card){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到集點卡資料']]]);
        }

        // 檢查集點卡是否再活動中與是否有會員已拿取且未使用
        if( $shop_loyalty_card->status == 'published' ){
            // 檢查是否已被會員拿取，若被拿取就不可以刪除
            if( $shop_loyalty_card->customers->count() != 0 ){
                return response()->json(['status'=>false,'errors'=>['message'=>['無法刪除此項集點卡，因為已經有會員擁有此集點卡']]]);
            }
        }

        // 刪除商家集點卡資料
        // 刪除背景圖片
        if ($shop_loyalty_card->background_img) {
            $filePath = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $shop_loyalty_card->background_img;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        // 刪除得點圖片
        if ($shop_loyalty_card->watermark_img) {
            $filePath = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $shop_loyalty_card->watermark_img;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        ShopLoyaltyCardLimit::where('shop_loyalty_card_id', $shop_loyalty_card_id)->delete();
        $shop_loyalty_card->delete();

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團集點卡
            $company_loyalty_card = CompanyLoyaltyCard::where('id',$shop_loyalty_card->company_loyalty_card_id)->first();
            if( $company_loyalty_card ){
                CompanyLoyaltyCardLimit::where('company_loyalty_card_id',$company_loyalty_card->id)->delete();
                $company_loyalty_card->delete();

                // 刪除背景圖片
                if( $company_loyalty_card->background_img ){
                    $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$company_loyalty_card->background_img;
                    if(file_exists($filePath)){
                        unlink($filePath);
                    }
                }
                
                // 刪除得點圖片
                if( $company_loyalty_card->watermark_img ){
                    $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$company_loyalty_card->watermark_img;
                    if(file_exists($filePath)){
                        unlink($filePath);
                    }
                }
            }
        }

        return response()->json(['status'=>true]);
    }
    
}
