<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Guide;
use App\Models\GuideItem;
use App\Models\Permission;

class TeachingController extends Controller
{
    // 取得引導模式資料
    public function guide($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_guide',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $user_shop_staff_permission = Permission::where('user_id',auth()->user()->id)->where('shop_id',$shop_id)->whereNotNull('shop_staff_id')->first();

        $mode = $shop_info->buy_mode_id == 0 ? 'basic' : 'plus';
        switch ($shop_info->buy_mode_id) {
            case 0: // basic
                $mode    = 'basic';
                $version = '基本版';
                break;
            case 1:
            case 2:
                $mode = 'plus';
                $version = '進階版';
                break;
            case 5;
            case 6;
                $mode = 'pro';
                $version = '專業版';
                break;
        }
        
        $guides = Guide::where('mode',$mode)->get();

        $menu = [];
        foreach( $guides as $guide ){
            switch ($shop_info->buy_mode_id) {
                case 0: // basic
                    $item_mode = ['basic'];
                    $items = GuideItem::whereIn('mode', $item_mode)->where('chapter', $guide->chapter)->orderBy('sequence', 'ASC')->get();
                    break;
                case 1:
                case 2:
                    $item_mode = ['basic', 'plus'];
                    // plus第三章只需要拿取plus部分
                    if ($guide->chapter == '03' && in_array($shop_info->buy_mode_id, [1, 2])) {
                        $item_mode = ['plus'];
                    }

                    // 處理項目的前往連結
                    // 單人版不需要新增員工
                    $not_in = [];
                    if ($shop_info->buy_mode_id == 1) $not_in[] = 12;

                    $items = GuideItem::whereIn('mode', $item_mode)->whereNotIn('id', $not_in)->where('chapter', $guide->chapter)->orderBy('sequence', 'ASC')->get();
                    break;
                case 5;
                case 6;
                    $item_mode = ['basic', 'plus', 'pro'];
                    // plus第三章只需要拿取plus部分
                    if ($guide->chapter == '03' && in_array($shop_info->buy_mode_id, [1, 2])) {
                        $item_mode = ['plus'];
                    }

                    // 處理項目的前往連結
                    // 單人版不需要新增員工
                    $not_in = [];
                    if ($shop_info->buy_mode_id == 1) $not_in[] = 12;

                    $items = GuideItem::whereIn('mode', $item_mode)->whereNotIn('id', $not_in)->where('chapter', $guide->chapter)->orderBy('sequence', 'ASC')->get();

                    break;
            }

            foreach( $items as $item ){
                // 基本版不需要Line官方帳號授權
                if( $item->id == 9 ) continue;
                
                $item->url   = str_replace('staff_id',$user_shop_staff_permission->shop_staff_id,$item->url);
                $item->video = $item->video ? $item->video_info->video_url : '';
            }

            $guide->items = $items;
            $guide->img   = url('/').'/images/'.$guide->img;

            $menu[] = $guide;
        }

        $data = [ 
            'status'     => true,
            'permission' => true,
            'type'       => $mode,
            'version'    => $version,
            'data'       => $menu 
        ];

        return response()->json($data);
    }
}
