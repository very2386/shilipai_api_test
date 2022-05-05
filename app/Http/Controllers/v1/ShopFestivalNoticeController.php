<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DefaultMessage;
use App\Models\Shop;
use App\Models\ShopCoupon;
use App\Models\ShopCustomer;
use App\Models\ShopFestivalNotice;
use App\Models\ShopManagementCustomerList;
use Illuminate\Http\Request;
use Overtrue\ChineseCalendar\Calendar;
use PhpParser\Node\Stmt\TryCatch;

class ShopFestivalNoticeController extends Controller
{
    // 取得節慶通知列表資料
    public function shop_festival_notice_lists($shop_id)
    {
        // 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_festival_notice_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	$notices = ShopFestivalNotice::where('shop_id',$shop_id)->get();
        $notice_data = [];
        foreach( $notices as $notice ){
        	$send_type = '';
        	switch ($notice->send_type){
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

            $calendar = new Calendar();
            if( $notice->week != '' ){
                // 母親節
                $sunday = 0;
                for($i = 1 ; $i <= 14 ; $i++ ){
                    $date_trans = $calendar->solar(date('Y'),$notice->month,$i);
                    $sunday += $date_trans['week_no'] == 0 ? 1 : 0;
                    if( $sunday == 2 ){
                        $date = date('Y-05-'). ($i > 9 ? $i : '0'.$i);
                        break;
                    }
                }
            }else{
                // 其他節慶
                if( $notice->type == 1 ){
                    // 國曆
                    $date = date('Y') . '-' . ($notice->month > 9 ? $notice->month : '0'.$notice->month) . '-' . ($notice->day > 9 ? $notice->day : '0'.$notice->day); 
                }else{
                    // 農曆
                    try {
                        $calendar->lunar(date('Y'),$notice->month,$notice->day);
                        $date_trans = $calendar->lunar(date('Y'),$notice->month,$notice->day);
                        $date       = date('Y') . '-' . $date_trans['gregorian_month'] . '-' . $date_trans['gregorian_day'];
                    } catch (\Exception $e) {
                        $date_trans = '';
                        $date       = '';
                    } 
                }
            }

            $send_date = date('Y-m-d',strtotime($date.' -'.$notice->before.' day') );

            $status = $notice->use;
        	if( $status == 'N' ){
        		$status = '關閉';
        	}else{
                // 判斷是否已經超過發送日期
                if( strtotime(date('Y-m-d')) > strtotime($send_date) ){
                    $status = '已發送';
                }else{
                    if( strtotime(date('Y-m-d')) >= strtotime($send_date) && date('H:i') >= date('H:i',strtotime($notice->send_datetime)) ){
                        $status = '已發送';
                    }else{
                        $status = '待發送';
                    }
                }
        	}
            
            $notice_delete_permission = in_array('shop_festival_notice_delete_btn',$user_shop_permission['permission']) ? true : false;
            if( $notice->default == 'Y' ){
                // 預設不可以刪除
                $notice_delete_permission = false;
            }

            $notice_data[] = [
        		'id'                => $notice->id,
        		'name'              => $notice->name,
                'date'              => substr($date,5,5),
                'send_date'         => substr($send_date,5,5),
        		'send_type'         => $send_type,
        		'status'            => $status,
                'link'              => $notice->link ? 'Y' : 'N',
                'coupon'            => $notice->coupon_info ? 'Y' : 'N',
                'delete_permission' => $notice_delete_permission,
        	];
        }

    	$data = [
            'status'                   => true,
            'permission'               => true,
            'notice_create_permission' => in_array('shop_festival_notice_create_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_edit_permission'   => in_array('shop_festival_notice_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_delete_permission' => $notice_delete_permission, 
            'send_log_permission'      => in_array('shop_festival_notice_send_log_btn',$user_shop_permission['permission']) ? true : false, 
            'data'                     => $notice_data,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家節慶通知資料
    public function shop_festival_notice_info($shop_id,$notice_id="")
    {
        if( $notice_id ){
            $shop_notice = ShopFestivalNotice::find($notice_id);
            if( !$shop_notice ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到節慶通知資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_notice = new ShopFestivalNotice;
            $type        = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_festival_notice_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

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
                'selected' => $coupon->id == $shop_notice->shop_coupons ? true : false,
                'disable'  => $coupon->end_date < date('Y-m-d') ? true : false,
            ];
        }

        // 罐頭模組選項
        $default_mode_select = [];
        $default_modes = DefaultMessage::get();
        foreach( $default_modes as $mode ){
            $default_mode_select[] = [
                'id'       => $mode->id,
                'name'     => $mode->name,
                'selected' => $mode->id == $shop_notice->default_message_id ? true : false,
            ]; 
        }

        $calendar = new Calendar();
        if( $shop_notice->week != '' ){ 
            // 母親節
            $sunday = 0;
            for($i = 1 ; $i <= 14 ; $i++ ){
                $date_trans = $calendar->solar(date('Y'),$shop_notice->month,$i);
                $sunday += $date_trans['week_no'] == 0 ? 1 : 0;
                if( $sunday == 2 ){
                    $date = date('Y-05-'). ($i > 9 ? $i : '0'.$i);
                    $date_text =  date('n月j日',strtotime($date)).'(5月的第二個週日)';
                    break;
                }
            }
        }else{
            // 其他節慶
            if( $shop_notice->type == 1 ){
                // 國曆
                $date = date('Y') . '-' . ($shop_notice->month > 9 ? $shop_notice->month : '0'.$shop_notice->month) . '-' . ($shop_notice->day > 9 ? $shop_notice->day : '0'.$shop_notice->day); 
                $date_text =  date('n月j日',strtotime($date));
            }else{
                // 農曆
                try {
                    $calendar->lunar(date('Y'),$shop_notice->month,$shop_notice->day);
                    $date_trans = $calendar->lunar(date('Y'),$shop_notice->month,$shop_notice->day);
                    $date       = date('Y') . '-' . $date_trans['gregorian_month'] . '-' . $date_trans['gregorian_day'];
                    $date_text  = date('n月j日',strtotime($date)).'(農曆'.$shop_notice->month.'月'.$shop_notice->day.'日)';
                } catch (\Exception $e) {
                    $date_trans = '';
                    $date       = '';
                    $date_text  = '';
                } 
            }
        }

        $data = [
            // 篩選條件
            'id'                         => $shop_notice->id,
            'default'                    => $type == 'create' ? 'N' : $shop_notice->default,
            
            'date'                       => $date_text,

            'name'                       => $shop_notice->name,
            'name_permission'            => in_array('shop_festival_notice_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            
            'type'                       => $shop_notice->type,
            'type_permission'            => $shop_notice->default == 'Y' ? false : (in_array('shop_festival_notice_'.$type.'_date',$user_shop_permission['permission']) ? true : false),
            'month'                      => $shop_notice->week != '' ? date('m',strtotime($date)) : $shop_notice->month,
            'month_permission'           => $shop_notice->default == 'Y' ? false : (in_array('shop_festival_notice_'.$type.'_date',$user_shop_permission['permission']) ? true : false),
            'day'                        => $shop_notice->week != '' ? date('d',strtotime($date)) : $shop_notice->day,
            'day_permission'             => $shop_notice->default == 'Y' ? false : (in_array('shop_festival_notice_'.$type.'_date',$user_shop_permission['permission']) ? true : false),

            // 發送訊息
            'mode'                       => $shop_notice->default_message_id,
            'mode_permission'            => in_array('shop_festival_notice_'.$type.'_mode',$user_shop_permission['permission']) ? true : false,
            'message'                    => $shop_notice->message?:'',
            'message_permission'         => in_array('shop_festival_notice_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
            'link'                       => $shop_notice->link,
            'link_permission'            => in_array('shop_festival_notice_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
            'shop_coupons'               => $shop_notice->shop_coupons,
            'shop_coupons_permission'    => in_array('shop_festival_notice_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
            'send_type'                  => $shop_notice->send_type?:2,
            'send_type_permission'       => in_array('shop_festival_notice_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,
            'before'                     => $shop_notice->before != '' || $shop_notice->before === 0 ? (string)$shop_notice->before : '15',
            'before_permission'          => in_array('shop_festival_notice_'.$type.'_send_datetime',$user_shop_permission['permission']) ? true : false,
            'send_datetime'              => $shop_notice->send_datetime ? date('c',strtotime(date('Y-m-d ') . $shop_notice->send_datetime)) : date('c',strtotime(date('Y-m-d H:i:s'))),
            'send_datetime_permission'   => in_array('shop_festival_notice_'.$type.'_send_datetime',$user_shop_permission['permission']) ? true : false,

            'test_send_permission'       => in_array('shop_festival_notice_'.$type.'_test_message',$user_shop_permission['permission']) ? true : false,
            'use'                        => $shop_notice->use?:'N',
            'use_permission'             => in_array('shop_festival_notice_'.$type.'_use',$user_shop_permission['permission']) ? true : false,
        ];

        $res = [
            'status'        => true,
            'permission'    => true,
            'mode_select'   => $default_mode_select,
            'coupon_select' => $coupon_select,
            'data'          => $data,
        ];

        return response()->json($res);
    }

    // 儲存商家節慶通知資料
    public function shop_festival_notice_save($shop_id,$notice_id="")
    {
        if( $notice_id ){
            // 編輯
            $shop_notice = ShopFestivalNotice::find($notice_id);
            if( !$shop_notice ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到節慶通知資料']]]);
            }
            $type = 'edit';
        }else{
            // 新增
            $shop_notice = new ShopFestivalNotice;
            $type = 'create';
        }

        // 判斷農曆時間是否可以
        if( request('type') == 2 ){
            $calendar = new Calendar();
            try {
                $calendar->lunar(date('Y'),request('month'),request('day'));
            } catch (\Exception $e) {
                return response()->json(['status'=>false,'errors'=>['message'=>['農曆日期錯誤']]]);
            }
        }

        if( request('type') == 1 ){
            $month_days  = cal_days_in_month(CAL_GREGORIAN, request('month'), date('Y'));
            if( request('day') > $month_days ){
                return response()->json(['status'=>false,'errors'=>['message'=>['國曆日期錯誤']]]);
            }
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_notice->shop_id = $shop_info->id;
        if( $type == 'create' || $shop_notice->default == 'N' ){
            // 新增或是編輯不是預設資料要儲存設定時間
            $shop_notice->type  = request('type');
            $shop_notice->month = request('month');
            $shop_notice->day   = request('day');
        }
        
        $shop_notice->default_message_id = request('mode');
        $shop_notice->name               = request('name');
        $shop_notice->link               = request('link');
        $shop_notice->message            = request('message');
        $shop_notice->send_type          = request('send_type');
        $shop_notice->before             = request('before');
        $shop_notice->send_datetime      = date('H:i',strtotime(request('send_datetime')));
        $shop_notice->use                = request('use') ? request('use') : 'N';
        $shop_notice->shop_coupons       = request('shop_coupons');
        $shop_notice->save();

        $shop_notice = ShopFestivalNotice::where('id',$shop_notice->id)->first();

        return response()->json(['status'=>true,'data'=>$shop_notice]);
    }

    // 刪除商家節慶通知資料 
    public function shop_festival_notice_delete($shop_id,$notice_id)
    {
        $shop_notice = ShopFestivalNotice::find($notice_id);
        if( !$shop_notice ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到節慶通知資料']]]);
        }

        $shop_notice->delete();

        return response()->json(['status'=>true]);
    }

    // 節慶通知發送清單
    public function shop_festival_notice_send_log($shop_id,$notice_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_festival_notice_send_log',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_notice = ShopFestivalNotice::find($notice_id);
        if( !$shop_notice ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到節慶通知資料']]]);
        }

        $send_type = [
            '1' => '手機簡訊與LINE都發送',
            '2' => '僅手機簡訊',
            '3' => '僅LINE',
            '4' => '以LINE優先，無LINE者發送手機簡訊',
        ];

        $notice_info = [
            'id'        => $notice_id,
            'name'      => $shop_notice->name,
            'send_type' => $send_type[$shop_notice->send_type],
        ];

        $logs = ShopManagementCustomerList::orderBy('updated_at','DESC')->where('shop_festival_notice_id',$notice_id)->get();
        $send_logs = [];
        foreach( $logs as $log ){

            if( !$log->customer_info ){
                $shop_customer = ShopCustomer::where('id',$log->shop_customer_id)->withTrashed()->first();
                if( !$shop_customer ) continue;
                if( !$shop_customer->customer_info && $shop_customer->id != 59 ) continue;
                $log->customer_info = Customer::where('id',$shop_customer->customer_id)->withTrashed()->first();
            }

            $sms_status = $line_status = '失敗';
            if( $shop_notice->send_type == 2 ){
                if( $log->sms == 'F' ) $sms_status = '失敗';
                if( $log->sms == 'Y' ) $sms_status = '成功';
            }elseif( $shop_notice->send_type == 3){
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
            'resend_permission' => in_array('shop_festival_notice_resend',$user_shop_permission['permission']) ? true : false,
            'refuse_permission' => in_array('shop_festival_notice_refuse',$user_shop_permission['permission']) ? true : false,
            'notice_info'       => $notice_info,
            'data'              => $send_logs,
        ];

        return response()->json($data);
    }


}
