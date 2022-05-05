<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\ShopCoupon;
use App\Models\ShopManagement;
use App\Models\ShopNoticeMode;
use App\Models\ShopNoticeModeQuestion;
use App\Models\ShopServiceCategory;
use App\Models\ShopService;
use App\Models\ShopManagementCustomerList;

class ShopNoticeController extends Controller
{
    // 商家訊息通知列表
    public function shop_notice_lists($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_notice_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	$notices = ShopManagement::where('shop_id',$shop_id)->where('type','notice')->orderBy('id','DESC')->get();
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
        		$status = '啟用中';
        	}

        	$shop_services = explode(',', $notice->shop_services);
        	$services = ShopService::whereIn('id',$shop_services)->pluck('name')->toArray();

        	$notice_data[] = [
        		'id'        => $notice->id,
        		'name'      => $notice->name,
        		'services'  => implode(',',$services),
        		'send_type' => $send_type,
        		'status'    => $status,
        	];
        }

    	$data = [
            'status'                   => true,
            'permission'               => true,
            'notice_create_permission' => in_array('shop_notice_create_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_edit_permission'   => in_array('shop_notice_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_delete_permission' => in_array('shop_notice_delete_btn',$user_shop_permission['permission']) ? true : false, 
            // 'notice_use_permission'    => in_array('shop_notice_use_btn',$user_shop_permission['permission']) ? true : false, 
            'send_log_permission'      => in_array('shop_notice_send_log',$user_shop_permission['permission']) ? true : false, 
            'notice_mode_permission'   => in_array('shop_notice_mode_lists_btn',$user_shop_permission['permission']) ? true : false, 
            'data'                     => $notice_data,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家訊息通知資料
    public function shop_notice_info($shop_id,$notice_id="")
    {
        if( $notice_id ){
            $shop_notice = ShopManagement::find($notice_id);
            if( !$shop_notice ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到訊息通知資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_notice = new ShopManagement;
            $type        = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_notice_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 優惠券選項
        $coupon_select = [];
        $shop_coupons = ShopCoupon::where('shop_id',$shop_info->id)->join('company_coupons','company_coupons.id','=','shop_coupons.company_coupon_id')
                            ->where('end_date','>=',date('Y-m-d'))
                            ->where('company_coupons.status','published')
                            ->get();
        foreach( $shop_coupons as $coupon ){
            $coupon_select[] = [
                'id'       => $coupon->id,
                'name'     => $coupon->title,
                'selected' => $coupon->id == $shop_notice->shop_coupons ? true : false,
            ];
        }

        // 模組選項
        $mode_select = [];
        $notice_modes = ShopNoticeMode::where('shop_id',$shop_info->id)->get();
        foreach( $notice_modes as $mode ){
            $mode_select[] = [
                'id'       => $mode->id,
                'name'     => $mode->name,
                'selected' => $mode->id == $shop_notice->shop_notice_mode_id ? true : false,
            ]; 
        }

        // 服務選項
        $shop_service_categories = ShopServiceCategory::where('shop_id',$shop_id)->select('id','name')->get();
        foreach( $shop_service_categories as $service_category ){
            $service_category->shop_services = ShopService::where('shop_service_category_id',$service_category->id)->select('id','name')->get();
            $shop_notice_services = explode(',',$shop_notice->shop_services);
            foreach( $service_category->shop_services as $service ){
                if( in_array( $service->id , $shop_notice_services) ){
                    $service->selected = true;
                }else{
                    $service->selected = false;
                }
            }
        }

        $data = [
            // 篩選條件
            'id'                         => $shop_notice->id,
            'name'                       => $shop_notice->name,
            'name_permission'            => in_array('shop_notice_'.$type.'_name',$user_shop_permission['permission']) ? true : false,

            // 發送訊息
            'message'                    => $shop_notice->message,
            'message_permission'         => in_array('shop_notice_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
            'link'                       => $shop_notice->link,
            'link_permission'            => in_array('shop_notice_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
            'shop_coupons'               => $shop_notice->shop_coupons,
            'shop_coupons_permission'    => in_array('shop_notice_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
            'send_type'                  => $shop_notice->send_type?:2,
            'send_type_permission'       => in_array('shop_notice_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,

            'notice_type'                => $shop_notice->notice_type,
            'notice_type_permission'     => in_array('shop_notice_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
            'notice_day'                 => $shop_notice->notice_day,
            'notice_day_permission'      => in_array('shop_notice_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
            'notice_time'                => $shop_notice->notice_time ? date('c',strtotime(date('Y-m-d ') . $shop_notice->notice_time)) : date('c',strtotime(date('Y-m-d H:i:s'))),
            'notice_time_permission'     => in_array('shop_notice_'.$type.'_notice_datetime',$user_shop_permission['permission']) ? true : false,
            'notice_cycle'               => $shop_notice->notice_cycle,
            'notice_cycle_permission'    => in_array('shop_notice_'.$type.'_notice_cycle',$user_shop_permission['permission']) ? true : false, 

            'shop_services'              => $shop_service_categories,
            'shop_services_permission'   => in_array('shop_notice_'.$type.'_shop_services',$user_shop_permission['permission']) ? true : false, 

            'mode'                       => $shop_notice->shop_notice_mode_id,
            'mode_permission'            => in_array('shop_notice_'.$type.'_mode',$user_shop_permission['permission']) ? true : false,
            // 'use'                        => $shop_notice->use?:'N',
            // 'use_permission'             => in_array('shop_notice_'.$type.'_use',$user_shop_permission['permission']) ? true : false,

            'test_send_permission'       => in_array('shop_notice_'.$type.'_test_message',$user_shop_permission['permission']) ? true : false,
            'use'                        => $shop_notice->use?:'N',
            'use_permission'             => in_array('shop_notice_'.$type.'_use',$user_shop_permission['permission']) ? true : false,
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

    // 儲存商家訊息通知資料
    public function shop_notice_save($shop_id,$notice_id="")
    {
        if( $notice_id ){
            // 編輯
            $shop_management = ShopManagement::find($notice_id);
            if( !$shop_management ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到訊息通知資料']]]);
            }
        }else{
            // 新增
            $shop_management = new ShopManagement;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_management->shop_id             = $shop_info->id;
        $shop_management->type                = 'notice';
        $shop_management->shop_notice_mode_id = request('mode');
        $shop_management->name                = request('name');
        $shop_management->link                = request('link');
        $shop_management->message             = request('message');
        $shop_management->send_type           = request('send_type');
        $shop_management->notice_cycle        = request('notice_cycle');
        $shop_management->notice_day          = request('notice_day');
        $shop_management->notice_time         = date('H:i',strtotime(request('notice_time')));
        $shop_management->notice_type         = request('notice_type');
        $shop_management->use                 = request('use') ? request('use') : 'N';
        $shop_management->shop_coupons        = request('shop_coupons');

        // 商家服務
        $shop_services = [];
        foreach( request('shop_services') as $category ){
            foreach( $category['shop_services'] as $service ){
                if( $service['selected'] == true ) $shop_services[] = $service['id'];
            }
        }
        $shop_management->shop_services = implode(',', $shop_services);
        $shop_management->save();

        $shop_management = ShopManagement::where('id',$shop_management->id)->with('customer_lists')->first();

        return response()->json(['status'=>true,'data'=>$shop_management]);
    }

    // 刪除商家訊息通知資料 
    public function shop_notice_delete($shop_id,$notice_id)
    {
        $shop_management = ShopManagement::find($notice_id);
        if( !$shop_management ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到訊息通知資料']]]);
        }

        $shop_management->delete();

        return response()->json(['status'=>true]);
    }

    // 訊息通知發送清單
    public function shop_notice_send_log($shop_id,$notice_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_notice_send_log',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_management = ShopManagement::find($notice_id);
        if( !$shop_management ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到訊息通知資料']]]);
        }

        $send_type = [
            '1' => '手機簡訊與LINE都發送',
            '2' => '僅手機簡訊',
            '3' => '僅LINE',
            '4' => '以LINE優先，無LINE者發送手機簡訊',
        ];

        $notice_info = [
            'id'        => $notice_id,
            'name'      => $shop_management->name,
            'send_type' => $send_type[$shop_management->send_type],
        ];

        $logs = ShopManagementCustomerList::where('shop_management_id',$notice_id)->get();
        $send_logs = [];
        foreach( $logs as $log ){

            $sms_status = $line_status = '-';
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
            'status'      => true,
            'permission'  => true,
            'resend_permission' => in_array('shop_notice_resend',$user_shop_permission['permission']) ? true : false,
            'refuse_permission' => in_array('shop_notice_refuse',$user_shop_permission['permission']) ? true : false,
            'notice_info' => $notice_info,
            'data'        => $send_logs,
        ];

        return response()->json($data);
    }

    // 訊息通知模組列表
    public function shop_notice_mode_lists($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_notice_mode_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $notice_modes = ShopNoticeMode::where('shop_id',$shop_id)->orderBy('id','DESC')->get();
        $notice_mode_data = [];
        foreach( $notice_modes as $notice_mode ){
            $notice_mode_data[] = [
                'id'   => $notice_mode->id,
                'name' => $notice_mode->name,
            ];
        }

        $data = [
            'status'                        => true,
            'permission'                    => true,
            'notice_mode_create_permission' => in_array('shop_notice_mode_create_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_mode_edit_permission'   => in_array('shop_notice_mode_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'notice_mode_delete_permission' => in_array('shop_notice_mode_delete_btn',$user_shop_permission['permission']) ? true : false, 
            'data'                          => $notice_mode_data,
        ];

        return response()->json($data);
    }

    // 新增/編輯訊息通知模組資料
    public function shop_notice_mode_info($shop_id,$management_mode_id="")
    {
    	if( $management_mode_id ){
    	    $shop_notice_info = ShopNoticeMode::find($management_mode_id);
    	    if( !$shop_notice_info ){
    	        return response()->json(['status'=>false,'errors'=>['message'=>['找不到訊息通知資料']]]);
    	    }
    	    $type = 'edit';
    	}else{
    	    $shop_notice_info = new ShopNoticeMode;
    	    $type             = 'create';
    	}
    	
    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_notice_mode_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	// 優惠券選項
    	$coupons = ShopCoupon::where('shop_id',$shop_info->id)
                            ->where('end_date','>=',date('Y-m-d'))
                            ->where('status','published')
                            ->orderBy('start_date','DESC')->get();
        $shop_notice_coupons = $shop_notice_info->shop_coupons;
        $coupon_info = [];
        foreach( $coupons as $coupon ){
        	$coupon_info[] = [
        		'id'       => $coupon->id,
        		'name'     => $coupon->title,
        		'selected' => $coupon->id == $shop_notice_coupons ? true : false,
        	];
        }

        // 服務選項
        $service_info = ShopServiceCategory::where('shop_id',$shop_id)->select('id','name')->get();
        $shop_services = explode(',', $shop_notice_info->shop_services);
        foreach( $service_info as $service_category ){
            $service_category->shop_services = ShopService::where('shop_service_category_id',$service_category->id)->select('id','name')->get();
            foreach( $service_category->shop_services as $service ){
                $service->selected = in_array($service->id,$shop_services) ? true : false;
            }
        }

        // 問卷內容
        $questions     = $shop_notice_info->notice_questions;
        $question_info = [];
        foreach( $questions as $question ){
        	$question_info[] = [
        		'id'              => $question->id,
        		'question'        => $question->question,
        		'question_type'   => $question->question_type,
        		'question_option' => $question->question_option ? explode(',', $question->question_option) : [], 
        	];
        }
        if( empty($question_info) ){
        	$question_info[] = [
        		'id'              => '',
        		'question'        => '',
        		'question_type'   => '',
        		'question_option' => [], 
        	];
        }

    	$notice_info = [
            'id'              	   => $shop_notice_info->id,
            'name'            	   => $shop_notice_info->name,
            'name_permission' 	   => in_array('shop_notice_mode_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'content'              => $shop_notice_info->content,
            'content_permission'   => in_array('shop_notice_mode_'.$type.'_content',$user_shop_permission['permission']) ? true : false,
            'questions'            => $question_info,
            'questions_permission' => in_array('shop_notice_mode_'.$type.'_questions',$user_shop_permission['permission']) ? true : false,
        ];

        $data = [
            'status'     => true,
            'permission' => true,
            'data'       => $notice_info,
        ];

		return response()->json($data);
    }

    // 儲存訊息通知模組資料
    public function shop_notice_mode_save($shop_id,$management_mode_id="")
    {
    	if( $management_mode_id ){
            // 編輯
            $shop_notice_info = ShopNoticeMode::find($management_mode_id);
            if( !$shop_notice_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到訊息通知資料']]]);
            }
        }else{
            // 新增
            $shop_notice_info = new ShopNoticeMode;
            $shop_notice_info->shop_id = $shop_id;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_notice_info->name    = request('name');
        $shop_notice_info->content = request('content');
        $shop_notice_info->save();

        // 問券
        foreach( request('questions') as $question ){
        	if( $question['id'] ){
        		// 編輯問題
        		$shop_notice_question = ShopNoticeModeQuestion::find($question['id']);
        		$shop_notice_question->question        = $question['question'];
        		$shop_notice_question->question_option = implode(',',$question['question_option']);
        		$shop_notice_question->save(); 
        	}else{
        		// 新增問題，有打問題敘述才可以寫入
        		if( $question['question'] ){
        			if( ($question['question_type'] == 'radio' || $question['question_type'] == 'checkbox') && !empty($question['question_option']) ){
        				$shop_notice_question = new ShopNoticeModeQuestion;
                        $shop_notice_question->shop_id             = $shop_info->id;
        				$shop_notice_question->shop_notice_mode_id = $shop_notice_info->id;
		        		$shop_notice_question->question            = $question['question'];
		        		$shop_notice_question->question_type       = $question['question_type'];
		        		$shop_notice_question->question_option     = implode(',',$question['question_option']);
		        		$shop_notice_question->save(); 
        			}elseif( $question['question_type'] == 'text' ){
        				$shop_notice_question = new ShopNoticeModeQuestion;
                        $shop_notice_question->shop_id             = $shop_info->id;
        				$shop_notice_question->shop_notice_mode_id = $shop_notice_info->id;
		        		$shop_notice_question->question            = $question['question'];
		        		$shop_notice_question->question_type       = $question['question_type'];
		        		$shop_notice_question->save();
        			}

        		}
        	}
        }

        return response()->json(['status'=>true]);
    }

    // 刪除商家訊息通知模組資料
    public function shop_notice_mode_delete($shop_id,$notice_mode_id)
    {
        $shop_notice_mode = ShopNoticeMode::find($notice_mode_id);
        if( !$shop_notice_mode ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到訊息通知模組資料']]]);
        }

        if( ShopManagement::where('shop_id',$shop_id)->where('shop_notice_mode_id',$notice_mode_id)->get()->count() ){
            return response()->json(['status'=>false,'errors'=>['message'=>['此模組已被使用，無法進行刪除！']] ]);
        }

        $shop_notice_mode->delete();
        // 問券也一起刪除
        ShopNoticeModeQuestion::where('shop_notice_mode_id',$notice_mode_id)->delete();

        return response()->json(['status'=>true]);
    }

}
