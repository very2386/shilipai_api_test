<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\ShopCoupon;
use App\Models\ShopCustomer;
use App\Models\ShopManagement;
use App\Models\ShopManagementCustomerList;
use App\Models\ShopManagementGroup;
use App\Models\ShopManagementService;
use App\Models\ShopNoticeMode;
use App\Models\ShopService;
use App\Models\ShopServiceCategory;

class ShopManagementGroupController extends Controller
{
    // 取得服務通知列表資料
    public function shop_management_group_lists($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_management_group_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $management_groups = ShopManagementGroup::where('shop_id', $shop_id)->orderBy('id', 'DESC')->get();
        $data = [];

        $send_type = [
            '1' => '手機簡訊與LINE都發送',
            '2' => '僅手機簡訊',
            '3' => '僅LINE',
            '4' => '以LINE優先，無LINE者發送手機簡訊',
        ];

        foreach ($management_groups as $group) {
            $before                 = $group->management_details->where('type', 'before')->first();
            $before->time_text      = ($before->notice_day == 0 ? '當天 ' : '前'.$before->notice_day.'天 ').substr($before->notice_time,0,5);
            $before->mode_text      = $before->mode_info ? $before->mode_info->name : "無" ;
            $before->send_type_text = $send_type[$before->send_type];
            $before->shop_coupons   = $before->shop_coupons ? $before->shop_coupon_info->title : "無";
            unset($before->shop_coupon_info);
            unset($before->mode_info);

            $after                 = $group->management_details->where('type', 'after')->first();
            $after->time_text      = '後'.($after->notice_hour == 1 ? '1小時' : ( $after->notice_hour == 24 ? '隔天' : ($after->notice_hour/24).'天')  .substr($after->notice_time,0,5) );
            $after->mode_text      = $after->mode_info ? $after->mode_info->name : "無";
            $after->send_type_text = $send_type[$after->send_type];
            $after->shop_coupons   = $after->shop_coupons ? $after->shop_coupon_info->title : "無";
            unset($after->shop_coupon_info);
            unset($after->mode_info);

            $back                 = $group->management_details->where('type', 'back')->first();
            $back->time_text      = ($back->notice_day == 0 ? '當天 ' : '後'.$back->notice_day.'天 ').substr($back->notice_time,0,5);
            $back->mode_text      = $back->mode_info ? $back->mode_info->name : "無";
            $back->send_type_text = $send_type[$back->send_type];
            $back->shop_coupons   = $back->shop_coupons ? $back->shop_coupon_info->title : "無";
            unset($back->shop_coupon_info);
            unset($back->mode_info);

            $group->details = [
                'before' => $before,
                'after'  => $after,
                'back'   => $back,
            ];
            $select_service = ShopManagementService::where('shop_management_group_id',$group->id)->pluck('shop_service_id')->toArray();
            $group->service_items = ShopService::whereIn('id', $select_service)->pluck('name');
            unset($group->management_details);
        }

        // 已被其他服務通知選擇的服務
        $selected_shop_services = ShopManagementService::whereIn('shop_management_group_id',$management_groups->pluck('id')->toArray())->pluck('shop_service_id');
        $shop_services          = ShopService::where('shop_id',$shop_id)->where('type', 'service')->whereNotIn('id',$selected_shop_services)->pluck('name');

        $data = [
            'status'              => true,
            'permission'          => true,
            'create_permission'   => in_array('shop_management_group_create_btn', $user_shop_permission['permission']) ? true : false,
            'edit_permission'     => in_array('shop_management_group_edit_btn', $user_shop_permission['permission']) ? true : false,
            'send_log_permission' => in_array('shop_management_group_send_log_btn', $user_shop_permission['permission']) ? true : false,
            'mode_permission'     => in_array('shop_management_group_mode_btn',$user_shop_permission['permission']) ? true : false,
            'shop_services'       => $shop_services,
            'data'                => $management_groups,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家服務通知資料
    public function shop_management_group_info($shop_id, $group_id = "")
    {
        if ($group_id) {
            $shop_management_group = ShopManagementGroup::find($group_id);
            if (!$shop_management_group) {
                return response()->json(['status' => false, 'errors' => ['message' => ['找不到服務通知資料']]]);
            }
            $type = 'edit';
        } else {
            $shop_management_group = new ShopManagementGroup;
            $type                  = 'create';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_management_group_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 模組選項
        $mode_select = [];
        $notice_modes = ShopNoticeMode::where('shop_id',$shop_info->id)->get();
        foreach( $notice_modes as $mode ){
            $mode_select[] = [
                'id'       => $mode->id,
                'name'     => $mode->name,
            ]; 
        }
       
        // 優惠券選項
        $coupon_select = [];
        $shop_coupons = ShopCoupon::where('shop_id',$shop_info->id)
                            // ->join('company_coupons','company_coupons.id','=','shop_coupons.company_coupon_id')
                            // ->where('end_date','>=',date('Y-m-d'))
                            ->where('status','published')
                            ->get();
        foreach( $shop_coupons as $coupon ){
            $coupon_select[] = [
                'id'       => $coupon->id,
                'name'     => $coupon->title,
                'disable'  => $coupon->end_date < date('Y-m-d') ? true : false,
            ];
        }

        // 已被其他服務通知選擇的服務
        $shop_management_groups = ShopManagementGroup::where('shop_id',$shop_id)->where('id','!=',$shop_management_group->id)->pluck('id')->toArray();
        $selected_shop_services = ShopManagementService::whereIn('shop_management_group_id',$shop_management_groups)->pluck('shop_service_id')->toArray();

        // 所有設定名稱陣列
        $management_group_name = ShopManagementGroup::where('shop_id',$shop_id)->pluck('name','id')->toArray();
    
        // 服務選項
        $shop_service_categories = ShopServiceCategory::where('shop_id',$shop_id)->select('id','name')->get();
        foreach( $shop_service_categories as $service_category ){
            $service_category->shop_services = ShopService::where('shop_service_category_id',$service_category->id)->select('id','name')->get();
            $shop_notice_services = $shop_management_group->shop_services->pluck('shop_service_id')->toArray();

            foreach( $service_category->shop_services as $service ){
                // 判斷是否被此筆資料選取
                if( in_array( $service->id , $shop_notice_services) ){
                    $service->selected = true;
                }else{
                    $service->selected = false;
                }
                // 判斷是否可以再被選取
                if( in_array( $service->id , $selected_shop_services ) ){
                    $service->alert_text = $service->name .' 已在『' . $management_group_name[$service->management_group->shop_management_group_id] . '』組合中，是否要移動到現在組合？';
                    unset($service->management_group); 
                }else{
                    $service->alert_text = '';
                }
            }
        }

        // 建立｜拿取組合內容
        if( $type == 'create' ){
            $before = new ShopManagement;
            $before->notice_type = 1;

            $after = new ShopManagement;
            $after->notice_type = 2;

            $back = new ShopManagement;
            $back->notice_type = 2;
        }else{
            $before = $shop_management_group->management_details->where('type','before')->first();
            $after  = $shop_management_group->management_details->where('type','after')->first();
            $back   = $shop_management_group->management_details->where('type','back')->first();
        }

        $before_default_message = '「"會員名稱"」您好，提醒您「"預約日期"」有預約「"商家名稱"」的「"服務名稱"」期待您的到來！若您有空餘可以填表告訴我們目前你目前的困惱與想改善的狀況喔！「"問卷模組"」';
        $after_default_message  = '感謝您「"預約日期"」蒞臨「"商家名稱"」消費，為能提供更好的服務給您，希望能撥空回饋建議給我們喔！「"問卷模組"」';
        $back_default_message   = '「"會員名稱"」您好，提醒您「"預約日期"」有預約「"商家名稱"」的「"服務名稱"」期待您的到來！若您有空餘可以填表告訴我們目前你目前的困惱與想改善的狀況喔！「"問卷模組"」'; 

        $info = [
            // 共同設定資料
            'id'                         => $shop_management_group->id,
            'name'                       => $shop_management_group->name,
            'name_permission'            => in_array('shop_management_group_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'shop_services'              => $shop_service_categories,
            'shop_services_permission'   => in_array('shop_management_group_'.$type.'_shop_services',$user_shop_permission['permission']) ? true : false, 

            // 細節資料
            'before_service' => [
                'id'                      => $before->id,
                'message'                 => $before->id ? $before->message : $before_default_message,
                'message_permission'      => in_array('shop_management_before_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
                'link'                    => $before->link,
                'link_permission'         => in_array('shop_management_before_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
                'shop_coupons'            => $before->shop_coupons,
                'shop_coupons_permission' => in_array('shop_management_before_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
                'send_type'               => $before->send_type?:2,
                'send_type_permission'    => in_array('shop_management_before_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,
                'notice_type'             => 1,
                'notice_type_permission'  => in_array('shop_management_before_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_day'              => $before->notice_day != '' || $before->notice_day === 0 ? (string)$before->notice_day : '1',
                'notice_day_permission'   => in_array('shop_management_before_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_time'             => $before->notice_time ? date('c',strtotime(date('Y-m-d ') . $before->notice_time)) : date('c',strtotime(date('Y-m-d H:i:s'))),
                'notice_time_permission'  => in_array('shop_management_before_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_cycle'            => $before->notice_cycle?:1,
                'notice_cycle_permission' => in_array('shop_management_before_'.$type.'_notice_cycle',$user_shop_permission['permission']) ? true : false, 
                'mode'                    => $before->shop_notice_mode_id,
                'mode_permission'         => in_array('shop_management_before_'.$type.'_mode',$user_shop_permission['permission']) ? true : false,
                'test_send_permission'    => in_array('shop_management_before_'.$type.'_test_message',$user_shop_permission['permission']) ? true : false,
                'use'                     => $before->use?:'N',
                'use_permission'          => in_array('shop_management_before_'.$type.'_use',$user_shop_permission['permission']) ? true : false,
                'show'                    => $before->use == 'Y' ? true : false,
            ],
            
            'after_service' => [
                'id'                      => $after->id,
                'message'                 => $after->id ? $after->message : $after_default_message,
                'message_permission'      => in_array('shop_management_after_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
                'link'                    => $after->link,
                'link_permission'         => in_array('shop_management_after_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
                'shop_coupons'            => $after->shop_coupons,
                'shop_coupons_permission' => in_array('shop_management_after_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
                'evaluate'                => $after->evaluate?:'N',
                'evaluate_permission'     => in_array('shop_management_after_'.$type.'_evaluate',$user_shop_permission['permission']) ? true : false,
                'send_type'               => $after->send_type?:2,
                'send_type_permission'    => in_array('shop_management_after_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,
                'notice_type'             => 2,
                'notice_type_permission'  => in_array('shop_management_after_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_hour'             => $after->notice_hour?:1,
                'notice_hour_permission'  => in_array('shop_management_after_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_time'             => $after->notice_time ? date('c',strtotime(date('Y-m-d ') . $after->notice_time)) : date('c',strtotime(date('Y-m-d H:i:s'))),
                'notice_time_permission'  => in_array('shop_management_after_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_cycle'            => $after->notice_cycle?:1,
                'notice_cycle_permission' => in_array('shop_management_after_'.$type.'_notice_cycle',$user_shop_permission['permission']) ? true : false, 
                'mode'                    => $after->shop_notice_mode_id,
                'mode_permission'         => in_array('shop_management_after_'.$type.'_mode',$user_shop_permission['permission']) ? true : false,
                'test_send_permission'    => in_array('shop_management_after_'.$type.'_test_message',$user_shop_permission['permission']) ? true : false,
                'use'                     => $after->use?:'N',
                'use_permission'          => in_array('shop_management_after_'.$type.'_use',$user_shop_permission['permission']) ? true : false,
                'show'                    => $after->use  == 'Y' ? true : false,
            ],

            'back_service' => [
                'id'                      => $back->id,
                'message'                 => $back->id ? $back->message : $back_default_message,
                'message_permission'      => in_array('shop_management_back_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
                'link'                    => $back->link,
                'link_permission'         => in_array('shop_management_back_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
                'shop_coupons'            => $back->shop_coupons,
                'shop_coupons_permission' => in_array('shop_management_back_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
                'send_type'               => $back->send_type?:2,
                'send_type_permission'    => in_array('shop_management_back_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,
                'notice_type'             => 2,
                'notice_type_permission'  => in_array('shop_management_back_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_day'              => $back->notice_day != '' || $back->notice_day === 0 ? (string)$back->notice_day : '1',
                'notice_day_permission'   => in_array('shop_management_back_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_time'             => $back->notice_time ? date('c',strtotime(date('Y-m-d ') . $back->notice_time)) : date('c',strtotime(date('Y-m-d H:i:s'))),
                'notice_time_permission'  => in_array('shop_management_back_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
                'notice_cycle'            => $back->notice_cycle?:1,
                'notice_cycle_permission' => in_array('shop_management_back_'.$type.'_notice_cycle',$user_shop_permission['permission']) ? true : false, 
                'mode'                    => $back->shop_notice_mode_id,
                'mode_permission'         => in_array('shop_management_back_'.$type.'_mode',$user_shop_permission['permission']) ? true : false,
                'test_send_permission'    => in_array('shop_management_back_'.$type.'_test_message',$user_shop_permission['permission']) ? true : false,
                'use'                     => $back->use?:'N',
                'use_permission'          => in_array('shop_management_back_'.$type.'_use',$user_shop_permission['permission']) ? true : false,
                'show'                    => $back->use == 'Y' ? true : false,
            ],
        ];

        $data = [
            'status'            => true,
            'permission'        => true,
            'delete_permission' => in_array('shop_management_group_'.$type.'_delete',$user_shop_permission['permission']) ? true : false,
            'mode_select'       => $mode_select,
            'coupon_select'     => $coupon_select,
            'data'              => $info,
        ];

        return response()->json($data);
    }

    // 儲存商家服務通知資料
    public function shop_management_group_save($shop_id,$group_id="")
    {
        if( $group_id ){
            // 編輯
            $shop_management_group = ShopManagementGroup::find($group_id);
            if( !$shop_management_group ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到服務通知資料']]]);
            }
        }else{
            // 新增
            $shop_management_group = new ShopManagementGroup;
            $shop_management_group->shop_id = $shop_id;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_management_group->name = request('name');
        $shop_management_group->save();

        // 商家服務
        $shop_services = $insert = [];
        foreach( request('shop_services') as $category ){
            foreach( $category['shop_services'] as $service ){
                if( $service['selected'] == true ){
                    $shop_services[] = $service['id'];
                    $insert[] = [
                        'shop_management_group_id' => $shop_management_group->id,
                        'shop_service_id'          => $service['id'],
                        'created_at'               => date('Y-m-d H:i:s'),
                        'updated_at'               => date('Y-m-d H:i:s')
                    ]; 
                } 
            }
        }

        ShopManagementService::where('shop_management_group_id',$group_id)->delete();
        ShopManagementService::whereIn('shop_service_id',$shop_services)->delete();
        ShopManagementService::insert($insert);

        // 服務前設定
        $shop_management = request('before_service')['id'] ? ShopManagement::find(request('before_service')['id']) : new ShopManagement;
        $shop_management->shop_id                  = $shop_info->id;
        $shop_management->shop_management_group_id = $shop_management_group->id;
        $shop_management->type                     = 'before';
        $shop_management->shop_notice_mode_id      = request('before_service')['mode'];
        $shop_management->link                     = request('before_service')['link'];
        $shop_management->message                  = request('before_service')['message'];
        $shop_management->send_type                = request('before_service')['send_type'];
        $shop_management->notice_cycle             = request('before_service')['notice_cycle'];
        $shop_management->notice_day               = request('before_service')['notice_day'];
        $shop_management->notice_time              = date('H:i',strtotime(request('before_service')['notice_time']));
        $shop_management->notice_type              = request('before_service')['notice_type'];
        $shop_management->use                      = request('before_service')['use'] ? request('before_service')['use'] : 'N';
        $shop_management->shop_coupons             = request('before_service')['shop_coupons'];
        $shop_management->save();

        // 服務後設定
        $shop_management = request('after_service')['id'] ? ShopManagement::find(request('after_service')['id']) : new ShopManagement;
        $shop_management->shop_id                  = $shop_info->id;
        $shop_management->shop_management_group_id = $shop_management_group->id;
        $shop_management->type                     = 'after';
        $shop_management->shop_notice_mode_id      = request('after_service')['mode'];
        $shop_management->link                     = request('after_service')['link'];
        $shop_management->message                  = request('after_service')['message'];
        $shop_management->evaluate                 = request('after_service')['evaluate'];
        $shop_management->send_type                = request('after_service')['send_type'];
        $shop_management->notice_cycle             = request('after_service')['notice_cycle'];
        $shop_management->notice_hour              = request('after_service')['notice_hour'];
        $shop_management->notice_time              = request('after_service')['notice_hour'] == 1 ? NULL : date('H:i',strtotime(request('after_service')['notice_time']));
        $shop_management->notice_type              = request('after_service')['notice_type'];
        $shop_management->use                      = request('after_service')['use'] ? request('after_service')['use'] : 'N';
        $shop_management->shop_coupons             = request('after_service')['shop_coupons'];
        $shop_management->save();

        // 服務回訪設定
        $shop_management = request('back_service')['id'] ? ShopManagement::find(request('back_service')['id']) : new ShopManagement;
        $shop_management->shop_id                  = $shop_info->id;
        $shop_management->shop_management_group_id = $shop_management_group->id;
        $shop_management->type                     = 'back';
        $shop_management->shop_notice_mode_id      = request('back_service')['mode'];
        $shop_management->link                     = request('back_service')['link'];
        $shop_management->message                  = request('back_service')['message'];
        $shop_management->send_type                = request('back_service')['send_type'];
        $shop_management->notice_cycle             = request('back_service')['notice_cycle'];
        $shop_management->notice_day               = request('back_service')['notice_day'];
        $shop_management->notice_time              = date('H:i',strtotime(request('back_service')['notice_time']));
        $shop_management->notice_type              = request('back_service')['notice_type'];
        $shop_management->use                      = request('back_service')['use'] ? request('back_service')['use'] : 'N';
        $shop_management->shop_coupons             = request('back_service')['shop_coupons'];
        $shop_management->save();

        return response()->json(['status'=>true,'data'=>request()->all()]);
    }

    // 刪除商家服務通知資料 
    public function shop_management_group_delete($shop_id,$group_id)
    {
        $shop_management_group = ShopManagementGroup::find($group_id);
        if( !$shop_management_group ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到服務通知資料']]]);
        }

        $shop_management_group->delete();

        ShopManagement::where('shop_management_group_id',$group_id)->delete();
        
        // 對應選取服務也刪除
        ShopManagementService::where('shop_management_group_id',$group_id)->delete();

        return response()->json(['status'=>true]);
    }

    // 服務通知發送清單
    public function shop_management_group_send_log($shop_id,$group_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_management_group_send_log',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_management_group = ShopManagementGroup::find($group_id);
        if( !$shop_management_group ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到服務通知資料']]]);
        }

        $notice_info = [
            'id'        => $group_id,
            'name'      => $shop_management_group->name,
        ];

        $logs = ShopManagementCustomerList::orderBy('updated_at','DESC')->whereIn('shop_management_id',$shop_management_group->management_details->pluck('id')->toArray())->get();
        $send_logs = [];
        foreach( $logs as $log ){
            if( !$log->customer_info ){
                $shop_customer = ShopCustomer::where('id',$log->shop_customer_id)->withTrashed()->first();
                if( !$shop_customer ) continue;
                if( !$shop_customer->customer_info && $shop_customer->id != 59 ) continue;
                $log->customer_info = Customer::where('id',$shop_customer->customer_id)->withTrashed()->first();
            }

            $sms_status = $line_status = '失敗';

            if( $log->management_info->send_type == 2 ){
                if( $log->sms == 'F' ) $sms_status = '失敗';
                if( $log->sms == 'Y' ) $sms_status = '成功';
            }elseif( $log->management_info->send_type == 3){
                if( $log->line == 'F' ) $line_status = '失敗';
                if( $log->line == 'Y' ) $line_status = '成功';
            }else{
                if( $log->sms == 'F' ) $sms_status = '失敗';
                if( $log->sms == 'Y' ) $sms_status = '成功';
                if( $log->line == 'F' ) $line_status = '失敗';
                if( $log->line == 'Y' ) $line_status = '成功';
            }

            $type = $shop_management_group->name.'【前】';
            if( $log->management_info->type == 'after' ){
                $type = $shop_management_group->name.'【後】';
            }elseif( $log->management_info->type == 'back' ){
                $type = $shop_management_group->name.'【回訪】';
            }

            $send_logs[] = [
                'id'               => $log->id,
                'type'             => $type,
                'shop_customer_id' => $log->shop_customer_id,
                'date'             => substr($log->updated_at,0,16),
                'name'             => $log->customer_info->realname,
                'phone'            => $log->customer_info->phone,
                'sms'              => $sms_status,
                'line'             => $line_status,
                'refuse_status'    => $log->refuse_status ? true : false,
            ];
        }

        $data = [
            'status'      => true,
            'permission'  => true,
            'resend_permission' => in_array('shop_management_group_notice_resend',$user_shop_permission['permission']) ? true : false,
            'refuse_permission' => in_array('shop_management_group_notice_refuse',$user_shop_permission['permission']) ? true : false,
            'notice_info' => $notice_info,
            'data'        => $send_logs,
        ];

        return response()->json($data);
    }


}
