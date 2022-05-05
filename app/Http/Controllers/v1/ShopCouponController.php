<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Models\CompanyCoupon;
use App\Models\CompanyCouponLimit;
use App\Models\Shop;
use App\Models\ShopCoupon;
use App\Models\ShopCouponLimit;
use App\Models\ShopService;

class ShopCouponController extends Controller
{
    // 取得商家全部優惠券
    public function shop_coupon($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_coupons',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $user_shop    = Shop::find($shop_id);
        $company_info = $user_shop->company_info;

        $coupons = ShopCoupon::where('shop_id',$shop_id)->get();

        $coupon_list = [];
        foreach( $coupons as $coupon ){
            $edit   = true;
            $copy   = true;
            $delete = true;

        	$status = '暫存';
        	if( $coupon->status == 'published' ){
        		if( $coupon->start_date > date('Y-m-d H:i:s') ){
        			$status = '尚未開始';
        		}elseif( date('Y-m-d H:i:s') >= $coupon->start_date && date('Y-m-d H:i:s') <= $coupon->end_date ){
        		    $status = '活動中';
        		}elseif( date('Y-m-d H:i:s') > $coupon->end_date ){
        		    $status = '過期';
        		}
        	}

            $copy   = $copy   ? (in_array('shop_coupon_copy_btn',$user_shop_permission['permission']) ? true : false) : false;
            $edit   = $edit   ? (in_array('shop_coupon_edit_btn',$user_shop_permission['permission']) ? true : false) : false;
            $delete = $delete ? (in_array('shop_coupon_delete_btn',$user_shop_permission['permission']) ? true : false) : false;

            $get_count       = $coupon->customers->count();
            $used_count      = $coupon->customers->where('status','Y')->count();
            $used_percentage = $get_count == 0 ? 0 : round($used_count/$get_count*100,2);

            $coupon_list[] = [
                'id'                => $coupon->id,
                'title'             => $coupon->title,
                'deadline'          => date('Y-m-d H:i',strtotime($coupon->end_date)),
                'view'              => $coupon->view,
                'get_count'         => $get_count,
                'used_count'        => $used_count,
                'used_percentage'   => $used_percentage,
                'photo'             => $coupon->photo ? env('SHOW_PHOTO').'/api/show/'.$user_shop->alias.'/'.$coupon->photo : '/static/media/logo.204b0ddf.png',
                'status'            => $status,
                'edit_permission'   => true,//$edit,
                'delete_permission' => $delete,
                'copy_permission'   => $copy,
            ];

        }

        $data = [
            'status'            => true,
            'permission'        => true,
            'create_permission' => in_array('shop_coupon_create_btn',$user_shop_permission['permission']) ? true : false,
            'edit_permission'   => in_array('shop_coupon_edit_btn',$user_shop_permission['permission']) ? true : false,
            'delete_permission' => in_array('shop_coupon_delete_btn',$user_shop_permission['permission']) ? true : false,
            'copy_permission'   => in_array('shop_coupon_copy_btn',$user_shop_permission['permission']) ? true : false,
            'status_permission' => in_array('shop_coupon_status_btn',$user_shop_permission['permission']) ? true : false,
            'data'              => $coupon_list
        ];
        
        return response()->json($data);
    }

