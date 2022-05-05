<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Models\Company;
use App\Models\CompanyCoupon;
use App\Models\CompanyCouponLimit;
use App\Models\CompanyLoyaltyCard;
use App\Models\CompanyLoyaltyCardLimit;
use App\Models\CompanyServiceCategory;
use App\Models\CompanyService;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\ShopServiceStaff;
use App\Models\ShopServiceAdvance;
use App\Models\ShopServiceCategory;
use App\Models\Permission;
use App\Models\ShopCoupon;
use App\Models\ShopManagement;
use App\Models\ShopManagementGroup;
use App\Models\ShopManagementMode;
use App\Models\ShopManagementService;
use App\Models\ShopMembershipCard;
use App\Models\ShopProgram;
use App\Models\ShopTopUp;

class ShopServiceController extends Controller
{
    // 取得商家全部服務資料
	public function shop_service($shop_id)
	{
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_service',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 先拿出分類
        $category_infos = $shop_info->shop_service_categories;
        // 在拿取shop裡有的服務
        foreach( $category_infos as $info ){
        	$info->services      = $info->shop_services;
            $info->service_count = $info->shop_services->count();
            unset($info->shop_services);
        }

        $data = [
            'status'                    => true,
            'permission'                => true,
            'category_sort_permission'  => in_array('shop_category_sort_btn',$user_shop_permission['permission']) ? true : false,   // 分類排序
            'category_add_permission'   => in_array('shop_category_create_btn',$user_shop_permission['permission']) ? true : false,    // 分類新增
            'category_edit_permission'  => in_array('shop_category_edit_btn',$user_shop_permission['permission']) ? true : false,   // 分類編輯
            'service_add_permission'    => in_array('shop_service_create_btn',$user_shop_permission['permission']) ? true : false,     // 服務新增
            'service_edit_permission'   => in_array('shop_service_edit_btn',$user_shop_permission['permission']) ? true : false,    // 服務編輯
            'service_delete_permission' => in_array('shop_service_delete',$user_shop_permission['permission']) ? true : false,      // 服務刪除
            'service_copy_permission'   => in_array('shop_service_copy',$user_shop_permission['permission']) ? true : false,        // 服務複製
            'service_status_permission' => in_array('shop_service_status',$user_shop_permission['permission']) ? true : false,      // 服務上下架
            'service_sort_permission'   => in_array('shop_service_sort_btn',$user_shop_permission['permission']) ? true : false,    // 服務排序
            'data'                      => $category_infos,
        ];

        return response()->json($data);
	}

