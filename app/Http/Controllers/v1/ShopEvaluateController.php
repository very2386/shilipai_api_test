<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopEvaluate;
use App\Models\ShopServiceCategory;
use App\Models\ShopService;

class ShopEvaluateController extends Controller
{
    // 服務評價設定資料
    public function shop_evaluate($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_evaluate',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

        $evaluate = ShopEvaluate::where('shop_id',$shop_id)->first();
        if( !$evaluate ){
        	// 沒有服務評價設定資料，所以先建一筆預設
        	$evaluate = new ShopEvaluate;
        	$evaluate->shop_id              = $shop_id;
        	$evaluate->send_type            = 1;
            $evaluate->send_type_perimssion = in_array('shop_evaluate_send_type',$user_shop_permission['permission']) ? true : false;
        	$evaluate->hour                 = 0;
            $evaluate->hour_perimssion      = in_array('shop_evaluate_hour',$user_shop_permission['permission']) ? true : false;
        	$evaluate->save();
        }else{
            $evaluate->send_type_perimssion = in_array('shop_evaluate_send_type',$user_shop_permission['permission']) ? true : false;
            $evaluate->hour_perimssion      = in_array('shop_evaluate_hour',$user_shop_permission['permission']) ? true : false;
        }

        // 服務選項
        $shop_service_categories = ShopServiceCategory::where('shop_id',$shop_id)->select('id','name')->get();
        foreach( $shop_service_categories as $service_category ){
            $service_category->shop_services = ShopService::where('shop_service_category_id',$service_category->id)->select('id','name')->get();
            $evaluate_services = explode(',',$evaluate->shop_services);
            foreach( $service_category->shop_services as $service ){
                if( in_array( $service->id , $evaluate_services) ){
                    $service->selected = true;
                }else{
                    $service->selected = false;
                }
            }
        }

        $evaluate->shop_services            = $shop_service_categories;
        $evaluate->shop_services_permission = in_array('shop_evaluate_service',$user_shop_permission['permission']) ? true : false;

        $data = [
            'status'     => true,
            'permission' => true,
            'data'       => $evaluate,
        ];

        return response()->json($data);
    }

    // 服務評價設定資料儲存
    public function shop_evaluate_save($shop_id)
    {
    	$evaluate = ShopEvaluate::where('shop_id',$shop_id)->first();

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	// 處理服務項目資料
    	$selected_service = [];
    	foreach( request('shop_services') as $category => $service ){
    	    foreach( $service['shop_services'] as $se ){
    	        if( $se['selected'] ) $selected_service[] = $se['id'];
    	    }
    	}

    	$evaluate->send_type     = request('send_type');
    	$evaluate->hour          = request('hour');
    	$evaluate->shop_services = implode(',',$selected_service);
    	$evaluate->save();

    	return response()->json(['status' => true , 'data' => $evaluate]);
    }
}