    // 新增/編輯商家優惠券資料
    public function shop_coupon_info($shop_id,$shop_coupon_id="",$mode="")
    {
        if( $shop_coupon_id ){
            $shop_coupon = ShopCoupon::find($shop_coupon_id);
            if( !$shop_coupon ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到優惠券資料']]]);
            }
            $type = 'edit';            
        }else{
            $type        = 'create';
            $shop_coupon = new ShopCoupon;
        }

        $default_content = "· 使用優惠券時，請向店員出示此畫面。\n"."· 已使用的優惠券無法再次使用。此外，若因顧客誤按而變為「已使用」狀態的優惠券也無法再次使用。";
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_info->id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_coupon_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $edit = true;
        if( $type == 'edit' && $mode == "" ){
            // 編輯需判斷權限
        	if( $shop_coupon->status == 'published' ){
        		if( date('Y-m-d H:i:s') >= $shop_coupon->start_date && date('Y-m-d H:i:s') <= $shop_coupon->end_date ){
                    // 活動中
                    $edit = false;
        		}elseif( date('Y-m-d H:i:s') > $shop_coupon->end_date ){
                    // 過期
                    $edit = false;
        		}
        	}else{
                // 暫存狀態，檢查是否已被會員拿取，若被拿取就不可以編輯
                if ($shop_coupon->customers->count() != 0) {
                    $edit = false;
                }
            }
        }
        $edit = $edit ? (in_array('shop_coupon_edit_btn', $user_shop_permission['permission']) ? true : false) : false;

        // 優惠券內容
        $coupon_info = $shop_coupon_id ? $shop_coupon : new CompanyCoupon;

        // 處理優惠券的使用限制
        $limit_service = [];
        $limit_product = []; //(待補)
        if( $coupon_info->limit == 4 ){
            $coupon_limits = $coupon_info->limit_commodity;
            // 檢查此限制項目是否有在商家的服務或產品內
            $limit_service = $coupon_limits->where('type','service')->pluck('commodity_id');
            $limit_product = $coupon_limits->where('type','product')->pluck('commodity_id');

            $shop_limit_service = ShopService::whereIn('company_service_id',$limit_service->pluck('id')->toArray())->pluck('id')->toArray();
        }

        $data = [
            'id'                         => $shop_coupon->id,
            'title'                      => $coupon_info->title,
            'title_permission'           => in_array('shop_coupon_'.$type.'_title',$user_shop_permission['permission']) ? true : false,

            'description'                => $coupon_info->description,
            'description_permission'     => in_array('shop_coupon_'.$type.'_description',$user_shop_permission['permission']) ? true : false,

            'start_date'                 => date('c',strtotime($coupon_info->start_date?:date('Y-m-d H:i:s'))),
            'start_date_permission'      => $edit ? (in_array('shop_coupon_'.$type.'_start_date',$user_shop_permission['permission']) ? true : false) : false,

            'end_date'                   => date('c',strtotime($coupon_info->end_date?:date('Y-m-d H:i:s'))),
            'end_date_permission'        => $edit ? (in_array('shop_coupon_'.$type.'_end_date',$user_shop_permission['permission']) ? true : false) : false,

            'photo_type'                 => $coupon_info->photo_type,
            'photo'                      => $coupon_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$coupon_info->photo : NULL,
            'photo_permission'           => $edit ? (in_array('shop_coupon_'.$type.'_photo',$user_shop_permission['permission']) ? true : false) : false,

            'content'                    => $type == 'create' ? $default_content : $coupon_info->content,
            'content_permission'         => in_array('shop_coupon_'.$type.'_content',$user_shop_permission['permission']) ? true : false,
            
            'type'                       => $coupon_info->type,
            'second_type'                => $coupon_info->second_type,
            'commodityId'                => $coupon_info->commodityId,
            'limit'                      => (string)$coupon_info->limit,
            'consumption'                => $coupon_info->consumption,
            'price'                      => $coupon_info->price,
            'self_definition'            => $coupon_info->self_definition,
            'discount'                   => $coupon_info->discount,
            'limit_service'              => $limit_service,
            'limit_product'              => $limit_product,
            'limit_total'                => count($limit_service) + count($limit_product),
            'type_permission'            => $edit ? (in_array('shop_coupon_'.$type.'_type',$user_shop_permission['permission']) ? true : false) : false ,

            'count_type'                 => $coupon_info->count_type,
            'count'                      => $coupon_info->count,
            'count_permission'           => $edit ? (in_array('shop_coupon_'.$type.'_count',$user_shop_permission['permission']) ? true : false) : false ,

            'use_type'                   => $coupon_info->use_type,
            'use_type_permission'        => $edit ? (in_array('shop_coupon_'.$type.'_use_type',$user_shop_permission['permission']) ? true : false) : false ,
            
            'get_level'                  => $coupon_info->get_level,
            'get_level_permission'       => $edit ? (in_array('shop_coupon_'.$type.'_get_level',$user_shop_permission['permission']) ? true : false) : false ,

            'customer_level'             => $coupon_info->customer_level,
            'customer_level_permission'  => $edit ? (in_array('shop_coupon_'.$type.'_get_level',$user_shop_permission['permission']) ? true : false) : false ,

            'show_type'                  => $coupon_info->show_type,
            'show_type_permission'       => $edit ? (in_array('shop_coupon_'.$type.'_get_level',$user_shop_permission['permission']) ? true : false) : false ,

            'status'                     => $coupon_info->status ? $coupon_info->status : 'pending',
            'status_permission'          => in_array('shop_coupon_'.$type.'_status',$user_shop_permission['permission']) ? true : false,

            'edit' => $edit,
        ];

        $shop_services = ShopServiceController::shop_service_select($shop_id);
        $shop_products = ShopProductController::shop_product_select($shop_id);

        $response = [
            'status'        => true,
            'permission'    => true,
            'shop_services' => $shop_services,
            'shop_products' => $shop_products,
            'data'          => $data
        ];

        return response()->json($response);
    }