	// 新增/編輯商家服務資料
	public function shop_service_info($shop_id,$shop_service_id="",$mode="")
	{
        if( $shop_service_id ){
            $shop_service_info = ShopService::find($shop_service_id);
            if( !$shop_service_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到服務項目資料']]]);
            }
            $type = $mode == "" ? 'edit' : 'create';
        }else{
            $shop_service_info = new ShopService;
            $type              = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_service_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 拿取商家員工資料，按照職稱分類
        $shop_sort_staffs = ShopStaffController::shop_staff_sort($shop_info->id);

        // 拿取商家有的可選的加值服務
        $shop_advances = ShopAdvanceController::shop_advance_select($shop_info->id);

        // return $shop_service_info->service_staffs->pluck('shop_staff_id')->toArray();
        
        $match_staff_arr = [];
        foreach( $shop_service_info->service_staffs->pluck('shop_staff_id')->toArray() as $service_staff ){
            $match_staff_arr[] = (string) $service_staff;
        }
        $match_advance_arr = [];
        foreach( $shop_service_info->service_advances->pluck('shop_advance_id')->toArray() as $advance ){
            $match_advance_arr[] = (string) $advance;
        }

        $buffer_time = '0';
        if( $shop_service_info->buffer_time  ) $buffer_time = $shop_service_info->buffer_time;
        elseif( !$shop_service_id ){
            if( $shop_info->shop_set->buffer_time ) $buffer_time = (string)$shop_info->shop_set->buffer_time;
        }

        $service_info = [
            'id'                                  => $shop_service_info->id,
            'shop_service_category_id'            => $shop_service_info->shop_service_category_id,
            'shop_service_category_id_permission' => in_array('shop_service_'.$type.'_category',$user_shop_permission['permission']) ? true : false,
            'name'                                => $shop_service_info->name,
            'name_permission'                     => in_array('shop_service_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'price'                               => $shop_service_info->price || $shop_service_info->price == 0 ?(string)$shop_service_info->price:'',
            'price_permission'                    => in_array('shop_service_'.$type.'_price',$user_shop_permission['permission']) ? true : false,
            'basic_price'                         => $shop_service_info->basic_price || $shop_service_info->basic_price == 0  ?(string)$shop_service_info->basic_price:'',
            'basic_price_permission'              => in_array('shop_service_'.$type.'_basic_price',$user_shop_permission['permission']) ? true : false,
            'show_type'                           => $shop_service_info->show_type,
            'show_type_permission'                => in_array('shop_service_'.$type.'_show_price',$user_shop_permission['permission']) ? true : false,
            'show_text'                           => $shop_service_info->show_text,
            'show_text_permission'                => in_array('shop_service_'.$type.'_show_price',$user_shop_permission['permission']) ? true : false,
            'show_time'                           => $shop_service_info->show_time,
            'show_time_permission'                => in_array('shop_service_'.$type.'_show_price',$user_shop_permission['permission']) ? true : false,
            'service_time'                        => $shop_service_info->service_time || $shop_service_info->service_time == 0 ? (string)$shop_service_info->service_time : '' ,
            'service_time_permission'             => in_array('shop_service_'.$type.'_service_time',$user_shop_permission['permission']) ? true : false,
            'lead_time'                           => $shop_service_info->lead_time?:'0',
            'lead_time_permission'                => in_array('shop_service_'.$type.'_lead_time',$user_shop_permission['permission']) ? true : false,
            'buffer_time'                         => $buffer_time,
            'buffer_time_permission'              => in_array('shop_service_'.$type.'_buffer_time',$user_shop_permission['permission']) ? true : false,
            'info'                                => $shop_service_info->info,
            'info_permission'                     => in_array('shop_service_'.$type.'_info',$user_shop_permission['permission']) ? true : false,
            'photo'                               => $shop_service_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_service_info->photo : NULL,
            'photo_permission'                    => in_array('shop_service_'.$type.'_photo',$user_shop_permission['permission']) ? true : false,
            'match_staffs'                        => $match_staff_arr,
            'match_staffs_permission'             => in_array('shop_service_'.$type.'_staff',$user_shop_permission['permission']) ? true : false,
            'match_advances'                      => $match_advance_arr,
            'match_advances_permission'           => in_array('shop_service_'.$type.'_advance',$user_shop_permission['permission']) ? true : false,
            'save_permission'                     => in_array('shop_service_'.$type.'_save',$user_shop_permission['permission']) ? true : false,
            'status'                              => $shop_service_info->status ?: 'published',
            'status_permission'                   => in_array('shop_service_'.$type.'_save',$user_shop_permission['permission']) ? true : false,
        ];

        $shop_categories = ShopServiceCategory::where('shop_id',$shop_info->id)->sort()->get();

        $data = [
            'status'                  => true,
            'permission'              => true,
            // 'save_permission'         => in_array('shop_service_'.$type.'_save',$user_shop_permission['permission']) ? true : false,
            'preview_permission'      => in_array('shop_service_'.$type.'_preview',$user_shop_permission['permission']) ? true : false,
            'shop_service_categories' => $shop_categories,
            'shop_staffs'             => $shop_sort_staffs,
            'shop_advances'           => $shop_advances,
            'data'                    => $service_info,
        ];

		return response()->json($data);
    }

    // 儲存商家服務資料
    public function shop_service_save($shop_id,$shop_service_id="")
    {
        // 驗證欄位資料
        $rules = [
            'name'                     => 'required', 
            'shop_service_category_id' => 'required', 
            'price'                    => 'required', 
            'service_time'             => 'required', 
            'basic_price'              => 'required',
        ];

        if( !$shop_service_id ){
            // 新增
            $rules['status'] = 'required';
        }

        $messages = [
            'name.required'                     => '請填寫服務名稱',
            'shop_service_category_id.required' => '請選擇服務類別',
            'price.required'                    => '請填寫價格',
            'service_time.required'             => '請填寫服務時間',
            'basic_price.required'              => '請填寫底價',
            'status.required'                   => '缺少是否暫存資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        if( $shop_service_id ){
            // 編輯
            $shop_service_info = ShopService::find($shop_service_id);
            if( !$shop_service_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到服務項目資料']]]);
            }
        }else{
            // 新增
            $shop_service_info = new ShopService;
            $shop_service_info->shop_id = $shop_id;
            $shop_service_info->type    = 'service';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 儲存商家服務資料
        $shop_service_info->shop_service_category_id = request('shop_service_category_id');
        $shop_service_info->name                     = request('name');
        $shop_service_info->price                    = request('price');
        $shop_service_info->basic_price              = request('basic_price');
        $shop_service_info->info                     = request('info');
        $shop_service_info->show_type                = request('show_type');
        $shop_service_info->show_text                = request('show_text');
        $shop_service_info->show_time                = request('show_time');
        $shop_service_info->service_time             = request('service_time');
        $shop_service_info->lead_time                = request('lead_time');
        $shop_service_info->buffer_time              = request('buffer_time');
        $shop_service_info->status                   = request('status');

        if( request('photo') && (!$shop_service_info->photo ||!preg_match('/'.$shop_service_info->photo.'/i',request('photo'))) ){
            $picName = PhotoController::save_base64_photo($shop_info->alias,request('photo'),$shop_service_info->photo);
            $shop_service_info->photo = $picName;
        } 

        $shop_service_info->save();  

        // 需判斷購買方案，若是基本和進階，基本上就是直接一起新增/編輯集團服務，多分店則只更新商家的服務資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            if( $shop_service_id ){
                $company_service_info = $shop_service_info->company_service_info;
                if( !$company_service_info ) {
                    $shop_service_category    = $shop_service_info->category_info;
                    $company_service_category = CompanyServiceCategory::where('id',$shop_service_category->company_category_id)->first();

                    $company_service_info = new CompanyService;
                    $company_service_info->company_id          = $company_info->id;
                    $company_service_info->company_category_id = $company_service_category->id;
                    $company_service_info->type                = 'service';
                }
            }else{
                $shop_service_category    = $shop_service_info->category_info;
                $company_service_category = CompanyServiceCategory::where('id',$shop_service_category->company_category_id)->first();

                $company_service_info = new CompanyService;
                $company_service_info->company_id          = $company_info->id;
                $company_service_info->company_category_id = $company_service_category->id;
                $company_service_info->type                = 'service';
            }
            
            // 一併更新集團的服務資料
            $company_service_info->name         = request('name');
            $company_service_info->price        = request('price');
            $company_service_info->basic_price  = request('basic_price');
            $company_service_info->info         = request('info');
            $company_service_info->show_type    = request('show_type');
            $company_service_info->show_text    = request('show_text');
            $company_service_info->show_time    = request('show_time');
            $company_service_info->service_time = request('service_time');
            $company_service_info->lead_time    = request('lead_time');
            $company_service_info->buffer_time  = request('buffer_time');
            $company_service_info->status       = request('status');
            if( request('photo') ){
                $company_service_info->photo = $shop_service_info->photo;
            }
            $company_service_info->save();

            if( !$shop_service_id ){
                $shop_service_info->company_service_id = $company_service_info->id;
                $shop_service_info->save();
            } 
        }

        // 此項服務可搭配的員工
        if( request('match_staffs') ){
            ShopServiceStaff::where('shop_service_id',$shop_service_info->id)->delete();
            $insert = [];
            foreach( request('match_staffs') as $staff_id ){
                if( $staff_id == null ) continue;
                $insert[] = [
                    'shop_service_id' => $shop_service_info->id,
                    'shop_staff_id'   => $staff_id,
                ]; 
            }
            ShopServiceStaff::insert($insert);
        }else{
            ShopServiceStaff::where('shop_service_id',$shop_service_info->id)->delete();
        }

        // 此服務可以選的加值選項
        if( request('match_advances') ){
            ShopServiceAdvance::where('shop_service_id',$shop_service_info->id)->delete();
            $insert = [];
            foreach( request('match_advances') as $advance_id ){
                if( $advance_id == null ) continue;
                $insert[] = [
                    'shop_service_id' => $shop_service_info->id,
                    'shop_advance_id' => $advance_id,
                ]; 
            }
            ShopServiceAdvance::insert($insert);
        }else{
            ShopServiceAdvance::where('shop_service_id',$shop_service_info->id)->delete();
        }

        return response()->json(['status'=>true,'data'=>$shop_service_info]);
    }

    // 刪除商家服務資料
    public function shop_service_delete($shop_id,$shop_service_id)
    {
        $shop_service_info = ShopService::find($shop_service_id);
        if( !$shop_service_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到服務項目資料']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 判斷是否已被優惠券、集點卡、儲值、方案、服務通知、條件通知模組使用
        $check_data = CompanyCoupon::where('company_id',$company_info->id)->get();
        foreach( $check_data as $data ){
            if( $data->commodityId == $shop_service_id ){
                return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->title.'(優惠券)使用。無法刪除服務']]]);
            }else{
                // 檢查使用限制裡得
                $check_coupon_limits = CompanyCouponLimit::where('company_coupon_id',$data->id)->where('commodity_id',$shop_service_id)->where('type','service')->first();
                if( $check_coupon_limits ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->title.'(優惠券)使用。無法刪除服務']]]);
            }
        }
        $check_data = CompanyLoyaltyCard::where('company_id',$company_info->id)->get();
        foreach( $check_data as $data ){
            if( $data->commodityId == $shop_service_id ){
                return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(集點卡)使用。無法刪除服務']]]);
            }else{
                // 檢查使用限制裡得
                $check_data_limits = CompanyLoyaltyCardLimit::where('company_loyalty_card_id',$data->id)->where('commodity_id',$shop_service_id)->where('type','service')->first();
                if( $check_data_limits ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(集點卡)使用。無法刪除服務']]]);
            }
        }
        $check_data = ShopTopUp::where('shop_id',$shop_info->id)->get();
        foreach( $check_data as $data ){
            if( $data->roles ){
                $check_limit = $data->roles->where('commodity_id',$shop_service_id)->where('second_type',3);
                if( $check_limit->count() ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(儲值)使用。無法刪除服務']]]);

                foreach( $data->roles as $role ){
                    if( $role->limit_commodity ){
                        $check_limit_commodity = $role->limit_commodity->where('commodity_id',$shop_service_id)->where('type','service');
                        if( $check_limit_commodity->count() ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(儲值)使用。無法刪除服務']]]);
                    }
                }
            } 
        }
        $check_data = ShopManagementMode::where('shop_id',$shop_info->id)->where('type','auto')->get();
        foreach( $check_data as $data ){
            $shop_services = explode(',',$data->shop_services );
            if( in_array($shop_service_id,$shop_services) ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(條件通知模組)使用。無法刪除服務']]]);
        }
        $check_data = ShopManagementGroup::where('shop_id',$shop_info->id)->get();
        foreach( $check_data as $data ){
            if( $data->shop_services ){
                $shop_services = $data->shop_services->pluck('shop_service_id')->toArray();
                if( in_array($shop_service_id,$shop_services) ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(服務通知)使用。無法刪除服務']]]);
            }
        }
        $check_data = ShopProgram::where('shop_id',$shop_info->id)->get();
        foreach( $check_data as $data ){
            foreach( $data->groups as $group ){
                if( $group->group_content ){
                    $content_commoditys = $group->group_content->where('commodity_type','service')->pluck('commodity_id')->toArray();
                    if( in_array($shop_service_id,$content_commoditys) ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(方案)使用。無法刪除服務']]]);
                }
            }
        }
        $check_data = ShopMembershipCard::where('shop_id',$shop_info->id)->get();
        foreach( $check_data as $data ){
            if( $data->roles ){
                foreach( $data->roles as $role ){
                    if( $role->limit_commodity ){
                        $check_limit_commodity = $role->limit_commodity->where('commodity_id',$shop_service_id)->where('type','service');
                        if( $check_limit_commodity->count() ) return response()->json(['status'=>false,'errors'=>['message'=>['此服務已被'.$data->name.'(會員卡)使用。無法刪除服務']]]);
                    }
                }
            } 
        }

        // 刪除商家服務資料
        $shop_service_info->delete();
        // 刪除關連資料
        ShopServiceStaff::where('shop_service_id',$shop_service_id)->delete();
        ShopServiceAdvance::where('shop_service_id',$shop_service_id)->delete();

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團服務
            CompanyService::where('id',$shop_service_info->company_service_id)->delete();

            // 刪除服務的圖片
            if( $shop_service_info->photo ){
                $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$shop_service_info->photo;
                if(file_exists($filePath)){
                    unlink($filePath);
                }
            }

            $shop_service_info->photo = NULL;
            $shop_service_info->save();
        }

        return response()->json(['status'=>true]);
    }

    // 更改商家服務上下架狀態
    public function shop_service_status($shop_id,$shop_service_id)
    {
        // 驗證欄位資料
        $rules     = ['status' => 'required'];
        $messages = [
            'status.required' => '缺少上下架資料',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_service = ShopService::find($shop_service_id);
        if( $shop_service ){
            $shop_service->status = request('status');
            $shop_service->save();
        }else{
            return response()->json(['status' => false,'errors' => ['message'=>['找不到服務項目資料']]]); 
        }
        
        return response()->json(['status'=>true]);
    }

    // 拿取商家有的可選的服務
    static public function shop_service_select($shop_id)
    {
        $shop_info = Shop::find( $shop_id );

        // 取得商家服務，並將有配合的服務做標記
        $category_infos = ShopServiceCategory::where('shop_id', $shop_id)->where('type', 'service')->get();
        // 在拿取shop裡有的服務
        $categories = [];
        foreach( $category_infos as $k => $info ){
            $services = [];
            foreach( $info->shop_services->where('status','published') as $service ){
                $services[] = [
                    'id'      => $service->id,
                    'name'    => $service->name,
                    'price'   => $service->price
                ];
            }

            $info->match_services = $services;
            unset($info->shop_services);

            $categories[] = [
                'category_name' => $info->name,
                'services'      => $services,
            ];
        }

        return $categories;
    }
}
