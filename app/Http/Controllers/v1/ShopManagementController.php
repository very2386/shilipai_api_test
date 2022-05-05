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
use App\Models\ShopManagementRefuse;
use App\Models\ShopManagementCustomerList;
use App\Models\ShopServiceCategory;
use App\Models\ShopService;
use App\Models\ShopServiceStaff;
use App\Models\ShopStaff;


class ShopManagementController extends Controller
{
    // 單次推廣列表
    public function shop_once_management_lists($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_once_management_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

        $managements = ShopManagement::where('shop_id',$shop_id)->where('type','once')->orderBy('id','DESC')->get();
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

        	$status = $management->status;
        	if( $status == 'N' ){
        		// 檢查是否有發送清單
        		if( $management->customer_lists->count() ){
        			$status = '待發送';
        		}else{
        			$status = '待編輯';
        		}
        	}else{
        		$status = '已發送';
        	}
        	$data[] = [
        		'id'               => $management->id,
        		'created_at'       => $management->created_at,
        		'name'             => $management->name,
        		'people'           => $management->send_datetime ? $management->customer_lists->count() : '-',
        		'reservation_date' => $management->send_datetime ? substr($management->send_datetime,0,16) : '-',
        		'send_type'        => $send_type,
        		'status'           => $status,
        	];
        }