    // 儲存商家優惠券資料
    public function shop_coupon_save($shop_id,$shop_coupon_id="")
    {
        $rules = [ 
            'status' => 'required',
        ];

        $messages = [
            'status.required'          => '缺少上下架資料',
            'title.required'           => '請填寫名稱',
            'description.required'     => '請填寫副標',
            'start_date.required'      => '請填寫起始時間',
            'end_date.required'        => '請填寫結束時間',
            'photo_type.required'      => '請選擇圖片類別',
            'photo.required'           => '請上傳圖片',
            'use_type.required'        => '請選擇可領取次數',
            'count_type.required'      => '請填寫發送數量',
            'type.required'            => '請選擇優惠券類型',
            'discount.required'        => '請填寫折扣折數',
            'second_type.required'     => '請選擇類型下的選項',
            'consumption.required'     => '請填寫消費金額',
            'limit.required'           => '請選擇使用限制/可抵扣的項目',
            'commodityId.required'     => '請選擇計有的產品或服務',
            'price.required'           => '請輸入價格/折抵金額',
            'self_definition.required' => '請輸入自定項目'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 上架才需判別
        if( request('status') == 'published' ){
            // 基本資料檢查
            $rules = [ 
                'status'      => 'required',
                'title'       => 'required',
                'description' => 'required',
                'start_date'  => 'required',
                'end_date'    => 'required',
                'photo_type'  => 'required',
                // 'use_type'    => 'required',
                'count_type'  => 'required',
                'type'        => 'required',
            ];

            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()){
                return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
            }

            // 圖片選擇上傳圖片，圖片需必填
            if( request('photo_type') == 3 ){
                $rules['photo'] = 'required';
            }

            // 若選擇限制發送數量，數量需必填
            if( request('count_type') == 2 ){
                $rules['count'] = 'required';
            }

            // 根據類型檢查必填
            switch (request('type')) {
                case 'discount': // 折扣
                    $rules['discount'] = 'required';
                    $rules['limit']    = 'required';
                    break;
                case 'free': // 免費體驗
                case 'gift': // 贈品
                    $rules['second_type'] = 'required';
                    break;
                case 'full_consumption': // 滿額折扣
                    $rules['consumption'] = 'required';
                    $rules['discount']    = 'required';
                    $rules['limit']       = 'required';
                    break;
                case 'experience': // 體驗價
                    $rules['commodityId'] = 'required';
                    $rules['price']       = 'required';
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

            if(request('get_level') == 1){
                $rules['use_type'] = 'required';
            }

            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()){
                return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
            }
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 儲存集團優惠券
        // 需判斷購買方案，若是基本和進階，基本上就是直接編輯集團優惠券，多分店則不能編輯優惠券資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){

            if( $shop_coupon_id ){
                $shop_coupon = ShopCoupon::find($shop_coupon_id);
                $coupon      = CompanyCoupon::where('id',$shop_coupon->company_coupon_id)->first();
            }else{
                $coupon             = new CompanyCoupon;
                $coupon->company_id = $company_info->id;
                $shop_coupon        = new ShopCoupon;
            }

            $coupon->type        = request('type');
            $coupon->title       = request('title');
            $coupon->description = request('description');
            $coupon->start_date  = request('start_date') > request('end_date') ? date('Y-m-d H:i:s',strtotime(request('end_date'))) : date('Y-m-d H:i:s',strtotime(request('start_date')));
            $coupon->end_date    = request('start_date') < request('end_date') ? date('Y-m-d H:i:s',strtotime(request('end_date'))) : date('Y-m-d H:i:s',strtotime(request('start_date')));
            $coupon->count       = request('count');
            $coupon->photo_type  = request('photo_type');
            $coupon->count_type  = request('count_type');
            $coupon->count       = request('count_type') == 1 ? NULL : (request('count')?:NULL);
            $coupon->use_type    = request('use_type');
            $coupon->get_level   = request('get_level');
            $coupon->status      = request('status');
            $coupon->content     = request('content');

            $shop_coupon->type        = request('type');
            $shop_coupon->title       = request('title');
            $shop_coupon->description = request('description');
            $shop_coupon->start_date  = request('start_date') > request('end_date') ? date('Y-m-d H:i:s', strtotime(request('end_date'))) : date('Y-m-d H:i:s', strtotime(request('start_date')));
            $shop_coupon->end_date    = request('start_date') < request('end_date') ? date('Y-m-d H:i:s', strtotime(request('end_date'))) : date('Y-m-d H:i:s', strtotime(request('start_date')));
            $shop_coupon->count       = request('count');
            $shop_coupon->photo_type  = request('photo_type');
            $shop_coupon->count_type  = request('count_type');
            $shop_coupon->count       = request('count_type') == 1 ? NULL : (request('count') ?: NULL);
            $shop_coupon->use_type    = request('use_type');
            $shop_coupon->get_level   = request('get_level');
            $shop_coupon->status      = request('status');
            $shop_coupon->content     = request('content');

            switch (request('type')) {
                case 'discount':
                    $coupon->discount = request('discount');
                    $coupon->limit    = request('limit');
                    $shop_coupon->discount = request('discount');
                    $shop_coupon->limit    = request('limit');
                    break;
                case 'free':
                    $coupon->second_type     = request('second_type');
                    $coupon->commodityId     = request('commodityId')?:NULL;
                    $coupon->self_definition = request('commodityId') ? NULL : request('self_definition');
                    $shop_coupon->second_type     = request('second_type');
                    $shop_coupon->commodityId     = request('commodityId') ?: NULL;
                    $shop_coupon->self_definition = request('commodityId') ? NULL : request('self_definition');
                    break;
                case 'gift':
                    $coupon->second_type     = request('second_type');
                    $coupon->commodityId     = request('commodityId')?:NULL;
                    $coupon->self_definition = request('commodityId') ? NULL : request('self_definition');
                    $shop_coupon->second_type     = request('second_type');
                    $shop_coupon->commodityId     = request('commodityId') ?: NULL;
                    $shop_coupon->self_definition = request('commodityId') ? NULL : request('self_definition');
                    break;
                case 'full_consumption':
                    $coupon->consumption = request('consumption');
                    $coupon->discount    = request('discount');
                    $coupon->limit       = request('limit');
                    $shop_coupon->consumption = request('consumption');
                    $shop_coupon->discount    = request('discount');
                    $shop_coupon->limit       = request('limit');
                    break;
                case 'experience':
                    $coupon->commodityId = request('commodityId');
                    $coupon->price       = request('price');
                    $shop_coupon->commodityId = request('commodityId');
                    $shop_coupon->price       = request('price');
                    break;
                case 'cash':
                    $coupon->second_type = request('second_type');
                    $coupon->consumption = request('consumption');
                    $coupon->price       = request('price');
                    $coupon->limit       = request('limit');
                    $shop_coupon->second_type = request('second_type');
                    $shop_coupon->consumption = request('consumption');
                    $shop_coupon->price       = request('price');
                    $shop_coupon->limit       = request('limit');
                    break;
            }
            $coupon->save();
            $shop_coupon->save();

            if( request('type') == 'cash' || request('type') == 'discount' || request('type') == 'full_consumption'  ){
                // 處理選擇項目
                if( $coupon->limit == 4 && (request('limit_product') || request('limit_service')) ){
                    CompanyCouponLimit::where('company_coupon_id',$coupon->id)->delete();
                    ShopCouponLimit::where('shop_coupon_id', $shop_coupon->id)->delete();
                    $insert = $shop_insert = [];

                    // 限制產品
                    foreach( request('limit_product') as $product ){
                        $insert[] = [
                            'company_id'        => $company_info->id,
                            'company_coupon_id' => $coupon->id,
                            'type'              => 'product',
                            'commodity_id'      => $product,
                        ];
                        $shop_insert[] = [
                            'company_id'        => $shop_id,
                            'shop_coupon_id'    => $shop_coupon->id,
                            'type'              => 'product',
                            'commodity_id'      => $product,
                        ];
                    }

                    // 限制服務資料
                    // $company_service_ids = ShopService::whereIn('id',request('limit_service'))->pluck('company_service_id');
                    foreach(request('limit_service') as $service_id ){
                        $insert[] = [
                            'company_id'        => $shop_info->company_info->id,
                            'company_coupon_id' => $coupon->id,
                            'type'              => 'service',
                            'commodity_id'      => $service_id,
                        ];
                        $shop_insert[] = [
                            'shop_id'           => $shop_id,
                            'shop_coupon_id'    => $shop_coupon->id,
                            'type'              => 'service',
                            'commodity_id'      => $service_id,
                        ];
                    }

                    CompanyCouponLimit::insert($insert);
                    ShopCouponLimit::insert($shop_insert);
                }
            }

            if (!$shop_coupon_id) {
                // 複製後儲存
                if (request('photo_type') == 3 && request('id') != '') {
                    $copy_coupon = ShopCoupon::find(request('id'));
                    $file_path = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $copy_coupon->photo;
                    $new_pic_name = sha1(uniqid('', true)) . '.jpg';
                    $new_path  = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $new_pic_name;
                    shell_exec("cp " . $file_path . " " . $new_path);
                    
                    $coupon->photo = $new_pic_name;
                    $shop_coupon->photo = $new_pic_name;
                }
            } 

            // 上傳照片處理
            if (request('photo_type') == 3 && request('photo') && preg_match('/base64/i', request('photo'))) {
                $picName = PhotoController::save_base64_photo($shop_info->alias, request('photo'), $coupon->photo);
                $coupon->photo = $picName;
                $shop_coupon->photo = $picName;
            } elseif (request('photo_type') != 3) {
                if ($coupon->photo) {
                    // 刪除照片
                    $filePath = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $coupon->photo;
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $coupon->photo = NULL;
                    $shop_coupon->photo = NULL;
                }
            } 

            $coupon->save();
            $shop_coupon->save();

            if( !$shop_coupon_id ){
                $shop_coupon->shop_id           = $shop_id;
                $shop_coupon->company_coupon_id = $coupon->id;
                $shop_coupon->status            = request('status');
                $shop_coupon->save();
            }else{
                $shop_coupon->status = request('status');
                $shop_coupon->save();
            }
        }
        
        return response()->json(['status'=>true,'data'=>$coupon]);
    }

