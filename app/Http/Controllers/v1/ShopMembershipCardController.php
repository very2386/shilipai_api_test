<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\CompanyMembershipCard;
use App\Models\Shop;
use App\Models\ShopMembershipCard;
use App\Models\ShopMembershipCardRole;
use App\Models\ShopMembershipCardRoleLimit;
use App\Models\ShopService;
use Illuminate\Http\Request;
use Validator;

class ShopMembershipCardController extends Controller
{
    // 取得商家全部會員卡
    public function shop_membershipCards($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'create_permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_membershipCards',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'create_permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $membership_cards = ShopMembershipCard::where('shop_id',$shop_id)->get();

        $all = [];
        foreach( $membership_cards as $membership_card ){
            $status = '尚未開始';
            $edit   = true;
            $delete = true;
            $copy   = true;

            if( $membership_card->during_type == 1 || ( $membership_card->during_type == 2 && date('Y-m-d H:i:s') <= $membership_card->end_date ) ){
                // 無期限或是還在活動期限內
                if( $membership_card->status == 'published' && $membership_card->during_type == 1){
                    $status = '活動中';
                }elseif( $membership_card->status == 'published' && ( $membership_card->during_type == 2 && date('Y-m-d H:i:s') >= $membership_card->start_date )  ){
                    $status = '活動中';
                }elseif( $membership_card->during_type == 1 && $membership_card->status == 'pending' ){
                    $status = '下架';
                }

                $copy   = $copy   ? (in_array('shop_membershipCard_copy_btn',$user_shop_permission['permission']) ? true : false) : false;
                $edit   = $edit   ? (in_array('shop_membershipCard_edit_btn',$user_shop_permission['permission']) ? true : false) : false;
                $delete = $delete ? (in_array('shop_membershipCard_delete_btn',$user_shop_permission['permission']) ? true : false) : false;

            }else{
                $status = '已過期';
            }

            $date = '';
            if( $membership_card->card_during_type == 1 ){
                $date = '無期限';
            }elseif( $membership_card->card_during_type == 2 ){
                $date = '顧客購買日起算'
                        .($membership_card->card_year && $membership_card->card_year != 0 ? $membership_card->card_year . '年' : '')
                        .($membership_card->card_month && $membership_card->card_month != 0 ? $membership_card->card_month . '個月' : '');
            }elseif( $membership_card->card_during_type == 3 ){
                $date = substr($membership_card->card_start_date,0,16) . ' 至 ' . substr($membership_card->card_end_date,0,16);
            }

            $all[] =  [
                'id'                => $membership_card->id,
                'name'              => $membership_card->name,
                'price'             => $membership_card->price,
                'date'              => $date,
                'status'            => $status,
                'edit_permission'   => true,//$edit,
                'delete_permission' => $delete,
                'copy_permission'   => $copy,
            ];
        }

        $data = [
            'status'            => true,
            'permission'        => true,
            'all'               => $all, 
            'create_permission' => in_array('shop_membershipCard_create_btn',$user_shop_permission['permission']) ? true : false,
        ];

        return response()->json($data);
    }

