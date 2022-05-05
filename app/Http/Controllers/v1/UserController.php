<?php

namespace App\Http\Controllers\v1;

use JWTAuth;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\BuyMode;
use App\Models\PermissionMenu;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopStaff;
use App\Models\ShopReservationMessage;
use App\Models\ShopReservationTag;
use App\Models\ShopBusinessHour;
use App\Models\ShopClose;
use App\Models\Company;
use App\Models\CompanyStaff;
use App\Models\SystemNotice;
use App\Models\ShopSet;
use App\Models\CompanyTitle;
use App\Jobs\GreateDefaultData;
use App\Models\LineControll;
use App\Models\UserTokenLog;
use DB;

class UserController extends Controller
{
    // 管理台使用者登入
    public function login()
    {
        // 驗證欄位資料
        $rules     = ['username' => 'required', 'password' => 'required|min:6'];
        $messages = [
            'username.required' => '請填寫帳號',
            'password.required' => '請填寫密碼',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 判斷加密部分是否符合，符合才可以進資料庫做比對[0]密碼[1]溝通協議
        $words = explode(env('KEY'), request('password'));
        if (password_verify(env('SERVER_BCRYPT'), $words[1])) {

            // 溝通協議正確
            $credentials = [
                'phone'    => request('username'),
                'password' => $words[0],
            ];

            if ( !$token = auth('api')->attempt($credentials) ) {
                return response()->json(['status'=>false,'errors' => ['message'=>['請確認帳號密碼輸入是否正確']]]);
            }

            $user         = User::where('id',auth()->user()->id)->with(['permissions.company_info','permissions.shop_info','permissions.shop_staff_info'])->first();
            $company_info = Company::find($user->permissions[0]->company_id);
            $shop_info    = Shop::where('company_id', $company_info->id)->first();

            $user->photo  = $user->photo  ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$user->photo : '';
            $user->banner = $user->banner ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$user->banner : '';

            // 處理方案資訊
            // $buy_mode_info = [
            //     'name'     => $company_info->buy_mode_info->title,
            //     'deadline' => $company_info->buy_mode_id == 0 ? '' : substr($company_info->deadline,0,10),
            //     'day'      => $company_info->buy_mode_id == 0 ? '' : round((strtotime($company_info->deadline) - strtotime(date('Y-m-d'))) / 86400) 
            // ];

            // 加入選擇頁面判斷            
            // 計算此帳號有多少集團、商家、員工身分
            $user_staff   = $user->permissions->where('shop_id','!=',NULL)->where('shop_staff_id','!=',NULL);
            $user_shop    = $user->permissions->where('shop_id','!=',NULL)->where('shop_staff_id',NULL);
            $user_company = $user->permissions->where('shop_id',NULL)->where('shop_staff_id',NULL);

            if( $user_staff->count() >= 1 && $user_shop->count() == 0 ){ // 只有店家員工身分

                $type = 'staff';  
                if( $user_staff->count() == 1 ){
                    // 單一員工
                    $home         = true;
                    $select_shops = [];

                    $shop_info = Shop::find( $user_staff->first()->shop_id );
                    $shop_info->type = $type;
                    $shop_info->logo = $shop_info->logo  ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_info->logo : '';

                    // $shop_staff = ShopStaff::where('shop_staffs.id',$user_staff->first()->shop_staff_id )
                    //                 ->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')->first();
                    // $shop_staff->photo = $shop_staff->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_staff->photo : '';

                }else{
                    // 多個商家的員工
                    $home       = false;
                    $shop_staff = $shop_info = [];
                    
                    $select_shops = Shop::whereIn('id',$user_staff->pluck('shop_id')->toArray())->get();
                    foreach( $select_shops as $shop ){
                        $shop->type       = $type;
                        $shop->shop_type  = '員工';
                        $shop->logo       = $shop->logo ? env('SHOW_PHOTO').'/api/show/'.$shop->alias.'/'.$shop->logo : '';

                        // $shop_staff_info = ShopStaff::where('shop_id',$shop->id)
                        //                     ->where('shop_staffs.id',$user->permissions->where('shop_id',$shop->id)->where('shop_staff_id','!=',NULL)->first()->shop_staff_id )
                        //                     ->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')->first();
                        // $shop_staff_info->photo = $shop_staff_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop->alias.'/'.$shop_staff_info->photo : '';
                        
                        // $shop->staff_info = $shop_staff_info;
                    }
                }                
            
            }elseif( $user->permissions->groupBy('company_id')->count() == 1 ){ // 擁有一間集團的老闆身分

                if( $user->permissions->whereNotNull('shop_id')->groupBy('shop_id')->count() == 1 ){
                    // 集團只有一個，商家是也是該集團下的，判斷是進入free首頁還是老闆首頁
                    $home = true;
                    $type = $user_company->first()->buy_mode_id == 0 ? 'free' : 'boss';

                    $select_shops = [];
                    $shop_info    = Shop::find( $user_shop->first()->shop_id );
                    $shop_staff   = ShopStaff::where('shop_staffs.id',$user_staff->first()->shop_staff_id )
                                               ->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')->first();
                    $shop_info->type = $type;
            
                }elseif( $user->permissions->whereNotNull('shop_id')->groupBy('shop_id')->count() > 1 ){
                    // 擁有a集團身分與b商家身分或是一個集團有很多商家
                    $home = false;
                    $shop_info = $shop_staff = [];
                    $shop_info    = Shop::whereIn('id',$user->permissions->pluck('shop_id')->toArray())->first();
                    $select_shops = Shop::whereIn('id',$user->permissions->pluck('shop_id')->toArray())->get();
                    $type = '';
                    foreach( $select_shops as $shop ){

                        if( $user_shop->where('shop_id',$shop->id)->first() ){
                            $t = 'boss';
                        }else{
                            $t = 'staff';
                        }

                        $shop->type      = $t;
                        $shop->shop_type = $t == 'boss' ? '店長' : '員工';
                        $shop->logo      = $shop->logo ? env('SHOW_PHOTO').'/api/show/'.$shop->alias.'/'.$shop->logo : '';
                    }

                }elseif( $user_shop->count() == 0 ){
                    // 只有一個集團權限，沒有商家權限

                }
            }elseif( $user->permissions->groupBy('company_id')->count() > 1 ){ // 身分權限在多間集團
                // 使用者可能在很多集團裡
                $home = false;
                $type = 'bose';

                $shop_info    = $shop_staff = [];
                $shop_info    = [];
                $select_shops = Shop::whereIn('id',$user->permissions->pluck('shop_id')->toArray())->get();
                $type = '';
                foreach( $select_shops as $shop ){

                    if( $user_shop->where('shop_id',$shop->id)->first() ){
                        $t = 'boss';
                    }else{
                        $t = 'staff';
                    }

                    $shop->type      = $t;
                    $shop->shop_type = $t == 'boss' ? '店長' : '員工';
                    $shop->logo      = $shop->logo ? env('SHOW_PHOTO').'/api/show/'.$shop->alias.'/'.$shop->logo : '';
                }
            }

            unset( $user->permissions );

            $data = [
                'status'          => true,
                'accessToken'     => $token,
                'user'            => $user,
                'home'            => $home,
                // 'type'            => $type,
                // 'buy_mode_info'   => $buy_mode_info,
                'shop_info'       => $shop_info,
                // 'shop_staff_info' => $shop_staff,
                'select_shops'    => $select_shops,
            ];

            UserTokenLog::insert([
                'user_id'    => $user->id,
                'token'      => $token,
                'status'     => 'login',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json($data);
            
        } else {
            // 溝通協議失敗
            return response()->json(['status'=>false,'errors' => ['message'=>['請確認帳號密碼輸入是否正確']]]);
        }
    }

    // 管理台註冊
    public function register()
    {
        // 驗證欄位資料
        $rules = [
            'company_name' => 'required', 
            'user_name'    => 'required', 
            'phone'        => 'required',
            'password'     => 'required|min:6',
            'repassword'   => 'required|min:6',
        ];

        $messages = [
            'company_name.required' => '請填寫行號/店名',
            'user_name.required'    => '請填寫姓名',
            'phone.required'        => '請填寫手機號碼',
            'password.required'     => '請填寫密碼',
            'repassword.required'   => '請填寫再次輸入密碼',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $password   = request('password');
        $repassword = request('repassword');

        // 檢查密碼與再次輸入密碼是否相同
        if ( $password != $repassword ){
            return response()->json(['status' => false,'errors' => ['message'=>['密碼與再次輸入密碼不相同']]]);
        } 

        $code_user = User::where('phone',request('recommend'))->first();

        $check_user = User::where('phone',request('phone'))->first();
        if( $check_user ) return response()->json(['status' => false,'errors' => ['message'=>['重複註冊']]]);

        $user = new User;
        $user->name     = request('user_name');
        $user->phone    = request('phone');
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->code     = $code_user ? $code_user->id : NULL; 
        $user->save();

        // 檢查是否重複ID
        do{
            $pattern = '1234567890ABCDEFGHJKLMNPQRSTUVWXYZ';
            $key = '';
            for($i=0;$i<7;$i++)   
            {   
                $key .= $pattern[mt_rand(0,33)];    //生成php隨機數   
            }

            // 隨機20碼檢查
            $check_id = Company::where('companyId',$key)->first();
        }while($check_id);

        // 建立company
        $company_info              = new Company;
        // $company_info->companyId   = 'S'.$key;
        $company_info->name        = request('company_name');
        // $company_info->buy_mode_id = request('recommend') == '0961331190' ? 1 : 0;
        // $company_info->gift_sms    = request('recommend') == '0961331190' ? 100 : 0;
        // $company_info->buy_sms     = 0;
        // $company_info->deadline    = request('recommend') == '0961331190' ? date('Y-m-d 23:59:59',strtotime('+30 day')) : NULL;
        $company_info->save(); 
        $company_info->companyId = 'C' . str_pad($company_info->id,8,"0",STR_PAD_LEFT);
        $company_info->save();

        // 建立shop
        $shop                      = new Shop;
        $shop->alias               = 'S'.$key;
        // $shop->buy_mode_id         = request('recommend') == '0961331190' ? 1 : 0;
        // $shop->gift_sms            = request('recommend') == '0961331190' ? 100 : 0;
        // $shop->deadline            = request('recommend') == '0961331190' ? date('Y-m-d 23:59:59',strtotime('+30 day')) : NULL;
        $shop->buy_mode_id         = 0;
        $shop->gift_sms            = 0;
        $shop->deadline            = NULL;
        $shop->buy_sms             = 0;
        $shop->company_id          = $company_info->id;
        $shop->name                = $company_info->name;
        $shop->phone               = request('phone');
        $shop->operating_status_id = 1;
        $shop->save();

        // 使用job建立初始資料
        $job = new GreateDefaultData($company_info,$shop,$user);
        dispatch($job);

        // 要檢查是否已綁定line＠
        $line_controll = LineControll::where('phone', request('phone'))->first();
        if( $line_controll ){
            $line_controll->status = 4;
            $line_controll->save();

            DB::connection('mysql2')->table('tb_lineControll')->where('phone',request('phone'))->update(['status'=>4]);

            $user->line_code = $line_controll->line_code;
            $user->save();
            
        }

        // 系統通知
        $system_notice = SystemNotice::where('shop_id',$shop->id)->get()->count();

        // 溝通協議正確
        $credentials = [
            'phone'    => $user->phone,
            'password' => $password,
        ];

        $token = auth('api')->attempt($credentials);

        $buy_mode_info = [
            'name'     => $shop->buy_mode_info->title,
            'deadline' => $shop->buy_mode_id == 0 ? '' : substr($shop->deadline,0,10),
            'day'      => $shop->buy_mode_id == 0 ? '' : round((strtotime($shop->deadline) - strtotime(date('Y-m-d'))) / 86400) 
        ];

        $data = [
            'status'          => true,
            'accessToken'     => $token,
            'user'            => $user,
            'home'            => false,
            'buy_mode_info'   => $buy_mode_info,
            'shop_info'       => $shop,
            'select_shops'    => [],
        ];

        return response()->json(['status' => true ,'data'=>$data ]);
    }

    // 管理台註冊/忘記密碼發送驗證碼
    public function auth_send_verification_code()
    {
        // 驗證欄位資料
        $rules = [
            'type'  => 'required',
            'phone' => 'required', 
        ];

        $messages = [
            'phone.required' => '請填寫手機號碼',
            'type.required'  => '缺少type資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $user = User::where('phone',request('phone'))->first();
        if( !$user && request('type') == 'forget' ){
            return response()->json(['status' => false,'errors' => ['message'=>['找不到使用者資料']]]);
        }

        if( $user && request('type') == 'register' ){
            return response()->json(['status' => false,'errors' => ['message'=>['此電話號碼已被註冊']]]);
        }

        $res = Controller::send_verification_code();

        return response()->json($res);
    }

    // 管理台註冊/忘記密碼簡訊驗證
    public function auth_check_verification_code()
    {
        // 驗證欄位資料
        $rules = [
            'phone' => 'required', 
            'code'  => 'required',
        ];

        $messages = [
            'phone.required' => '請填寫手機號碼',
            'code.required'  => '請填寫驗證碼',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $check = \DB::table('phone_check')->where('phone',request('phone'))->where('phone_check',request('code'))->first();
        if ( !$check ){
            return response()->json(['status' => false, 'errors' => ['message'=>['請檢查簡訊訊驗證碼是否輸入正確']]]);
        }
        \DB::table('phone_check')->where('phone',request('phone'))->where('phone_check',request('code'))->delete();

        return response()->json(['status' => true]);
    }

    // 管理台忘記密碼修改新密碼
    public function auth_new_password()
    {
        // 驗證欄位資料
        $rules = [
            'phone'        => 'required',
            'new_password' => 'required', 
            're_password'  => 'required',
        ];

        $messages = [
            'phone.required'        => '請填寫手機號碼',
            'new_password.required' => '請填寫新密碼',
            're_password.required'  => '請填寫再次輸入密碼',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 確認新密碼與再次輸入密碼是否相同
        if( request('new_password') != request('re_password') ){
            return response()->json(['status'=>false,'errors'=>['message'=>['請確認新密碼與再次輸入密碼是否相同']]]);
        }

        $user = User::where('phone',request('phone'))->first();
        if( !$user ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到使用者資料']]]);
        }
        $user->password = password_hash(request('new_password'),PASSWORD_DEFAULT);
        $user->save();

        return response()->json(['status'=>true]);
    }

    // 取得使用者管理得集團與商家
    public function user_permission()
    {
        $user = auth()->user();

        $company = [];
        $shops   = [];
        foreach( $user->permissions as $permission ){
            if( $permission->shop_id == NULL && $permission->company_info != NULL ){
                // 集團資料
                $company_info = $permission->company_info;
                $permission->company_info->shop_infos;

                // 計算簡訊總量
                $company_info->total_sms = $company_info->gift_sms+$company_info->buy_sms;

                // 計算平均每月使用簡訊數量
                $first_sms_data = $company_info->company_message_logs->first();
                if( !$first_sms_data ){
                    $use_month = 1;
                }else{
                    $first_sms_date = $first_sms_data->created_at;
                    $during_days = ( strtotime(date('Y-m-d')) - strtotime( substr($first_sms_date,0,10) ) ) / (60*60*24);
                    $use_month = ceil($during_days/30);
                }

                // 使用簡訊數量
                $total_use_sms = $company_info->company_message_logs->sum('use');

                $company_info->avg_sms = round($total_use_sms/$use_month,2);

                // 集團可使用的側邊欄
                $company_info->menu = $company_info->buy_mode_info->menu;
                if( date('Y-m-d') > substr($company_info->deadline,0,10) ){
                    $company_info->menu = BuyMode::where('id',0)->value('menu');
                }
                
                // 集團資料需加入會員數、員工數、商鋪數
                $company_info->staff_count = $company_info->company_staffs->count();
                $company_info->shop_count  = $company_info->shop_infos->count();
                
                $customer_count = 0;
                foreach( $company_info->shop_infos as $shop ){
                    $customer_count += $shop->shop_customers->count();
                }
                $company_info->customer_count = $customer_count;

                array_push($company, $company_info);

            }elseif( $permission->shop_id != NULL &&$permission->shop_info != NULL ){
                // 商家資料
                $shop_info = $permission->shop_info;
                $permission->shop_info->company_info;

                // 商家可使用的側邊欄
                $shop_info->menu = $shop_info->company_info->buy_mode_info->menu;
                if( date('Y-m-d') > substr($shop_info->company_info->deadline,0,10) ){
                    $shop_info->menu = BuyMode::where('id',0)->value('menu');
                }
                
                // 商家資料需加入會員數、員工數、服務數
                $shop_info->customer_count = $shop_info->shop_customers->count();
                $shop_info->staff_count    = $shop_info->shop_staffs->count();
                $shop_info->service_count  = $shop_info->shop_services->count();

                $chk = 0;
                foreach( $shops as $k => $sp ){
                    if( $sp['company'] == $permission->shop_info->company_info->name ){
                        $chk = 1;
                        $shops[$k]['shops'][] = $shop_info;
                        break;
                    }
                }

                if( $chk == 0 ){
                    $shops[] = [
                        'company' => $permission->shop_info->company_info->name,
                        'shops'   => $shop_info,
                    ];
                }
            }
        }

        if( count($company) == 0 && count($shops) == 0 ){
            return response()->json(['status'=>false,'errors'=>['message'=>['資料錯誤']]]);
        }
        
        return response()->json(['status'=>true,'company'=>$company,'shop'=>$shops]);
    } 

    // 使用者登出
    public function logout()
    {
        auth()->logout();
        return response()->json(['status'=>true]) ;
    }

    // jwt回傳測試
    public function me()
    {
        if ( !$user = JWTAuth::parseToken()->authenticate() ) {
            return response()->json(['user_not_found']);
        }

        return response()->json(auth()->user());
    }

}
