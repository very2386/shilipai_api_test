<?php

namespace App\Http\Controllers\v1;

use JWTAuth;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Permission;
use App\Models\PermissionMenu;
use App\Models\Shop;

class PermissionController extends Controller
{
    // 判斷是否為員工身分
    static public function is_staff($shop_id)
    {
        $permission = Permission::where('user_id',auth()->getUser()->id)->where('shop_id',$shop_id)->get();
        if( $permission->count() == 1 ){
            return true;
        }
        return false;
    }

    // 取得company/shop權限
    public function get_permission($type,$id)
    {
        $permission = Permission::where('user_id',auth()->user()->id)->where($type.'_id',$id)->with($type.'_info')->first();

        if( $permission ){
            return response()->json(['status'=>true,'data'=>$permission]);
        }else{
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到權限資料']]]);
        }
    }

    // 拿取使用者的集團權限
    static public function user_company_permission($company_id)
    {
        // 使用者資料
        $user_info = auth()->User();
        // 集團資料
        $company_info = Company::find($company_id);

        // 確認user對應的集團資料
        $user_company_permission = Permission::where('user_id',$user_info->id)->where('company_id',$company_id)->where('shop_id',NULL)->first();
        if( !$user_company_permission ){
            return ['status'=>false,'errors'=>['message'=>['使用者沒有集團權限']]];
        }

        // 判斷完權限後，需最後判定購買方案的權限
        switch ($company_info->buy_mode_id) {
            case 0: // 美業官網基本版
                $value = PermissionMenu::where('basic',1)->pluck('value')->toArray();
                break;
            case 1: // 美業官網進階單員工版（一年份）
                $value = PermissionMenu::where('plus',1)->pluck('value')->toArray();
                break;
            case 2: // 美業管家進階多員工版（一年份）
                $value = PermissionMenu::where('plus_m',1)->pluck('value')->toArray();
                break;
            case 5: // 美業管家專業單員工版（一年份）
                $value = PermissionMenu::where('pro',1)->pluck('value')->toArray();
                break;
            case 6: // 美業管家專業多員工版（一年份）
                $value = PermissionMenu::where('pro_m',1)->pluck('value')->toArray();
                break;
        }

        $permissions = array_intersect($value, explode(',',$user_company_permission->permission));
        
        return [ 'status' => true , 'permission' => $permissions ];
    }

    // 拿取使用者的商家權限
    static public function user_shop_permission($shop_id)
    {
        // 使用者資料
        $user_info    = auth()->getUser();
        // 商家資料
        $shop_info    = Shop::find($shop_id);
        // 所屬集團資料
        $company_info = $shop_info->company_info; 

        // 確認user對應的分店資料
        $user_shop_permission_info = Permission::where('user_id',$user_info->id)->where('shop_id',$shop_id)->where('shop_staff_id',NULL)->first();
        if( !$user_shop_permission_info ){
            return [ 'status' => false , 'errors'=>['message'=>['使用者沒有集團權限']] ];
        }
        $user_shop_permission = explode(',',$user_shop_permission_info->permission);

        // 判斷完權限後，需最後判定購買方案的權限
        switch ($shop_info->buy_mode_id) {
            case 0: // 美業官網基本版
                $value = PermissionMenu::where('basic',1)->pluck('value')->toArray();
                break;
            case 1: // 美業官網進階單員工版（一年份）
                $value = PermissionMenu::where('plus',1)->pluck('value')->toArray();
                break;
            case 2: // 美業管家進階多員工版（一年份）
                $value = PermissionMenu::where('plus_m',1)->pluck('value')->toArray();
                break;
            case 5: // 美業管家專業單員工版（一年份）
                $value = PermissionMenu::where('pro',1)->pluck('value')->toArray();
                break;
            case 6: // 美業管家專業多員工版（一年份）
                $value = PermissionMenu::where('pro_m',1)->pluck('value')->toArray();
                break;
        }

        $permissions = array_intersect($value, $user_shop_permission);
        
        return [ 'status' => true , 'permission' => $permissions ];
    }

    // 拿取員工的商家權限
    static public function user_staff_permission($shop_id)
    {
        // 使用者資料
        $user_info    = auth()->User();
        // 商家資料
        $shop_info    = Shop::find($shop_id);
        // 所屬集團資料
        $company_info = $shop_info->company_info; 

        // 確認user對應的分店資料
        $user_staff_permission_info = Permission::where('user_id',$user_info->id)->where('shop_id',$shop_id)->where('shop_staff_id','!=',NULL)->first();
        if( !$user_staff_permission_info ){
            return [ 'status' => false , 'errors' => ['message'=>['使用者沒有員工權限']] ];
        }
        $user_staff_permission = explode(',',$user_staff_permission_info->permission);
        
        return [ 'status' => true , 'permission' => $user_staff_permission ];
    }
}
