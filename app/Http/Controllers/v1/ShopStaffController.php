<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteGoogleCalendarEvent;
use Illuminate\Http\Request;
use Validator;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Photo;
use App\Models\Company;
use App\Models\CompanyStaff;
use App\Models\CustomerReservation;
use App\Models\Permission;
use App\Models\PermissionMenu;
use App\Models\Shop;
use App\Models\ShopStaff;
use App\Models\ShopServiceCategory;
use App\Models\ShopService;
use App\Models\ShopServiceStaff;
use App\Models\ShopBusinessHour;
use App\Models\ShopClose;
use App\Models\ShopVacation;
use App\Models\ShopCustomer;
use App\Models\User;

class ShopStaffController extends Controller
{
    // 取得商家全部員工資料
    public function shop_staffs($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_staffs',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	$staffs = ShopStaff::where('shop_id',$shop_id)->get();//->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')->get();
    	$staffs_info = [];
    	foreach( $staffs as $staff ){
    	    $evaluate_day = 0;
    	    if( $staff->company_staff_info->onboard ){
    	    	$evaluate_day = (strtotime(date('Y-m-d'))-strtotime($staff->onboard))/86400;
    	    }

    		$staffs_info[] = [
    			'id'                     => $staff->id,
    			'name'                   => $staff->company_staff_info->name,
    			'phone'                  => $staff->company_staff_info->phone,
    			'staff_no'               => $staff->company_staff_info->staff_no,
    			'title'                  => $staff->company_title_a_info ? $staff->company_title_a_info->name : NULL,
    			'seniority'              => $evaluate_day == 0 ? '-' : round( $evaluate_day/365 , 1 ),
    			'service_count'          => $staff->staff_services->count(),
    			'customer_count'         => '-',
    			'evaluate'               => '-',
    			'google_calendar_status' => $staff->company_staff_info->fire_time ? '已離職' : ( $staff->company_staff_info->calendar_token ? '已綁定':'未綁定' ),
    			'fire_status'            => $staff->company_staff_info->fire_time ? true : false,
    		];
    	}

