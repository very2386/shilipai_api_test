<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\CompanyTopUp;
use App\Models\CompanyTopUpLimit;
use App\Models\CompanyTopUpRole;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\ShopTopUp;
use App\Models\ShopTopUpRoleLimit;
use App\Models\ShopTopUpRole;
use Validator;
use Illuminate\Http\Request;

class ShopTopUpController extends Controller
{
    // 拿取商家全部儲值
    public function shop_TopUp($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'create_permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_TopUps',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'create_permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $top_ups = ShopTopUp::where('shop_id',$shop_id)->get();

        $during = $close = $all = [];
        foreach( $top_ups as $top_up ){
            $status = '尚未開始';
            $edit   = true;
            $delete = true;
            $copy   = true;
            $day    = '';

            if( $top_up->during_type == 1 || ( $top_up->during_type == 2 && date('Y-m-d H:i:s') <= $top_up->end_date ) ){
                // 無期限或是還在活動期限內
                if( $top_up->during_type == 1 && $top_up->status == 'published' 
                    || ( $top_up->status == 'published' && $top_up->during_type == 2 && date('Y-m-d H:i:s') >= $top_up->start_date )  ){
                    $status = '活動中';
                    $day    = $top_up->during_type == 2 ? round((strtotime($top_up->end_date)-time())/86400) : false; 
                }elseif( $top_up->during_type == 1 && $top_up->status == 'pending' ){
                    $status = '下架';
                    $day    = round((strtotime($top_up->end_date)-time())/86400); 
                }

                $copy   = $copy   ? (in_array('shop_TopUp_copy_btn',$user_shop_permission['permission']) ? true : false) : false;
                $edit   = $edit   ? (in_array('shop_TopUp_edit_btn',$user_shop_permission['permission']) ? true : false) : false;
                $delete = $delete ? (in_array('shop_TopUp_delete_btn',$user_shop_permission['permission']) ? true : false) : false;

                $during[] = [
                    'id'                => $top_up->id,
                    'name'              => $top_up->name,
                    'price'             => $top_up->price,
                    'date'              => $top_up->during_type == 1 ? '無期限' : substr($top_up->start_date,0,16) . ' 至 ' . substr($top_up->end_date,0,16) . '',
                    'status'            => $status,
                    'day'               => $day,
                    'edit_perimssion'   => true,//$edit,
                    'delete_permission' => $delete,
                    'copy_permission'   => $copy,
                ];
            }else{
                $status = '已過期';
                $close[] = [
                    'id'                => $top_up->id,
                    'name'              => $top_up->name,
                    'price'             => $top_up->price,
                    'date'              => $top_up->during_type == 1 ? '無期限' : substr($top_up->start_date,0,16) . ' 至 ' . substr($top_up->end_date,0,16). '',
                    'edit_perimssion'   => true,//$edit,
                    'delete_permission' => $delete,
                    'copy_permission'   => $copy,
                ];

                $copy   = $copy   ? (in_array('shop_TopUp_copy_btn',$user_shop_permission['permission']) ? true : false) : false;
                $edit   = $edit   ? (in_array('shop_TopUp_edit_btn',$user_shop_permission['permission']) ? true : false) : false;
                $delete = $delete ? (in_array('shop_TopUp_delete_btn',$user_shop_permission['permission']) ? true : false) : false;
            }

            $all[] =  [
                'id'                => $top_up->id,
                'name'              => $top_up->name,
                'price'             => $top_up->price,
                'date'              => $top_up->during_type == 1 ? '無期限' : substr($top_up->start_date,0,16) . ' 至 ' . substr($top_up->end_date,0,16). '',
                'day'               => $day,
                'status'            => $status,
                'edit_perimssion'   => true,//$edit,
                'delete_permission' => $delete,
                'copy_permission'   => $copy,
            ];
        }

        $data = [
            'status'            => true,
            'permission'        => true,
            'during'            => $during,
            'close'             => $close,
            'all'               => $all, 
            'create_permission' => in_array('shop_TopUp_create_btn',$user_shop_permission['permission']) ? true : false,
        ];

        return response()->json($data);
    }

