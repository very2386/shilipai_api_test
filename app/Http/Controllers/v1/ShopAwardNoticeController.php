<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DefaultMessage;
use App\Models\Shop;
use App\Models\ShopAwardNotice;
use App\Models\ShopCoupon;
use App\Models\ShopCustomer;
use App\Models\ShopManagementCustomerList;
use Illuminate\Http\Request;

class ShopAwardNoticeController extends Controller
{
    // 取得獎勵通知列表資料
    public function shop_award_notice_lists($shop_id)
    {
        // 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_award_notice_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	$notices = ShopAwardNotice::where('shop_id',$shop_id)->get();
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

            $status = $notice->use;
        	if( $status == 'N' ){
        		$status = '關閉';
        	}else{
                // 判斷是否已經超過發送日期
                if( $notice->during_type == 2 && strtotime(date('Y-m-d')) > strtotime($notice->end_date) ){
                    $status = '已結束';
                }else{
                    $status = '活動中';
                }
        	}
            
        	$notice_data[] = [
        		'id'        => $notice->id,
        		'name'      => $notice->name,
                'date'      => $notice->during_type == 1 ? '無期限' : $notice->start_date . ' 至 ' . $notice->end_date,
        		'send_type' => $send_type,
        		'status'    => $status,
                'link'      => $notice->link ? 'Y' : 'N',
                'coupon'    => $notice->coupon_info ? 'Y' : 'N',
        	];
        }