    // 刪除商家優惠券資料
    public function shop_coupon_delete($shop_id,$shop_coupon_id)
    {
        $shop_info = Shop::find($shop_id);

        $shop_coupon = ShopCoupon::find($shop_coupon_id);
        if( !$shop_coupon ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到優惠券資料']]]);
        }

        // 檢查優惠券是否再活動中(未過期)與是否有會員已拿取且未使用
        if( $shop_coupon->status == 'published' && $shop_coupon->end_date <= date('Y-m-d H:i:s') ){
            // 檢查是否已被會員拿取，若被拿取就不可以刪除
            if( $shop_coupon->customers->count() != 0 ){
                return response()->json(['status'=>false,'errors'=>['message'=>['因為已經有會員擁有此張優惠券，所以無法刪除']]]);
            }
        } 

        // 刪除商家優惠券資料
        ShopCouponLimit::where('shop_coupon_id',$shop_coupon->id)->delete();
        // 刪除圖片
        if ($shop_coupon->photo) {
            $filePath = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $shop_coupon->photo;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $shop_coupon->delete();

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團優惠券
            $company_coupon = CompanyCoupon::where('id',$shop_coupon->company_coupon_id)->first();
            if( $company_coupon ){
                CompanyCouponLimit::where('company_coupon_id',$company_coupon->id)->delete();
                $company_coupon->delete();

                // 刪除圖片
                if( $company_coupon->photo ){
                    $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$company_coupon->photo;
                    if(file_exists($filePath)){
                        unlink($filePath);
                    }
                }
            }
        }

        return response()->json(['status'=>true]);
    }

}