    // 新增｜編輯 商家儲值資料
    public function shop_top_up_info($shop_id,$shop_top_up_id="",$mode="")
    {
        if( $shop_top_up_id ){
            $shop_topUp = ShopTopUp::find($shop_top_up_id);
            if( !$shop_topUp ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到儲值資料']]]);
            }
            $type = 'edit';
        }else{
            $type        = 'create';
            $shop_topUp = new ShopTopUp;
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_info->id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_TopUp_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $edit = true;
        if( $type == 'edit' && $mode == "" ){
            if( $shop_topUp->during_type == 1 || ( $shop_topUp->during_type == 2 && date('Y-m-d H:i:s') <= $shop_topUp->end_date ) ){
                // 無期限或是還在活動期限內
                if( $shop_topUp->during_type == 1 && $shop_topUp->status == 'published' 
                    || ( $shop_topUp->status == 'published' && $shop_topUp->during_type == 2 && date('Y-m-d H:i:s') >= $shop_topUp->start_date )  ){
                    // 活動中
                    $edit = false;
                }elseif( $shop_topUp->status == 'pending' ){
                    // 下架
                    $edit = true;
                    // 暫存狀態，檢查是否已被會員拿取，若被拿取就不可以編輯
                    if ($shop_topUp->customers->count() != 0) {
                        $edit = false;
                    }
                }
            }else{
                // 過期
                $edit = false;
            }
        }
        $edit = $edit ? (in_array('shop_TopUp_edit_btn', $user_shop_permission['permission']) ? true : false) : false;
        
        // 儲值內容
        $top_up_info = $shop_top_up_id ? $shop_topUp : new ShopTopUp;

        if( $top_up_info->roles->count() ){
            $roles = $top_up_info->roles;

            foreach( $roles as $role ){
                if( $role->type == 1 || $role->type == 2 ){
                    // 儲值金｜折扣
                    $limit_service = [];
                    $limit_prodict = []; //(待補)
                    
                    $role_limits = $role->limit_commodity;
                    // 檢查此限制項目是否有在商家的服務或產品內
                    $limit_service = $role_limits->where('type','service');
                    $limit_product = $role_limits->where('type','product');

                    $limit_service = ShopService::whereIn('id',$limit_service->pluck('commodity_id')->toArray())->pluck('id')->toArray();

                    $role->limit_service    = $limit_service;
                    $role->limit_product    = $limit_prodict;
                    $role->limit_total      = count($role->limit_service) + count($role->limit_product);
                    // $role->limit_permission = in_array('shop_TopUp_'.$type.'_limit',$user_shop_permission['permission']) ? true : false;

                    unset( $role->limit_commodity );
                    
                }else{
                    // 贈品｜免費
                    $role->limit_service    = [];
                    $role->limit_product    = [];
                    $role->limit_total      = 0;
                    // $role->limit_permission = in_array('shop_TopUp_'.$type.'_limit',$user_shop_permission['permission']) ? true : false;
                }
            }

        }else{
            $roles = [
                [
                    'id'               => '',
                    'type'             => '',
                    'second_type'      => '',
                    'price'            => 1000,
                    'discount'         => '',
                    'commodity_id'     => '',
                    'self_definition'  => '',
                    'limit'            => 1,
                    'deadline_month'   => 1,
                    // 'limit_permission' => in_array('shop_TopUp_'.$type.'_limit',$user_shop_permission['permission']) ? true : false,
                    'limit_service'    => [],
                    'limit_product'    => [],
                    'limit_total'      => 0,
                ],
            ];
        }

        $top_up = [
            'id'                     => $top_up_info->id,
            
            'name'                   => $top_up_info->name,
            'name_permission'        => in_array('shop_TopUp_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            
            'price'                  => $top_up_info->price,
            'price_permission'       => $edit ? (in_array('shop_TopUp_'.$type.'_price',$user_shop_permission['permission']) ? true : false) : false,
            
            'during_type'            => $top_up_info->during_type?:1,
            'during_type_permission' => $edit ? (in_array('shop_TopUp_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'start_date'             => date('c',strtotime($top_up_info->start_date?:date('Y-m-d H:i:s'))),
            'start_date_permission'  => $edit ? (in_array('shop_TopUp_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'end_date'               => date('c',strtotime($top_up_info->end_date?:date('Y-m-d H:i:s'))),
            'end_date_permission'    => $edit ? (in_array('shop_TopUp_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'show_day'               => $top_up_info->show_day,
            'show_day_permission'    => $edit ? (in_array('shop_TopUp_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,

            'use_coupon'             => $top_up_info->use_coupon || $top_up_info->use_coupon === 0 ? (string)$top_up_info->use_coupon : '0',
            'use_coupon_permission'  => $edit ? (in_array('shop_TopUp_'.$type.'_use_coupon',$user_shop_permission['permission']) ? true : false) : false,

            'status'                 => $top_up_info->status ? $top_up_info->status : 'pending',
            'status_permission'      => in_array('shop_TopUp_'.$type.'_status',$user_shop_permission['permission']) ? true : false,

            'roles'                  => $roles, 
            'roles_permission'       => $edit ? (in_array('shop_TopUp_'.$type.'_roles',$user_shop_permission['permission']) ? true : false) : false,
        ];

        $shop_services = ShopServiceController::shop_service_select($shop_id);
        $shop_products = ShopProductController::shop_product_select($shop_id);

        $data = [
            'status'        => true,
            'permission'    => true,
            'shop_services' => $shop_services,
            'shop_products' => $shop_products,
            'data'          => $top_up
        ];

        return response()->json($data);
    }

    // 儲存儲值資料
    public function shop_top_up_save($shop_id,$shop_top_up_id="")
    {
        $rules = [ 
            'status' => 'required',
        ];

        $messages = [
            'status.required'          => '缺少上下架資料',
            'name.required'            => '請填寫名稱',
            'price'                    => '請填寫售價',
            'during_type.required'     => '請選擇起迄時間',
            'use_coupon.required'      => '請選擇優惠券是否可以折抵'   
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
                case 1:// 儲值金
                    if( $role['price'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請輸入贈送儲值金']];
                    }
                    if( $role['limit'] == '' ){
                        $break = true;
                        $errors = ['message'=>['儲值金設定，請選擇使用限制']];
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
                    $discount_info[] = $role;
                    break;
                case 3:// 贈品
                    if( $role['second_type'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請選擇從既有產品品項選擇或是自定名稱']];
                    }elseif( $role['second_type'] == 1 && $role['commodity_id'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請選擇產品品項']];
                    }elseif( $role['second_type'] == 2 && $role['self_definition'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請輸入自定名稱']];
                    }elseif( $role['deadline_month'] == '' || $role['deadline_month'] == NULL ){
                        $break = true;
                        $errors = ['message' => ['請選擇使用期限']];
                    }
                    break; 
                case 4:// 免費體驗
                    if( $role['second_type'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請選擇從既有服務品項選擇或是自定名稱']];
                    }elseif( $role['second_type'] == 3 && $role['commodity_id'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請選擇服務品項']];
                    }elseif( $role['second_type'] == 4 && $role['self_definition'] == '' ){
                        $break = true;
                        $errors = ['message'=>['請輸入自定名稱']];
                    }elseif( $role['deadline_month'] == '' || $role['deadline_month'] == NULL ){
                        $break = true;
                        $errors = ['message' => ['請選擇使用期限']];
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

            if( $shop_top_up_id ){
                $shop_top_up = ShopTopUp::find($shop_top_up_id);
                $top_up      = CompanyTopUp::where('id',$shop_top_up->company_top_up_id)->first();
            }else{
                $top_up               = new CompanyTopUp;
                $top_up->company_id   = $shop_info->company_info->id;
                $shop_top_up          = new ShopTopUp;
                $shop_top_up->shop_id = $shop_id;
            }

            $top_up->name        = request('name');
            $top_up->price       = request('price');
            $top_up->during_type = request('during_type');
            $top_up->start_date  = request('during_type') == 2 ? ( request('start_date') > request('end_date') ? request('end_date') : request('start_date') ) : NULL;
            $top_up->end_date    = request('during_type') == 2 ? ( request('start_date') < request('end_date') ? request('end_date') : request('start_date') ) : NULL;
            $top_up->show_day    = request('during_type') == 2 ? request('show_day') : NULL;
            $top_up->use_coupon  = request('use_coupon');
            $top_up->status      = request('during_type') == 1 ? request('status') : 'published';
            $top_up->save();

            $shop_top_up->company_top_up_id = $top_up->id;
            $shop_top_up->name              = request('name');
            $shop_top_up->price             = request('price');
            $shop_top_up->during_type       = request('during_type');
            $shop_top_up->start_date        = request('during_type') == 2 ? request('start_date') : NULL;
            $shop_top_up->end_date          = request('during_type') == 2 ? request('end_date') : NULL;
            $shop_top_up->show_day          = request('during_type') == 2 ? request('show_day') : NULL;
            $shop_top_up->use_coupon        = request('use_coupon');
            $shop_top_up->status            = request('during_type') == 1 ? request('status') : 'published';
            $shop_top_up->save();

            // 規則儲存
            $role_id = [];
            foreach( request('roles') as $role ){
                
                if( $role['id'] != '' && $shop_top_up_id ){
                    $role_data = ShopTopUpRole::find($role['id']);
                }else{
                    $role_data = new ShopTopUpRole;
                    $role_data->shop_id        = $shop_info->id;
                    $role_data->shop_top_up_id = $shop_top_up->id;
                }

                $role_data->type            = $role['type'];
                $role_data->second_type     = $role['type'] == 3 || $role['type'] == 4 ? $role['second_type'] : NULL;
                $role_data->price           = $role['type'] == 1 ? $role['price'] : NULL;
                $role_data->discount        = $role['type'] == 2 ? $role['discount'] : NULL;
                $role_data->commodity_id    = $role['second_type'] == 1 || $role['second_type'] == 3 ? $role['commodity_id'] : NULL;
                $role_data->self_definition = $role['second_type'] == 2 || $role['second_type'] == 4 ? $role['self_definition'] : NULL;
                $role_data->limit           = $role['type'] == 1 || $role['type'] == 2 ?  $role['limit'] : NULL;
                $role_data->deadline_month  = $role['type'] == 1 ? NULL : $role['deadline_month'];
                $role_data->save();
                
                // 使用限制
                // ShopTopUpRoleLimit::where('shop_top_up_role_id',$role_data->id)->where('type','service')->delete();
                // foreach( $role['limit_service'] as $service_id ){
                //     $limit_data = new ShopTopUpRoleLimit;
                //     $limit_data->shop_id             = $shop_info->id;
                //     $limit_data->shop_top_up_role_id = $role_data->id;
                //     $limit_data->type                = 'service';
                //     $limit_data->commodity_id        = $service_id;
                //     $limit_data->save();
                // }
                // ShopTopUpRoleLimit::where('shop_top_up_role_id',$role_data->id)->where('type','product')->delete();
                // foreach( $role['limit_product'] as $product_id ){
                //     $limit_data = new ShopTopUpRoleLimit;
                //     $limit_data->shop_id             = $shop_info->id;
                //     $limit_data->shop_top_up_role_id = $role_data->id;
                //     $limit_data->type                = 'product';
                //     $limit_data->commodity_id        = $product_id;
                //     $limit_data->save();
                // }

                $role_id[] = $role_data->id;
            }

            $delete_role = ShopTopUpRole::where('shop_top_up_id',$shop_top_up->id)->whereNotIn('id',$role_id)->get();
            ShopTopUpRoleLimit::whereIn('shop_top_up_role_id',$delete_role->pluck('id')->toArray())->delete();
            ShopTopUpRole::where('shop_top_up_id',$shop_top_up->id)->whereNotIn('id',$role_id)->delete();

            if( !$shop_top_up_id ){
                $shop_top_up->shop_id           = $shop_id;
                $shop_top_up->company_top_up_id = $top_up->id;
                $shop_top_up->status            = request('status');
                $shop_top_up->save();
            }
        }

        return response()->json(['status'=>true,'data'=>$top_up]);
    }

    // 刪除商家儲值資料
    public function shop_top_up_delete($shop_id,$shop_top_up_id)
    {
        $shop_top_up = ShopTopUp::find($shop_top_up_id);
        if( !$shop_top_up ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到儲值資料']]]);
        }

        // 檢查儲值是否再活動中(未過期)與是否有會員已拿取且未使用
        if( $shop_top_up->status == 'published' && $shop_top_up->end_date < date('Y-m-d H:i:s') ){
            // 檢查是否已被會員拿取，若被拿取就不可以刪除
            if( $shop_top_up->customers->count() != 0 ){
                return response()->json(['status'=>false,'errors'=>['message'=>['因為已經有會員購買此儲值項目，所以無法刪除']]]);
            }
        } 

        // 刪除商家儲值資料
        // ShopTopUpRoleLimit::where('shop_top_up_id',$shop_top_up->id)->delete();
        // ShopTopUpRole::where('shop_top_up_id',$shop_top_up->id)->delete();
        $shop_top_up->delete();

        $shop_info = Shop::find($shop_id);

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團儲值
            CompanyTopUp::where('id',$shop_top_up->company_top_up_id)->delete();
        }

        return response()->json(['status'=>true]);
    }

}
