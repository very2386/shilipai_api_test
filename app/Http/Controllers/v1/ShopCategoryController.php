<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Models\Company;
use App\Models\CompanyServiceCategory;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\ShopServiceCategory;
use App\Models\Permission;

class ShopCategoryController extends Controller
{
    private $permission_key = 'shop_category';

    // 取得集團全部服務分類資料
    public function shop_service_category($shop_id)
    {
        $shop_info       = Shop::find($shop_id);
        $shop_company    = $shop_info->company_info;
        $shop_categories = ShopServiceCategory::where('shop_id',$shop_info->id)->sort()->get();

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_category',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $delete_permission   = in_array('shop_category_sequence',$user_shop_permission['permission']) ? true : false;
        $sequence_permission = in_array('shop_category_delete',$user_shop_permission['permission']) ? true : false;

        return response()->json(['status'=>true,'permission'=> true,'delete_permission'=>$delete_permission,'sequence_permission'=>$sequence_permission,'data'=>$shop_categories]);
    }

    // 新增/編輯指定商家服務分類資料
    public function shop_service_category_info($shop_id,$shop_category_id="")
    {
        if( $shop_category_id ){
            $shop_category_info = ShopServiceCategory::find( $shop_category_id );
            if( !$shop_category_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到分類資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_category_info = new ShopServiceCategory;
            $type               = 'create';
        }

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_category_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_category_info->name_permission  = in_array('shop_category_'.$type.'_name',$user_shop_permission['permission']) ? true : false;
        $shop_category_info->photo_permission = in_array('shop_category_'.$type.'_photo',$user_shop_permission['permission']) ? true : false;
        $shop_category_info->info_permission  = in_array('shop_category_'.$type.'_info',$user_shop_permission['permission']) ? true : false;

        $category_info = [
            'id'               => $shop_category_info->id,
            'name'             => $shop_category_info->name,
            'name_permission'  => in_array('shop_category_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'photo'            => $shop_category_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_category_info->photo : NULL,
            'photo_permission' => in_array('shop_category_'.$type.'_photo',$user_shop_permission['permission']) ? true : false,
            'info'             => $shop_category_info->info,
            'info_permission'  => in_array('shop_category_'.$type.'_info',$user_shop_permission['permission']) ? true : false,
            'services'         => $shop_category_info->shop_services,
        ];

        $data = [
            'status'             => true,
            'permission'         => true,
            'preview_permission' => in_array('shop_category_'.$type.'_preview',$user_shop_permission['permission']) ? true : false,
            'data'               => $category_info,
        ];

        return response()->json($data);
    }

    // 儲存服務分類資料
    public function shop_service_category_save($shop_id,$shop_category_id="")
    {
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info; 

        if( $shop_category_id ){
            // 編輯
            $shop_category_info = ShopServiceCategory::find( $shop_category_id );
            if( !$shop_category_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到分類資料']]]);
            }
        }else{
            // 新增
            // 驗證欄位資料
            $rules     = [ 'name' => 'required' ];
            $messages = [
                'name.required' => '請填寫分類名稱',
            ];

            $validator = Validator::make(request()->all(), $rules, $messages);

            if ($validator->fails()){
                return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
            }

            $shop_category_info = new ShopServiceCategory;
            $shop_category_info->shop_id = $shop_id;
            $shop_category_info->type = 'service';
        }
    	
        $shop_category_info->name = request('name');
        $shop_category_info->info = request('info');
    	if( request('photo') && ( !$shop_category_info->photo || !preg_match('/'.$shop_category_info->photo.'/i',request('photo'))) ){
			$picName = PhotoController::save_base64_photo($shop_info->alias,request('photo'),$shop_category_info->photo);
	        $shop_category_info->photo = $picName;
    	}	

        $shop_category_info->save();

        // 需判斷購買方案，若是基本和進階，基本上就是直接一起新增/編輯集團服務分類，多分店則只更新商家的分類資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            if( $shop_category_id ){
                $company_category_info = CompanyServiceCategory::find($shop_category_info->company_category_id);
                if( !$company_category_info ){
                    $company_category_info = new CompanyServiceCategory;
                    $company_category_info->company_id = $company_info->id;
                    $company_category_info->type       = 'service';
                }
            }else{
                $company_category_info = new CompanyServiceCategory;
                $company_category_info->company_id = $company_info->id;
                $company_category_info->type       = 'service';
            }
            
            $company_category_info->name  = request('name');
            $company_category_info->info  = request('info');
            $company_category_info->photo = $shop_category_info->photo;
            $company_category_info->save();

            if( !$shop_category_id ){
                $shop_category_info->company_category_id = $company_category_info->id;
                $shop_category_info->save();
            } 
        }
        
        return response()->json(['status'=>true,'data'=>$shop_category_info]);
    }

    // 刪除指定商家指定的分類資料
    public function shop_service_category_delete($shop_id,$shop_category_id)
    {
        $shop_category_info = ShopServiceCategory::find( $shop_category_id );

        if( !$shop_category_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到分類資料']]]);
        }

        if( $shop_category_info->shop_services->count() != 0 ){
            return response()->json(['status'=>false,'errors'=>['message'=>['因為此分類內已有服務項目，所以無法刪除分類資料']]]);
        }

        $shop_category_info->delete();

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info; 
        
        // 需判斷購買方案，若是基本和進階，基本上就是直接一起刪除集團服務分類，多分店則只刪除商家的分類資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            CompanyServiceCategory::where('id',$shop_category_info->company_category_id)->delete();
            // 刪除圖片
            $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$shop_category_info->photo;
            if($shop_category_info->photo && file_exists($filePath)){
                unlink($filePath);
            }
        } 

        return response()->json(['status'=>true]);
    }

}
