<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Models\Company;
use App\Models\CompanyStaff;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopStaff;
use App\Models\ShopService;
use App\Models\ShopCustomer;
use App\Models\ShopSet;
use App\Models\ShopServiceCategory;
use App\Models\ShopReservationTag;
use App\Models\ShopReservationMessage;
use App\Models\CustomerReservation;
use App\Models\CustomerReservationAdvance;
use App\Jobs\InsertGoogleCalendarEvent;
use App\Jobs\SendManagementSms;
use App\Jobs\SendSms;
use App\Models\CompanyCustomer;
use App\Models\CustomerCoupon;
use App\Models\ShopAwardNotice;
use App\Models\ShopCoupon;
use App\Models\ShopManagementCustomerList;
use App\Models\User;

class ShopReservationController extends Controller
{
    // 取得商家指定月份預約行事曆資料
    public function shop_calendar($shop_id)
    {
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( PermissionController::is_staff($shop_id) ){
            // 員工身分
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if( $user_staff_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_staff_permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_calendar', $user_staff_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $shop_staff = Permission::where('user_id',auth()->getUser()->id)->where('shop_id', $shop_id)->first()->shop_staff_id;
            request()->offsetSet('staff', [$shop_staff]);
            request()->offsetSet('date', date('Y-m'));

            $data = json_decode(json_encode(Self::staff_calendar()));
            $data = $data->original;

            $shop_staff_info = ShopStaff::find($shop_staff);
            $staff_service_relations = ShopStaff::where('shop_id', $shop_id)
                                                ->join('company_staffs', 'company_staffs.id', '=', 'shop_staffs.company_staff_id')
                                                ->where('fire_time', NULL);
            // 可不可以看到全部預約
            if( $shop_staff_info->company_staff_info->show_all_reservation == 'N' ){
                $staff_service_relations = $staff_service_relations->where('shop_staffs.id', $shop_staff);
            }
            $staff_service_relations = $staff_service_relations->with('staff_services')->get();

            $data->staff_service_relations = $staff_service_relations;
            $data->create_permission = in_array('staff_reservation_create_btn',$user_staff_permission['permission']) ? true : false;
            $data->edit_permission = in_array('staff_reservation_edit_btn',$user_staff_permission['permission']) ? true : false;
            $data->permission      = true;

            return response()->json($data);
        }else{
            // 商家身分
            // 拿取使用者的商家權限
            $user_shop_permission = PermissionController::user_shop_permission($shop_id);
            if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

            // 確認頁面瀏覽權限
            if( !in_array('shop_calendar',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

            $calendar_data = [];

            $start_date = date(request('date').'-1');
            $end_date   = date(request('date').'-31');

            $start_date = date( 'Y-m-d' , strtotime($start_date .'-7 day') );
            $end_date   = date( 'Y-m-d' , strtotime($end_date .'+7 day') );

            $reservations = CustomerReservation::where('shop_id',$shop_id)
                                                  ->whereBetween('start',[$start_date,$end_date])
                                                  ->where('status','Y')
                                                  ->where('cancel_status',NULL)
                                                  ->with('customer_info','service_info','staff_info','check_user_info')
                                                  ->get();

            foreach( $reservations as $reservation ){
                if( !$reservation->customer_info ) continue;
                $calendar_data[] = [
                    'id'       => $reservation->id,
                    'title'    => $reservation->customer_info->realname.'-'.$reservation->service_info->name,
                    'color'    => $reservation->staff_info->calendar_color?:'#AC8CD5',
                    'start'    => date('Y-m-d H:i', strtotime($reservation->start)),
                    'end'      => date('Y-m-d H:i', strtotime($reservation->end)),
                    'advances' => $reservation->advances,
                    'tag'      => $reservation->tag,
                    'phone'    => $reservation->customer_info->phone,
                    'staff'    => $reservation->staff_info,
                ];
            }

            $staff_service_relations = ShopStaff::where('shop_id',$shop_id)
                                                    ->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')
                                                    ->where('fire_time',NULL)
                                                    ->with('staff_services')
                                                    ->get();

            $data = [
                'status'                    => true,
                'permission'                => true,
                'customer'                  => ShopCustomer::where('shop_id',$shop_id)->with('customer_info')->get(),
                'staff_service_relations'   => $staff_service_relations,
                'service_advance_relations' => ShopService::where('shop_id',$shop_id)->where('type','service')->with('service_advances')->get(),
                'create_permission'         => in_array('shop_reservation_create_btn',$user_shop_permission['permission']) ? true : false,
                'edit_permission'           => in_array('shop_reservation_edit_btn',$user_shop_permission['permission']) ? true : false,
                'data'                      => $calendar_data,
            ];

            return response()->json($data);     
        }                   
    }

    // 依據員工與時間拿取行事曆資料
    public function staff_calendar()
    {
        // 驗證欄位資料
        $rules = [
            'staff'   => 'required', 
            'date'    => 'required',
        ];

        $messages = [
            'staff.required' => '缺少員工資料',
            'date.required'  => '缺少日期資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_staffs = ShopStaff::whereIn('shop_staffs.id',request('staff'))->join('company_staffs','shop_staffs.company_staff_id','=','company_staffs.id')->get();

        $calendar_data = [];

        $start_date = date(request('date').'-1');
        $end_date   = date(request('date').'-31');

        $start_date = date( 'Y-m-d' , strtotime($start_date .'-7 day') );
        $end_date   = date( 'Y-m-d' , strtotime($end_date .'+7 day') );

        $reservations = CustomerReservation::whereIn('shop_staff_id',$shop_staffs->pluck('id')->toArray())
                                            //   ->where('start','like',date('Y-m',strtotime(request('date'))).'%')
                                              ->whereBetween('start',[$start_date,$end_date])
                                              ->where('status','Y')
                                              ->where('cancel_status',NULL)
                                              ->with('customer_info','service_info','staff_info','check_user_info')
                                              ->get();

        foreach( $reservations as $reservation ){
            if( !$reservation->customer_info ) continue;
            $calendar_data[] = [
                'id'       => $reservation->id,
                'title'    => $reservation->customer_info->realname.'-'.$reservation->service_info->name,
                'color'    => $reservation->staff_info->calendar_color?:'#AC8CD5',
                'start'    => date('Y-m-d H:i', strtotime($reservation->start)),
                'end'      => date('Y-m-d H:i', strtotime($reservation->end)),
                'advances' => $reservation->advances,
                'tag'      => $reservation->tag,
                'phone'    => $reservation->customer_info->phone,
                'staff'    => $reservation->staff_info,
            ];
        }

        $shop_id = '';
        foreach( $shop_staffs as $staff ){
            $shop_id = $staff->shop_id;
            if( $staff->calendar_token != NULL ) {
                $client = new \Google_Client();
                // 設定憑證 (前面下載的 json 檔)
                $client->setAuthConfig(base_path('config/').'google_client_secret.json');
                // 回傳後要記住AccessToken，之後利用這個就可以一直查詢
                $client->refreshToken($staff->calendar_token);

                $service = new \Google_Service_Calendar($client);

                // 讀取未來日曆上的事件
                $calendarId = 'primary';
                $optParams = array(
                  'maxResults'   => 1000,
                  'orderBy'      => 'startTime',
                  'singleEvents' => true,
                );
                $optParams['timeMin'] = date('c',strtotime(request('date').' 00:00:00'));
                $optParams['timeMax'] = date('c',strtotime(request('date').'-'.cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'))." 23:59:59"));

                try {
                    $results = $service->events->listEvents('primary', $optParams);
                } catch (\Google_Service_Exception  $e) {
                    $res = json_decode($e->getMessage());
                    continue;
                }

                $events = $results->getItems();

                foreach ($events as $event) {
                    // 檢查google calendar的事件是否是預約事件
                    $check = false;
                    foreach( $reservations as $reservation ){
                        if( $reservation->google_calendar_id == $event->id ){
                            $check = true;
                            break;
                        }
                    }

                    // 只寫入私人行事曆事件
                    if( !$check ){
                        $calendar_data[] = [
                            'id'       => '',
                            'title'    => $staff->name.'-忙碌',
                            'color'    => '#EEEEEE',
                            'start'    => $event->start->dateTime ? date('Y-m-d H:i', strtotime($event->start->dateTime)) : date('Y-m-d 00:00', strtotime($event->start->date)),
                            'end'      => $event->end->dateTime ? date('Y-m-d H:i', strtotime($event->end->dateTime)) : date('Y-m-d 24:00', strtotime($event->end->date)),
                            'advances' => '',
                            'tag'      => '',
                            'phone'    => '',
                            'staff'    => $staff,
                        ];
                    }
                }
            }
        }

        $data = [
            'status'                    => true,
            'permission'                => true,
            'customer'                  => ShopCustomer::where('shop_id',$shop_id)->with('customer_info')->get(),
            'staff_service_relations'   => ShopStaff::where('shop_id',$shop_id)->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')->with('staff_services')->get(),
            'service_advance_relations' => ShopService::where('shop_id',$shop_id)->where('type','service')->with('service_advances')->get(),
            'data'                      => $calendar_data,
        ];

        return response()->json($data);
    }

    // 取得商家預約資料
    public function shop_reservations($shop_id)
    {
        if( PermissionController::is_staff($shop_id) ){
            // 員工身分
            $shop_staff = Permission::where('user_id',auth()->getUser()->id)->where('shop_id',$shop_id)->first()->shop_staff_id;

            $permission = PermissionController::user_staff_permission($shop_id);
            if( $permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$permission['errors']]]]);
            $pm_text = 'staff';

            // 確認頁面瀏覽權限
            if (!in_array('staff_reservations', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        }else{
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if( $permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if( !in_array('shop_reservations',$permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);
            $pm_text = 'shop';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $reservations = CustomerReservation::where('shop_id',$shop_id);
        if( isset($shop_staff) ){
            $delete_permission = true;
            $edit_permission   = true;
            $batch_permission  = true;
            $check_permission  = true;
            $cancel_permission = true;

            // 是員工的話就要判斷是否可以看到其他人的預約
            $shop_staff_info = ShopStaff::find($shop_staff);
            // 可不可以看到全部預約
            if ($shop_staff_info->company_staff_info->show_all_reservation == 'N') {
                $reservations = $reservations->where('shop_staff_id',$shop_staff);
            } else {
                if ($shop_staff_info->company_staff_info->edit_all_reservation == 'N') {
                    $delete_permission = false;
                    $edit_permission   = false;
                    $batch_permission  = false;
                    $check_permission  = false;
                    $cancel_permission = false;
                }
            }

        } else{
            $delete_permission = in_array($pm_text.'_reservation_delete',$permission['permission']) ? true : false;
            $edit_permission   = in_array($pm_text.'_reservation_edit',$permission['permission']) ? true : false;
            $batch_permission  = in_array($pm_text.'_reservation_batch',$permission['permission']) ? true : false;
            $check_permission  = in_array($pm_text.'_reservation_check',$permission['permission']) ? true : false;
            $cancel_permission = in_array($pm_text.'_reservation_cancel',$permission['permission']) ? true : false;
        }

        $reservations = $reservations->orderBy('id','DESC')->with('customer_info','service_info','staff_info','check_user_info')->get();
        $data = [
        	'wait'    => [],
        	'success' => [],
        	'failed'  => [],
        ];
        foreach( $reservations as $reservation ){
            if (!$reservation->customer_info) continue;

        	if( $reservation->status == 'N' && $reservation->cancel_status == NULL ){
        		// 待審核
        		$type = 'wait';
        	}elseif( $reservation->status == 'Y' && $reservation->cancel_status == NULL ){
        		// 已通過
        		$type = 'success';
        	}elseif( in_array($reservation->cancel_status,['C','A']) ){
        		// 未通過(商家取消或是時間到自動取消)
        		$type = 'failed';
        	}else{
        		continue;
        	}

            // 審核人員與審核時間
            $check_time = $reservation->check_time;
            $check_user = $reservation->check_user ? $reservation->check_user_info->name : NULL;
            $days       = 0;
            if( $type != 'wait' ){
                if( $check_time == NULL ) $check_time = $reservation->updated_at;
                if( $check_user == NUll && $type == 'success' ){
                    $check_user = $company_info->name;
                }elseif( $check_user == NUll && $type == 'failed' ){
                    $check_user = '系統自動拒絕';
                } 
            }else{
                $startdate = strtotime(date('Y-m-d'));
                $enddate   = strtotime(date('Y-m-d',strtotime($reservation->start)));
                $days      = round(($enddate-$startdate)/3600/24) ;
            }

            if( $reservation->customer_info ){
                $phone = substr($reservation->customer_info->phone, 0, 4)
                    . '-' . substr($reservation->customer_info->phone, 4, 3)
                    . '-' . substr($reservation->customer_info->phone, 7, 3);
            }else{
                $phone = '';
            }
           
        	$data[$type][] = [
    			'id'                => $reservation->id,
    			'customer_name'     => $reservation->customer_info->realname,
    			'phone'             => $phone,
    			'service'           => $reservation->service_info->name,
    			'staff'             => $reservation->staff_info->name,
    			'time'              => substr($reservation->start,0,16),
                'auto_cancel'       => $days,
    			'check_time'        => $check_time ? date('Y-m-d H:i',strtotime($check_time)) : $check_time,
    			'check_user'        => $check_user,
                'delete_permission' => $delete_permission,
                'edit_permission'   => $edit_permission,
    		];
        } 

        $res = [
            'status'            => true,
            'permission'        => true,
            'data'              => $data,
            'batch_permission'  => $batch_permission,
            'check_permission'  => $check_permission,
            'cancel_permission' => $cancel_permission,
        ];
        return response()->json($res);     
    }

    // 新增/編輯預約資料
    public function shop_reservation_info($shop_id,$customer_reservation_id="")
    {
    	if( $customer_reservation_id ){
            $reservation_info = CustomerReservation::find($customer_reservation_id);
            if( !$reservation_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到預約資料']]]);
            }
            $type = 'edit';
        }else{
            $reservation_info = new CustomerReservation;
            $type             = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        // $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        // if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('reservation_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $reservation = [
            'id'        => $reservation_info->id,
            'staff'     => $reservation_info->shop_staff_id,
            'service'   => $reservation_info->shop_service_id,
            'advances'  => $reservation_info->advances->pluck('id')->toArray(),
            'customer'  => ShopCustomer::where('shop_id',$shop_id)->where('customer_id',$reservation_info->customer_id)->value('id'),
            'date'      => $reservation_info->start ? substr($reservation_info->start,0,10) : NULL,
            'date_time' => $reservation_info->start ? substr($reservation_info->start,11,5) : NULL,
            'check'     => 'Y',
        ];

        if (PermissionController::is_staff($shop_id)) {
            $shop_staff = Permission::where('user_id', auth()->getUser()->id)->where('shop_id', $shop_id)->first()->shop_staff_id;
            $shop_staff_info = ShopStaff::find($shop_staff);
            $staff_service_relations = ShopStaff::where('shop_id', $shop_id)
                                                        ->join('company_staffs', 'company_staffs.id', '=', 'shop_staffs.company_staff_id')
                                                        ->where('fire_time', NULL);
            // 可不可以幫其他員工預約
            if ($shop_staff_info->company_staff_info->edit_all_reservation == 'N') {
                $staff_service_relations = $staff_service_relations->where('shop_staffs.id', $shop_staff);

                // 判斷是否可以修改別人的預約
            }
            $staff_service_relations = $staff_service_relations->with('staff_services')->get();
        }else{
            $staff_service_relations = ShopStaff::where('shop_id', $shop_id)
                                                    ->join('company_staffs', 'company_staffs.id', '=', 'shop_staffs.company_staff_id')
                                                    ->where('fire_time', NULL)
                                                    ->with('staff_services')->get();
        }

        $service_advance_relations = ShopService::where('shop_id',$shop_id)->where('type','service')->with('service_advances')->get();
        $customers                 = ShopCustomer::select('shop_customers.id','customers.realname')->where('shop_id',$shop_id)->join('customers','customers.id','=','shop_customers.customer_id')->get()->toArray();
        array_unshift($customers,[ 'id' => '' , 'realname' => '非會員' ]);

        $data = [
            'status'                    => true,
            'permission'                => false,
            'staff_service_relations'   => $staff_service_relations,
            'service_advance_relations' => $service_advance_relations,
            'customers'                 => $customers,
            'data'                      => $reservation,       
        ];

		return response()->json($data);
    }

    // 儲存商家預約資料
    public function shop_reservation_save($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'staff'     => 'required', 
            'service'   => 'required', 
            'date'      => 'required',
            'date_time' => 'required',
            'check'     => 'required'
        ];

        if( !request('customer') ){
            $rules['phone'] = 'required';
        }

        $messages = [
            'staff.required'    => '缺少員工資料',
            'date.required'     => '缺少日期資料',
            'date_time.required'=> '缺少時間',
            'service.required'  => '缺少服務資料',
            'check.required'    => '缺少check資料',
            'phone'             => '請填寫會員手機號碼'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            foreach( $validator->getMessageBag()->toArray() as $msg_arr ){
                $msg = $msg_arr[0];
                break;
            }
            return response()->json(['status' => false,'errors' => $msg]); 
        }

        // 儲存預約資料
        $shop_info       = Shop::find($shop_id);
        $company_info    = $shop_info->company_info;
        $shop_service    = $shop_info->shop_services->where('id',request('service'))->first();
        $shop_advances   = $shop_info->shop_advances->whereIn('id',request('advances'));
        $shop_staff      = $shop_info->shop_staffs->where('id',request('staff'))->first();
        $message_type    = request('id') && request('id') != '' ? 'change' : 'check';
        $reservation_msg = ShopReservationMessage::where('shop_id',$shop_id)->where('type',$message_type)->first();
        $set             = ShopSet::where('shop_id',$shop_id)->first();
        $request_date    = request('date') . ' ' . request('date_time');

        // 計算此次預約所需要的時間
        $need_time = $shop_service->service_time+$shop_service->lead_time+$shop_service->buffer_time;
        foreach( $shop_advances as $advance ){
            $need_time += $advance->service_time+$advance->buffer_time;
        }

        // 檢查是否可預約
        if( request('check') == 'Y' ){
            // 處理若是非會員進行預約
            if (!request('customer')) {
                $customer_name = '親愛的會員';
            } else {
                // 確認字數長度
                $customer_name = ShopCustomer::where('shop_customers.id', request('customer'))
                                            ->join('customers', 'customers.id', '=', 'shop_customers.customer_id')
                                            ->first()
                                            ->realname;
            }

            $store_name    = $shop_info->name;
            $serviceName   = ShopService::where('id', request('service'))->first()->name;
            $staffName     = ShopStaff::where('shop_staffs.id', request('staff'))->join('company_staffs', 'company_staffs.id', '=', 'shop_staffs.company_staff_id')->first()->name;
            if (mb_strwidth($customer_name) >= 8  || (!preg_match("/^([A-Za-z]+)$/", $customer_name))) $customer_name = Controller::cut_str($customer_name, 0, 8);
            if (mb_strwidth($store_name)    >= 24 || (!preg_match("/^([A-Za-z]+)$/", $store_name)))    $store_name    = Controller::cut_str($store_name, 0, 24);
            if (mb_strwidth($serviceName)   >= 20 || (!preg_match("/^([A-Za-z]+)$/", $serviceName)))   $serviceName   = Controller::cut_str($serviceName, 0, 20);
            if (mb_strwidth($staffName)     >= 16 || (!preg_match("/^([A-Za-z]+)$/", $staffName)))     $staffName     = Controller::cut_str($staffName, 0, 16);

            // 處理需替換的文字
            $sendword = $reservation_msg->content;

            $sendword = str_replace('「"商家名稱"」', $store_name, $sendword);
            $sendword = str_replace('「"服務名稱"」', $serviceName, $sendword);
            $sendword = str_replace('「"會員名稱"」', $customer_name, $sendword);
            $sendword = str_replace('「"服務日期"」', request('date'), $sendword);
            $sendword = str_replace('「"預約日期時間"」', request('date_time'), $sendword);

            // 訂單連結
            $url = '/store/' . $shop_info->alias . '/member/reservation';
            $transform_url_code = Controller::get_transform_url_code($url);
            $sendword = str_replace('「"訂單連結"」', env('SHILIPAI_WEB') . '/T/' . $transform_url_code, $sendword);

            // 再次預約連結
            $sendword = str_replace('「"再次預約連結"」', '', $sendword);

            if( request('id') && request('id') != '' ){
                // 編輯
                $reservation_info = CustomerReservation::find(request('id'));
                if( !$reservation_info ){
                    response()->json(['status'=>false,'errors'=>['message'=>['找不到預約資料']]]);
                } else {
                    // 判斷時間是否有更換
                    if (request('date') . ' ' . request('date_time').':00' == $reservation_info->start ) $message_type = 'check';
                    else $message_type = 'change';
                    // 處理需替換的文字
                    $sendword = ShopReservationMessage::where('shop_id',$shop_id)->where('type',$message_type)->value('content');

                    $sendword = str_replace('「"商家名稱"」', $store_name, $sendword);
                    $sendword = str_replace('「"服務名稱"」', $serviceName, $sendword);
                    $sendword = str_replace('「"會員名稱"」', $customer_name, $sendword);
                    $sendword = str_replace('「"服務日期"」', request('date'), $sendword);
                    $sendword = str_replace('「"預約日期時間"」', request('date_time'), $sendword);

                    // 訂單連結
                    $url = '/store/' . $shop_info->alias . '/member/reservation';
                    $transform_url_code = Controller::get_transform_url_code($url);
                    $sendword = str_replace('「"訂單連結"」', env('SHILIPAI_WEB') . '/T/' . $transform_url_code, $sendword);

                    // 再次預約連結
                    $sendword = str_replace('「"再次預約連結"」', '', $sendword);
                }

                if( $request_date . ':00' == $reservation_info->start ){
                    $data = [
                        'status'       => true,
                        'message'      => '確定要送出嗎？',
                        'color'        => false,
                        'send_message' => 'N',
                        'content'      => $sendword,
                        'date_time'    => request('date_time'),
                        'send_action'  => true,// 是否詢問要送簡訊
                    ];
                    return response()->json($data);
                }
            }
        
            $get_reservation_time = ReservationController::get_reservation_time('check');
            $end_time = date('H:i',strtotime($request_date.' +'.$need_time.' minute'));
            $check = 0;
            for( $i = strtotime(request('date_time')) ; $i < strtotime($end_time) ; $i = $i + 60 * 30 ) {
                foreach( $get_reservation_time as $grt ){
                    // 先比對是否有相同的時間
                    if( $grt['time'] == date("H:i", $i) ){
                        if( $set->reservation_repeat_time_type == 1 ){
                            // 可重疊預約，需檢查次數
                            if( $set->reservation_repeat_time <= $grt['use'] ){
                                $check = 1; // 超過重疊次數
                            }else{
                                $check = 2; // 已有重疊，但還是可以預約
                            }
                        }elseif( $set->reservation_repeat_time_type == 0 && $grt['use'] != 0 ){
                            $check = 3; // 不可重疊，且預約的時間內已經有其他筆預約
                        }

                        if( $check != 0 ) break;
                    }
                }
                if( $check != 0 ) break;
            }  

            if( $check == 0 || $check == 2 ){
                if( $check == 0 ){
                    $data = [
                        'status'       => true,
                        'message'      => '確定要送出嗎？',
                        'color'        => false,
                        'send_message' => 'N',
                        'content'      => $sendword,
                        'send_action'  => true,//strtotime(date('Y-m-d H:i:s')) <= strtotime(request('date') . ' ' . request('date_time') ) ? true : false,
                        'date_time'    => request('date_time')
                    ];
                }elseif( $check == 2 ){
                    $data = [
                        'status'       => true,
                        'message'      => '提醒！本時段也有其他預約喔，確定要送出嗎？',
                        'color'        => true,
                        'send_message' => 'N',
                        'content'      => $sendword,
                        'send_action'  =>
                        true,//strtotime(date('Y-m-d H:i:s')) <= strtotime(request('date') . ' ' . request('date_time') ) ? true : false,
                        'date_time'    => request('date_time')
                    ];
                }
                return response()->json($data);
            }else{
                if( $check == 1 ){
                    // 超過重疊次數
                    $data = [
                        'status'       => true,
                        'message'      => '提醒！本時段已超過可以預約的次數，確定要送出嗎？',
                        'color'        => true,
                        'send_message' => 'N',
                        'content'      => $sendword,
                        'send_action'  => true,//strtotime(date('Y-m-d H:i:s')) <= strtotime(request('date') . ' ' . request('date_time') ) ? true : false,
                        'date_time'    => request('date_time')
                    ];
                    
                }elseif( $check == 3 ){
                    // 不能重疊預約，卻重疊了
                    $data = [
                        'status'       => true,
                        'message'      => '提醒！預約時間有重覆，確定要送出嗎？',
                        'color'        => true,
                        'send_message' => 'N',
                        'content'      => $sendword,
                        'send_action'  => true,//strtotime(date('Y-m-d H:i:s')) <= strtotime(request('date') . ' ' . request('date_time') ) ? true : false,
                        'date_time'    => request('date_time')
                    ];
                    // return response()->json(['status'=>false,'message'=>'預約時間已重覆，請重新選擇，或您可開啟「可重覆預約」完成您的需求','date_time'=>request('date_time')]);
                }
                return response()->json($data);
            }
        }

        if( request('customer') ){
            $shop_customer = ShopCustomer::find(request('customer'));
        }else{
            // 建立新會員
            // 先確認是否在customers裡有資料，建立集團和商家會員
            $customer = Customer::where('phone',request('phone'))->first();
            if( !$customer ){
                $customer = new Customer;
                $customer->phone    = request('phone');
                $customer->realname = '新會員';
                $customer->save(); 
            }

            $company_customer = CompanyCustomer::where('customer_id',$customer->id)->first();
            if( !$company_customer ) $company_customer = new CompanyCustomer;
            $company_customer->customer_id = $customer->id;
            $company_customer->company_id  = $shop_info->company_info->id;
            $company_customer->save();

            $shop_customer = ShopCustomer::where('customer_id', $customer->id)->first();
            if (!$shop_customer) $shop_customer = new ShopCustomer;
            $shop_customer->shop_id     = $shop_info->id;
            $shop_customer->company_id  = $shop_info->company_info->id;
            $shop_customer->customer_id = $customer->id;
            $shop_customer->save();

        }

        if( request('id') && request('id') != '' ){
            // 編輯
            $reservation_info = CustomerReservation::find(request('id'));
            if( !$reservation_info ){
                response()->json(['status'=>false,'errors'=>['message'=>['找不到預約資料']]]);
            }
            if( $reservation_info->start != $request_date ) $reservation_info->change = 2;
        }else{
            // 新增
            $reservation_info = new CustomerReservation;
            $reservation_info->shop_id     = $shop_id;
            $reservation_info->company_id  = $company_info->id;
        }
        
        $reservation_info->customer_id     = $shop_customer->customer_id;
        $reservation_info->shop_service_id = request('service');
        $reservation_info->shop_staff_id   = request('staff');
        $reservation_info->start           = $request_date;
        $reservation_info->end             = date('Y-m-d H:i:s',strtotime($request_date.'+ '.$need_time.' minute'));
        $reservation_info->need_time       = $need_time;
        $reservation_info->status          = request('status') ? request('status') : 'Y';
        if( !request('status') || request('status') == 'Y' ){
            $reservation_info->check_time  = date('Y-m-d H:i:s');
            $reservation_info->check_user  = auth()->user()->id;
        }
        $reservation_info->save();

        // 預約加值項目
        if( request('advances') ){
            // 先刪除原本的加值項目資料
            CustomerReservationAdvance::where('customer_reservation_id',$reservation_info->id)->delete();
            $insert = [];
            foreach( request('advances') as $advance ){
                $insert[] = [
                    'customer_reservation_id' => $reservation_info->id,
                    'shop_service_id'         => $advance,
                ];
            }

            CustomerReservationAdvance::insert($insert);
        }

        if( !request('status') || request('status') == 'Y' ){
            // 寫入google calendar
            if( $shop_staff->calendar_token ){
                // 先刪除
                if( $reservation_info->google_calendar_id ){
                    GoogleCalendarController::delete_calendar_event($reservation_info);
                }
                
                // 再寫入
                $google_calendar_id = GoogleCalendarController::insert_calendar_event($reservation_info,$shop_staff);
                
                $reservation_info->google_calendar_id = $google_calendar_id;
                $reservation_info->save();
            }

            // 若有選擇要發送簡訊才發送
            if( request('send_message') == 'Y' && strtotime(date('Y-m-d H:i:s')) <= strtotime($request_date) ){
                Controller::send_phone_message($reservation_info->customer_info->phone,request('content'),$shop_info);
            }
        }

        return response()->json(['status'=>true,'data'=>$reservation_info]);
    }

    // 刪除商家指定預約資料
    public function shop_reservation_delete($shop_id,$customer_reservation_id)
    {
        $reservation_info = CustomerReservation::where('id',$customer_reservation_id)->with('customer_info','service_info','staff_info','advances')->first();

        if( !$reservation_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到預約資料']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 將google calendar事件刪除
        if( $reservation_info->google_calendar_id && $reservation_info->staff_info->calendar_token ){
            GoogleCalendarController::delete_calendar_event($reservation_info);
        }

        $reservation_info->google_calendar_id = NULL;
        $reservation_info->save();

        // 刪除加值項目記錄
        CustomerReservationAdvance::where('customer_reservation_id',$reservation_info->id)->delete();

        // 刪除商家預約資料
        $reservation_info->delete();

        // 通知預約訊息，有開啟通知，且是未來日期才需要發送，刪除預約單通知顧客(尚未開始的預約資料)
        $reservation_msg = ShopReservationMessage::where('shop_id',$shop_id)->where('type','shop_cancel')->first();
        if( $reservation_msg && $reservation_msg->status == 'Y' && $reservation_info->start > date('Y-m-d H:i:s') ) {
            // 確認字數長度
            $customer_name = $reservation_info->customer_info->realname;
            $store_name    = $shop_info->name;
            $serviceName   = $reservation_info->service_info->name;
            $staffName     = $reservation_info->staff_info->name;
            if( mb_strwidth($customer_name) >= 8  || (!preg_match("/^([A-Za-z]+)$/", $customer_name)) ) $customer_name = Controller::cut_str( $customer_name , 0 , 8 );
            if( mb_strwidth($store_name)    >= 24 || (!preg_match("/^([A-Za-z]+)$/", $store_name)) )    $store_name    = Controller::cut_str( $store_name , 0 , 24 );
            if( mb_strwidth($serviceName)   >= 20 || (!preg_match("/^([A-Za-z]+)$/", $serviceName)) )   $serviceName   = Controller::cut_str( $serviceName , 0 , 20 );
            if( mb_strwidth($staffName)     >= 16 || (!preg_match("/^([A-Za-z]+)$/", $staffName)) )     $staffName     = Controller::cut_str( $staffName , 0 , 16 );

            // 處理需替換的文字
            $sendword = $reservation_msg->content;

            $sendword = str_replace('「"商家名稱"」'    , $store_name, $sendword);
            $sendword = str_replace('「"服務名稱"」'    , $serviceName, $sendword);
            $sendword = str_replace('「"會員名稱"」'    , $customer_name, $sendword);
            $sendword = str_replace('「"服務日期"」'    , substr($reservation_info->start,0,10), $sendword);
            $sendword = str_replace('「"預約日期時間"」' , substr($reservation_info->start,11,5), $sendword);

            // 訂單連結
            $url = '/store/'.$shop_info->alias.'/member/reservation';
            $transform_url_code = Controller::get_transform_url_code($url); 
            $sendword = str_replace('「"訂單連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);

            // 再次預約連結
            $url = '/store/'.$shop_info->alias.'/reservation/again/'.$reservation_info->id;
            $transform_url_code = Controller::get_transform_url_code($url); 
            $sendword = str_replace('「"再次預約連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);

            Controller::send_phone_message($reservation_info->customer_info->phone,$sendword,$shop_info);
        }
        
        return response()->json(['status'=>true]);
    }

    // 審核商家預約資料(可單可多)
    public function shop_reservation_check($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'reservation_id' => 'required', 
            'check'          => 'required', 
        ];

        $messages = [
            'reservation_id.required' => '缺少預約資料',
            'check.required'          => '缺少check資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 拿取使用者的商家權限
        // $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        // if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_reservation_check',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $reservation_infos = CustomerReservation::whereIn('id',request('reservation_id'))->get();
        $reservation_msg_check  = ShopReservationMessage::where('shop_id',$shop_id)->where('type','check')->first();
        $reservation_msg_cancel = ShopReservationMessage::where('shop_id',$shop_id)->where('type','shop_cancel')->first();

        $update_reservation_id = [];
        foreach( $reservation_infos as $reservation_info ){

            // 判斷是否是員工審核
            if (PermissionController::is_staff($shop_id)) {
                
                $shop_staff = Permission::where('user_id', auth()->getUser()->id)->where('shop_id', $shop_id)->first()->shop_staff_id;

                $shop_staff_info = ShopStaff::find($shop_staff);
                // 可不可以審核別人的預約
                if ($shop_staff_info->company_staff_info->edit_all_reservation == 'N' && $shop_staff != $reservation_info->staff_info->id) {
                    continue;
                }
            }

            // 確認字數長度
            $customer_name = $reservation_info->customer_info->realname;
            $store_name    = $shop_info->name;
            $serviceName   = $reservation_info->service_info->name;
            $staffName     = $reservation_info->staff_info->name;
            if( mb_strwidth($customer_name) >= 8  || (!preg_match("/^([A-Za-z]+)$/", $customer_name)) ) $customer_name = Controller::cut_str( $customer_name , 0 , 8 );
            if( mb_strwidth($store_name)    >= 24 || (!preg_match("/^([A-Za-z]+)$/", $store_name)) )    $store_name    = Controller::cut_str( $store_name , 0 , 24 );
            if( mb_strwidth($serviceName)   >= 20 || (!preg_match("/^([A-Za-z]+)$/", $serviceName)) )   $serviceName   = Controller::cut_str( $serviceName , 0 , 20 );
            if( mb_strwidth($staffName)     >= 16 || (!preg_match("/^([A-Za-z]+)$/", $staffName)) )     $staffName     = Controller::cut_str( $staffName , 0 , 16 );

            // 處理需替換的文字
            $sendword = request('check') == 'Y' ? $reservation_msg_check->content : $reservation_msg_cancel->content;

            $sendword = str_replace('「"商家名稱"」'    , $store_name, $sendword);
            $sendword = str_replace('「"服務名稱"」'    , $serviceName, $sendword);
            $sendword = str_replace('「"會員名稱"」'    , $customer_name, $sendword);
            $sendword = str_replace('「"服務日期"」'    , substr($reservation_info->start,0,10), $sendword);
            $sendword = str_replace('「"預約日期時間"」' , substr($reservation_info->start,11,5), $sendword);

            // 訂單連結
            $url = '/store/'.$shop_info->alias.'/member/reservation';
            $transform_url_code = Controller::get_transform_url_code($url); 
            $sendword = str_replace('「"訂單連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);

            // 再次預約連結
            $url = '/store/'.$shop_info->alias.'/reservation/again/'.$reservation_info->id;
            $transform_url_code = Controller::get_transform_url_code($url); 
            $sendword = str_replace('「"再次預約連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);
            
            if( request('check') == 'Y' ){
                // 通過
                if( $reservation_info->staff_info->calendar_token ){
                    // 寫入google calendar
                    $job = new InsertGoogleCalendarEvent($reservation_info,$reservation_info->staff_info);
                    dispatch($job);
                }
                
                if( $reservation_info->service_info->shop_service_category_id == env('TEST_PAY_CATEGORY') ){
                    // 符合預設要發送訂金簡訊的服務分類
                    $sendword = "您的預約已通過，請支付定金費用完成預約 " . env('SHILIPAI_WEB').'/pay/'.$reservation_info->id;
                    $job = new SendSms($reservation_info->customer_info->phone,$sendword,$shop_info);
                    dispatch($job);

                    $reservation_info->status     = 'N';
                    $reservation_info->check_time = date('Y-m-d H:i:s');
                    $reservation_info->save();

                    return response()->json(['status'=>true]);

                }else{
                    // 發送通過簡訊
                    if( $reservation_msg_check->status == 'Y' && $reservation_info->start > date('Y-m-d H:i:s') ){
                        $job = new SendSms($reservation_info->customer_info->phone,$sendword,$shop_info);
                        dispatch($job);
                    }
                }

            }else{
                // 拒絕
                // 發送拒絕簡訊
                if( $reservation_msg_cancel->status == 'Y' && $reservation_info->start > date('Y-m-d H:i:s') ){
                    $job = new SendSms($reservation_info->customer_info->phone,$sendword,$shop_info);
                    dispatch($job);
                }
            }
            
            $update_reservation_id[] = $reservation_info->id;
        }

        $update = [
            'status'        => 'Y',
            'cancel_status' => request('check') == 'N' ? 'C' : NULL,
            'check_time'    => date('Y-m-d H:i:s'),
            'check_user'    => auth()->user()->id,
        ];

        CustomerReservation::whereIn('id',$update_reservation_id)->update($update);
        
        return response()->json(['status'=>true]);
    }

    // 編輯預約設定-條件設定
    public function shop_reservation_setting($shop_id)
    {
        if( PermissionController::is_staff($shop_id) ){
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if( $user_staff_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_staff_permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_reservations', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('reservation_setting',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $setting = ShopSet::where('shop_id',$shop_id)->first();

        $data_info = [
            'reservation_check'                         => $setting->reservation_check,
            'reservation_check_permission'              => in_array('reservation_check_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_during_type'                   => $setting->reservation_during_type,
            'reservation_during_type_permission'        => in_array('reservation_during_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_during_month_after'              => $setting->reservation_during_month_after,
            'reservation_during_month_after_permission'   => in_array('reservation_during_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_during_day_close'            => $setting->reservation_during_day_close,
            'reservation_during_day_close_permission' => in_array('reservation_during_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_during_open'                   => $setting->reservation_during_open,
            'reservation_during_open_permission'        => in_array('reservation_during_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_limit_type'                    => $setting->reservation_limit_type,
            'reservation_limit_permission'              => in_array('reservation_limit_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_limit_day'                     => $setting->reservation_limit_day?:0,
            'reservation_limit_day_permission'          => in_array('reservation_limit_edit',$user_shop_permission['permission']) ? true : false,
            'customer_edit_reservation'                 => $setting->customer_edit_reservation,
            'customer_edit_reservation_permission'      => in_array('customer_edit_reservation_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_repeat_time_type'              => $setting->reservation_repeat_time_type,
            'reservation_repeat_time_type_permission'   => in_array('reservation_repeat_edit',$user_shop_permission['permission']) ? true : false,
            'reservation_repeat_time'                   => $setting->reservation_repeat_time,
            'reservation_repeat_time_permission'        => in_array('reservation_repeat_edit',$user_shop_permission['permission']) ? true : false,
            'buffer_time'                               => $setting->buffer_time ?:0 ,
            'buffer_time_permission'                    => in_array('buffer_time_edit',$user_shop_permission['permission']) ? true : false,
            'deposit_type'                              => $setting->deposit_type,
            'deposit_type_permission'                   => in_array('deposit_type_edit',$user_shop_permission['permission']) ? true : false,
            'deposit_dollar'                            => $setting->deposit_dollar,
            'deposit_dollar_permission'                 => in_array('deposit_type_edit',$user_shop_permission['permission']) ? true : false,
            'deposit_handle'                            => $setting->deposit_handle,
            'deposit_handle_permission'                 => in_array('deposit_type_edit',$user_shop_permission['permission']) ? true : false,
        ];

        $setting->reservation_check_permission = in_array('reservation_check',$user_shop_permission['permission']) ? true : false;

        $data = [
            'status'          => true,
            'permission'      => true,
            // 'edit_permission' => in_array('reservation_setting_edit',$user_shop_permission['permission']) ? true : false,
            'data'            => $data_info,
        ];

        return response()->json($data);
    }

    // 儲存預約設定-條件設定
    public function shop_reservation_setting_save($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'reservation_check'            => 'required', 
            'reservation_during_type'      => 'required', 
            'reservation_limit_type'       => 'required',
            'customer_edit_reservation'    => 'required',
            'reservation_repeat_time_type' => 'required',
            'buffer_time'                  => 'required',
        ];

        $messages = [
            'reservation_check.required'              => '請選擇是否審核客戶預約時間',
            'reservation_during_type.required'        => '請選擇預約期間與限制',
            'reservation_limit_type.required'         => '請選擇預約限制',
            'customer_edit_reservation.required'      => '請選擇客戶幾天前可修改預約的時間',
            'reservation_repeat_time_type.required'   => '請選擇是否可以重複被預約',
            'buffer_time.required'                    => '請填寫緩衝時間預設值',
            'reservation_during_open.required'        => '請填寫每個月固定幾號開放',
            'reservation_during_month_after.required'   => '請填寫開放後幾個月時間可預約',
            'reservation_during_day_close.required' => '請填寫近幾個月開放預約',
            'reservation_limit_day.required'          => '請填寫距當日幾天以上',
            'reservation_repeat_time.required'        => '請填寫可重複次數',
            'reservation_overlapping.required'        => '請填寫緩衝時間預設值',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 預約期間與限制
        if( request('reservation_during_type') == 1 ){
            $rules['reservation_during_open']        = 'required';
            $rules['reservation_during_month_after'] = 'required';
        }elseif( request('reservation_during_type') == 2 ){
            $rules['reservation_during_day_close'] = 'required';
        }

        // 限制預約
        if( request('reservation_limit_type') == 2 ){
            $rules['reservation_limit_day'] = 'required';
        }

        // 同一服務人員的時間 是否可以重覆預約
        if( request('reservation_repeat_time_type') == 1 ){
            $rules['reservation_repeat_time'] = 'required';
        }

        // 同一服務人員的時間 可以重疊的時間
        if( request('reservation_overlapping_type') == 1 ){
            $rules['reservation_overlapping'] = 'required';
        }

        // 是否收取訂金
        // if( request('deposit_type') == 1 ){
        //     $rules['deposit_dollar'] = 'required';
        //     $rules['deposit_handle'] = 'required';      
        // }

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $set = ShopSet::where('shop_id',$shop_id)->first();
        $set->reservation_check              = request('reservation_check');
        $set->reservation_during_type        = request('reservation_during_type');   //1每月固定2只能約近3不限制
        $set->reservation_during_month_after = request('reservation_during_type') == 1 ? request('reservation_during_month_after') : NULL; //開放後幾個月時間可預約
        $set->reservation_during_day_close   = request('reservation_during_type') == 2 ? request('reservation_during_day_close') : NULL; //近幾個天開放預約
        $set->reservation_during_open        = request('reservation_during_type') == 1 ? request('reservation_during_open') : NULL;//每個月固定幾號開放
        $set->reservation_limit_type         = request('reservation_limit_type');//1.當日不可預約2距當日3不限制
        $set->reservation_limit_day          = request('reservation_limit_type') == 2 ? request('reservation_limit_day') : NULL;//距當日幾天以上
        $set->customer_edit_reservation      = request('customer_edit_reservation');//客戶幾天前可以修改預約的時間
        $set->reservation_repeat_time_type   = request('reservation_repeat_time_type');//0否1是
        $set->reservation_repeat_time        = request('reservation_repeat_time');//可重複次數
        $set->buffer_time                    = request('buffer_time'); //緩衝時間
        $set->save();

        return response()->json(['status' => true]);
    }

    // 編輯預約設定-標籤設定
    public function shop_reservation_tag($shop_id)
    {
        if( PermissionController::is_staff($shop_id) ){
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if( $user_staff_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_staff_permission['errors']]]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('reservation_tag',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $setting = ShopSet::select('tag_during','blacklist_limit')->where('shop_id',$shop_id)->first();

        $reservation_tags = ShopReservationTag::where('shop_id',$shop_id)->orderBy('type','ASC')->get();
        $cate = [];
        foreach( $reservation_tags as $tag ){
            $tag->people = $tag->customers->count();
            $cate[$tag->type][] = $tag; 
        }

        $shop_service_categories = ShopServiceCategory::where('shop_id',$shop_id)->select('id','name')->get();
        foreach( $shop_service_categories as $service_category ){
            $service_category->shop_services = ShopService::where('shop_service_category_id',$service_category->id)->select('id','name','blacklist_limit')->get();
        }

        $data_info = [
            'tag_during'                  => $setting->tag_during,
            'tag_during_permission'       => in_array('tag_during',$user_shop_permission['permission']) ? true : false,
            'blacklist_limit'             => $setting->blacklist_limit,
            'blacklist_limit_permission'  => in_array('blacklist_limit',$user_shop_permission['permission']) ? true : false,
            'reservation_tags'            => $cate,
            'reservation_tags_permission' => in_array('reservation_tags',$user_shop_permission['permission']) ? true : false,
            'limit_service'               => $shop_service_categories,
        ];

        $data = [
            'status'          => true,
            'permission'      => true,
            'edit_permission' => in_array('reservation_tag_edit',$user_shop_permission['permission']) ? true : false,
            'data'            => $data_info,
        ];

        return response()->json($data);
    }

    // 儲存預約設定-標籤設定
    public function shop_reservation_tag_save($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'tag_during'       => 'required', 
            'blacklist_limit'  => 'required',
            'reservation_tags' => 'required',
        ];

        if( request('blacklist_limit') == 3 ){
            $rules['limit_service'] = 'required';
        }

        $messages = [
            'tag_during.required'       => '請選擇近幾個月重新計算',
            'blacklist_limit.required'  => '請選擇黑名單限制',
            'reservation_tags.required' => '請填寫預約標籤內容',
            'limit_service.required'    => '請選擇限制預約項目',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        ShopSet::where('shop_id',$shop_id)->update(['tag_during'=>request('tag_during'),'blacklist_limit'=>request('blacklist_limit')]);

        // 預約標籤記錄
        ShopReservationTag::where('shop_id',$shop_id)->delete();
        $insert = [];
        foreach( request('reservation_tags') as $type => $tags ){
            foreach( $tags as $tag ){
                $insert[] = [
                    'shop_id'   => $shop_id,
                    'type'      => $type,
                    'times'     => $tag['times'],
                    'name'      => $tag['name'],
                    'blacklist' => $tag['blacklist'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        ShopReservationTag::insert($insert);

        if( request('blacklist_limit') == 1 ){
            // 不限制預約項目
            ShopService::where('shop_id',$shop_id)->update(['blacklist_limit'=>0]);
        }elseif( request('blacklist_limit') == 2 ){
            // 全限制項目
            ShopService::where('shop_id',$shop_id)->update(['blacklist_limit'=>1]);
        }else{
            // 部分限制
            $limitY = $limitN = [];
            foreach( request('limit_service') as $service_category ){
                foreach( $service_category['shop_services'] as $service ){
                    if( $service['blacklist_limit'] == 1 ){
                        $limitY[] = $service['id'];
                    }else{
                        $limitN[] = $service['id'];
                    }
                }
            }
            ShopService::whereIn('id',$limitY)->update(['blacklist_limit'=>1]);
            ShopService::whereIn('id',$limitN)->update(['blacklist_limit'=>0]);
        }

        return response()->json(['status' => true , 'data' => request()->all() ]);
    }

    // 編輯預約通知設定
    public function shop_reservation_message($shop_id)
    {
        if( PermissionController::is_staff($shop_id) ){
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if( $user_staff_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_staff_permission['errors']]]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if( !in_array('reservation_message',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $messages = ShopReservationMessage::where('shop_id',$shop_id)->get();
        $data = [];
        foreach( $messages as $message ){
            $data[] = [
                'id'                 => $message->id,
                'type'               => $message->type,
                'content'            => $message->content?:'',
                'content_permission' => in_array($message->type.'_reservation_edit',$user_shop_permission['permission']) ? true : false,
                'status'             => $message->status,
                'status_permission'  => in_array($message->type.'_reservation_status',$user_shop_permission['permission']) ? true : false,
            ];
        } 

        $data = [
            'status'     => true,
            'permission' => true,
            'data'       => $messages,
        ];

        return response()->json($data);
    }

    // 儲存預約通知設定
    public function shop_reservation_message_save($shop_id)
    {
        $messages = request()->all();
        foreach( $messages as $message ){
            $model = ShopReservationMessage::find($message['id']);
            $model->content = $message['content'];
            $model->status  = $message['status'];
            $model->save();
        }

        return response()->json(['status' => true , 'data' => request()->all()]);
    }

    // 拿取指定行事曆跳窗預約事件資料
    public function shop_calendar_reservation_info($shop_id,$customer_reservation_id)
    {
        $reservation_info = CustomerReservation::find($customer_reservation_id);
        if( !$reservation_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到預約資料']]]);
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( PermissionController::is_staff($shop_id) ){
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if( $user_staff_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_staff_permission['errors']]]]);
            $permission = $user_staff_permission;
        }else{
            // 拿取使用者的商家權限
            $user_shop_permission = PermissionController::user_shop_permission($shop_id);
            if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);
            $permission = $user_shop_permission;
        }

        // 確認頁面瀏覽權限
        // if( !in_array('calendar_reservation_edit',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 時間格式
        $weeks    = ['星期一','星期二','星期三','星期四','星期五','星期六','星期日'];
        $text_arr = [ 'am' => '上午' , 'pm' => '下午']; 
        $date  = date('n月d日',strtotime($reservation_info->start)) 
                . ' (' . $weeks[date('N',strtotime($reservation_info->start))-1]. ') ' 
                . $text_arr[date('a',strtotime($reservation_info->start))] . date('h:i',strtotime($reservation_info->start)) . ' - '
                . $text_arr[date('a',strtotime($reservation_info->end))] . date('h:i',strtotime($reservation_info->end));

        // 1到了 / 2爽約 / 3小遲到 / 4大遲到 / 5 提早
        $tags = [
            [
                'name'        => '提早',
                'description' => '(30分鐘以上)',
                'selected'    => $reservation_info->tag == 5 ? true : false,
                'value'       => 5,
            ],
            [
                'name'        => '到囉！',
                'description' => '',
                'selected'    => $reservation_info->tag == 1 ? true : false,
                'value'       => 1,
            ],
            [
                'name'        => '大遲到',
                'description' => '(30分鐘以上)',
                'selected'    => $reservation_info->tag == 4 ? true : false,
                'value'       => 4,
            ],
            [
                'name'        => '小遲到',
                'description' => '(30分鐘以內)',
                'selected'    => $reservation_info->tag == 3 ? true : false,
                'value'       => 3,
            ],
            [
                'name'        => '爽約',
                'description' => '',
                'selected'    => $reservation_info->tag == 2 ? true : false,
                'value'       => 2,
            ]
        ];

        $reservation = [
            'id'          => $reservation_info->id,
            'staff'       => $reservation_info->staff_info->name,
            'staff_photo' => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$reservation_info->staff_info->photo,
            'service'     => $reservation_info->service_info->name,
            'advances'    => $reservation_info->advances->pluck('name'),
            'customer'    => $reservation_info->customer_info->realname,
            'phone'       => $reservation_info->customer_info->phone,
            'date'        => $date,
            'tags'        => $tags,
        ];

        $edit_permission = $status_permission = true;
        if( PermissionController::is_staff($shop_id) ){
            $status_permission = in_array('staff_reservation_edit_btn',$permission['permission']) ? true : false;

            $shop_staff = Permission::where('user_id', auth()->getUser()->id)->where('shop_id', $shop_id)->first()->shop_staff_id;
            $shop_staff_info = ShopStaff::find($shop_staff);
            
            // 可不可以修改別人的預約
            if ($shop_staff_info->company_staff_info->edit_all_reservation == 'N' && $reservation_info->staff_info->id != $shop_staff_info->id) {
                $edit_permission   = false;
                $status_permission = false;
            } 
        }else{
            $status_permission = in_array('shop_reservation_edit_btn',$permission['permission']) ? true : false;
        }

        $data = [
            'status'            => true,
            'permission'        => true,
            'status_permission' => $status_permission,
            'data'              => $reservation,
            'edit_permission'   => $edit_permission,
        ];

        return response()->json($data);
    }

    // 儲存指定行事曆跳窗預約事件資料
    public function shop_calendar_reservation_info_save($shop_id,$customer_reservation_id)
    {
        $reservation_info = CustomerReservation::find(request('id'));
        if( !$reservation_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到預約資料']]]);
        }

        $send_notice = true;
        if( $reservation_info->tag != NULL ){
            $send_notice = false;
        }

        foreach( request('tags') as $tag ){
            if( $tag['selected'] == true ){
                $reservation_info->tag = $tag['value'];
                $reservation_info->save();
                break;
            }
        }

        $shop_info = Shop::find($reservation_info->shop_id);

        $customer = Customer::find($reservation_info->customer_id);

        // 判斷是否第一次預約且出席
        $award_notices = ShopAwardNotice::where('shop_id',$shop_info->id)
                                        ->where('use','Y')
                                        ->where('condition_type',3)
                                        ->where('finish_type',2)
                                        ->where('send_cycle',1)
                                        ->get();
        if( $award_notices->count() ){
            foreach( $award_notices as $award_notice ){

                if( $award_notice->message == '' || $award_notice->message == NULL ) continue;

                // 檢查活動時間
                if( $award_notice->during_type == 2 ){
                    // 自定時間
                    if( $award_notice->start_date > date('Y-m-d') || $award_notice->end_date < date('Y-m-d') ) continue;
                }

                // 檢查是否是第一次預約且出席
                $check = CustomerReservation::where('shop_id',$shop_info->id)
                                            ->where('customer_id',$reservation_info->customer_id) 
                                            ->where('start','<',$reservation_info->start)
                                            ->whereIn('tag',[1,3,4,5])
                                            ->count();
                if( $send_notice == false || $check != 0 ) continue;

                // 發送文字內容整理
                $message = $award_notice->message;
                $coupon  = ShopCoupon::find($award_notice->shop_coupons);

                $message = str_replace('「"商家名稱"」' , $shop_info->name, $message);
                $message = str_replace('「"會員名稱"」' , $customer->realname, $message);
                $message = str_replace('「"下個月份"」' , ( date('n')+1 == 13 ? '01' : (string)date('n')+1 ).'月', $message);

                if( $award_notice->link ){
                    $message = str_replace('「"連結"」' , ' ' . $award_notice->link . ' ', $message);
                }else{
                    $message = str_replace('「"連結"」' , '', $message);
                }

                if( $coupon ){
                    // 建立縮短網址
                    $url = '/store/' . $shop_info->alias . '/member/coupon?select=2';
                    $transform_url_code = Controller::get_transform_url_code($url);
                    $message = str_replace('「"優惠券"」', ' ' . env('SHILIPAI_WEB') . '/T/' . $transform_url_code . ' ', $message);
                }else{
                    $message = str_replace('「"優惠券"」' , '', $message);
                }

                // 建立要寫入推廣的顧客列表
                $insert_lists[] = [
                    'shop_id'              => $shop_info->id,
                    'shop_award_notice_id' => $award_notice->id,
                    'shop_customer_id'     => ShopCustomer::where('shop_id',$shop_info->id)->where('customer_id',$customer->id)->first()->id,
                    'phone'                => $customer->phone,
                    'type'                 => $award_notice->send_type,
                    'message'              => $message,
                    'created_at'           => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                ];

                ShopManagementCustomerList::insert($insert_lists);

                // 拿出尚未發送的會員
                $management_customers = ShopManagementCustomerList::where('shop_award_notice_id',$award_notice->id)->where('status','N')->get();

                foreach( $management_customers as $customer ){
                    // 被刪除的會員不用發送
                    if (!$customer->customer_info) continue;
                    // 沒有電話的不用發送
                    if (!$customer->phone) continue;

                    // 如果有設定優惠券
                    if ($customer->award_info && $customer->award_info->shop_coupons) {
                        $coupon = ShopCoupon::find($customer->award_info->shop_coupons);
                        if ($coupon && $coupon->status == 'published') {
                            // 先檢查優惠券是否過期
                            if (strtotime($coupon->start_date) <= time() && time() <= strtotime($coupon->end_date)) {

                                if ($coupon->get_level == 2
                                ) {
                                    // 特定條件
                                    $add = true;
                                } else {
                                    // 所有人，需判斷可領取次數，若只能領取一次，需判斷是否可以在給予優惠券
                                    $add = true;
                                    if ($coupon->use_type == 1) {
                                        $customer_coupon = CustomerCoupon::where('customer_id', $customer->customer_info->id)
                                            ->where('shop_id', $shop_info->id)
                                            ->where('shop_coupon_id', $coupon->id)
                                            ->first();
                                        if ($customer_coupon) $add = false;
                                    }
                                }

                                if ($add) {
                                    // 將該優惠券直接寫入會員裡
                                    $customer_coupon = new CustomerCoupon;
                                    $customer_coupon->customer_id    = $customer->customer_info->id;
                                    $customer_coupon->company_id     = $shop_info->company_info->id;
                                    $customer_coupon->shop_id        = $shop_info->id;
                                    $customer_coupon->shop_coupon_id = $coupon->id;
                                    $customer_coupon->save();
                                }
                            }
                        }
                    }

                    switch ($award_notice->send_type) {
                        case 1: // 手機與line
                            // 手機發送
                            $job = new SendManagementSms($customer,$customer->message,$shop_info,'','',$award_notice->id);
                            dispatch($job);

                            // line發送

                            break;

                        case 2: // 手機
                            // 手機發送
                            $job = new SendManagementSms($customer,$customer->message,$shop_info,'','',$award_notice->id);
                            dispatch($job);

                            break;

                        case 3: // line

                            break;

                        case 4: // line優先

                            // 手機發送
                            $job = new SendManagementSms($customer,$customer->message,$shop_info,'','',$award_notice->id);
                            dispatch($job);

                            break;
                    }
                }
            }

        }

        return response()->json(['status' => true ]);
    }

    // 拿取今日確認/未確認預約
    public function check_today_reservation()
    {
        $companyId = request('company_id');
        $status    = request('status');
        $staff     = request('staff_id');
        $line_code = request('line_code');
        $shop_id   = request('shop_id');
        $result    = [];

        $user = User::where('line_code',$line_code)->first();
        $user_shop_staffs = ShopStaff::where('user_id',$user->id)->where('shop_id',$shop_id)->get();

        foreach( $user_shop_staffs as $shop_staff ){
            $shop = Shop::where('id',$shop_staff->shop_id)->first();

            if( $status == 'Y' ){
                // 今日已確認預約
                $events = CustomerReservation::where('shop_id',$shop->id)->where('status','Y')->where('cancel_status',NULL);

                // 判斷是否是員工還是老闆
                $permission = Permission::where('shop_id',$shop->id)->where('user_id',$user->id)->get();
                if( $permission->count() == 1 ){
                    $events = $events->where('shop_staff_id',$shop_staff->id);
                }

                $events = $events->where('start','like','%'.date('Y-m-d').'%')->orderBy('start','ASC')->get();

                foreach( $events as $event ){
                    $result[] = [
                        'shop_name'        => $shop->name,
                        'time'             => substr($event->start,11,5),
                        'name'             => $event->customer_info->realname,
                        'product_item'     => $event->service_info->name,
                        'service_personel' => $event->staff_info->name,
                    ];
                }
                
            }else{
                // 尚未確認預約單
                $reservations = CustomerReservation::where('shop_id',$shop->id)->where('status','N');
                // 判斷是否是員工還是老闆
                $permission = Permission::where('shop_id',$shop->id)->where('user_id',$user->id)->get();
                if( $permission->count() == 1 ){
                    // if( $line_code == 'U3b6580b93f43ae3fd8dcfc6087426e4d' ) dd('123');
                    $reservations = $reservations->where('shop_staff_id',$shop_staff->id);
                }

                $reservations = $reservations->orderBy('id','DESC')->get();

                foreach( $reservations as $reservation ){
                    $result[] = [
                        'id'               => $reservation->id,
                        'shop_name'        => $shop->name,
                        'time'             => substr($reservation->start,0,10) 
                                            . (date('a',strtotime($reservation->start)) == 'am' ? ' 上午 ' : ' 下午 ') 
                                            . date('h:i',strtotime(substr($reservation->start,11,5))) ,
                        'name'             => $reservation->customer_info->realname,
                        'product_item'     => $reservation->service_info->name,
                        'service_personel' => $reservation->staff_info->name,
                    ]; 
                } 
            } 

        }

        return response()->json($result) ; 
    }

    // 預約事件確認/取消
    public function check_reservation()
    {
        $reservation_info       = CustomerReservation::where('id',request('id'))->first();
        
        if( $reservation_info->status != 'N' ){
            return ['status' => false , 'message' => '無法重覆審核' ];
        }

        $shop_info = Shop::find($reservation_info->shop_id);

        $reservation_msg_check  = ShopReservationMessage::where('shop_id',$shop_info->id)->where('type','check')->first();
        $reservation_msg_cancel = ShopReservationMessage::where('shop_id',$shop_info->id)->where('type','shop_cancel')->first();

        // 確認字數長度
        $customer_name = $reservation_info->customer_info->realname;
        $store_name    = $shop_info->name;
        $serviceName   = $reservation_info->service_info->name;
        $staffName     = $reservation_info->staff_info->name;
        if( mb_strwidth($customer_name) >= 8  || (!preg_match("/^([A-Za-z]+)$/", $customer_name)) ) $customer_name = Controller::cut_str( $customer_name , 0 , 8 );
        if( mb_strwidth($store_name)    >= 24 || (!preg_match("/^([A-Za-z]+)$/", $store_name)) )    $store_name    = Controller::cut_str( $store_name , 0 , 24 );
        if( mb_strwidth($serviceName)   >= 20 || (!preg_match("/^([A-Za-z]+)$/", $serviceName)) )   $serviceName   = Controller::cut_str( $serviceName , 0 , 20 );
        if( mb_strwidth($staffName)     >= 16 || (!preg_match("/^([A-Za-z]+)$/", $staffName)) )     $staffName     = Controller::cut_str( $staffName , 0 , 16 );

        // 處理需替換的文字
        $sendword = request('type') == 'Y' ? $reservation_msg_check->content : $reservation_msg_cancel->content;

        $sendword = str_replace('「"商家名稱"」'    , $store_name, $sendword);
        $sendword = str_replace('「"服務名稱"」'    , $serviceName, $sendword);
        $sendword = str_replace('「"會員名稱"」'    , $customer_name, $sendword);
        $sendword = str_replace('「"服務日期"」'    , substr($reservation_info->start,0,10), $sendword);
        $sendword = str_replace('「"預約日期時間"」' , substr($reservation_info->start,11,5), $sendword);

        // 訂單連結
        $url = '/store/'.$shop_info->alias.'/member/reservation';
        $transform_url_code = Controller::get_transform_url_code($url); 
        $sendword = str_replace('「"訂單連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);

        // 再次預約連結
        $url = '/store/'.$shop_info->alias.'/reservation/again/'.$reservation_info->id;
        $transform_url_code = Controller::get_transform_url_code($url); 
        $sendword = str_replace('「"再次預約連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);
        
        if( request('type') == 'Y' ){
            if( $reservation_info->status == 'Y' ){
                return ['status'=>true,'message'=>'預約確認完成'];
            }

            // 通過
            if( $reservation_info->staff_info->calendar_token ){
                // 寫入google calendar
                $job = new InsertGoogleCalendarEvent($reservation_info,$reservation_info->staff_info);
                dispatch($job);
            }
            // 發送通過簡訊
            if( $reservation_msg_check->status == 'Y' && $reservation_info->start > date('Y-m-d H:i:s') ){
                $job = new SendSms($reservation_info->customer_info->phone,$sendword,$shop_info);
                dispatch($job);
            }
            $message = "預約確認完成";
        }else{
            if( $reservation_info->status == 'C' ){
                return ['status'=>true,'message'=>'預約確認完成'];
            }

            // 拒絕
            // 發送拒絕簡訊
            if( $reservation_msg_cancel->status == 'Y' && $reservation_info->start > date('Y-m-d H:i:s') ){
                $job = new SendSms($reservation_info->customer_info->phone,$sendword,$shop_info);
                dispatch($job);
            }
            $message = "預約取消完成";
        }

        $need_time = strtotime($reservation_info->end) - strtotime($reservation_info->start);

        $update = [
            'status'        => 'Y',
            'cancel_status' => request('type') == 'N' ? 'C' : NULL,
            'check_time'    => date('Y-m-d H:i:s'),
            'need_time'     => request('type') == 'Y' ? $need_time/60 : NULL , 
            'check_user'    => '',
        ];

        CustomerReservation::where('id',request('id'))->update($update);

        return ['status'=>true,'message'=>$message];
    }
}