    	$data = [
            'status'                   => true,
            'permission'               => true,
            'notice_create_permission' => in_array('shop_award_notice_create_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_edit_permission'   => in_array('shop_award_notice_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_delete_permission' => in_array('shop_award_notice_delete_btn',$user_shop_permission['permission']) ? true : false, 
            'send_log_permission'      => in_array('shop_award_notice_send_log_btn',$user_shop_permission['permission']) ? true : false, 
            'data'                     => $notice_data,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家獎勵通知資料
    public function shop_award_notice_info($shop_id,$notice_id="")
    {
        if( $notice_id ){
            $shop_notice = ShopAwardNotice::find($notice_id);
            if( !$shop_notice ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到獎勵通知資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_notice = new ShopAwardNotice;
            $type        = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_award_notice_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 優惠券選項
        $coupon_select = [];
        $shop_coupons = ShopCoupon::where('shop_id',$shop_info->id)
                            // ->join('company_coupons','company_coupons.id','=','shop_coupons.company_coupon_id')
                            // ->where('end_date','>=',date('Y-m-d'))
                            ->where('status','published')
                            ->get();
        foreach( $shop_coupons as $coupon ){
            // if( !$coupon->coupon_info ) continue;
            $coupon_select[] = [
                'id'       => $coupon->id,
                'name'     => $coupon->title,
                'selected' => $coupon->id == $shop_notice->shop_coupons ? true : false,
                'disable'  => $coupon->end_date < date('Y-m-d') ? true : false,
            ];
        }

        $data = [
            // 篩選條件
            'id'                         => $shop_notice->id,
            'name'                       => $shop_notice->name,
            'name_permission'            => in_array('shop_award_notice_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'during_type'                => $shop_notice->during_type,
            'during_type_permission'     => in_array('shop_award_notice_'.$type.'_date',$user_shop_permission['permission']) ? true : false,
            'start_date'                 => $shop_notice->start_date,
            'start_date_permission'      => in_array('shop_award_notice_'.$type.'_date',$user_shop_permission['permission']) ? true : false,
            'end_date'                   => $shop_notice->end_date,
            'end_date_permission'        => in_array('shop_award_notice_'.$type.'_date',$user_shop_permission['permission']) ? true : false,

            // 發送訊息
            'message'                    => $shop_notice->message?:'',
            'message_permission'         => in_array('shop_award_notice_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
            'link'                       => $shop_notice->link,
            'link_permission'            => in_array('shop_award_notice_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
            'shop_coupons'               => $shop_notice->shop_coupons,
            'shop_coupons_permission'    => in_array('shop_award_notice_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
            'send_type'                  => $shop_notice->send_type?:2,
            'send_type_permission'       => in_array('shop_award_notice_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,

            // 條件設定
            'condition_type'             => $shop_notice->condition_type,
            'condition_type_permission'  => in_array('shop_award_notice_'.$type.'_condition_type',$user_shop_permission['permission']) ? true : false,
            'finish_type'                => $shop_notice->finish_type,
            'finish_type_permission'     => in_array('shop_award_notice_'.$type.'_condition_type',$user_shop_permission['permission']) ? true : false,
            'send_cycle'                 => $shop_notice->send_cycle,
            'send_cycle_permission'      => in_array('shop_award_notice_'.$type.'_send_cycle',$user_shop_permission['permission']) ? true : false,
            'send_cycle_type'            => $shop_notice->send_cycle_type,
            'send_cycle_type_permission' => in_array('shop_award_notice_'.$type.'_send_cycle_type',$user_shop_permission['permission']) ? true : false,
            'send_day'                   => $shop_notice->send_day != '' || $shop_notice->send_day === 0 ? (string)$shop_notice->send_day : '1',
            'send_day_permission'        => in_array('shop_award_notice_'.$type.'_send_cycle',$user_shop_permission['permission']) ? true : false,
            'send_datetime'              => $shop_notice->send_datetime ? date('c',strtotime(date('Y-m-d ') . $shop_notice->send_datetime)) : date('c',strtotime(date('Y-m-d H:i:s'))),
            'send_datetime_permission'   => in_array('shop_award_notice_'.$type.'_send_cycle',$user_shop_permission['permission']) ? true : false,
            'condition_times'            => $shop_notice->condition_times,
            'condition_times_permission' => in_array('shop_award_notice_'.$type.'_condition_type',$user_shop_permission['permission']) ? true : false,
            'price_type'                 => $shop_notice->price_type,
            'price_type_permission'      => in_array('shop_award_notice_'.$type.'_condition_type',$user_shop_permission['permission']) ? true : false,
            'price_condition'            => $shop_notice->price_condition,
            'price_condition_permission' => in_array('shop_award_notice_'.$type.'_condition_type',$user_shop_permission['permission']) ? true : false,
            'min_price'                  => $shop_notice->min_price,
            'min_price_permission'       => in_array('shop_award_notice_'.$type.'_condition_type',$user_shop_permission['permission']) ? true : false,
            'max_price'                  => $shop_notice->max_price,
            'max_price_permission'       => in_array('shop_award_notice_'.$type.'_condition_type',$user_shop_permission['permission']) ? true : false,
            'send_cycle_type'            => $shop_notice->send_cycle_type?:1,
            'send_cycle_type_permission' => in_array('shop_award_notice_'.$type.'_send_cycle_type',$user_shop_permission['permission']) ? true : false,
            'test_send_permission'       => in_array('shop_award_notice_'.$type.'_test_message',$user_shop_permission['permission']) ? true : false,
            'use'                        => $shop_notice->use?:'N',
            'use_permission'             => in_array('shop_award_notice_'.$type.'_use',$user_shop_permission['permission']) ? true : false,
        ];

        $res = [
            'status'        => true,
            'permission'    => true,
            'coupon_select' => $coupon_select,
            'data'          => $data,
        ];

        return response()->json($res);
    }

    // 儲存商家獎勵通知資料
    public function shop_award_notice_save($shop_id,$notice_id="")
    {
        if( $notice_id ){
            // 編輯
            $shop_notice = ShopAwardNotice::find($notice_id);
            if( !$shop_notice ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到獎勵通知資料']]]);
            }
            $type = 'edit';
        }else{
            // 新增
            $shop_notice = new ShopAwardNotice;
            $type = 'create';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_notice->shop_id = $shop_info->id;
        $shop_notice->during_type     = request('during_type');
        $shop_notice->name            = request('name');
        $shop_notice->start_date      = request('during_type') == 2 ? request('start_date') : NULL;
        $shop_notice->end_date        = request('during_type') == 2 ? request('end_date') : NULL;
        $shop_notice->use             = request('during_type') == 1 ? (request('use') ? request('use') : 'N') : 'Y';
        $shop_notice->link            = request('link');
        $shop_notice->shop_coupons    = request('shop_coupons');
        $shop_notice->message         = request('message');
        $shop_notice->send_type       = request('send_type');
        $shop_notice->condition_type  = request('condition_type');
        $shop_notice->finish_type     = request('condition_type') == 3 || request('condition_type') == 4 ? request('finish_type') : NULL ;
        $shop_notice->send_cycle      = request('send_cycle');
        $shop_notice->send_day        = request('condition_type') == 2 ? request('send_day') : NULL;
        $shop_notice->send_datetime   = request('send_cycle') == 1 ? NULL : (date('H:i',strtotime(request('send_datetime'))));
        $shop_notice->condition_times = request('condition_type') == 4 ? request('condition_times') : NULL;
        $shop_notice->price_type      = request('condition_type') == 5 ? request('price_type') : NULL;
        $shop_notice->price_condition = request('condition_type') == 5 ? request('price_condition') : NULL;
        $shop_notice->min_price       = request('condition_type') == 5 ? request('min_price') : NULL;
        $shop_notice->max_price       = request('condition_type') == 5 ? request('max_price') : NULL;
        $shop_notice->send_cycle_type = request('condition_type') == 5 ? request('send_cycle_type') : NULL;
        $shop_notice->save();

        $shop_notice = ShopAwardNotice::where('id',$shop_notice->id)->first();

        return response()->json(['status'=>true,'data'=>$shop_notice]);
    }

    // 刪除商家獎勵通知資料 
    public function shop_award_notice_delete($shop_id,$notice_id)
    {
        $shop_notice = ShopAwardNotice::find($notice_id);
        if( !$shop_notice ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到獎勵通知資料']]]);
        }

        $shop_notice->delete();

        return response()->json(['status'=>true]);
    }

    // 獎勵通知發送清單
    public function shop_award_notice_send_log($shop_id,$notice_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_notice_send_log',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_notice = ShopAwardNotice::find($notice_id);
        if( !$shop_notice ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到獎勵通知資料']]]);
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

        $logs = ShopManagementCustomerList::orderBy('updated_at','DESC')->where('shop_award_notice_id',$notice_id)->get();
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
            'resend_permission' => in_array('shop_award_notice_resend',$user_shop_permission['permission']) ? true : false,
            'refuse_permission' => in_array('shop_award_notice_refuse',$user_shop_permission['permission']) ? true : false,
            'notice_info'       => $notice_info,
            'data'              => $send_logs,
        ];

        return response()->json($data);
    }


}
