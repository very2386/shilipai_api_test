<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\CompanyProgram;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\ShopProgram;
use App\Models\ShopProgramGroup;
use App\Models\ShopProgramGroupContent;
use App\Models\ShopService;
use Validator;
use Illuminate\Http\Request;

class ShopProgramController extends Controller
{
    // 拿取商家全部方案
    public function shop_programs($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'create_permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_programs',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'create_permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $programs = ShopProgram::where('shop_id',$shop_id)->get();

        $during = $close = $all = [];
        foreach( $programs as $program ){
            $status = '尚未開始';
            $edit   = true;
            $delete = true;
            $copy   = true;
            $day    = '';

            if( $program->during_type == 1 || ( $program->during_type == 2 && date('Y-m-d H:i:s') <= $program->end_date ) ){
                // 無期限或是還在活動期限內
                if( ($program->during_type == 1 && $program->status == 'published') 
                    || ( $program->status == 'published' && $program->during_type == 2 && date('Y-m-d H:i:s') >= $program->start_date ) ){
                    $status = '活動中';
                    $day    = $program->during_type == 2 ? round( (strtotime($program->end_date) - time())/86400 ) : '' ;
                }elseif( $program->during_type == 1 && $program->status == 'pending' ){
                    $status = '下架';
                    $day    = round((strtotime($program->end_date)-time())/86400); 
                }
            }else{
                $status = '已過期';
                $edit   = false;
            }

            $copy   = $copy   ? (in_array('shop_program_copy_btn',$user_shop_permission['permission']) ? true : false) : false;
            $edit   = $edit   ? (in_array('shop_program_edit_btn',$user_shop_permission['permission']) ? true : false) : false;
            $delete = $delete ? (in_array('shop_program_delete_btn',$user_shop_permission['permission']) ? true : false) : false;

            $date = '無期限';
            if( $program->during_type == 2 ){
                $date = substr($program->start_date,0,10) . ' 00:00 至 ' . substr($program->end_date,0,10) . ' 23:59';
            }

            $all[] = [
                'id'                => $program->id,
                'name'              => $program->name,
                'price'             => $program->price,
                'date'              => $date,
                'day'               => $day,
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
            'create_permission' => in_array('shop_program_create_btn',$user_shop_permission['permission']) ? true : false,
        ];

        return response()->json($data);
    }

    // 新增｜編輯 商家方案資料
    public function shop_program_info($shop_id,$shop_program_id="",$mode="")
    {
        if( $shop_program_id ){
            $shop_program = ShopProgram::find($shop_program_id);
            if( !$shop_program ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到方案資料']]]);
            }
            $type = 'edit';
        }else{
            $type        = 'create';
            $shop_program = new ShopProgram;
        }
        
        $shop_info = Shop::find($shop_id);

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_info->id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_program_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $edit = true;
        if( $type == 'edit' && $mode == "" ){
           if( $shop_program->during_type == 1 || ( $shop_program->during_type == 2 && date('Y-m-d H:i:s') <= $shop_program->end_date ) ){
                // 無期限或是還在活動期限內
                if( ($shop_program->during_type == 1 && $shop_program->status == 'published') 
                    || ( $shop_program->status == 'published' && $shop_program->during_type == 2 && date('Y-m-d H:i:s') >= $shop_program->start_date ) ){
                    // 活動中
                    $edit = false;
                }elseif( $shop_program->status == 'pending'){
                    // 下架
                    $edit = true;
                    // 暫存狀態，檢查是否已被會員拿取，若被拿取就不可以編輯
                    if ($shop_program->customers->count() != 0) {
                        $edit = false;
                    }
                }
            }else{
                // 已過期
                $edit = false;
            }
        }
        $edit = $edit ? (in_array('shop_program_edit_btn', $user_shop_permission['permission']) ? true : false) : false; 
        
        // 方案內容
        $program_info = $shop_program_id ? $shop_program : new ShopProgram;

        // 組合內容
        if( !$shop_program_id ){
            $set_groups = [
                [
                    'id'              => '',
                    'shop_program_id' => '',
                    'type'            => '',
                    'name'            => '',
                    'count'           => '', 
                    'contents'        => [
                        [
                            'id'             => '',
                            'name'           => '',
                            'price'          => '',
                            'split_accounts' => '',
                        ],
                    ],
                ]
            ];
        }else{
            $set_groups = $shop_program->groups;
            foreach( $set_groups as $group ){
                $contents = [];
                foreach( $group->group_content as $content ){
                    if( $content->commodity_type == 'service'){
                        $service_info = $content->service_info; 
                        $contents[] = [
                            'id'             => 'service-' . $service_info->id,
                            'name'           => $service_info->name,
                            'price'          => $service_info->price,
                            'split_accounts' => $content->split_accounts,
                        ];
                    }else{
                        $product_info = $content->product_info;
                        $contents[] = [
                            'id'             => 'product-' . $product_info->id,
                            'name'           => $product_info->name,
                            'price'          => $product_info->price,
                            'split_accounts' => $content->split_accounts,
                        ];
                    }
                }
                $group->contents = $contents;
                unset($group->group_content);
            }
        }
        
        $program = [
            'id'                     => $program_info->id,
            
            'name'                   => $program_info->name,
            'name_permission'        => in_array('shop_program_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            
            'price'                  => $program_info->price,
            'price_permission'       => $edit ? (in_array('shop_program_'.$type.'_price',$user_shop_permission['permission']) ? true : false) : false,
            
            'during_type'            => $program_info->during_type?:1,
            'during_type_permission' => $edit ? (in_array('shop_program_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'start_date'             => date('c',strtotime($program_info->start_date?:date('Y-m-d H:i:s'))),
            'start_date_permission'  => $edit ? (in_array('shop_program_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'end_date'               => date('c',strtotime($program_info->end_date?:date('Y-m-d H:i:s'))),
            'end_date_permission'    => $edit ? (in_array('shop_program_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,
            'show_day'               => $program_info->show_day,
            'show_day_permission'    => $edit ? (in_array('shop_program_'.$type.'_date',$user_shop_permission['permission']) ? true : false) : false,

            'use_coupon'             => $program_info->use_coupon || $program_info->use_coupon === 0 ? (string)$program_info->use_coupon : '0' ,
            'use_coupon_permission'  => $edit ? (in_array('shop_program_'.$type.'_use_condition',$user_shop_permission['permission']) ? true : false) : false,
            'use_topUp'              => $program_info->use_topUp || $program_info->use_topUp === 0 ? (string) $program_info->use_topUp : '0',
            'use_topUp_permission'   => $edit ? (in_array('shop_program_'.$type.'_use_condition',$user_shop_permission['permission']) ? true : false) : false,

            'status'                 => $program_info->status ? $program_info->status : 'pending',
            'status_permission'      => in_array('shop_program_'.$type.'_status',$user_shop_permission['permission']) ? true : false,

            'set_groups'             => $set_groups,
            'set_group_permission'   => $edit ? (in_array('shop_program_'.$type.'_set_group',$user_shop_permission['permission']) ? true : false) : false,
        ];

        $shop_services = ShopService::select('id','name','price')->where('shop_id',$shop_id)->where('type','service')->orderBy('sequence','ASC')->get();
        $shop_products = ShopProduct::select('id', 'name', 'price')->where('shop_id', $shop_id)->orderBy('sequence', 'ASC')->get();
        
        $price_items = [];
        foreach( $shop_services as $service ){
            $price_items[] = [
                'id'             => 'service-' . $service->id,
                'name'           => $service->name,
                'price'          => $service->price,
                'count'          => '',
                'split_accounts' => '',
            ];
        }
        foreach( $shop_products as $product ){
            $price_items[] = [
                'id'             => 'product-' . $product->id,
                'name'           => $product->name,
                'price'          => $product->price,
                'count'          => '',
                'split_accounts' => '',
            ];
        }

        $shop_items = [];
        $new_shop_services = ShopServiceController::shop_service_select($shop_id);
        $shop_products = ShopProductController::shop_product_select($shop_id);
        foreach ($new_shop_services as $key => $cate) {
            $items = [];
            foreach ($cate['services'] as $k => $service) {
                $new_shop_services[$key]['services'][$k]['price']          = $service['price'];
                $new_shop_services[$key]['services'][$k]['count']          = '';
                $new_shop_services[$key]['services'][$k]['split_accounts'] = '';
                $new_shop_services[$key]['services'][$k]['id']             = 'service-' . $service['id'];
            }
            $shop_items[] = $new_shop_services[$key];
        }
        foreach ($shop_products as $key => $cate) {
            foreach ($cate['products'] as $k => $product) {
                $shop_products[$key]['products'][$k]['price']          = $product['price'];
                $shop_products[$key]['products'][$k]['count']          = '';
                $shop_products[$key]['products'][$k]['split_accounts'] = '';
                $shop_products[$key]['products'][$k]['id']             = 'product-' . $product['id'];
            }
            $shop_items[] = $shop_products[$key];
        }

        $data = [
            'status'        => true,
            'permission'    => true,
            'shop_services' => $price_items,
            'shop_items'    => $shop_items,
            'data'          => $program,
        ];

        return response()->json($data);
    }

    // 儲存商家方案資料
    public function shop_program_save($shop_id,$shop_program_id="")
    {
        $rules = [ 
            'status' => 'required',
        ];

        $messages = [
            'status.required'      => '缺少上下架資料',
            'name.required'        => '請填寫名稱',
            'price'                => '請填寫售價',
            'during_type.required' => '請選擇起迄時間',
            'use_coupon.required'  => '請選擇優惠券是否可以抵扣',
            'use_topUp.required'   => '請選擇方案金是否可以抵扣',
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
            if( request('start_date') == '' ) return response()->json(['status'=>false,'errors'=>['message'=>['請選擇起始時間']]]);
            if( request('end_date') == '' )   return response()->json(['status'=>false,'errors'=>['message'=>['請選擇結束時間']]]);
            if( request('show_day') == '' )   return response()->json(['status'=>false,'errors'=>['message'=>['請選擇自動上架顯示的選項']]]);
        }

        // 檢查組合規則欄位資料
        foreach (request('set_groups') as $group) {
            if( $group['name'] == '' || $group['name'] == NULL ) return response()->json(['status' => false, 'errors' => ['message' => ['請輸入組合名稱']]]);
            if( $group['count'] == '' || $group['count'] == NULL ) return response()->json(['status' => false, 'errors' => ['message' => ['請輸入組合數量']]]);
        }
                
        $shop_info = Shop::find($shop_id);

        // 儲存資料
        // 需判斷購買方案，若是基本和進階，基本上就是直接編輯集團優惠券，多分店則不能編輯優惠券資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){

            if( $shop_program_id ){
                $shop_program    = ShopProgram::find($shop_program_id);
                $company_program = CompanyProgram::where('id',$shop_program->company_program_id)->first();
            }else{
                $company_program             = new CompanyProgram;
                $company_program->company_id = $shop_info->company_info->id;
                $shop_program                = new ShopProgram;
                $shop_program->shop_id       = $shop_info->id;
            }

            $company_program->name        = request('name');
            $company_program->price       = request('price');
            $company_program->during_type = request('during_type');
            $company_program->start_date  = request('during_type') == 2 ? request('start_date') : NULL;
            $company_program->end_date    = request('during_type') == 2 ? request('end_date') : NULL;
            $company_program->show_day    = request('during_type') == 2 ? request('show_day') : NULL;
            $company_program->use_coupon  = request('use_coupon');
            $company_program->use_topUp   = request('use_topUp');
            $company_program->status      = request('during_type') == 1 ? request('status') : 'published';
            $company_program->save();

            $shop_program->company_program_id = $company_program->id;
            $shop_program->name               = request('name');
            $shop_program->price              = request('price');
            $shop_program->during_type        = request('during_type');
            $shop_program->start_date         = request('during_type') == 2 ? (request('start_date') > request('end_date') ? request('end_date') : request('start_date')) : NULL;
            $shop_program->end_date           = request('during_type') == 2 ? (request('start_date') < request('end_date') ? request('end_date') : request('start_date')) : NULL;
            $shop_program->show_day           = request('during_type') == 2 ? request('show_day') : NULL;
            $shop_program->use_coupon         = request('use_coupon');
            $shop_program->use_topUp          = request('use_topUp');
            $shop_program->status             = request('during_type') == 1 ? request('status') : 'published';
            $shop_program->save();

            // 組合資料儲存
            $group_id = [];
            foreach( request('set_groups') as $group ){
                $program_group = $group['id'] != '' && $shop_program_id ? ShopProgramGroup::find($group['id']) : new ShopProgramGroup;
                $program_group->shop_id         = $shop_info->id;
                $program_group->shop_program_id = $shop_program->id;
                $program_group->type            = $group['type'];
                $program_group->name            = $group['name'];
                $program_group->count           = $group['count'];
                $program_group->save();

                // 組合內容儲存
                ShopProgramGroupContent::where('shop_program_group_id',$program_group->id)->delete();
                $content_insert = [];
                foreach( $group['contents'] as $content ){
                    // 處理id
                    if (preg_match('/service-/i', $content['id']) || preg_match('/product-/i', $content['id'])) {
                        $word = explode('-', $content['id']);
                        $content_insert[] = [
                            'shop_id'               => $shop_info->id,
                            'shop_program_group_id' => $program_group->id,
                            'commodity_type'        => $word[0],
                            'commodity_id'          => $word[1],
                            'split_accounts'        => $content['split_accounts'],
                            'created_at'            => date('Y-m-d H:i:s'),
                            'updated_at'            => date('Y-m-d H:i:s'),
                        ];
                    } else {
                        $content_insert[] = [
                            'shop_id'               => $shop_info->id,
                            'shop_program_group_id' => $program_group->id,
                            'commodity_type'        => 'service',
                            'commodity_id'          => $content['id'],
                            'split_accounts'        => $content['split_accounts'],
                            'created_at'            => date('Y-m-d H:i:s'),
                            'updated_at'            => date('Y-m-d H:i:s'),
                        ];
                    }
                    
                }
                ShopProgramGroupContent::insert($content_insert);
                $group_id[] = $program_group->id;
            }

            $delete_group = ShopProgramGroup::where('shop_program_id',$shop_program->id)->whereNotIn('id',$group_id)->get();
            ShopProgramGroupContent::whereIn('shop_program_group_id',$delete_group->pluck('id')->toArray())->delete();
            ShopProgramGroup::where('shop_program_id',$shop_program->id)->whereNotIn('id',$group_id)->delete();
        }

        return response()->json(['status'=>true,'data'=>$shop_program]);
    }

    // 刪除商家方案資料
    public function shop_program_delete($shop_id,$shop_program_id)
    {
        $shop_program = ShopProgram::find($shop_program_id);
        if( !$shop_program ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到方案資料']]]);
        }

        // 檢查方案是否再活動中(未過期)與是否有會員已拿取且未使用
        if( $shop_program->status == 'published' && $shop_program->end_date < date('Y-m-d H:i:s') ){
            // 檢查是否已被會員拿取，若被拿取就不可以刪除
            if( $shop_program->customers->count() != 0 ){
                return response()->json(['status'=>false,'errors'=>['message'=>['因為已經有會員購買此方案項目，所以無法刪除']]]);
            }
        } 

        // 刪除商家方案資料
        // $program_groups = ShopProgramGroup::where('shop_program_id',$shop_program->id)->get();
        // foreach( $program_groups as $pg ){
        //     ShopProgramGroupContent::where('shop_program_group_id',$pg->id)->delete();
        // } 
        // ShopProgramGroup::where('shop_program_id',$shop_program->id)->delete();
        $shop_program->delete();

        $shop_info = Shop::find($shop_id);

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團方案
            CompanyProgram::where('id',$shop_program->company_program_id)->delete();
        }

        return response()->json(['status'=>true]);
    }
}
