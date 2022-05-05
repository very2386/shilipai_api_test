<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CompanyProductCategory;
use Validator;
use App\Models\Shop;
use App\Models\ShopProductCategory;

class ShopProductCategoryController extends Controller
{
    // 取得集團全部產品分類資料
    public function shop_product_category($shop_id)
    {
        $shop_info          = Shop::find($shop_id);
        $product_categories = ShopProductCategory::select('id','name')->where('shop_id',$shop_info->id)->sort()->get();

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_product_category',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $sequence_permission = in_array('shop_product_category_sequence',$user_shop_permission['permission']) ? true : false;

        $data = [
            'status'              => true,
            'permission'          => true,
            'sequence_permission' => $sequence_permission,
            'data'                => $product_categories
        ];
        return response()->json($data);
    }

    // 新增/編輯指定商家產品分類資料
    public function shop_product_category_info($shop_id,$shop_product_category_id="")
    {
        if( $shop_product_category_id ){
            $product_category = ShopProductCategory::find( $shop_product_category_id );
            if( !$product_category ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到分類資料']]]);
            }
            $type = 'edit';
        }else{
            $product_category = new ShopProductCategory;
            $type               = 'create';
        }

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_product_category_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $product_category->name_permission  = in_array('shop_product_category_'.$type.'_name',$user_shop_permission['permission']) ? true : false;
        $product_category->photo_permission = in_array('shop_product_category_'.$type.'_photo',$user_shop_permission['permission']) ? true : false;
        $product_category->info_permission  = in_array('shop_product_category_'.$type.'_info',$user_shop_permission['permission']) ? true : false;

        $category_info = [
            'id'               => $product_category->id,
            'name'             => $product_category->name,
            'name_permission'  => in_array('shop_product_category_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'photo'            => $product_category->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$product_category->photo : NULL,
            'photo_permission' => in_array('shop_product_category_'.$type.'_photo',$user_shop_permission['permission']) ? true : false,
            'info'             => $product_category->info,
            'info_permission'  => in_array('shop_product_category_'.$type.'_info',$user_shop_permission['permission']) ? true : false,
            // 'products'         => $product_category->shop_products,
        ];

        $data = [
            'status'             => true,
            'permission'         => true,
            'preview_permission' => in_array('shop_product_category_'.$type.'_preview',$user_shop_permission['permission']) ? true : false,
            'data'               => $category_info,
        ];

        return response()->json($data);
    }

    // 儲存產品分類資料
    public function shop_product_category_save($shop_id,$shop_product_category_id="")
    {
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info; 

        if( $shop_product_category_id ){
            // 編輯
            $shop_product_category = ShopProductCategory::find( $shop_product_category_id );
            if( !$shop_product_category ){
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

            $shop_product_category = new ShopProductCategory;
            $shop_product_category->shop_id = $shop_id;
        }
    	
        $shop_product_category->name = request('name');
        $shop_product_category->info = request('info');
    	if( request('photo') && ( !$shop_product_category->photo || !preg_match('/'.$shop_product_category->photo.'/i',request('photo'))) ){
			$picName = PhotoController::save_base64_photo($shop_info->alias,request('photo'),$shop_product_category->photo);
	        $shop_product_category->photo = $picName;
    	}	

        $shop_product_category->save();

        // 需判斷購買方案，若是基本和進階，基本上就是直接一起新增/編輯集團產品分類，多分店則只更新商家的分類資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            if( $shop_product_category_id ){
                $company_category_info = CompanyProductCategory::find($shop_product_category->company_product_category_id);
                if( !$company_category_info ){
                    $company_category_info = new CompanyProductCategory;
                    $company_category_info->company_id = $company_info->id;
                }
            }else{
                $company_category_info = new CompanyProductCategory;
                $company_category_info->company_id = $company_info->id;
            }
            
            $company_category_info->name  = request('name');
            $company_category_info->info  = request('info');
            $company_category_info->photo = $shop_product_category->photo;
            $company_category_info->save();

            if( !$shop_product_category_id ){
                $shop_product_category->company_product_category_id = $company_category_info->id;
                $shop_product_category->save();
            } 
        }
        
        return response()->json(['status' => true, 'data' => $shop_product_category]);
    }

    // 刪除指定商家指定的分類資料
    public function shop_product_category_delete($shop_id,$shop_product_category_id)
    {
        $product_category = ShopProductCategory::find( $shop_product_category_id );

        if( !$product_category ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到分類資料']]]);
        }

        if( $product_category->shop_products->count() != 0 ){
            return response()->json(['status'=>false,'errors'=>['message'=>['因為此分類內已有產品項目，所以無法刪除分類資料']]]);
        }

        $product_category->delete();

        $shop_info = Shop::find($shop_id);
        
        // 需判斷購買方案，若是基本和進階，基本上就是直接一起刪除集團產品分類，多分店則只刪除商家的分類資料
        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            CompanyProductCategory::where('id',$product_category->company_category_id)->delete();
            // 刪除圖片
            $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$product_category->photo;
            if($product_category->photo && file_exists($filePath)){
                unlink($filePath);
            }
        } 

        return response()->json(['status'=>true]);
    }

}