    	$data = [
            'status'                              => true,
            'permission'                          => true,
            'once_management_create_permission'   => in_array('once_management_create_btn',$user_shop_permission['permission']) ? true : false, 
            'once_management_edit_permission'     => in_array('once_management_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'once_management_delete_permission'   => in_array('once_management_delete_btn',$user_shop_permission['permission']) ? true : false, 
            'once_management_copy_permission'     => in_array('once_management_copy_btn',$user_shop_permission['permission']) ? true : false, 
            'once_management_mode_permission'     => in_array('once_management_mode_lists',$user_shop_permission['permission']) ? true : false, 
            'once_management_send_log_permission' => in_array('once_management_send_log',$user_shop_permission['permission']) ? true : false, 
            'data'                                => $data,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家單次推廣資料
    public function shop_once_management_info($shop_id,$management_id="")
    {
    	if( $management_id ){
            $shop_management = ShopManagement::find($management_id);
            if( !$shop_management ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣資料']]]);
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
        if( !in_array('shop_once_management_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 商家會員選項
        $customer_select = [];
        $refuse_customer = ShopManagementRefuse::pluck('shop_customer_id')->toArray();
        $shop_customers  = ShopCustomer::where('shop_id',$shop_id)->whereNotIn('id',$refuse_customer)->get();
        foreach( $shop_customers as $sc ){
            $shop_customer_ids = $shop_management->customer_lists->pluck('shop_customer_id')->toArray();

            // 剔除拒絕與篩選過的會員
            if( !in_array($sc->id, $shop_customer_ids) ){
                $customer_select[] = [
                    'id'       => $sc->id,
                    'name'     => $sc->customer_info->realname,
                    'phone'    => $sc->customer_info->phone,
                    'line'     => '-',
                ];
            }
        }

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
                'selected' => $coupon->id == $shop_management->shop_coupons ? true : false,
            ];
        }

        // 模組選項
        $mode_select = [];
        $management_mode = ShopManagementMode::where('shop_id',$shop_info->id)->where('type','once')->get();
        foreach( $management_mode as $mode ){
            $mode_select[] = [
                'id'       => $mode->id,
                'name'     => $mode->name,
                'selected' => $mode->id == $shop_management->shop_management_mode_id ? true : false,
            ]; 
        }

        // 符合條件會員
        $shop_customer_ids = $shop_management->customer_lists->where('add','N')->pluck('shop_customer_id')->toArray();
        $shop_customers    = ShopCustomer::whereIn('id',$shop_customer_ids)->get();
        $customer_lists    = [];
        foreach( $shop_customers as $customer ){
            $customer_lists[] = [
                'id'       => $customer->id,
                'name'     => $customer->customer_info->realname,
                'phone'    => $customer->customer_info->phone,
                'line'     => '-',
                'selected' => true,
            ];
        }

        // 額外增加名單
        $add_shop_customer_ids = $shop_management->customer_lists->where('add','Y')->pluck('shop_customer_id')->toArray();
        $shop_customers    = ShopCustomer::whereIn('id',$add_shop_customer_ids)->get();
        $add_customer_lists    = [];
        foreach( $shop_customers as $customer ){
            $add_customer_lists[] = [
                'id'       => $customer->id,
                'name'     => $customer->customer_info->realname,
                'phone'    => $customer->customer_info->phone,
                'line'     => '-',
                'selected' => true,
            ];
        }

        $data = [
        	// 篩選條件
            'id'                              => $shop_management->id,
            'name'                            => $shop_management->name,
            'name_permission'                 => in_array('shop_once_management_'.$type.'_name',$user_shop_permission['permission']) ? true : false,

            // 推廣期間
            // 'during_type'                     => $shop_management->during_type,
            // 'during_type_permission'          => in_array('shop_once_management_'.$type.'_during_type',$user_shop_permission['permission']) ? true : false,
            // 'start_date'                      => $shop_management->start_date,
            // 'start_date_permission'           => in_array('shop_once_management_'.$type.'_start_date',$user_shop_permission['permission']) ? true : false,
            // 'end_date'                        => $shop_management->end_date,
            // 'end_date_permission'             => in_array('shop_once_management_'.$type.'_end_date',$user_shop_permission['permission']) ? true : false,

            // 符合條件會員列表
            'customer_lists'                  => $customer_lists,
            'add_customer_lists'              => $add_customer_lists,
            'customer_list_add_permission'    => in_array('shop_once_management_'.$type.'_customer_list_add',$user_shop_permission['permission']) ? true : false,
            // 'customer_list_delete_permission' => in_array('shop_once_management_'.$type.'_customer_list_delete',$user_shop_permission['permission']) ? true : false,

            // 發送訊息
            'message'                         => $shop_management->message,
            'message_permission'              => in_array('shop_once_management_'.$type.'_message',$user_shop_permission['permission']) ? true : false,
            'link'                            => $shop_management->link,
            'link_permission'                 => in_array('shop_once_management_'.$type.'_link',$user_shop_permission['permission']) ? true : false,
            'shop_coupons'                    => $shop_management->shop_coupons,
            'shop_coupons_permission'         => in_array('shop_once_management_'.$type.'_shop_coupons',$user_shop_permission['permission']) ? true : false,
            'send_datetime'                   => date('c',strtotime($shop_management->send_datetime?:date('Y-m-d H:i:s'))),
            'send_datetime_permission'        => in_array('shop_once_management_'.$type.'_send_datetime',$user_shop_permission['permission']) ? true : false,
            'send_type'                       => $shop_management->send_type?:2,
            'send_type_permission'            => in_array('shop_once_management_'.$type.'_send_type',$user_shop_permission['permission']) ? true : false,
            'mode'                            => $shop_management->shop_management_mode_id,
            'mode_permission'                 => in_array('shop_once_management_'.$type.'_mode',$user_shop_permission['permission']) ? true : false,
        ];

        $res = [
        	'status'          => true,
        	'permission'      => true,
            'customer_select' => $customer_select,
            'mode_select'     => $mode_select,
            'coupon_select'   => $coupon_select,
        	'data'            => $data,
        ];

        return response()->json($res);
    }

    // 儲存商家單次推廣資料
    public function shop_once_management_save($shop_id,$management_id="")
    {
        if( $management_id ){
            // 編輯
            $shop_management = ShopManagement::find($management_id);
            if( !$shop_management ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣資料']]]);
            }
        }else{
            // 新增
            $shop_management = new ShopManagement;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_management->shop_id                 = $shop_info->id;
        $shop_management->type                    = 'once';
        $shop_management->shop_management_mode_id = request('mode');
        $shop_management->name                    = request('name');
        $shop_management->during_type             = request('during_type');
        $shop_management->start_date              = date('Y-m-d',strtotime(request('start_date')));
        $shop_management->end_date                = date('Y-m-d',strtotime(request('end_date')));
        $shop_management->link                    = request('link');
        $shop_management->message                 = request('message');
        $shop_management->send_datetime           = date('Y-m-d H:i:s',strtotime(request('send_datetime')));
        $shop_management->send_type               = request('send_type');

        // 優惠券
        $shop_management->shop_coupons = request('shop_coupons');
        $shop_management->save();

        // 篩選顧客名單
        $id_arr = [];
        foreach( request('customer_lists') as $customer ) {
            if( $shop_management->customer_lists->where('shop_customer_id',$customer['id'])->count() == 0 ){
                $model = new ShopManagementCustomerList;
                $model->shop_id            = $shop_id;
                $model->shop_management_id = $shop_management->id;
                $model->shop_customer_id   = $customer['id'];
                $model->phone              = $customer['phone'];
                $model->type               = $shop_management->send_type;
                $model->datetime           = $shop_management->send_datetime;
                $model->message            = $shop_management->message;
                $model->save();
            }
            $id_arr[] = $customer['id'];
        }
        // 額外增加顧客名單
        foreach( request('add_customer_lists') as $customer ) {
            if( $shop_management->customer_lists->where('shop_customer_id',$customer['id'])->count() == 0 ){
                $model = new ShopManagementCustomerList;
                $model->shop_id            = $shop_id;
                $model->shop_management_id = $shop_management->id;
                $model->shop_customer_id   = $customer['id'];
                $model->phone              = $customer['phone'];
                $model->type               = $shop_management->send_type;
                $model->datetime           = $shop_management->send_datetime;
                $model->message            = $shop_management->message;
                $model->add                = 'Y';
                $model->save();
            }
            $id_arr[] = $customer['id'];
        }

        // 刪除不再名單內的名單人員
        ShopManagementCustomerList::where('shop_management_id',$shop_management->id)->whereNotIn('shop_customer_id',$id_arr)->delete();
        
        $shop_management->customer_lists     = ShopManagementCustomerList::where('shop_management_id',$shop_management->id)->where('add','N')->get();
        $shop_management->add_customer_lists = ShopManagementCustomerList::where('shop_management_id',$shop_management->id)->where('add','Y')->get();

        $shop_management = ShopManagement::where('id',$shop_management->id)->with('customer_lists')->first();

        return response()->json(['status'=>true,'data'=>$shop_management]);
    }

    // 刪除商家單次推廣資料 
    public function shop_once_management_delete($shop_id,$management_id)
    {
        $shop_management = ShopManagement::find($management_id);
        if( !$shop_management ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣資料']]]);
        }

        $shop_management->delete();

        return response()->json(['status'=>true]);
    }

    // 單次推廣發送清單
    public function shop_once_management_send_log($shop_id,$management_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('shop_once_management_send_log',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_management = ShopManagement::find($management_id);
        if( !$shop_management ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣資料']]]);
        }

        $logs = ShopManagementCustomerList::where('shop_management_id',$management_id)->get();
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

        $send_type = '手機簡訊與LINE都發送';
        if( $shop_management->send_type == 2 ){
            $send_type = '僅手機簡訊';
        }elseif( $shop_management->send_type == 3 ){
            $send_type = '僅LINE';
        }elseif( $shop_management->send_type == 4 ){
            $send_type = '以LINE優先，無LINE者發送手機簡訊';
        }

        $management_info = [
            'id'        => $management_id,
            'name'      => $shop_management->name,
            'send_type' => $send_type,
            'date'      => substr($shop_management->send_datetime,0,16),

        ];

        $data = [
            'status'          => true,
            'permission'      => true,
            'resend_permission' => !in_array('shop_once_management_resend',$user_shop_permission['permission']) ? false : true,
            'refuse_permission' => !in_array('shop_once_management_refuse',$user_shop_permission['permission']) ? false : true,
            'management_info' => $management_info,
            'data'            => $send_logs,
        ];

        return response()->json($data);
    }

    // 利用模組篩選名單
    public function shop_once_management_customer_list($shop_id,$shop_management_mode_id)
    {
        $customer_lists = [];

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_management_mode = ShopManagementMode::find($shop_management_mode_id);
        if( !$shop_management_mode ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣模組資料']]]);
        }

        // 先判別關鍵字、性別
        $shop_customers = ShopCustomer::where('shop_id',$shop_info->id)->get();
        // $customers      = Customer::whereIn('id',$shop_customers->pluck('customer_id')->toArray())
        //                                  ->where('realname','like','%'.$shop_management_mode->keyword.'%')
        //                                  ->whereIn('sex',explode(',',$shop_management_mode->sex));
        $customers = Customer::whereIn('id',$shop_customers->pluck('customer_id')->toArray());

        // 有設定關鍵字
        if( $shop_management_mode->keyword ){
            $customers = $customers->where('realname','like','%'.$shop_management_mode->keyword.'%');
        }

        // 有設定性別
        if( $shop_management_mode->sex ){
            $customers = $customers ->whereIn('sex',explode(',',$shop_management_mode->sex));
        }

        // 有設定年齡
        $min_birthday_year = $max_birthday_year = 0;
        if( $shop_management_mode->min_age ){
            $min_birthday_year = date('Y') - $shop_management_mode->min_age;
        }
        if( $shop_management_mode->max_age ){
            $max_birthday_year = date('Y') - $shop_management_mode->max_age;
        }

        if( $min_birthday_year == 0 && $max_birthday_year != 0 ){
            $customers = $customers->whereBetween('birthday',[ $max_birthday_year.'-01-01' , date('Y-m-d') ])->get();
        }elseif( $min_birthday_year != 0 && $max_birthday_year == 0 ){
            $customers = $customers->whereBetween('birthday',[  '1911-01-01' , $min_birthday_year.'-12-31' ])->get();
        }elseif( $min_birthday_year != 0 && $max_birthday_year != 0 ){
            $customers = $customers->whereBetween('birthday',[ $max_birthday_year.'-01-01' , $min_birthday_year.'-12-31' ])->get();
        }else{
            $customers = $customers->get();
        }

        if( $shop_management_mode->birthday_or_constellation == 1 ){
            // 篩選生日月份
            if( $shop_management_mode->birthday_month ){
                $birthday_month_arr = explode(',',$shop_management_mode->birthday_month);
                foreach( $customers as $customer ){
                    if( $customer->birthday && in_array(date('n',strtotime($customer->birthday)),$birthday_month_arr) ){
                        $check_in = false;
                        foreach( $customer_lists as $cl ){
                            if( $cl->id == $customer->id ){
                                $check_in = true;
                            }
                        }
                        if( $check_in == false ) $customer_lists[] =  $customer;
                    }
                }
            }
        }else{
            // 篩選星座
            if( $shop_management_mode->constellation ){
                $constellation_arr = explode(',',$shop_management_mode->constellation);
                foreach( $customers as $customer ){
                    if( $customer->birthday 
                        && in_array(ShopCustomerController::constellation($customer->birthday),$constellation_arr) ){
                        $check_in = false;
                        foreach( $customer_lists as $cl ){
                            if( $cl->id == $customer->id ){
                                $check_in = true;
                            }
                        }
                        if( $check_in == false ) $customer_lists[] =  $customer;
                    }
                }
            }
        }

        if( !$shop_management_mode->birthday_month && !$shop_management_mode->constellation ){
            $customer_lists = $customers;
        }

        // 先將符合條件的顧客id取出
        $customer_id_arr = [];
        foreach( $customer_lists as $cl ){
            $customer_id_arr[] = $cl->id; 
        }

        // 有在消費區間消費的會員(預約)
        $model = CustomerReservation::where('shop_id',$shop_id)->whereIn('customer_id',$customer_id_arr)->where('status','Y');
        if( $shop_management_mode->start_date ){
            $model = $model->where('start','>=',$shop_management_mode->start_date.' 00:00:00');
        }
        if( $shop_management_mode->end_date ){
            $model = $model->where('start','<=',$shop_management_mode->end_date.' 23:59:59');
        }

        // plus(預約且有出席區間)
        $reservation_customer_id_arr = $model->whereIn('tag',[1,3,4,5])->groupBy('customer_id')->pluck('customer_id')->toArray();
        $customer_lists = Customer::whereIn('id',$reservation_customer_id_arr)->get();

        // pro版本(消費金額)
        // if( $shop_management_mode->consumption == 1 ){
        //     // 期間內有消費的會員
        //     $reservation_customers = $model->get();

        //     // 選擇服務項目
        //     if( $shop_management_mode->shop_services ){
        //         $shop_management_mode_services = explode(',',$shop_management_mode->shop_services);
        //         $reservation_customers = $reservation_customers->whereIn('shop_service_id',$shop_management_mode_services);
        //     }

        //     // 選擇服務人員
        //     if( $shop_management_mode->shop_staffs ){
        //         $shop_management_mode_staffs = explode(',',$shop_management_mode->shop_staffs);
        //         $reservation_customers = $reservation_customers->whereIn('shop_staff_id',$shop_management_mode_staffs);
        //     }

        //     // 消費金額
        //     $tmp_customer_id_arr = [];
        //     foreach( $reservation_customers as $rc ){
        //         if( $shop_management_mode->min_price && $shop_management_mode->max_price ){
        //             if( $rc->service_info->price >= $shop_management_mode->min_price && $rc->service_info->price <= $shop_management_mode->max_price ){
        //                 if( !in_array($rc->customer_id,$tmp_customer_id_arr) ) $tmp_customer_id_arr[] = $rc->customer_id;
        //             }
        //         }elseif( $shop_management_mode->min_price && !$shop_management_mode->max_price ){
        //             if( $rc->service_info->price >= $shop_management_mode->min_price ){
        //                 if( !in_array($rc->customer_id,$tmp_customer_id_arr) ) $tmp_customer_id_arr[] = $rc->customer_id;
        //             }
        //         }elseif( !$shop_management_mode->min_price && $shop_management_mode->max_price ){
        //             if( $rc->service_info->price <= $shop_management_mode->max_price ){
        //                 if( !in_array($rc->customer_id,$tmp_customer_id_arr) ) $tmp_customer_id_arr[] = $rc->customer_id;
        //             }
        //         }else{
        //             if( !in_array($rc->customer_id,$tmp_customer_id_arr) ) $tmp_customer_id_arr[] = $rc->customer_id;
        //         }
        //     }            

        //     $consumption_customer = ShopCustomer::where('shop_id',$shop_info->id)->whereIn('customer_id',$tmp_customer_id_arr)->pluck('customer_id')->toArray();
        //     $customer_lists = Customer::whereIn('id',$consumption_customer)->get();
        // }else{
        //     // 期間內無消費的會員
        //     $reservation_customer_id_arr = $model->groupBy('customer_id')->pluck('customer_id')->toArray();
        //     $no_consumption_customer = array_diff($customer_id_arr, $reservation_customer_id_arr);
        //     $customer_lists = Customer::whereIn('id',$no_consumption_customer)->get();
        // } 

        // 等級（待補）

        // 標籤（待補）

        // 轉換id成shop_customer_id
        $customer_list_data = [];
        $refuse_customer = ShopManagementRefuse::pluck('shop_customer_id')->toArray();
        foreach( $customer_lists  as $cl ){
            $shop_customer_id = ShopCustomer::where('shop_id',$shop_info->id)->where('customer_id',$cl->id)->value('id');
            if( !in_array( $shop_customer_id , $refuse_customer ) ){
                $customer_list_data[] = [
                    'id'       => $shop_customer_id,
                    'name'     => $cl->realname,
                    'phone'    => $cl->phone,
                    'line'     => '-',
                ];
                $refuse_customer[] = $shop_customer_id; 
            }
        }  

        // 可以增加的會員名單
        $add_customer_select = ShopCustomer::whereNotIn('id',$refuse_customer)->get();
        $customer_select = [];
        foreach( $add_customer_select as $acs ){
            $customer_select[] = [
                'id'       => $acs->id,
                'name'     => $acs->customer_info->realname,
                'phone'    => $acs->customer_info->phone,
                'line'     => '-',
            ];
        }                        

        return response()->json([ 'status' => true , 'customer_select' => $customer_select ,'data' => $customer_list_data ]);
    }

    // 單次推廣模組列表
    public function shop_once_management_mode_lists($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('once_management_mode_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $management_modes = ShopManagementMode::where('shop_id',$shop_id)->where('type','once')->orderBy('id','DESC')->get();
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
            'once_management_mode_create_permission' => in_array('once_management_mode_create_btn',$user_shop_permission['permission']) ? true : false, 
            'once_management_mode_edit_permission'   => in_array('once_management_mode_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'once_management_mode_delete_permission' => in_array('once_management_mode_delete_btn',$user_shop_permission['permission']) ? true : false, 
            'data'                                   => $data,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家單次推廣模組資料
    public function shop_once_management_mode_info($shop_id,$management_mode_id="")
    {
        if( $management_mode_id ){
            $shop_management_mode = ShopManagementMode::find($management_mode_id);
            if( !$shop_management_mode ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣模組資料']]]);
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
        if( !in_array('once_management_mode_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 服務選項
        $shop_service_categories = ShopServiceCategory::where('shop_id',$shop_id)->select('id','name')->get();
        foreach( $shop_service_categories as $service_category ){
            $service_category->shop_services = ShopService::where('shop_service_category_id',$service_category->id)->select('id','name')->get();
            $shop_management_mode_services = explode(',',$shop_management_mode->shop_services);
            foreach( $service_category->shop_services as $service ){
                if( in_array( $service->id , $shop_management_mode_services) ){
                    $service->selected = true;
                }else{
                    $service->selected = false;
                }
            }
        }

        // 服務人員選項
        $shop_staffs = ShopStaff::where('shop_id',$shop_id)
                                        ->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')
                                        ->where('fire_time',NULL)
                                        ->get();
        $staff_select = [];
        $selected_staff = [];
        foreach( $shop_staffs as $staff ){
            $staff_select[] = [
                'id'       => $staff->id,
                'name'     => $staff->name,
                // 'selected' => in_array($staff->id,explode(',', $shop_management_mode->shop_staffs)) ? true : false,
            ];

            if( in_array($staff->id,explode(',', $shop_management_mode->shop_staffs)) ){
                $selected_staff[] = [
                    'id'   => $staff->id,
                    'name' => $staff->name,
                ];
            }
        }

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

        $data = [
            // 篩選條件
            'id'                                   => $shop_management_mode->id,
            'name'                                 => $shop_management_mode->name,
            'name_permission'                      => in_array('once_management_mode_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
            'keyword'                              => $shop_management_mode->keyword,
            'keyword_permission'                   => in_array('once_management_mode_'.$type.'_keyword',$user_shop_permission['permission']) ? true : false,
            'start_date'                           => $shop_management_mode->start_date,
            'start_date_permission'                => in_array('once_management_mode_'.$type.'_date',$user_shop_permission['permission']) ? true : false,
            'end_date'                             => $shop_management_mode->end_date,
            'end_date_permission'                  => in_array('once_management_mode_'.$type.'_date',$user_shop_permission['permission']) ? true : false,
            'consumption'                          => $shop_management_mode->consumption?:0,
            'consumption_permission'               => in_array('once_management_mode_'.$type.'_consumption',$user_shop_permission['permission']) ? true : false,
            'min_price'                            => $shop_management_mode->min_price,
            'min_price_permission'                 => in_array('once_management_mode_'.$type.'_min_price',$user_shop_permission['permission']) ? true : false,
            'max_price'                            => $shop_management_mode->max_price,
            'max_price_permission'                 => in_array('once_management_mode_'.$type.'_max_price',$user_shop_permission['permission']) ? true : false,
            'shop_staffs'                          => $selected_staff,
            'shop_staffs_permission'               => in_array('once_management_mode_'.$type.'_shop_staffs',$user_shop_permission['permission']) ? true : false,
            'shop_services'                        => $shop_service_categories,
            'shop_services_permission'             => in_array('once_management_mode_'.$type.'_shop_services',$user_shop_permission['permission']) ? true : false,
            'sex'                                  => $sex,
            'sex_permission'                       => in_array('once_management_mode_'.$type.'_sex',$user_shop_permission['permission']) ? true : false,
            'min_age'                              => $shop_management_mode->min_age,
            'min_age_permission'                   => in_array('once_management_mode_'.$type.'_age',$user_shop_permission['permission']) ? true : false,
            'max_age'                              => $shop_management_mode->max_age,
            'max_price_permission'                 => in_array('once_management_mode_'.$type.'_age',$user_shop_permission['permission']) ? true : false,
            'birthday_or_constellation'            => $shop_management_mode->birthday_or_constellation,
            'birthday_or_constellation_permission' => in_array('once_management_mode_'.$type.'_birthday_or_constellation',$user_shop_permission['permission']) ? true : false,
            'birthday_month'                       => $birthday_month,
            'birthday_month_permission'            => in_array('once_management_mode_'.$type.'_birthday_or_constellation',$user_shop_permission['permission']) ? true : false,
            'constellation'                        => $constellation,
            'constellation_permission'             => in_array('once_management_mode_'.$type.'_birthday_or_constellation',$user_shop_permission['permission']) ? true : false,
            'customer_level'                       => [],
            'customer_level_permission'            => in_array('once_management_mode_'.$type.'_customer_level',$user_shop_permission['permission']) ? true : false,
            'customer_tags'                        => [],
            'customer_tags_permission'             => in_array('once_management_mode_'.$type.'_customer_tags',$user_shop_permission['permission']) ? true : false,
        ];

        $res = [
            'status'       => true,
            'permission'   => true,
            'staff_select' => $staff_select,
            'data'         => $data
        ];

        return response()->json($res);
    }

    // 儲存商家單次推廣模組資料
    public function shop_once_management_mode_save($shop_id,$management_mode_id="")
    {
        if( $management_mode_id ){
            // 編輯
            $shop_management_mode = ShopManagementMode::find($management_mode_id);
            if( !$shop_management_mode ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣模組資料']]]);
            }
        }else{
            // 新增
            $shop_management_mode = new ShopManagementMode;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 處理服務人員資料
        $selected_staff = [];
        foreach( request('shop_staffs') as $staff ){
            $selected_staff[] = $staff['id'];
        }

        // 處理服務項目資料
        $selected_service = [];
        foreach( request('shop_services') as $category => $service ){
            foreach( $service['shop_services'] as $se ){
                if( $se['selected'] ) $selected_service[] = $se['id'];
            }
        }

        // 處理性別資料
        $selected_sex = [];
        foreach( request('sex') as $sex ){
            if( $sex['selected'] ) $selected_sex[] = $sex['value'];
        }

        if( request('birthday_or_constellation') == 1 ){

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

        $shop_management_mode->shop_id                   = $shop_info->id;
        $shop_management_mode->type                      = 'once';
        $shop_management_mode->name                      = request('name');
        $shop_management_mode->keyword                   = request('keyword');
        $shop_management_mode->start_date                = request('start_date');
        $shop_management_mode->end_date                  = request('end_date');
        $shop_management_mode->consumption               = request('consumption');
        $shop_management_mode->min_price                 = request('min_price');
        $shop_management_mode->max_price                 = request('max_price');
        $shop_management_mode->shop_staffs               = implode(',',$selected_staff);
        $shop_management_mode->shop_services             = implode(',',$selected_service);
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
    
    // 刪除商家單次推廣模組資料
    public function shop_once_management_mode_delete($shop_id,$management_mode_id)
    {
        $shop_management_mode = ShopManagementMode::find($management_mode_id);
        if( !$shop_management_mode ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到單次推廣模組資料']]]);
        }

        if( ShopManagement::where('shop_id',$shop_id)->where('shop_management_mode_id',$management_mode_id)->get()->count() ){
            return response()->json(['status'=>false,'errors'=>['message'=>['此模組已被使用，無法進行刪除！']]]);
        }

        $shop_management_mode->delete();
        return response()->json(['status'=>true]);
    }

}
