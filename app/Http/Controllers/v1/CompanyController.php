<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Models\BuyMode;
use App\Models\Company;
use App\Models\Shop;
use App\Models\Permission;
use App\Models\User;

class CompanyController extends Controller
{
    // 取得company合約資料
    public function company_contract($company_id)
    {
        // 拿取使用者的集團權限
        $user_company_permission = PermissionController::user_company_permission($company_id);
        if( $user_company_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_company_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('contract',$user_company_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $company_info = Company::find($company_id);

        // 方案內容
        $contact = [
        	'buy_mode'       => $company_info->buy_mode_info->title,
            'change_mode'    => in_array('change_mode',$user_company_permission['permission']) ? true : false,
            'mode_terms'     => in_array('mode_terms',$user_company_permission['permission']) ? true : false,
        	'deadline'       => substr($company_info->deadline,0,10),
            'renew'          => in_array('renew',$user_company_permission['permission']) ? true : false,
            'point_exchange' => in_array('point_exchange',$user_company_permission['permission']) ? true : false,
        	'last_day'       => ( strtotime( substr($company_info->deadline,0,10) ) - strtotime( date('Y-m-d') ) ) / (60*60*24),
        	'price'          => $company_info->buy_mode_info->price,
        ];

        // 簡訊方案
        $sms = [
        	'last_sms'    => $company_info->gift_sms + $company_info->free_sms,
            'buy_sms'     => in_array('buy_sms',$user_company_permission['permission']) ? true : false,
            'message_log' => in_array('message_log',$user_company_permission['permission']) ? true : false,
        ];

        return response()->json(['status'=>true,'permission'=>true,'data'=>compact('contact','sms')]);
    }

    // 取得company付款記錄資料
    public function company_order($company_id)
    {
    	// 拿取使用者的集團權限
        $user_company_permission = PermissionController::user_company_permission($company_id);
        if( $user_company_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_company_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('order_log',$user_company_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $company_info = Company::find($company_id);

        $orders = $company_info->company_orders->where('pay_status','!=','N');
        $data = [];
        foreach( $orders as $order ){
        	$data[] = [
        		'oid'        => $order->oid,
        		'name'       => $order->note,
        		'date'       => date('Y-m-d' , strtotime($order->created_at)),
        		'pay_date'   => $order->pay_date,
        		'pay_status' => $order->pay_status == 'Y' ? '已付款' : ( $order->pay_status == 'N' ? '待付款' : '付款逾期' ),
        		'price'      => $order->price,
        	];
        }

        return response()->json(['status'=>true,'permission'=>true,'data'=>$data]);
    }

    // 取得集團續費方案
    public function company_renew_mode($company_id)
    {
        // 拿取使用者的集團權限
        $user_company_permission = PermissionController::user_company_permission($company_id);
        if( $user_company_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_company_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('renew_read',$user_company_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $company = Company::where('id',$company_id)->with('buy_mode_info')->first();

        $company->buy_mode_info->deadline = date('Y-m-d',strtotime($company->deadline."+1 year"));

        $permission = Permission::where('company_id',$company_id)->first();
        $company->buy_mode_info->phone = User::find($permission->user_id)->phone;
        
        return response()->json(['status'=>true,'permission'=>true,'data'=>$company->buy_mode_info]);  
    }

    // 方案變更項目(原集團方案為基本版、進階版使用)
    public function change_mode_list($company_id)
    {
        // 拿取使用者的集團權限
        $user_company_permission = PermissionController::user_company_permission($company_id);
        if( $user_company_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_company_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('change_mode_read',$user_company_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $company = Company::where('id',$company_id)->with('buy_mode_info')->first();

        // switch ($company->buy_mode_id) {
        // 	case 0:
        // 		$buy_modes = BuyMode::whereIn('id',[1,2,3])->get();
        // 		break;
        // 	case 1:
        // 		$buy_modes = BuyMode::whereIn('id',[2,3])->get();
        // 		break;
        // }

        $buy_modes = BuyMode::whereIn('id',[1])->get();

        foreach( $buy_modes as $mode ){
            $today = date('Y-m-d');
            if( $company->buy_mode_id != 0 ){
                $deadline = $company->deadline ? substr($company->deadline,0,10) : date('Y-m-d');
                $last_day = (strtotime($deadline) - strtotime(date('Y-m-d'))) / ( 60*60*24 );// 剩餘天數
                switch ($mode->id) {
                    case 1:
                        $mode->deadline = date('Y-m-d',strtotime($company->deadline."+1 year"));
                        break;
                    case 2:
                        $add_day = $last_day > 0 ? ceil($last_day/3) : 0; 
                        $mode->deadline = date('Y-m-d',strtotime($today."+1 year +".$add_day." day"));
                        break;
                    case 3:
                        $add_day = $last_day > 0 ? ceil($last_day/5.5) : 0; 
                        $mode->deadline = date('Y-m-d',strtotime($today."+1 year +".$add_day." day"));
                        break;
                }
            }else{
                $mode->deadline = date('Y-m-d',strtotime($today."+1 year"));
            }
        }     

    	return response()->json(['status'=>true,'permission'=>true,'data'=>$buy_modes]);
    }
}