    // 新增｜編輯 商家會員卡資料
    public function shop_membershipCard_info($shop_id,$shop_membership_card_id="",$mode="")
    {
        if( $shop_membership_card_id ){
            $shop_membershop_card = ShopMembershipCard::find($shop_membership_card_id);
            if( !$shop_membershop_card ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員卡資料']]]);
            }
            $type = 'edit';
        }else{
            $type        = 'create';
            $shop_membershop_card = new ShopMembershipCard;
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_info->id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_membershipCard_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $edit = true;
        if( $type == 'edit' && $mode == "" ){
            if( $shop_membershop_card->during_type == 1 || ( $shop_membershop_card->during_type == 2 && date('Y-m-d H:i:s') <= $shop_membershop_card->end_date ) ){
                // 無期限或是還在活動期限內
                if( ($shop_membershop_card->during_type == 1 && $shop_membershop_card->status == 'published') 
                    || ( $shop_membershop_card->during_type == 2 && date('Y-m-d H:i:s') >= $shop_membershop_card->start_date )  ){
                    // 活動中
                    $edit = false;
                }elseif( $shop_membershop_card->status == 'pending'){
                    // 下架
                    $edit = true;
                    // 暫存狀態，檢查是否已被會員拿取，若被拿取就不可以編輯
                    if ($shop_membershop_card->customers->count() != 0) {
                        $edit = false;
                    }
                }
            }else{
                // 已過期
                $edit = false;
            }
        }
        $edit = $edit ? (in_array('shop_membershipCard_edit_btn', $user_shop_permission['permission']) ? true : false) : false;
        
        // 會員卡內容
        $membershop_card_info = $shop_membership_card_id ? $shop_membershop_card : new ShopMembershipCard;

        if( $membershop_card_info->roles->count() ){
            $roles = $membershop_card_info->roles;

            foreach( $roles as $role ){
                if( $role->type == 1 || $role->type == 2 ){
                    // 會員卡金｜折扣
                    $limit_service = [];
                    $limit_product = []; //(待補)
                    
                    $role_limits = $role->limit_commodity;
                    // 檢查此限制項目是否有在商家的服務或產品內
                    $limit_service = $role_limits->where('type','service');
                    $limit_product = $role_limits->where('type','product');

                    $limit_service = ShopService::whereIn('id',$limit_service->pluck('commodity_id')->toArray())->pluck('id')->toArray();

                    $role->select_service = '';
                    $role->select_product = '';
                    $role->limit_service  = $limit_service;
                    $role->limit_product  = $limit_product;
                    $role->limit_total    = count($role->limit_service) + count($role->limit_product);

                    unset( $role->limit_commodity );
                    
                }else{
                    // 專屬優惠
                    $role_limits = $role->limit_commodity;
                    // 檢查此限制項目是否有在商家的服務或產品內
                    $limit_service = $role_limits->where('type', 'service');
                    $limit_product = $role_limits->where('type', 'product');

                    $role->select_service = $role->limit == 5 ? $limit_service->first()->commodity_id : '';
                    $role->select_product = $role->limit == 6 ? $limit_product->first()->commodity_id : '';
                    $role->limit_service  = [];
                    $role->limit_product  = [];
                    $role->limit_total    = 0;

                    unset($role->limit_commodity);
                }
            }

        }else{
            $roles = [
                [
                    'id'               => '',
                    'type'             => '',
                    'price'            => '',
                    'discount'         => '',
                    'limit'            => 1,
                    'limit_service'    => [],
                    'limit_product'    => [],
                    'select_service'   => '',
                    'select_product'   => '',
                    'limit_total'      => 0,
                ],
            ];
        }

        $membership_card = [
            'id'                          => $membershop_card_info->id,
            
            'name'                        => $membershop_card_info->name,
            'name_permission'             => in_array('shop_membershipCard_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'tag_name'                    => $membershop_card_info->tag_name,
            'tag_name_permission'         => in_array('shop_membershipCard_'.$type.'_tag_name',$user_shop_permission['permission']) ? true : false,
            'price'                       => $membershop_card_info->price,
            'price_permission'            => $edit ? (in_array('shop_membershipCard_'.$type.'_price',$user_shop_permission['permission']) ? true : false) : false,

            'count_type'                  => $membershop_card_info->count_type?:1,
            'count_type_permission'       => $edit ? (in_array('shop_membershipCard_'.$type.'_count',$user_shop_permission['permission']) ? true : false) : false,
            'count'                       => $membershop_card_info->count,
            'count_permission'            => $edit ? (in_array('shop_membershipCard_'.$type.'_count',$user_shop_permission['permission']) ? true : false) : false,
            
            'during_type'                 => $membershop_card_info->during_type?:1,
            'during_type_permission'      => $edit ? (in_array('shop_membershipCard_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'start_date'                  => date('c',strtotime($membershop_card_info->start_date?:date('Y-m-d H:i:s'))),
            'start_date_permission'       => $edit ? (in_array('shop_membershipCard_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'end_date'                    => date('c',strtotime($membershop_card_info->end_date?:date('Y-m-d H:i:s'))),
            'end_date_permission'         => $edit ? (in_array('shop_membershipCard_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'show_day'                    => $membershop_card_info->show_day,
            'show_day_permission'         => $edit ? (in_array('shop_membershipCard_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,

            'card_during_type'            => $membershop_card_info->card_during_type?:1,
            'card_during_type_permission' => $edit ? (in_array('shop_membershipCard_'.$type.'_card_date',$user_shop_permission['permission']) ? true : false) : false,
            'card_year'                   => $membershop_card_info->card_year || $membershop_card_info->card_year === 0 ? (string)$membershop_card_info->card_year : '0',
            'card_year_permission'        => $edit ? (in_array('shop_membershipCard_'.$type.'_card_date',$user_shop_permission['permission']) ? true : false) : false,
            'card_month'                  => $membershop_card_info->card_month || $membershop_card_info->card_month === 0 ? (string)$membershop_card_info->card_month : '0',
            'card_month_permission'       => $edit ? (in_array('shop_membershipCard_'.$type.'_card_date',$user_shop_permission['permission']) ? true : false) : false,
            'card_start_date'             => date('c',strtotime($membershop_card_info->card_start_date?:date('Y-m-d H:i:s'))),
            'card_start_date_permission'  => $edit ? (in_array('shop_membershipCard_'.$type.'_card_date',$user_shop_permission['permission']) ? true : false) : false,
            'card_end_date'               => date('c',strtotime($membershop_card_info->card_end_date?:date('Y-m-d H:i:s'))),
            'card_end_date_permission'    => $edit ? (in_array('shop_membershipCard_'.$type.'_card_date',$user_shop_permission['permission']) ? true : false) : false,

            'use_coupon'                  => $membershop_card_info->use_coupon || $membershop_card_info->use_coupon === 0 ? (string)$membershop_card_info->use_coupon : '0',
            'use_coupon_permission'       => $edit ? (in_array('shop_membershipCard_'.$type.'_use_condition',$user_shop_permission['permission']) ? true : false) : false,
            'use_topUp'                   => $membershop_card_info->use_topUp || $membershop_card_info->use_topUp === 0 ? (string)$membershop_card_info->use_topUp : '0',
            'use_topUp_permission'        => $edit ? (in_array('shop_membershipCard_'.$type.'_use_condition',$user_shop_permission['permission']) ? true : false) : false,

            'status'                      => $membershop_card_info->status ? $membershop_card_info->status : 'pending',
            'status_permission'           => in_array('shop_membershipCard_'.$type.'_status',$user_shop_permission['permission']) ? true : false,

            'roles'                       => $roles, 
            'roles_permission'            => $edit ? (in_array('shop_membershipCard_'.$type.'_roles',$user_shop_permission['permission']) ? true : false) : false,
        ];

        $shop_services = ShopServiceController::shop_service_select($shop_id);
        $shop_products = ShopProductController::shop_product_select($shop_id);

        $data = [
            'status'        => true,
            'permission'    => true,
            'shop_services' => $shop_services,
            'shop_products' => $shop_products,
            'data'          => $membership_card
        ];

        return response()->json($data);
    }

    // 儲存會員卡資料
    public function shop_membershipCard_save($shop_id,$shop_membership_card_id="")
    {
        $rules = [ 
            'status' => 'required',
        ];

        $messages = [
            'status.required'          => '缺少上下架資料',
            'name.required'            => '請填寫名稱',
            'price'                    => '請填寫售價',
            'during_type.required'     => '請選擇起迄時間',
            'count_type.required'      => '請選擇數量',
            'count.required'           => '請輸入數量',
            'use_coupon.required'      => '請選擇優惠券是否可以折抵',
            'use_topUp.required'       => '請選擇會員卡金是否可以折抵'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 基本資料檢查
        $rules = [ 
            'status'      => 'required',
            'name'        => 'required',
            'price'       => 'required',
            'during_type' => 'required',
            'use_coupon'  => 'required',
            'use_topUp'   => 'required',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 販售起迄資料判斷
        if( request('during_type') == 2 ){
            if( request('start_date') == '' ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['請選擇起始時間']]]);
            if( request('end_date') == '' ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['請選擇結束時間']]]);
            if( request('show_day') == '' ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['請選擇自動上架選項']]]);
        }

        // 規則參數判斷
        $break          = false;
        $discount_info  = [];
        foreach( request('roles') as $role ){
            // second_type  1:贈品從既有的2:贈品自定3體驗從既有的4:體驗自定
            switch ($role['type']) {
                case 1:// 現金折價
                    if( $role['price'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請輸入現金折價金額']];
                    }
                    if( $role['limit'] == '' ){
                        $break = true;
                        $errors = ['message'=>['現金折價設定，請選擇使用限制']];
                    }
                    if( $role['limit'] == 4 && empty($role['limit_service']) ){
                        $break = true;
                        $errors = ['message'=>['現金折價設定，請選擇使用限制的項目']];
                    } 
                    break; 
                case 2:// 折扣
                    if( $role['discount'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請輸入折扣數']];
                    }
                    if( $role['limit'] == '' ){
                        $break = true;
                        $errors = ['message'=>['折扣設定，請選擇使用限制']];
                    }
                    if( $role['limit'] == 4 && empty($role['limit_service']) ){
                        $break = true;
                        $errors = ['message'=>['折扣設定，請選擇使用限制的項目']];
                    }
                    $discount_info[] = $role;
                    break;
                case 3:// 專屬優惠
                    if( $role['price'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請輸入專屬優惠金額']];
                    }
                    if( $role['limit'] == '' ){
                        $break = true;
                        $errors = ['message'=>['專屬優惠設定，請選擇使用限制']];
                    }
                    if( $role['limit'] == 5 && $role['select_service'] == '' ){
                        $break = true;
                        $errors = ['message'=>['專屬優惠設定，請選擇使用限制的服務']];
                    }
                    if ($role['limit'] == 6 && $role['select_product'] == '' ) {
                        $break = true;
                        $errors = ['message' => ['專屬優惠設定，請選擇使用限制的產品']];
                    }
                    break; 
            }
            if( $break ) break;
        }

        if( $break ) return response()->json([ 'status' => false , 'errors' => $errors ]);

        // 檢查是否有兩個折扣
        if( count($discount_info) >= 2 ){
            $selected_service = [];
            $break = false;
            foreach( $discount_info as $dinfo ){
                foreach( $dinfo['limit_service'] as $ls ){
                    if( !in_array($ls,$selected_service) ) $selected_service[] = $ls;
                    else{
                        $shop_service_info = ShopService::where('id',$ls)->first();
                        $msg = $shop_service_info->name . '出現在兩個折扣內，請修正';
                        $break = true;
                    }

                    if( $break ) break;
                }

                if( $break ){
                    
                    break;
                }
            }

            if( $break ) return response()->json([ 'status' => false , 'errors' => ['message'=>[$msg]] ]);
        }

        $shop_info = Shop::find($shop_id);

        // 儲存資料
        // 需判斷購買方案，若是基本和進階，基本上就是直接編輯集團優惠券，多分店則不能編輯優惠券資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){

            if( $shop_membership_card_id ){
                $shop_membership_card = ShopMembershipCard::find($shop_membership_card_id);
                $membership_card      = CompanyMembershipCard::where('id',$shop_membership_card->company_membership_card_id)->first();
            }else{
                $membership_card               = new CompanyMembershipCard;
                $membership_card->company_id   = $shop_info->company_info->id;
                $shop_membership_card          = new ShopMembershipCard;
                $shop_membership_card->shop_id = $shop_id;
            }

            $membership_card->name             = request('name');
            $membership_card->tag_name         = request('tag_name');
            $membership_card->price            = request('price');
            $membership_card->count_type       = request('count_type');
            $membership_card->count            = request('count');
            $membership_card->during_type      = request('during_type');
            $membership_card->start_date       = request('during_type') == 2 ? request('start_date') : NULL;
            $membership_card->end_date         = request('during_type') == 2 ? request('end_date') : NULL;
            $membership_card->show_day         = request('during_type') == 2 ? request('show_day') : NULL;
            $membership_card->card_during_type = request('card_during_type');
            $membership_card->card_year        = request('card_during_type') == 2 ? request('card_year') : NULL;
            $membership_card->card_month       = request('card_during_type') == 2 ? request('card_month') : NULL;
            $membership_card->card_start_date  = request('card_during_type') == 3 ? request('card_start_date') : NULL;
            $membership_card->card_end_date    = request('card_during_type') == 3 ? request('card_end_date') : NULL;
            $membership_card->use_coupon       = request('use_coupon');
            $membership_card->use_topUp        = request('use_topUp');
            $membership_card->status           = request('during_type') == 1 ? request('status') : 'published';
            $membership_card->save();

            $shop_membership_card->company_membership_card_id = $membership_card->id;
            $shop_membership_card->name                       = request('name');
            $shop_membership_card->tag_name                   = request('tag_name');
            $shop_membership_card->price                      = request('price');
            $shop_membership_card->count_type                 = request('count_type');
            $shop_membership_card->count                      = request('count');
            $shop_membership_card->during_type                = request('during_type');
            $shop_membership_card->start_date                 = request('during_type') == 2 ? (request('start_date') > request('end_date') ? request('end_date') : request('start_date')) : NULL;
            $shop_membership_card->end_date                   = request('during_type') == 2 ? (request('start_date') < request('end_date') ? request('end_date') : request('start_date')) : NULL;
            $shop_membership_card->show_day                   = request('during_type') == 2 ? request('show_day') : NULL;
            $shop_membership_card->card_during_type           = request('card_during_type');
            $shop_membership_card->card_year                  = request('card_during_type') == 2 ? request('card_year') : NULL; 
            $shop_membership_card->card_month                 = request('card_during_type') == 2 ? request('card_month') : NULL;
            $shop_membership_card->card_start_date            = request('card_during_type') == 3 ? (request('card_start_date') > request('card_end_date') ? request('card_end_date') : request('card_start_date')) : NULL;
            $shop_membership_card->card_end_date              = request('card_during_type') == 3 ? (request('card_start_date') < request('card_end_date') ? request('card_end_date') : request('card_start_date')) : NULL;
            $shop_membership_card->use_coupon                 = request('use_coupon');
            $shop_membership_card->use_topUp                  = request('use_topUp');
            $shop_membership_card->status                     = request('during_type') == 1 ? request('status') : 'published';
            $shop_membership_card->save();

            // 規則儲存
            $role_id = [];
            foreach( request('roles') as $role ){
                if( $role['id'] != '' && $shop_membership_card_id ){
                    $role_data = ShopMembershipCardRole::find($role['id']);
                }else{
                    $role_data = new ShopMembershipCardRole;
                    $role_data->shop_id                 = $shop_info->id;
                    $role_data->shop_membership_card_id = $shop_membership_card->id;
                }

                $role_data->type     = $role['type'];
                $role_data->price    = $role['type'] == 1 || $role['type'] == 3 ? $role['price'] : NULL;
                $role_data->discount = $role['type'] == 2 ? $role['discount'] : NULL;
                $role_data->limit    = $role['limit'];
                $role_data->save();

                $role_id[] = $role_data->id; 

                // 使用限制
                if( $role['type'] == 1 || $role['type'] == 2 ){
                    ShopMembershipCardRoleLimit::where('shop_membership_card_role_id', $role_data->id)->where('type', 'service')->delete();
                    foreach ($role['limit_service'] as $service_id) {
                        $limit_data = new ShopMembershipCardRoleLimit;
                        $limit_data->shop_id                      = $shop_info->id;
                        $limit_data->shop_membership_card_role_id = $role_data->id;
                        $limit_data->type                         = 'service';
                        $limit_data->commodity_id                 = $service_id;
                        $limit_data->save();
                    }
                    ShopMembershipCardRoleLimit::where('shop_membership_card_role_id', $role_data->id)->where('type', 'product')->delete();
                    foreach ($role['limit_product'] as $product_id) {
                        $limit_data = new ShopMembershipCardRoleLimit;
                        $limit_data->shop_id                      = $shop_info->id;
                        $limit_data->shop_membership_card_role_id = $role_data->id;
                        $limit_data->type                         = 'product';
                        $limit_data->commodity_id                 = $product_id;
                        $limit_data->save();
                    }
                }else{
                    if( $role['select_service'] != '' ){
                        ShopMembershipCardRoleLimit::where('shop_membership_card_role_id', $role_data->id)->where('type', 'service')->delete();
                        $limit_data = new ShopMembershipCardRoleLimit;
                        $limit_data->type         = 'service';
                        $limit_data->commodity_id = $role['select_service'];
                    }else{
                        ShopMembershipCardRoleLimit::where('shop_membership_card_role_id', $role_data->id)->where('type', 'product')->delete();
                        $limit_data = new ShopMembershipCardRoleLimit;
                        $limit_data->type         = 'product';
                        $limit_data->commodity_id = $role['select_product'];
                    }
                    $limit_data->shop_id                      = $shop_info->id;
                    $limit_data->shop_membership_card_role_id = $role_data->id;
                    $limit_data->save();
                }
               
            }

            $delete_role = ShopMembershipCardRole::where('shop_membership_card_id',$shop_membership_card->id)->whereNotIn('id',$role_id)->get();
            ShopMembershipCardRoleLimit::whereIn('shop_membership_card_role_id',$delete_role->pluck('id')->toArray())->delete();
            ShopMembershipCardRole::where('shop_membership_card_id',$shop_membership_card->id)->whereNotIn('id',$role_id)->delete();
        }

        return response()->json(['status'=>true,'data'=>$shop_membership_card]);
    }

    // 刪除商家會員卡資料
    public function shop_membershipCard_delete($shop_id,$shop_membership_card_id)
    {
        $shop_membership_card = ShopMembershipCard::find($shop_membership_card_id);
        if( !$shop_membership_card ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員卡資料']]]);
        }

        // 檢查會員卡是否再活動中(未過期)與是否有會員已拿取且未使用
        if( $shop_membership_card->status == 'published' && $shop_membership_card->end_date < date('Y-m-d H:i:s') ){
            // 檢查是否已被會員拿取，若被拿取就不可以刪除
            if( $shop_membership_card->customers->count() != 0 ){
                return response()->json(['status'=>false,'errors'=>['message'=>['因為已經有會員購買此會員卡，所以無法刪除']]]);
            }
        } 

        // 刪除商家會員卡資料
        // foreach( ShopMembershipCardRole::where('shop_membership_card_id',$shop_membership_card->id)->get() as $role ){
        //     ShopMembershipCardRoleLimit::where('shop_membership_card_role_id',$role->id)->delete();
        // }
        // ShopMembershipCardRole::where('shop_membership_card_id',$shop_membership_card->id)->delete();
        $shop_membership_card->delete();

        $shop_info = Shop::find($shop_id);

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團會員卡
            CompanyMembershipCard::where('id',$shop_membership_card->company_membership_card_id)->delete();
        }

        return response()->json(['status'=>true]);
    }

}
