<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerReservation;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerTag;
use App\Models\ShopCoupon;
use App\Models\ShopManagement;
use App\Models\ShopManagementMode;
use App\Models\ShopManagementCustomerList;
use App\Models\ShopServiceCategory;
use App\Models\ShopService;
use App\Models\MessageLog;
use App\Models\ShopManagementRefuse;
use App\Models\ShopStaff;

class ShopAutoManagementController extends Controller
{
    // 自動推廣列表
    public function shop_auto_management_lists($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_auto_management_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

        $managements = ShopManagement::where('shop_id',$shop_id)->where('type','auto')->orderBy('id','DESC')->get();
        $data = [];
        foreach( $managements as $management ){
        	$send_type = '';
        	switch ($management->send_type){
        		case 1: 
        		    $send_type = '都發送';
        		    break;
        		case 2: 
        		    $send_type = '僅簡訊';
        		    break;
        		case 3: 
        		    $send_type = '僅LINE';
        		    break;
        		case 4: 
        		    $send_type = 'LINE優先';
        		    break;
        	}

        	$status = '推廣中';
        	if( $management->use == 'N' ){
        		// 檢查是否有發送清單
        		$status = '關閉';
        	}else{
                if( $management->during_type == 2 ){
                    if( strtotime($management->end_date) < time() ){
                        $status = '已結束';
                    }
                }
            }

        	$data[] = [
        		'id'        => $management->id,
        		'name'      => $management->name,
                'during'    => $management->during_type == 1 ? '無期限' : $management->start_date.' 至 '.$management->end_date,
                'link'      => $management->link ? 'Y' : 'N',
                'coupon'    => $management->shop_coupon_info ? 'Y' : 'N',
        		'send_type' => $send_type,
        		'status'    => $status,
                'use'       => $management->use == 'Y' ? '啟用' : '關閉',
        	];
        }

    	$data = [
            'status'                              => true,
            'permission'                          => true,
            'auto_management_create_permission'   => in_array('auto_management_create_btn',$user_shop_permission['permission']) ? true : false, 
            'auto_management_edit_permission'     => in_array('auto_management_edit_btn',$user_shop_permission['permission']) ? true : false, 
            // 'auto_management_use_permission'   => in_array('auto_management_use_btn',$user_shop_permission['permission']) ? true : false, 
            'auto_management_delete_permission'   => in_array('auto_management_delete_btn',$user_shop_permission['permission']) ? true : false, 
            'auto_management_mode_permission'     => in_array('auto_management_mode_btn',$user_shop_permission['permission']) ? true : false, 
            'auto_management_send_log_permission' => in_array('auto_management_send_log_btn',$user_shop_permission['permission']) ? true : false, 
            'data'                                => $data,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家自動推廣資料
    public function shop_auto_management_info($shop_id,$management_id="")
    {
    	if( $management_id ){
            $shop_management = ShopManagement::find($management_id);
            if( !$shop_management ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到自動推廣資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_management = new ShopManagement;
            $type            = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_auto_management_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

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
                'selected' => $coupon->id == $shop_management->shop_coupons ? true : false,
                'disable'  => $coupon->end_date < date('Y-m-d') ? true : false,
            ];
        }

        // 模組選項
        $mode_select = [];
        $management_mode = ShopManagementMode::where('shop_id',$shop_info->id)->where('type','auto')->get();
        foreach( $management_mode as $mode ){
            $mode_select[] = [
                'id'       => $mode->id,
                'name'     => $mode->name,
                'selected' => $mode->id == $shop_management->shop_management_mode_id ? true : false,
            ]; 
        }

        $data = [
        	// 篩選條件
            'id'                         => $shop_management->id,
            'name'                       => $shop_management->name,
            'name_permission'            => in_array('shop_auto_management_'.$type.'_name',$user_shop_permission['permission']) ? true : false,

            // 發送訊息
            'message'                    => $shop_management->message?:'',
            'message_permission'         => in_array('shop_auto_management_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
            'link'                       => $shop_management->link,
            'link_permission'            => in_array('shop_auto_management_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
            'shop_coupons'               => $shop_management->shop_coupons,
            'shop_coupons_permission'    => in_array('shop_auto_management_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
            'send_type'                  => $shop_management->send_type?:2,
            'send_type_permission'       => in_array('shop_auto_management_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,
            'during_type'                => $shop_management->during_type,
            'during_type_permission'     => in_array('shop_auto_management_'.$type.'_during',$user_shop_permission['permission']) ? true : false,
            'start_date'                 => $shop_management->start_date,
            'start_date_permission'      => in_array('shop_auto_management_'.$type.'_during',$user_shop_permission['permission']) ? true : false,
            'end_date'                   => $shop_management->end_date,
            'end_date_permission'        => in_array('shop_auto_management_'.$type.'_during',$user_shop_permission['permission']) ? true : false,
           	'send_cycle'                 => $shop_management->send_cycle,
            'send_cycle_permission'      => in_array('shop_auto_management_'.$type.'_send_cycle',$user_shop_permission['permission']) ? true : false,
            'send_cycle_week'            => $shop_management->send_cycle_week,
            'send_cycle_week_permission' => in_array('shop_auto_management_'.$type.'_send_cycle_type',$user_shop_permission['permission']) ? true : false,
            'send_cycle_day'             => $shop_management->send_cycle_day,
            'send_cycle_day_permission'  => in_array('shop_auto_management_'.$type.'_send_cycle_type',$user_shop_permission['permission']) ? true : false,
            'send_cycle_time'            => !$shop_management->send_cycle_time ? date('c') : date('c',strtotime(date('Y-m-d').' '.$shop_management->send_cycle_time)),
            'send_cycle_time_permission' => in_array('shop_auto_management_'.$type.'_send_cycle_type',$user_shop_permission['permission']) ? true : false,
            'send_cycle_type'            => $shop_management->send_cycle_type,
            'send_cycle_type_permission' => in_array('shop_auto_management_'.$type.'_send_cycle_type',$user_shop_permission['permission']) ? true : false,            
            'mode'                       => $shop_management->shop_management_mode_id,
            'mode_permission'            => in_array('shop_auto_management_'.$type.'_mode',$user_shop_permission['permission']) ? true : false,
            'use'                        => $shop_management->use?:'N',
            'use_permission'             => in_array('shop_auto_management_'.$type.'_use',$user_shop_permission['permission']) ? true : false,
        ];

        $res = [
        	'status'        => true,
        	'permission'    => true,
            'mode_select'   => $mode_select,
            'coupon_select' => $coupon_select,
        	'data'          => $data,
        ];

        return response()->json($res);
    }

    // 儲存商家自動推廣資料
    public function shop_auto_management_save($shop_id,$management_id="")
    {
        if( $management_id ){
            // 編輯
            $shop_management = ShopManagement::find($management_id);
            if( !$shop_management ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到自動推廣資料']]]);
            }
        }else{
            // 新增
            $shop_management = new ShopManagement;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_management->shop_id                 = $shop_info->id;
        $shop_management->type                    = 'auto';
        $shop_management->shop_management_mode_id = request('mode');
        $shop_management->name                    = request('name');
        $shop_management->link                    = request('link');
        $shop_management->message                 = request('message');
        $shop_management->during_type             = request('during_type');
        $shop_management->start_date              = request('during_type') == 2 ? date('Y-m-d',strtotime(request('start_date') )) : NULL;
        $shop_management->end_date                = request('during_type') == 2 ? date('Y-m-d',strtotime(request('end_date') ) ) : NULL;
        $shop_management->send_type               = request('send_type');
        $shop_management->send_cycle              = request('send_cycle');
        $shop_management->send_cycle_week         = request('send_cycle_week');
        $shop_management->send_cycle_day          = request('send_cycle_day');
        $shop_management->send_cycle_time         = date('H:i',strtotime(request('send_cycle_time')));
        $shop_management->send_cycle_type         = request('send_cycle_type');
        $shop_management->use                     = request('during_type') == 1 ? (request('use') ? request('use') : 'N') : 'Y';

        // 優惠券
        $shop_management->shop_coupons = request('shop_coupons');
        $shop_management->save();

        $shop_management = ShopManagement::where('id',$shop_management->id)->with('customer_lists')->first();

        return response()->json(['status'=>true,'data'=>$shop_management]);
    }

    // 自動推廣發送清單
    public function shop_auto_management_send_log($shop_id,$management_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_auto_management_send_log',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_management = ShopManagement::find($management_id);
        if( !$shop_management ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到自動推廣資料']]]);
        }

        $send_type = [
            '1' => '手機簡訊與LINE都發送',
            '2' => '僅手機簡訊',
            '3' => '僅LINE',
            '4' => '以LINE優先，無LINE者發送手機簡訊',
        ];

        $management_info = [
        	'id'        => $management_id,
            'name'      => $shop_management->name,
            'send_type' => $send_type[$shop_management->send_type],
        ];

        $logs = ShopManagementCustomerList::orderBy('updated_at','DESC')->where('shop_management_id',$management_id)->get();
        $send_logs = [];
        foreach( $logs as $log ){
            if( !$log->customer_info ){
                $shop_customer = ShopCustomer::where('id',$log->shop_customer_id)->withTrashed()->first();
                if( !$shop_customer ) continue;
                if( !$shop_customer->customer_info && $shop_customer->id != 59 ) continue;
                $log->customer_info = Customer::where('id',$shop_customer->customer_id)->withTrashed()->first();
            }

            $sms_status = $line_status = '失敗';
            if( $shop_management->send_type == 2 ){
                if( $log->sms == 'F' ) $sms_status = '失敗';
                if( $log->sms == 'Y' ) $sms_status = '成功';
            }elseif( $shop_management->send_type == 3){
                if( $log->line == 'F' ) $line_status = '失敗';
                if( $log->line == 'Y' ) $line_status = '成功';
            }else{
                if( $log->sms == 'F' ) $sms_status = '失敗';
                if( $log->sms == 'Y' ) $sms_status = '成功';
                if( $log->line == 'F' ) $line_status = '失敗';
                if( $log->line == 'Y' ) $line_status = '成功';
            }

            $send_logs[] = [
                'id'               => $log->id,
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
            'status'            => true,
            'permission'        => true,
            'resend_permission' => !in_array('shop_auto_management_resend',$user_shop_permission['permission']) ? false : true,
            'refuse_permission' => !in_array('shop_auto_management_refuse',$user_shop_permission['permission']) ? false : true,
            'management_info'   => $management_info,
            'data'              => $send_logs,
        ];

        return response()->json($data);
    }

    // 刪除商家自動推廣資料 
    public function shop_auto_management_delete($shop_id,$management_id)
    {
        $shop_management = ShopManagement::find($management_id);
        if( !$shop_management ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到自動推廣資料']]]);
        }

        $shop_management->delete();

        return response()->json(['status'=>true]);
    }

    // 自動推廣模組列表
    public function shop_auto_management_mode_lists($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('auto_management_mode_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $management_modes = ShopManagementMode::where('shop_id',$shop_id)->where('type','auto')->orderBy('id','DESC')->get();
        $data = [];
        foreach( $management_modes as $management_mode ){
            $data[] = [
                'id'   => $management_mode->id,
                'name' => $management_mode->name,
            ];
        }

        $data = [
            'status'                                 => true,
            'permission'                             => true,
            'auto_management_mode_create_permission' => in_array('auto_management_mode_create_btn',$user_shop_permission['permission']) ? true : false, 
            'auto_management_mode_edit_permission'   => in_array('auto_management_mode_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'auto_management_mode_delete_permission' => in_array('auto_management_mode_delete_btn',$user_shop_permission['permission']) ? true : false, 
            'data'                                   => $data,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家自動推廣模組資料
    public function shop_auto_management_mode_info($shop_id,$management_mode_id="")
    {
        if( $management_mode_id ){
            $shop_management_mode = ShopManagementMode::find($management_mode_id);
            if( !$shop_management_mode ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到自動推廣模組資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_management_mode = new ShopManagementMode;
            $type                 = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('auto_management_mode_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 性別
        $sex = [
            [
                'name'     => '女',
                'value'    => 'F',
                'selected' => in_array('F',explode(',', $shop_management_mode->sex)) ? true : false,
            ],
            [
                'name'     => '中性',
                'value'    => 'C',
                'selected' => in_array('C',explode(',', $shop_management_mode->sex)) ? true : false,
            ],
            [
                'name'     => '男',
                'value'    => 'M',
                'selected' => in_array('M',explode(',', $shop_management_mode->sex)) ? true : false,
            ],
        ];

        // 生日月份
        $birthday_month = [];
        for( $m = 1 ; $m <= 12 ; $m++ ){
            $birthday_month[] = [
                'name'     => $m.'月',
                'value'    => $m,
                'selected' => in_array($m,explode(',', $shop_management_mode->birthday_month)) ? true : false,
            ];
        }

        // 星座
        $constellations = [
            '白羊座', '金牛座', '雙子座','巨蟹座','獅子座', '處女座', '天秤座', '天蠍座', '射手座','摩羯座','水瓶座', '雙鱼座', 
        ];
        $constellation = [];
        foreach( $constellations as $name ){
            $constellation[] = [
                'name'     => $name,
                'value'    => $name,
                'selected' => in_array($name,explode(',', $shop_management_mode->constellation)) ? true : false,
            ];
        }

        // 會員等級(待補)

        // 會員標籤
        $shop_customer_tags = ShopCustomerTag::where('shop_id',$shop_id)->get();
        $once_customer_tags = [];
        foreach( $shop_customer_tags as $tag ){
            $once_customer_tags[] = [
                'id'       => $tag->id,
                'name'     => $tag->name,
                'selected' => in_array($tag->id,explode(',', $shop_management_mode->customer_tags)) ? true : false,
            ];
        }

        // 服務人員
        $shop_staffs = ShopStaff::where('shop_id',$shop_id)
                                        // ->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')
                                        // ->where('fire_time',NULL)
                                        ->get();
        $staff_select = [];
        $selected_staff = [];
        foreach( $shop_staffs as $staff ){
            if( $staff->fire_time != NULL ) continue;
            $staff_select[] = [
                'id'       => $staff->id,
                'name'     => $staff->company_staff_info->name,
            ];

            if( in_array($staff->id,explode(',', $shop_management_mode->shop_staffs)) ){
                $selected_staff[] = [
                    'id'   => $staff->id,
                    'name' => $staff->company_staff_info->name,
                ];
            }
        }

        // 服務項目
        $shop_services = ShopService::where('shop_id',$shop_id) ->get();
        $service_select = [];
        $selected_service = [];
        foreach( $shop_services as $service ){
            $service_select[] = [
                'id'       => $service->id,
                'name'     => $service->name,
            ];

            if( in_array($service->id,explode(',', $shop_management_mode->shop_services)) ){
                $selected_service[] = [
                    'id'   => $service->id,
                    'name' => $service->name,
                ];
            }
        }

        $data = [
            // 篩選條件
            'id'                                   => $shop_management_mode->id,
            'name'                                 => $shop_management_mode->name,
            'name_permission'                      => in_array('auto_management_mode_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'keyword'                              => $shop_management_mode->keyword,
            'keyword_permission'                   => in_array('auto_management_mode_'.$type.'_keyword',$user_shop_permission['permission']) ? true : false,
            
            'start_date'                           => $shop_management_mode->start_date,
            'start_date_permission'                => in_array('auto_management_mode_'.$type.'_date',$user_shop_permission['permission']) ? true : false,
            'end_date'                             => $shop_management_mode->end_date,
            'end_date_permission'                  => in_array('auto_management_mode_'.$type.'_date',$user_shop_permission['permission']) ? true : false,

            'shop_staffs'                          => $selected_staff,
            'shop_staffs_permission'               => in_array('auto_management_mode_'.$type.'_shop_staffs',$user_shop_permission['permission']) ? true : false,
            'shop_services'                        => $selected_service,
            'shop_services_permission'             => in_array('auto_management_mode_'.$type.'_shop_services',$user_shop_permission['permission']) ? true : false,

            'sex'                                  => $sex,
            'sex_permission'                       => in_array('auto_management_mode_'.$type.'_sex',$user_shop_permission['permission']) ? true : false,
            'min_age'                              => $shop_management_mode->min_age,
            'min_age_permission'                   => in_array('auto_management_mode_'.$type.'_age',$user_shop_permission['permission']) ? true : false,
            'max_age'                              => $shop_management_mode->max_age,
            'max_age_permission'                   => in_array('auto_management_mode_'.$type.'_age',$user_shop_permission['permission']) ? true : false,
            'birthday_or_constellation'            => $shop_management_mode->birthday_or_constellation,
            'birthday_or_constellation_permission' => in_array('auto_management_mode_'.$type.'_birthday_or_constellation',$user_shop_permission['permission']) ? true : false,
            'birthday_month'                       => $birthday_month,
            'birthday_month_permission'            => in_array('auto_management_mode_'.$type.'_birthday_or_constellation',$user_shop_permission['permission']) ? true : false,
            'constellation'                        => $constellation,
            'constellation_permission'             => in_array('auto_management_mode_'.$type.'_birthday_or_constellation',$user_shop_permission['permission']) ? true : false,
            'customer_level'                       => [],
            'customer_level_permission'            => in_array('auto_management_mode_'.$type.'_customer_level',$user_shop_permission['permission']) ? true : false,
            'customer_tags'                        => [],
            'customer_tags_permission'             => in_array('auto_management_mode_'.$type.'_customer_tags',$user_shop_permission['permission']) ? true : false,
        ];

        $res = [
            'status'         => true,
            'permission'     => true,
            'staff_select'   => $staff_select,
            'service_select' => $service_select,
            'data'           => $data
        ];

        return response()->json($res);
    }

    // 儲存商家自動推廣模組資料
    public function shop_auto_management_mode_save($shop_id,$management_mode_id="")
    {
        if( $management_mode_id ){
            // 編輯
            $shop_management_mode = ShopManagementMode::find($management_mode_id);
            if( !$shop_management_mode ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到自動推廣模組資料']]]);
            }
        }else{
            // 新增
            $shop_management_mode = new ShopManagementMode;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 處理性別資料
        $selected_sex = [];
        foreach( request('sex') as $sex ){
            if( $sex['selected'] ) $selected_sex[] = $sex['value'];
        }

        // 處理生日月份
        $selected_birthday_month = [];
        foreach( request('birthday_month') as $birthday_month ){
            if( $birthday_month['selected'] ) $selected_birthday_month[] = $birthday_month['value'];
        }

        // 處理星座
        $selected_constellation = [];
        foreach( request('constellation') as $constellation ){
            if( $constellation['selected'] ) $selected_constellation[] = $constellation['value'];
        }

        // 處理會員標籤
        $selected_customer_tags = [];
        foreach( request('customer_tags') as $customer_tags ){
            if( $customer_tags['selected'] ) $selected_customer_tags[] = $customer_tags['id'];
        }

        // 處理會員等級(待補)

        // 處理服務人員資料
        $selected_staff = [];
        foreach( request('shop_staffs') as $staff ){
            $selected_staff[] = $staff['id'];
        }

        // 處理服務項目資料
        $selected_service = [];
        foreach( request('shop_services') as $service ){
            $selected_service[] = $service['id'];
        }

        $shop_management_mode->shop_id                   = $shop_info->id;
        $shop_management_mode->type                      = 'auto';
        $shop_management_mode->name                      = request('name');
        $shop_management_mode->keyword                   = request('keyword');
        $shop_management_mode->start_date                = request('start_date');
        $shop_management_mode->end_date                  = request('end_date');
        $shop_management_mode->shop_services             = implode(',',$selected_service);
        $shop_management_mode->shop_staffs               = implode(',',$selected_staff);
        $shop_management_mode->sex                       = implode(',',$selected_sex);
        $shop_management_mode->min_age                   = request('min_age');
        $shop_management_mode->max_age                   = request('max_age');
        $shop_management_mode->birthday_or_constellation = request('birthday_or_constellation');
        $shop_management_mode->birthday_month            = request('birthday_or_constellation') == 1 ? implode(',',$selected_birthday_month) : NULL;
        $shop_management_mode->constellation             = request('birthday_or_constellation') == 2 ? implode(',',$selected_constellation) : NULL;
        $shop_management_mode->customer_tags             = implode(',',$selected_customer_tags);
        $shop_management_mode->customer_level            = NULL;
        $shop_management_mode->save();

        return response()->json(['status'=>true,'data'=>$shop_management_mode]);
    }

    // 刪除商家自動推廣模組資料
    public function shop_auto_management_mode_delete($shop_id,$management_mode_id)
    {
        $shop_management_mode = ShopManagementMode::find($management_mode_id);
        if( !$shop_management_mode ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到自動推廣模組資料']]]);
        }

        if( ShopManagement::where('shop_id',$shop_id)->where('shop_management_mode_id',$management_mode_id)->get()->count() ){
            return response()->json(['status'=>false,'errors'=>['message'=>['此模組已被使用，無法進行刪除！']]]);
        }

        $shop_management_mode->delete();
        return response()->json(['status'=>true]);
    }

}
