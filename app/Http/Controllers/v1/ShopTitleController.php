<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Models\Company;
use App\Models\CompanyTitle;
use App\Models\Shop;
use App\Models\Permission;
use App\Models\CompanyStaff;
use App\Models\ShopStaff;


class ShopTitleController extends Controller
{
    // 取得商家全部職稱資料
    public function shop_titles($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_titles',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	$titles = CompanyTitle::where('company_id',$company_info->id)->orderBy('id','DESC')->get();

    	$data = [
            'status'                       => true,
            'permission'                   => true,
            'shop_title_create_permission' => in_array('shop_title_create_btn',$user_shop_permission['permission']) ? true : false, 
            'shop_title_edit_permission'   => in_array('shop_title_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'shop_title_delete_permission' => in_array('shop_title_delete',$user_shop_permission['permission']) ? true : false, 
            'data'                         => $titles,
        ];

        return response()->json($data);
    }

    // 儲存商家職稱資料
    public function shop_title_save($shop_id)
    {
    	$return_data = request()->all();

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	foreach( $return_data as $data ){
    		if( isset($data['id']) && $data['id'] ){
    			CompanyTitle::where('id',$data['id'])->update(['name'=>$data['name']]);
    		}else{
				if( !isset($data['name']) ){
					return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['職稱不可以輸入空白']]]);
				}



    			$new_data = new CompanyTitle;
    			$new_data->company_id = $company_info->id;
    			$new_data->name       = $data['name'];
    			$new_data->save();
    		}
    	}

    	$titles = $company_info->company_titles;

    	return response()->json(['status'=>true,'data'=>$titles]);
    }

    // 刪除商家職稱資料
    public function shop_title_delete($shop_id,$company_title_id)
    {
    	$model = CompanyTitle::find($company_title_id);

    	$model->delete();

    	// 相關員工有使用此職稱的話，就需要變成null
    	CompanyStaff::where('company_title_id_a',$company_title_id)->update(['company_title_id_a'=>NULL]);
    	CompanyStaff::where('company_title_id_b',$company_title_id)->update(['company_title_id_b'=>NULL]);
    	ShopStaff::where('company_title_id_a',$company_title_id)->update(['company_title_id_a'=>NULL]);
    	ShopStaff::where('company_title_id_b',$company_title_id)->update(['company_title_id_b'=>NULL]);

    	return response()->json(['status'=>true]);
    }
}
