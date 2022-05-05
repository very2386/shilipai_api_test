<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyService;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\ShopServiceStaff;
use App\Models\ShopServiceAdvance;
use App\Models\Permission;

class ShopAdvanceController extends Controller
{
    // 取得商家全部加值服務資料
    public function shop_advance($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_advance',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $advances = ShopService::where('shop_id',$shop_id)->where('type','advance')->sort()->get();

        $data = [
            'status'                    => true,
            'permission'                => true,
            'advance_add_permission'    => in_array('shop_advance_create_btn',$user_shop_permission['permission']) ? true : false,     // 加值服務新增
            'advance_edit_permission'   => in_array('shop_advance_edit_btn',$user_shop_permission['permission']) ? true : false,       // 加值服務編輯
            'advance_delete_permission' => in_array('shop_advance_delete',$user_shop_permission['permission']) ? true : false,         // 加值服務刪除
            'advance_copy_permission'   => in_array('shop_advance_copy',$user_shop_permission['permission']) ? true : false,           // 加值服務複製
            'advance_status_permission' => in_array('shop_advance_status',$user_shop_permission['permission']) ? true : false,         // 加值服務上下架
            'data'                      => $advances,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家加值服務資料
	public function shop_advance_info($shop_id,$shop_advance_id="",$mode="")
	{
        if( $shop_advance_id ){
            $shop_advance_info = ShopService::find($shop_advance_id);
            if( !$shop_advance_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到加值項目資料']]]);
            }
            $type = $mode == '' ? 'edit' : 'create';
        }else{
            $type = 'create';
            $shop_advance_info = new ShopService;
        }
		
        $shop_info = Shop::find($shop_id);

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_advance_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 拿取商家可選的服務
        $categories = ShopServiceController::shop_service_select($shop_id);

        $match_service = [];
        foreach( $shop_advance_info->match_services->pluck('shop_service_id')->toArray() as $service ){
            $match_service[] = (string) $service;
        }

        $buffer_time = '0';
        if( $shop_advance_info->buffer_time  ) $buffer_time = $shop_advance_info->buffer_time;
        elseif( !$shop_advance_id ){
            if( $shop_info->shop_set->buffer_time ) $buffer_time = (string)$shop_info->shop_set->buffer_time;
        }

        $advance_data = [
            'id'                        => $shop_advance_info->id,
            'name'                      => $shop_advance_info->name,
            'name_permission'           => in_array('shop_advance_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'price'                     => $shop_advance_info->price || $shop_advance_info->price == 0 ? (string)$shop_advance_info->price : '',
            'price_permission'          => in_array('shop_advance_'.$type.'_price',$user_shop_permission['permission']) ? true : false,
            'basic_price'               => $shop_advance_info->basic_price || $shop_advance_info->basic_price == 0 ? (string)$shop_advance_info->basic_price : '',
            'basic_price_permission'    => in_array('shop_advance_'.$type.'_basic_price',$user_shop_permission['permission']) ? true : false,
            'show_type'                 => $shop_advance_info->show_type,
            'show_type_permission'      => in_array('shop_advance_'.$type.'_show_price',$user_shop_permission['permission']) ? true : false,
            'show_text'                 => $shop_advance_info->show_text,
            'show_text_permission'      => in_array('shop_advance_'.$type.'_show_price',$user_shop_permission['permission']) ? true : false,
            'show_time'                 => $shop_advance_info->show_time,
            'show_time_permission'      => in_array('shop_advance_'.$type.'_show_time',$user_shop_permission['permission']) ? true : false,
            'service_time'              => $shop_advance_info->service_time || $shop_advance_info->service_time == 0 ? (string)$shop_advance_info->service_time : '0',
            'service_time_permission'   => in_array('shop_advance_'.$type.'_time',$user_shop_permission['permission']) ? true : false,
            'buffer_time'               => $buffer_time,
            'buffer_time_permission'    => in_array('shop_advance_'.$type.'_buffer_time',$user_shop_permission['permission']) ? true : false,
            'match_services'            => $match_service,
            'match_services_permission' => in_array('shop_advance_'.$type.'_service',$user_shop_permission['permission']) ? true : false,
            'status'                    => $shop_advance_info->status ?: 'pending',
            'status_permission'         => in_array('shop_advance_'.$type.'_save',$user_shop_permission['permission']) ? true : false,
        ];

        $data = [
            'status'               => true,
            'permission'           => true,
            // 'pending_permission'   => in_array('shop_advance_'.$type.'_pending',$user_shop_permission['permission']) ? true : false,
            'published_permission' => in_array('shop_advance_'.$type.'_published',$user_shop_permission['permission']) ? true : false,
            'shop_services'        => $categories,
            'data'                 => $advance_data,
        ];

		return response()->json($data);
    }

    // 儲存shop加值服務資料
    public function shop_advance_create()
    {
        // 驗證欄位資料 
        $rules = [ 'shop_id' => 'required' , 'status' => 'required', 'name' => 'required' , 'price' => 'required' , 'basic_price' => 'required' , 'service_time' => 'required'];

        $messages = [
            'shop_id.required'      => '缺少商家id資料',
            'status.required'       => '缺少上下架資料',
            'name.required'         => '請填寫分類名稱',
            'price.required'        => '請填寫結帳金額',
            'basic_price.required'  => '請填寫底價金額',
            'service_time.required' => '請服務時間',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_info    = Shop::find(request('shop_id'));
        $company_info = $shop_info->company_info;

        // 新增集團加值服務資料
        $company_advavce_info = new CompanyService;
        $company_advavce_info->company_id   = $company_info->id;
        $company_advavce_info->name         = request('name');
        $company_advavce_info->type         = 'advance';
        $company_advavce_info->price        = request('price');
        $company_advavce_info->basic_price  = request('basic_price');
        $company_advavce_info->show_type    = request('show_type');
        $company_advavce_info->show_text    = request('show_text');
        $company_advavce_info->show_time    = request('show_time');
        $company_advavce_info->service_time = request('service_time');
        $company_advavce_info->buffer_time  = request('buffer_time');
        $company_advavce_info->status       = request('status');
        $company_advavce_info->save();

        // 儲存商加值服務資料
        $shop_advavce_info = new ShopService;
        $shop_advavce_info->shop_id            = request('shop_id');
        $shop_advavce_info->company_service_id = $company_advavce_info->id;
        $shop_advavce_info->name               = request('name');
        $shop_advavce_info->type               = 'advance';
        $shop_advavce_info->price              = request('price');
        $shop_advavce_info->basic_price        = request('basic_price');
        $shop_advavce_info->show_type          = request('show_type');
        $shop_advavce_info->show_text          = request('show_text');
        $shop_advavce_info->show_time          = request('show_time');
        $shop_advavce_info->service_time       = request('service_time');
        $shop_advavce_info->buffer_time        = request('buffer_time');
        $shop_advavce_info->status             = request('status');
        $shop_advavce_info->save();  

        // 此加值服務可以搭配的服務選項
        if( request('match_services') ){
            ShopServiceAdvance::where('shop_advance_id',$shop_advavce_info->id)->delete();
            $insert = [];
            foreach( request('match_services') as $service_id ){
                if( $service_id == null ) continue;
                $insert[] = [
                    'shop_advance_id' => $shop_advavce_info->id,
                    'shop_service_id' => $service_id,
                ]; 
            }
            ShopServiceAdvance::insert($insert);
        }else{
            ShopServiceAdvance::where('shop_advance_id',$shop_advavce_info->id)->delete();
        }

        return response()->json(['status'=>true,'data'=>$shop_advavce_info]);
    }

    // 儲存shop加值服務資料
    public function shop_advance_save($shop_id,$shop_advance_id="")
    {
        // 驗證欄位資料
        $rules = [
            'name'         => 'required', 
            'price'        => 'required', 
            'service_time' => 'required', 
            'basic_price'  => 'required',
        ];

        if( !$shop_advance_id ){
            // 新增
            $rules['status'] = 'required';
        }

        $messages = [
            'name.required'         => '請填寫分類名稱',
            'price.required'        => '請填寫結帳金額',
            'basic_price.required'  => '請填寫底價金額',
            'service_time.required' => '請服務時間',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        if( $shop_advance_id ){
            $shop_advance_info = ShopService::find($shop_advance_id);
            if( !$shop_advance_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到加值項目資料']]]);
            }
        }else{
            // 新增
            $shop_advance_info = new ShopService;
            $shop_advance_info->shop_id = $shop_id;
            $shop_advance_info->type    = 'advance';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 儲存商家服務資料
        $shop_advance_info->name         = request('name');
        $shop_advance_info->price        = request('price');
        $shop_advance_info->basic_price  = request('basic_price');
        $shop_advance_info->show_type    = request('show_type');
        $shop_advance_info->show_text    = request('show_text');
        $shop_advance_info->show_time    = request('show_time');
        $shop_advance_info->service_time = request('service_time');
        $shop_advance_info->buffer_time  = request('buffer_time');
        $shop_advance_info->status       = request('status');
        $shop_advance_info->save();  

        // 需判斷購買方案，若是基本和進階，基本上就是直接一起新增/編輯集團加值服務，多分店則只更新商家的加值服務資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            if( $shop_advance_id ){
                $company_advance_info = CompanyService::find($shop_advance_info->company_service_id);
            }else{
                $company_advance_info = new CompanyService;
                $company_advance_info->company_id          = $company_info->id;
                $company_advance_info->type                = 'advance';
            }

	        // 一併更新集團的加值服務資料
	        $company_advance_info->name         = request('name');
	        $company_advance_info->price        = request('price');
	        $company_advance_info->basic_price  = request('basic_price');
	        $company_advance_info->show_type    = request('show_type');
	        $company_advance_info->show_text    = request('show_text');
	        $company_advance_info->show_time    = request('show_time');
	        $company_advance_info->service_time = request('service_time');
	        $company_advance_info->buffer_time  = request('buffer_time');
	        $company_advance_info->status       = request('status');
	        $company_advance_info->save();

            if( !$shop_advance_id ){
                $shop_advance_info->company_service_id = $company_advance_info->id;
                $shop_advance_info->save();
            } 
	    }

        // 此加值服務可以搭配的服務選項
        if( request('match_services') ){
            ShopServiceAdvance::where('shop_advance_id',$shop_advance_info->id)->delete();
            $insert = [];
            foreach( request('match_services') as $service_id ){
                if( $service_id == null ) continue;
                $insert[] = [
                    'shop_advance_id' => $shop_advance_info->id,
                    'shop_service_id' => $service_id,
                ]; 
            }
            ShopServiceAdvance::insert($insert);
        }else{
            ShopServiceAdvance::where('shop_advance_id',$shop_advance_info->id)->delete();
        }

        return response()->json(['status'=>true,'data'=>$shop_advance_info]);
    }

    // 刪除shop加值服務資料
    public function shop_advance_delete($shop_id,$shop_advance_id)
    {
        $shop_advance = ShopService::find($shop_advance_id);
        if( !$shop_advance ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到加值項目資料']]]);
        }

        $shop_advance->delete();
        // 刪除關連資料
        ShopServiceAdvance::where('shop_advance_id',$shop_advance_id)->delete();

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團服務
            CompanyService::where('id',$shop_advance->company_service_id)->delete();
        }

        return response()->json(['status'=>true]);
    }

    // 更改shop加值服務上下架狀態
    public function shop_advance_status($shop_id,$shop_advance_id)
    {
        // 驗證欄位資料
        $rules    = ['status' => 'required'];
        $messages = [
            'status.required' => '缺少上下架資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_service = ShopService::find($shop_advance_id);
        $shop_service->status = request('status');
        $shop_service->save();

        return response()->json(['status'=>true]);
    }

    // 拿取商家有的可選的加值服務
    static public function shop_advance_select($shop_id)
    {
        $shop_advances = ShopService::where('shop_id',$shop_id)->where('type','advance')->sort()->get();
        $advances = [];
        foreach( $shop_advances as $advance ){
            $advances[] = [
                'id'      => $advance->id,
                'name'    => $advance->name,
            ];
        }

        return $advances;
    }
}