    	$data = [
            'status'                         => true,
            'permission'                     => true,
            'shop_staff_create_permission'   => in_array('shop_staff_create_btn',$user_shop_permission['permission']) ? true : false, 
            'shop_staff_evaluate_permission' => in_array('shop_staff_evaluate',$user_shop_permission['permission']) ? true : false, 
            'shop_staff_edit_permission'     => in_array('shop_staff_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'shop_staff_fire_permission'     => in_array('shop_staff_fire',$user_shop_permission['permission']) ? true : false, 
            'shop_staff_delete_permission'   => in_array('shop_staff_delete',$user_shop_permission['permission']) ? true : false, 
            'data'                           => $staffs_info,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家員工資料
    public function shop_staff_info($shop_id,$shop_staff_id="")
    {
        // 判斷是新增還編輯還是員工自己登入編輯
    	if( $shop_staff_id ){
            $shop_staff_info = ShopStaff::find($shop_staff_id);
            if( !$shop_staff_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_staff_info = new ShopStaff;
            $type            = 'create';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;
        
        $permission_text = 'shop_staff_';
        if( $type == 'edit' ){
            // $user = User::where('id',auth()->user()->id)->with(['permissions'])->first();
            // $t = 0;
            // foreach( $user->permissions->where('shop_id',$shop_id) as $permission ){
            //     if( !$permission->shop_id && $permission->company_id ){
            //         // 代表是管理者登入
            //         $t = 1;
            //         break;
            //     }
            // }

            // if( $t == 0 ){
            //     // 代表是員工自己登入
            //     $permission_text = 'staff_self';
            // }
            if (PermissionController::is_staff($shop_id)){
                $permission_text = 'staff_self';
            }
        }

        $per = $permission_text == 'staff_self' ? $permission_text : $permission_text.$type;

        if( PermissionController::is_staff($shop_id) ){
            // 員工身分
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if( $user_staff_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_staff_permission['errors']]]]);

            if (!in_array('staff_self', $user_staff_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $user_shop_permission = $user_staff_permission;

            $per = 'staff_self';
        }else{
            // 拿取使用者的商家權限
            $user_shop_permission = PermissionController::user_shop_permission($shop_id);
            if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

            // 確認頁面瀏覽權限
            if( !in_array( $per,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'per'=>$per,'errors'=>['message'=>['使用者沒有權限']]]);
            $per = 'shop_staff_'.$type; ;
        }   
        
        $photo = $banner = NULL;
        if( $shop_staff_info->company_staff_info && $shop_staff_info->company_staff_info->photo ){
        	$photo = env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_staff_info->company_staff_info->photo;
        }
        if( $shop_staff_info->company_staff_info && $shop_staff_info->company_staff_info->banner ){
        	$banner = env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_staff_info->company_staff_info->banner;
        }

        // 員工基本資料
        $staff_info = [
            'id'                            => $shop_staff_info->id,
            'password_permission'           => in_array($per.'_password',$user_shop_permission['permission']) ? true : false,
            'staff_no'                      => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->staff_no : NULL,
            'staff_no_permission'           => in_array($per.'_staff_no',$user_shop_permission['permission']) ? true : false,
            'phone'                         => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->phone : NULL,
            'phone_permission'              => in_array($per.'_phone',$user_shop_permission['permission']) ? true : false,
            'name'                          => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->name : NULL,
            'name_permission'               => in_array($per.'_name',$user_shop_permission['permission']) ? true : false,
            'nick_name'                     => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->nick_name : NULL,
            'nick_name_permission'          => in_array($per.'_nick_name',$user_shop_permission['permission']) ? true : false,
            'company_title_id_a'            => $shop_staff_info->company_title_id_a,
            'company_title_id_a_permission' => in_array($per.'_title',$user_shop_permission['permission']) ? true : false,
            'company_title_id_b'            => $shop_staff_info->company_title_id_b,
            'company_title_id_b_permission' => in_array($per.'_title',$user_shop_permission['permission']) ? true : false,
            'onboard'                       => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->onboard : NULL,
            'onboard_permission'            => in_array($per.'_onboard',$user_shop_permission['permission']) ? true : false,
            'line_id'                       => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->line_id : NULL,
            'line_id_permission'            => in_array($per.'_line_id',$user_shop_permission['permission']) ? true : false,
            'info'                          => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->info : NULL,
            'info_permission'               => in_array($per.'_info',$user_shop_permission['permission']) ? true : false,
            'photo'                         => $photo,
            'photo_permission'              => in_array($per.'_photo',$user_shop_permission['permission']) ? true : false,
            'banner'                        => $banner,
            'banner_permission'             => in_array($per.'_banner',$user_shop_permission['permission']) ? true : false,
            'reservation_limit'             => $shop_staff_info->company_staff_info ? $shop_staff_info->company_staff_info->reservation_limit : 1,
            'reservation_limit_permission'  => in_array($per.'_reservation_limit',$user_shop_permission['permission']) ? true : false,
            'user_role'                     => '',
            'user_role_permission'          => '',
        ];

        // 角色與權限

        // 職稱
        $title_select = $company_info->company_titles;

        $data = [
		    'status'       => true,
		    'permission'   => true,
		    'title_select' => $title_select,
		    'data'         => $staff_info,
            'per'          => $per,
        ];

		return response()->json($data);
    }

    // 儲存商家員工資料
    public function shop_staff_save($shop_id,$shop_staff_id="")
    {
    	// 驗證欄位資料
        $rules = [
            'name'               => 'required', 
            'phone'              => 'required', 
            'company_title_id_a' => 'required', 
        ];

        $messages = [
            'name.required'               => '請填寫員工真實姓名',
            'phone.required'              => '請填寫手機號碼',
            'company_title_id_a.required' => '請選擇職稱',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( $shop_staff_id ){
            // 編輯
            $shop_staff_info    = ShopStaff::find($shop_staff_id);
            $company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
            if( !$company_staff_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
            }
        }else{
            // 先判斷手機號碼是否重複
            $company_staff_info = CompanyStaff::where('company_id',$company_info->id)->where('phone',request('phone'))->first();
            if( $company_staff_info ){
                $shop_staff_info = ShopStaff::where('shop_id',$shop_id)->where('company_staff_id',$company_staff_info->id)->first();
                if( $shop_staff_info ) return response()->json(['status'=>false,'errors'=>['message'=>['手機號碼已重複']]]);
            }else{
                // 新增
                $company_staff_info = new CompanyStaff;
            }
        }

        // 儲存商家服務資料
        $company_staff_info->company_id          = $company_info->id;
        $company_staff_info->name                = request('name');
        $company_staff_info->nick_name           = request('nick_name');
        $company_staff_info->phone               = request('phone');
        $company_staff_info->staff_no            = request('staff_no');
        $company_staff_info->company_title_id_a  = request('company_title_id_a');
        // $company_staff_info->company_title_id_b  = isset(request('title')[1]) ? request('title')[1] : NULL;
        $company_staff_info->onboard             = request('onboard');
        $company_staff_info->line_id             = request('line_id');
        $company_staff_info->info                = request('info');
        $company_staff_info->reservation_limit   = request('reservation_limit');
        $company_staff_info->save();

        // 處理大頭照與背景圖
        if( request('photo') && preg_match('/base64/i',request('photo')) ){
            $photo_picName = PhotoController::save_base64_photo($shop_info->alias,request('photo'),$company_staff_info->photo);
            $company_staff_info->photo = $photo_picName;
        } 
        if( request('banner') && preg_match('/base64/i',request('banner')) ){
            $banner_picName = PhotoController::save_base64_photo($shop_info->alias,request('banner'),$company_staff_info->banner);
            $company_staff_info->banner = $banner_picName;
        } 
        $company_staff_info->save();

        if( !$shop_staff_id ){
			$shop_staff_info = new ShopStaff;
			$shop_staff_info->shop_id            = $shop_id;
			$shop_staff_info->company_staff_id   = $company_staff_info->id;
			$shop_staff_info->company_title_id_a = request('company_title_id_a');
            $shop_staff_info->nickname           = request('nick_name');
	        $shop_staff_info->save(); 

            // 建立管理台登入帳號
            $password = rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);

            $user = User::where('phone',request('phone'))->first();
            if( !$user ){
                $user = new User;
                $user->name     = request('name');
                $user->phone    = request('phone');
                $user->photo    = $company_staff_info->photo;
                $user->banner   = $company_staff_info->banner;
                $user->password = password_hash($password, PASSWORD_DEFAULT);
                $user->save();

                $shop_staff_info->user_id = $user->id;
                $shop_staff_info->save();

                // 寄送簡訊
                $store_name = $shop_info->name;
                if( mb_strwidth($store_name) >= 20 || (!preg_match("/^([A-Za-z]+)$/", $store_name)) ) $store_name = Controller::cut_str( $store_name , 0 , 20 );
                $sendword = '您在「'.$store_name.'」的員工資料已開通，第一次登入密碼為「'.$password.'」，請至管理台登入並修改密碼 '.env('DOMAIN_NAME');
            }else{
                $shop_staff_info->user_id = $user->id;
                $shop_staff_info->save();
                // 在另一個集團已經有資料
                // 寄送簡訊
                $store_name = $shop_info->name;
                if( mb_strwidth($store_name) >= 20 || (!preg_match("/^([A-Za-z]+)$/", $store_name)) ) $store_name = Controller::cut_str( $store_name , 0 , 20 );
                $sendword = '您在「'.$store_name.'」的員工資料已開通，可至管理台登入確認'.env('DOMAIN_NAME');
            }   

            Controller::send_phone_message(request('phone'),$sendword,$shop_info);
            
            // company_staff資料加入user_id
            $company_staff_info->user_id = $user->id;
            $company_staff_info->save();

            // 建立員工權限
            $permission = new Permission;
            $permission->user_id       = $user->id;
            $permission->company_id    = $company_info->id;
            $permission->shop_id       = $shop_id;
            $permission->shop_staff_id = $shop_staff_info->id;
            $permission->buy_mode_id   = $shop_info->buy_mode_id;
            $permission->permission    = implode(',',PermissionMenu::where('value','like','staff_%')->pluck('value')->toArray());
            $permission->save(); 
            
            // 儲存員工服務時間
            $insert = [];
            $business_hours = ShopBusinessHour::where('shop_id',$shop_id)->where('shop_staff_id',NULL)->get();
            foreach( $business_hours as $business_hour ){
                $insert[] = [
                    'shop_id'       => $shop_id,
                    'shop_staff_id' => $shop_staff_info->id,
                    'type'          => false,
                    'week'          => $business_hour->week,
                    'start'         => date('H:i:s',strtotime($business_hour->start)),
                    'end'           => date('H:i:s',strtotime($business_hour->end)),
                ];
            }
            ShopBusinessHour::insert($insert);

            // 固定公休
            $close_data = ShopClose::where('shop_id',$shop_id)->where('shop_staff_id',null)->first();
            $close = new ShopClose;
            $close->shop_id       = $shop_id;
            $close->shop_staff_id = $shop_staff_info->id;
            $close->type          = $close_data->type;
            $close->week          = $close_data->type != 0 ? $close_data->week : NULL;
            $close->save();
            
        }else{
            // 更新user資料
            $user_id = Permission::where('shop_staff_id',$shop_staff_id)->first()->user_id;
            $user = User::where('id',$user_id)->first();
            $user->name     = request('name');
            $user->phone    = request('phone');
            $user->photo    = $company_staff_info->photo;
            $user->banner   = $company_staff_info->banner;
            $user->save();

            // 更新商家員工資料
            $shop_staff_info = ShopStaff::find($shop_staff_id);
            $shop_staff_info->user_id            = $user_id;
            $shop_staff_info->nickname           = request('nick_name');
            $shop_staff_info->company_title_id_a = request('company_title_id_a');
            $shop_staff_info->save(); 
        }

        return response()->json(['status'=>true,'staff_id'=>$shop_staff_info->id]);
    }

    // 員工修改密碼
    public function shop_staff_change_password($shop_id,$shop_staff_id)
    {
        // 驗證欄位資料
        $rules = [
            'old_password' => 'required', 
            'new_password' => 'required', 
            're_password'  => 'required', 
        ];

        $messages = [
            'old_password.required' => '請填寫舊密碼',
            'new_password.required' => '請填寫新密碼',
            're_password.required'  => '請填寫再次輸入密碼',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_staff_info    = ShopStaff::find($shop_staff_id);
        $company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
        if( !$company_staff_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }

        // 確認新密碼與再次輸入密碼是否相同
        if( request('new_password') != request('re_password') ){
            return response()->json(['status'=>false,'errors'=>['message'=>['請確認新密碼與再次輸入密碼是否相同']]]);
        }

        $user = User::find($company_staff_info->user_id);

        if( !$user ){
            return response()->json(['status'=>false,'errors'=>['message'=>['此員工尚未建立帳號']]]);
        }

        if( password_verify(request('old_password'), $user->password) ){
            // 舊密碼比對正確，可以進行修改密碼
            $user->password = password_hash(request('new_password'),PASSWORD_DEFAULT);
            $user->save();
            return response()->json(['status'=>true]);
        }else{
            // 舊密碼輸入錯誤
            return response()->json(['status'=>false,'errors'=>['message'=>['舊密碼輸入錯誤']]]);
        }
    }

    // 員工忘記密碼發送驗證碼
    public function shop_staff_send_verification_code($shop_id,$shop_staff_id)
    {
        // 驗證欄位資料
        $rules = [
            'phone' => 'required', 
        ];

        $messages = [
            'phone.required' => '請輸入手機號碼',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_staff_info    = ShopStaff::find($shop_staff_id);
        $company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
        if( !$company_staff_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $res = Controller::send_verification_code($shop_info);

        return response()->json($res);
    }

    // 員工忘記密碼確認驗證碼
    public function shop_staff_check_verification_code($shop_id,$shop_staff_id)
    {
        // 驗證欄位資料
        $rules = [
            'phone' => 'required', 
            'code'  => 'required',
        ];

        $messages = [
            'phone.required' => '請輸入手機號碼',
            'code.required'  => '請輸入驗證碼'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_staff_info    = ShopStaff::find($shop_staff_id);
        $company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
        if( !$company_staff_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }

        $check = \DB::table('phone_check')->where('phone',request('phone'))->where('phone_check',request('code'))->first();
        if ( !$check ){
            return response()->json(['status' => false, 'errors' => ['message'=>['請檢查簡訊訊驗證碼是否輸入正確']]]);
        }
        \DB::table('phone_check')->where('phone',request('phone'))->where('phone_check',request('code'))->delete();

        return response()->json(['status' => true]);
    }

    // 員工忘記密碼更換新密碼
    public function shop_staff_new_password($shop_id,$shop_staff_id)
    {
        // 驗證欄位資料
        $rules = [
            'new_password' => 'required', 
            're_password'  => 'required',
        ];

        $messages = [
            'new_password.required' => '請填寫新密碼',
            're_password.required'  => '請填寫再次輸入密碼'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_staff_info    = ShopStaff::find($shop_staff_id);
        $company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
        if( !$company_staff_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }

        // 確認新密碼與再次輸入密碼是否相同
        if( request('new_password') != request('re_password') ){
            return response()->json(['status'=>false,'errors'=>['message'=>['請確認新密碼與再次輸入密碼是否相同']]]);
        }

        $user = User::find($company_staff_info->user_id);
        $user->password = password_hash(request('new_password'),PASSWORD_DEFAULT);
        $user->save();

        return response()->json(['status'=>true]);
    }

    // 解聘員工資料
    public function shop_staff_fire($shop_id,$shop_staff_id)
    {
    	$shop_staff_info    = ShopStaff::find($shop_staff_id);
    	$company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
    	if( !$company_staff_info ){
    	    return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
    	}

        // 判斷是否是自己的員工資料，員工不可解聘自己
        $permission = Permission::where('user_id',auth()->user()->id)->where('shop_staff_id',$shop_staff_id)->first();
        if( $permission ){
            return response()->json(['status'=>false,'errors'=>['message'=>['無法將自己的員工資料做離職動作']]]);
        }

        // 若有綁定google calendar時，需同時解除綁定事件
        $customer_reservations = CustomerReservation::where('shop_staff_id',$shop_staff_id)->get();
        foreach( $customer_reservations as $reservation ){
            $token = $reservation->staff_info->calendar_token;
            if( $token && $reservation->google_calendar_id != '' ){
                $job = new DeleteGoogleCalendarEvent($reservation,$reservation->staff_info,$token);
                dispatch($job);
            }
        }

    	// 解聘員工
    	$company_staff_info->fire_time = date('Y-m-d H:i:s');
    	$company_staff_info->save();

    	return response()->json(['status'=>true]);
    }

    // 復職員工資料
    public function shop_staff_recover($shop_id,$shop_staff_id)
    {
        $shop_staff_info    = ShopStaff::find($shop_staff_id);
        $company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
        if( !$company_staff_info ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }

        // 判斷是否是自己的員工資料，員工不可解聘自己
        $permission = Permission::where('user_id',auth()->user()->id)->where('shop_staff_id',$shop_staff_id)->first();
        if( $permission ){
            return response()->json(['status'=>false,'errors'=>['message'=>['無法將自己的員工資料做復職動作']]]);
        }

        // 復職員工
        $company_staff_info->fire_time = NULL;
        $company_staff_info->save();

        return response()->json(['status'=>true]);
    }

    // 刪除商家員工資料
    public function shop_staff_delete($shop_id,$shop_staff_id)
    {
    	$shop_staff_info    = ShopStaff::find($shop_staff_id);
    	$company_staff_info = CompanyStaff::find($shop_staff_info->company_staff_id);
    	if( !$company_staff_info ){
    	    return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
    	}

        // 判斷是否是自己的員工資料，員工不可刪除自己
        $permission = Permission::where('user_id',auth()->user()->id)->where('shop_staff_id',$shop_staff_id)->first();
        if( $permission ){
            return response()->json(['status'=>false,'errors'=>['message'=>['無法將自己的員工資料做刪除動作']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團員工

            // 刪除員工的圖片
            if( $company_staff_info->photo ){
                $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$company_staff_info->photo;
                if(file_exists($filePath)){
                    unlink($filePath);
                }
            }

            // 刪除員工的背景圖
            if( $company_staff_info->banner ){
                $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$company_staff_info->banner;
                if(file_exists($filePath)){
                    unlink($filePath);
                }
            }

            // 將員工資料徹底移除
            $company_staff_info->calendar_token = NULL;
            $company_staff_info->save();
            $company_staff_info->delete();
        }

        // 移除歸屬會員
        ShopCustomer::where('shop_id',$shop_id)->where('shop_staff_id',$shop_staff_id)->update(['shop_staff_id'=>NULL]);
        ShopStaff::where('id',$shop_staff_id)->delete();

        // 移除員工權限
        Permission::where('shop_staff_id',$shop_staff_id)->delete();
    	
    	return response()->json(['status'=>true]);
    }

    // 新增/編輯員工服務項目
    public function shop_staff_service($shop_id,$shop_staff_id)
    {
        if( $shop_staff_id ){
            $shop_staff_info = ShopStaff::find($shop_staff_id);
            if( !$shop_staff_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
            }

            // 判斷是編輯還是新建
            if( $shop_staff_info->staff_services->count() == 0 ){
                $type = 'create';
            }else{
                $type = 'edit';
            }
        }else{
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // $permission_text = 'shop_staff_service_';
        // if( $type == 'edit' ){
        //     $user = User::where('id',auth()->user()->id)->with(['permissions'])->first();
        //     $t = 0;
        //     foreach( $user->permissions as $permission ){
        //         if( !$permission->shop_id && $permission->company_id ){
        //             // 代表是管理者登入
        //             $t = 1;
        //         }
        //     }

        //     if( $t == 0 ){
        //         // 代表是員工自己登入
        //         $permission_text = 'staff_self_service_';
        //     }
        // }

        if( PermissionController::is_staff($shop_id) ){
            // 員工身分
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if( $user_staff_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_staff_permission['errors']]]]);

            if (!in_array('staff_self_service_edit', $user_staff_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $user_shop_permission = $user_staff_permission;

            $permission_text = 'staff_self_service_';
            $type = 'edit';
        }else{
            // 拿取使用者的商家權限
            $user_shop_permission = PermissionController::user_shop_permission($shop_id);
            if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

            // 確認頁面瀏覽權限
            // if( !in_array( $per,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

            $permission_text = 'shop_staff_service_';
        } 

        // 確認頁面瀏覽權限
        if( !in_array($permission_text.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_service_categories = ShopServiceCategory::where('shop_id',$shop_id)->select('id','name')->get();
        foreach( $shop_service_categories as $service_category ){
            $service_category->shop_services = ShopService::where('shop_service_category_id',$service_category->id)->select('id','name')->get();
            foreach( $service_category->shop_services as $service ){
                $shop_service_staff = ShopServiceStaff::where('shop_service_id',$service->id)->pluck('shop_staff_id')->toArray();
                if( in_array( $shop_staff_id , $shop_service_staff) ){
                    $service->selected = true;
                }else{
                    $service->selected = false;
                }
            }
        }

        $data = [
            'status'          => true,
            'permission'      => true,
            'edit_permission' => in_array($permission_text.$type.'_match',$user_shop_permission['permission']) ? true : false,
            'data'            => $shop_service_categories,
        ];

        return response()->json($data);
    }

    // 儲存商家員工服務項目資料
    public function shop_staff_service_save($shop_id,$shop_staff_id)
    {
        $return_data = request()->all();

        ShopServiceStaff::where('shop_staff_id',$shop_staff_id)->delete();
        $insert = [];
        foreach( $return_data as $category ){
            foreach( $category['shop_services'] as $service ){
                if( $service['selected'] == true ){
                    $insert[] = [
                        'shop_staff_id'   => $shop_staff_id,
                        'shop_service_id' => $service['id'],
                    ];
                }
            }
        }

        ShopServiceStaff::insert($insert);

        return response()->json(['status'=>true,'data'=>$return_data]);
    }

    // 編輯員工作品
    public function shop_staff_collect($shop_id,$shop_staff_id)
    {
        if( $shop_staff_id ){
            $shop_staff_info = ShopStaff::find($shop_staff_id);
            if( !$shop_staff_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
            }
        }else{
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( PermissionController::is_staff($shop_id) ){
            $permission_text      = 'staff_self';
            $user_shop_permission = PermissionController::user_staff_permission($shop_id);
        }else{
            $permission_text      = 'shop_staff';
            $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        }

        // 拿取使用者的商家權限
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);
        if( !in_array($permission_text.'_collection_edit',$user_shop_permission['permission']) ) 
            return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 代表作品
        $representative_work = Album::where('type','representative_work')->where('staff_id',$shop_staff_id)->first();
        if( !$representative_work ) $representative_work = new Album;
        $representative_work_photos = $representative_work->photos;
        
        $photo_data = [];
        foreach( $representative_work_photos as $photo ){
            $photo_data[] = [
                'id'       => $photo->id,
                'photo_id' => $photo->photo_id,
                'cover'    => $photo->cover,
                'photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo,
                'date'     => date('Y年m月',strtotime($photo->created_at)),
            ];
        }

        // 既有相簿選項
        // 依月份分相簿
        $shop_albums = Album::where('shop_id',$shop_id)->pluck('id')->toArray();
        $shop_photos = AlbumPhoto::whereIn('album_id',$shop_albums)
                                     ->orderBy('album_photos.created_at','DESC')
                                     ->get();
        $select_albums = []; 
        foreach( $shop_photos as $photo ){
            // 顯示是否被選取過
            $selected = false;
            foreach( $photo_data as $po ){
                if( $po['id'] == $photo->id ){
                    $selected = true;
                    break;
                }
            }

            $check_month = false;
            foreach( $select_albums as $k => $album ){
                if( $album['date'] == date('Y年m月',strtotime($photo->created_at)) ){
                    $check_month = true;

                    $same_photo = false;
                    foreach( $select_albums[$k]['photos'] as $sa ){
                        if( $sa['photo_id'] == $photo->photo_id ){
                            $same_photo = true;
                            break;
                        }
                    }

                    if( $same_photo == false ){
                        $select_albums[$k]['photos'][] = [
                            'id'       => $selected ? $photo->id : '',
                            'album_id' => $photo->album_id,
                            'photo_id' => $photo->photo_id,
                            'photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo,
                            'selected' => $selected,
                            'cover'    => $representative_work->id == $photo->album_id ? $photo->cover : 'N',
                            'date'     => date('Y年m月',strtotime($photo->created_at)),
                        ];
                    }
                    break;
                }
            }

            if( $check_month == false ){
                $select_albums[] = [
                    'date'   => date('Y年m月',strtotime($photo->created_at)),
                    'photos' => [[
                        'id'       => $selected ? $photo->id : '',
                        'album_id' => $photo->album_id,
                        'photo_id' => $photo->photo_id,
                        'photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo,
                        'selected' => $selected,
                        'cover'    => $representative_work->id == $photo->album_id ? $photo->cover : 'N',
                        'date'     => date('Y年m月',strtotime($photo->created_at)),
                    ],]
                ];
            }
        }
        
        $data = [
            'status'                               => true,
            'permission'                           => true,
            'permission_text'                      => $permission_text,
            'representative_work_photo_permission' => in_array($permission_text.'_representative_work_photo',$user_shop_permission['permission']) ? true : false,
            'select_albums'                        => $select_albums,
            'data'                                 => $photo_data ,
        ];

        return response()->json($data);
    }

    // 儲存員工代表作品
    public function shop_staff_collect_save($shop_id,$shop_staff_id)
    {
        if( $shop_staff_id ){
            $shop_staff_info = ShopStaff::find($shop_staff_id);
            if( !$shop_staff_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
            }
        }else{
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }

        $user_info    = auth()->User();
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $photo_data = request()->all();

        // 代表作品
        $representative_work = Album::where('type','representative_work')->where('staff_id',$shop_staff_id)->first();
        if( !$representative_work ){
            $representative_work = new Album;
            $representative_work->type     = 'representative_work';
            $representative_work->staff_id = $shop_staff_id;
            $representative_work->shop_id  = $shop_id;
            $representative_work->name     = '員工作品';
            $representative_work->sequence = 1;
            $representative_work->save();
        } 

        // 處理貼文裡的照片
        $old_photo_id    = [];
        $insert          = [];
        $select_photos   = [];
        $old_photo_cover = [];
        foreach( $photo_data as $photo ){
            if( $photo['id'] != '' ){
                $old_photo_id[] = $photo['id'];
                // 先將舊有照片裡，有被設成cover的先記錄起來
                if( $photo['cover'] == 'Y' ){
                    $old_photo_cover[] = $photo['id'];
                    AlbumPhoto::where('id',$photo['id'])->update(['cover'=>'Y']);   
                }
            } else {
                if( $photo['photo_id'] != "" || $photo['photo_id'] != NULL ){
                    // 既有的照片加入
                    $select_photos[] = [
                        'album_id' => $photo['album_id'],
                        'photo_id' => $photo['photo_id'],
                        'cover'    => $photo['cover'],
                    ];
                }else{
                    // 新上傳的照片
                    $insert[] = [
                        'photo' => $photo['photo'],
                        'cover' => $photo['cover'],
                    ];
                }
            }               
        }

        AlbumPhoto::whereNotIn('id',$old_photo_cover)->update(['cover'=>'N']);  

        // 先刪除照片
        $delete_photos = AlbumPhoto::where('album_id',$representative_work->id)
                                    ->whereNotIn('album_photos.id',$old_photo_id)
                                    ->join('photos', 'album_photos.photo_id', '=', 'photos.id')
                                    ->get();
        
        foreach( $delete_photos as $dp ){
            // 先檢查此張照片是否有被其他相簿存放，若有就只刪除相簿關連，若都沒有其他相簿存放，就直接刪除照片
            $photo_count = AlbumPhoto::where('photo_id',$dp->photo_id)->get()->count();
            if( $photo_count == 1 ){
                // 刪除檔案
                $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$dp->photo;
                if(file_exists($filePath)){
                    unlink($filePath);
                }
                Photo::find($dp->photo_id)->delete();
            }
        }
        // 刪除相簿與照片的關連
        AlbumPhoto::where('album_id',$representative_work->id)->whereNotIn('album_photos.id',$old_photo_id)->delete();

        // 儲存既有相片
        foreach( $select_photos as $k => $data ){
            $new_album_photo = new AlbumPhoto;
            $new_album_photo->album_id = $representative_work->id;
            $new_album_photo->photo_id = $data['photo_id'];
            $new_album_photo->cover    = $data['cover']?:'N';
            $new_album_photo->save();
        }

        // 儲存新照片
        $inser_photo = [];
        foreach( $insert as $k => $data ){

            $picName = PhotoController::save_base64_photo($shop_info->alias,$data['photo']);

            $new_photo = new Photo;
            $new_photo->user_id = $user_info->id;
            $new_photo->photo   = $picName;
            $new_photo->save();

            $new_album_photo = new AlbumPhoto;
            $new_album_photo->album_id = $representative_work->id;
            $new_album_photo->photo_id = $new_photo->id;
            $new_album_photo->cover    = $data['cover']?:'N';
            $new_album_photo->save();
        }

        return response()->json(['status'=>true]);
    }

    // 編輯員工設定資料
    public function shop_staff_set($shop_id,$shop_staff_id)
    {
        if( $shop_staff_id ){
            $shop_staff_info = ShopStaff::find($shop_staff_id);
            if( !$shop_staff_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
            }
        }else{
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( PermissionController::is_staff($shop_id) ){
            $permission_text      = 'staff_self_set_edit';
            $user_shop_permission = PermissionController::user_staff_permission($shop_id);
        }else{
            $permission_text      = 'shop_staff_set_edit';
            $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        }

        // 拿取使用者的商家權限
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);
        if( !in_array($permission_text,$user_shop_permission['permission']) ) 
            return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 可預約時段
        $business_hour  = ShopBusinessHour::where('shop_id',$shop_info->id)->where('shop_staff_id',$shop_staff_id)->orderBy('start')->get();   
        $business_hours = []; 

        for( $i = 1 ; $i <= 7 ; $i++ ){
            $business_hours[$i-1] = [
                'type'    => true,
                'week'    => $i,
                'time'    => [],
                'default' => [],
            ];
            $defaulf_business_hour = ShopBusinessHour::where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->where('week',$i)->get();
            foreach( $defaulf_business_hour as $dbh ){
                $business_hours[$i-1]['default'][] = [
                    'start' => date('c',strtotime(date('Y-m-d H:i:s',strtotime($dbh->start)))),
                    'end'   => date('c',strtotime(date('Y-m-d H:i:s',strtotime($dbh->end)))),
                ];
            }

            if( $business_hour->where('week',$i)->count() == 0 ){
                // 沒有預設的營業時間
                $business_hours[$i-1]['type']   = false;
                $business_hours[$i-1]['time'][] = [
                    'start' => '',
                    'end'   => '',
                ];
            }else{
                // 已經有預設的營業時間
                foreach( $business_hour->where('week',$i)  as $hour ){
                    if( $hour->type == 0 ){
                        $business_hours[$i-1]['type'] = false;
                    }
                    $business_hours[$i-1]['time'][] = [
                        'start' => strtotime($hour->start) < strtotime($hour->end) 
                                                ? date('c',strtotime(date('Y-m-d H:i:s',strtotime($hour->start)))) 
                                                : date('c',strtotime(date('Y-m-d H:i:s',strtotime($hour->end)))),
                        'end'   => strtotime($hour->start) > strtotime($hour->end) 
                                                ? date('c',strtotime(date('Y-m-d H:i:s',strtotime($hour->start)))) 
                                                : date('c',strtotime(date('Y-m-d H:i:s',strtotime($hour->end)))),
                    ];
                }
            }
        }

        // 間隔公休日
        $close = ShopClose::where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->first();
        if( !$close ){
            $close = [
                'type' => NULL,
                'week' => ''
            ]; 
        }else{
            $close->type = (string)$close->type;
        }

        // 特殊休假日
        $vacation = $shop_staff_info->vacations;
        foreach( $vacation as $v ){
            $v->start_time = $v->start_time ? date('c',strtotime(date('Y-m-d H:i:s',strtotime($v->start_time)))) : NULL;
            $v->end_time   = $v->end_time   ? date('c',strtotime(date('Y-m-d H:i:s',strtotime($v->end_time))))   : NULL;
        }
        if( $vacation->count() == 0 ){
            $vacation[] = [
                "id"            => null,
                "shop_id"       => 1,
                "shop_staff_id" => 4,
                "type"          => null,
                "start_date"    => null,
                "start_time"    => null,
                "end_date"      => null,
                "end_time"      => null,
                "note"          => null,
            ];
        }

        // google行事曆綁定與解除綁定連結
        if( !$shop_staff_info->company_staff_info->calendar_token ){
            $google_url = GoogleCalendarController::get_connect_url($shop_staff_id);
        }else{
            $google_url = url("/api/shop/".$shop_info->id."/disconect/staff/".$shop_staff_id."/googleCalendar");
        }

        // 判斷是否可以調整權限
        $show_all_customer_permission    = false;
        $show_all_reservation_permission = false;
        $edit_all_reservation_permission = false;
        if (!PermissionController::is_staff($shop_id)) {
            // 老闆身分，判斷此員工是不是自己，若是！則不調整權限
            $shop_staff_self = Permission::where('user_id', auth()->getUser()->id)->where('shop_id', $shop_id)->where('shop_staff_id','!=',NULL)->first()->shop_staff_id;
            // 可不可以審核別人的預約
            if ($shop_staff_info->id != $shop_staff_self) {
                $show_all_customer_permission    = true;
                $show_all_reservation_permission = true;
                $edit_all_reservation_permission = true;
            }   
        } 

        $data = [
            'role'                            => '',
            'role_permission'                 => in_array($permission_text.'_role',$user_shop_permission['permission']) ? true : false,
            'google_calendar_status'          => $shop_staff_info->company_staff_info->calendar_token ? '解綁定' : '綁定',        
            'google_calendar'                 => $google_url,
            'google_calendar_permission'      => in_array($permission_text.'_google_calendar',$user_shop_permission['permission']) ? true : false,
            'calendar_color_type'             => $shop_staff_info->company_staff_info->calendar_color_type,
            'calendar_color_type_permission'  => in_array($permission_text.'_google_calendar',$user_shop_permission['permission']) ? true : false,
            'calendar_color'                  => $shop_staff_info->company_staff_info->calendar_color,
            'calendar_color_permission'       => in_array($permission_text.'_google_calendar',$user_shop_permission['permission']) ? true : false,
            'reservation_limit'               => $shop_staff_info->company_staff_info->reservation_limit,
            'reservation_limit_permission'    => in_array($permission_text.'_reservation_limit',$user_shop_permission['permission']) ? true : false,
            'business_hours'                  => $business_hours,
            'business_hours_permission'       => in_array($permission_text.'_business_hour',$user_shop_permission['permission']) ? true : false,
            'close'                           => $close,
            'close_permission'                => in_array($permission_text.'_close',$user_shop_permission['permission']) ? true : false,
            'vacation'                        => $vacation,
            'vacation_permission'             => in_array($permission_text.'_vacation',$user_shop_permission['permission']) ? true : false,
            'show_all_customer'               => $shop_staff_info->company_staff_info->show_all_customer,
            'show_all_customer_permission'    => $show_all_customer_permission,
            'show_all_reservation'            => $shop_staff_info->company_staff_info->show_all_reservation,
            'show_all_reservation_permission' => $show_all_reservation_permission,
            'edit_all_reservation'            => $shop_staff_info->company_staff_info->edit_all_reservation,
            'edit_all_reservation_permission' => $edit_all_reservation_permission,
        ];

        $data = [
            'status'              => true,
            'permission'          => true,
            'permission_text'     => $permission_text,
            'set_save_permission' => in_array($permission_text.'_save',$user_shop_permission['permission']) ? true : false,
            'data'                => $data,
        ];

        return response()->json($data);
    }
    
    // 儲存員工設定資料
    public function shop_staff_set_save($shop_id,$shop_staff_id)
    {
        if( $shop_staff_id ){
            $shop_staff_info = ShopStaff::find($shop_staff_id);
            if( !$shop_staff_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
            }
        }else{
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到員工資料']]]);
        }

        $user_info    = auth()->User();
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 角色與權限

        // 基本設定
        $shop_staff_info->company_staff_info->calendar_color       = request('calendar_color');
        $shop_staff_info->company_staff_info->reservation_limit    = request('reservation_limit');
        $shop_staff_info->company_staff_info->show_all_customer    = request('show_all_customer');
        $shop_staff_info->company_staff_info->show_all_reservation = request('show_all_reservation');
        $shop_staff_info->company_staff_info->edit_all_reservation = request('edit_all_reservation');
        $shop_staff_info->company_staff_info->save();

        // 可預約時間
        $insert = [];
        ShopBusinessHour::where('shop_id',$shop_id)->where('shop_staff_id',$shop_staff_id)->delete();
        foreach( request('business_hours') as $business_hour ){
            if( $business_hour['type'] ){
                foreach( $business_hour['time'] as $time ){
                    $insert[] = [
                        'shop_id'       => $shop_id,
                        'shop_staff_id' => $shop_staff_id,
                        'type'          => $business_hour['type'],
                        'week'          => $business_hour['week'],
                        'start'         => date('H:i:s',strtotime($time['start'])),
                        'end'           => date('H:i:s',strtotime($time['end'])),
                    ];
                }
            }else{
                foreach( $business_hour['default'] as $time ){
                    $insert[] = [
                        'shop_id'       => $shop_id,
                        'shop_staff_id' => $shop_staff_id,
                        'type'          => $business_hour['type'],
                        'week'          => $business_hour['week'],
                        'start'         => date('H:i:s',strtotime($time['start'])),
                        'end'           => date('H:i:s',strtotime($time['end'])),
                    ];
                }
            }
        }
        ShopBusinessHour::insert($insert);

        // 固定公休
        ShopClose::where('shop_id',$shop_id)->where('shop_staff_id',$shop_staff_id)->delete();
        $close_data = request('close');
        $weeks = explode(',',$close_data['week']);
        $weeks = array_filter($weeks);

        $close = new ShopClose;
        $close->shop_id       = $shop_id;
        $close->shop_staff_id = $shop_staff_id;
        $close->type          = $close_data['type'];
        $close->week          = $close_data['type'] != 0 ? implode(',', $weeks) : NULL;
        $close->save();

        // 特殊休假日
        ShopVacation::where('shop_id',$shop_id)->where('shop_staff_id',$shop_staff_id)->delete();
        $insert = [];
        foreach( request('vacation') as $vacation ){
            if( $vacation['type'] != '' ){
                $insert[] = [
                    'shop_id'       => $shop_id,
                    'shop_staff_id' => $shop_staff_id,
                    'type'          => $vacation['type'],
                    'start_date'    => $vacation['start_date'],
                    'start_time'    => $vacation['type'] == 2 ? ($vacation['start_time'] > $vacation['end_time'] ? date('H:i',strtotime($vacation['end_time'])) : date('H:i',strtotime($vacation['start_time'])) ) : NULL,
                    'end_date'      => $vacation['type'] == 3 || $vacation['type'] == 2 ? $vacation['start_date'] : $vacation['end_date'],
                    'end_time'      => $vacation['type'] == 2 ? ($vacation['start_time'] < $vacation['end_time'] ? date('H:i',strtotime($vacation['end_time'])) : date('H:i',strtotime($vacation['start_time'])) ) : NULL,
                    'note'          => $vacation['note'],
                ]; 
            }
            
        }
        ShopVacation::insert($insert);

        return response()->json(['status'=>true]);
    }

    // 取得指定商家的服務人員(按職稱分類版本)
    static public function shop_staff_sort($shop_id)
    {
    	$user = auth()->user();

        // 確認user對應的分店資料
        $user_shop = Permission::where('user_id',$user->id)->where('shop_id',$shop_id)->first();
        if( !$user_shop ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到商家資料']]]);
        }

        $shop_info   = Shop::find($shop_id);
        $shop_staffs = ShopStaff::where('shop_id',$shop_id)->orderBy('company_title_id_a','DESC')->get();

        $data = [];
        foreach( $shop_staffs as $k => $staff ){

            $company_staff_info = $staff->company_staff_info;

            if( $company_staff_info->fire_time ) continue;

            // 根據有沒有職稱做分類
            if( $staff->company_title_id_a != NULL ){
                // 有職稱
                $company_title_info = $staff->company_title_a_info;

                $chk = 0 ;
                foreach( $data as $k => $rd ){
                    if( $rd['title'] == $company_title_info->name ){
                        $chk = 1;
                        $data[$k]['staffs'][] = [
                            'id'   => $staff->id,
                            'name' => $company_staff_info->name,
                        ];
                        break;
                    }
                }

                if( $chk == 0 ){
                    $data[] = [
                        'title'  => $company_title_info->name,
                        'staffs' => [
                            [
                                'id'   => $staff->id,
                                'name' => $company_staff_info->name,
                            ],
                        ],
                    ];
                }
            }else{
                // 沒有職稱
                $chk = 0 ;
                foreach( $data as $k => $rd ){
                    if( $rd['title'] == '其他' ){
                        $chk = 1;
                        $data[$k]['staffs'][] = [
                            'id'   => $staff->id,
                            'name' => $company_staff_info->name,
                        ];
                        break;
                    }
                }

                if( $chk == 0 ){
                    $data[] = [
                        'title'  => '其他',
                        'staffs' => [
                            [
                                'id'   => $staff->id,
                                'name' => $company_staff_info->name,
                            ],
                        ],
                    ];
                }
            }
        }

        return $data;
    }

    // line bot 新增員工
    public function linebot_newStaff()
    {
        $shop_info = Shop::where('alias',request('company_id'))->first();

        $company_staff_info = new CompanyStaff;
        // 儲存商家員工資料
        $company_staff_info->company_id = $shop_info->company_info->id;
        $company_staff_info->name       = request('name');
        $company_staff_info->phone      = request('phone');
        $company_staff_info->onboard    = date('Y-m-d H:i:s');
        $company_staff_info->line_code  = request('line_code');
        $company_staff_info->save();

        $shop_staff_info = new ShopStaff;
        $shop_staff_info->shop_id          = $shop_info->id;
        $shop_staff_info->company_staff_id = $company_staff_info->id;
        $shop_staff_info->save(); 

        // 建立管理台登入帳號
        $password = rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);

        $user = new User;
        $user->name      = request('name');
        $user->phone     = request('phone');
        $user->photo     = $company_staff_info->photo;
        $user->banner    = $company_staff_info->banner;
        $user->password  = password_hash($password, PASSWORD_DEFAULT);
        $user->line_code = request('line_code');
        $user->save();

        // 寄送簡訊
        $store_name = $shop_info->name;
        if( mb_strwidth($store_name) >= 20 || (!preg_match("/^([A-Za-z]+)$/", $store_name)) ) $store_name = Controller::cut_str( $store_name , 0 , 20 );
        $sendword = '您在「'.$store_name.'」的員工資料已開通，第一次登入密碼為「'.$password.'」，請至管理台登入並修改密碼 '.env('DOMAIN_NAME');  

        Controller::send_phone_message(request('phone'),$sendword,$shop_info);
        
        // company_staff資料加入user_id
        $company_staff_info->user_id = $user->id;
        $company_staff_info->save();

        $shop_staff_info->user_id = $user->id;
        $shop_staff_info->save();

        // 建立員工權限
        $permission = new Permission;
        $permission->user_id       = $user->id;
        $permission->company_id    = $shop_info->company_info->id;
        $permission->shop_id       = $shop_info->id;
        $permission->shop_staff_id = $shop_staff_info->id;
        $permission->buy_mode_id   = $shop_info->buy_mode_id;
        $permission->permission    = implode(',',PermissionMenu::where('value','like','staff_%')->pluck('value')->toArray());
        $permission->save(); 
        
        // 儲存員工服務時間
        $insert = [];
        $business_hours = ShopBusinessHour::where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->get();
        foreach( $business_hours as $business_hour ){
            $insert[] = [
                'shop_id'       => $shop_info->id,
                'shop_staff_id' => $shop_staff_info->id,
                'type'          => false,
                'week'          => $business_hour->week,
                'start'         => date('H:i:s',strtotime($business_hour->start)),
                'end'           => date('H:i:s',strtotime($business_hour->end)),
            ];
        }
        ShopBusinessHour::insert($insert);

        // 固定公休
        $close_data = ShopClose::where('shop_id',$shop_info->id)->where('shop_staff_id',null)->first();
        $close = new ShopClose;
        $close->shop_id       = $shop_info->id;
        $close->shop_staff_id = $shop_staff_info->id;
        $close->type          = $close_data->type;
        $close->week          = $close_data->type != 0 ? $close_data->week : NULL;
        $close->save();
            
        return response()->json(['status' => true]);
    }
}
