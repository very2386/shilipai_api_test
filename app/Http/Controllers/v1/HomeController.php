<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Validator;
use App\Models\Album;
use App\Models\Company;
use App\Models\CompanyServiceCategory;
use App\Models\CompanyService;
use App\Models\Shop;
use App\Models\ShopService;
use App\Models\Permission;
use App\Models\CustomerReservation;
use App\Models\ShopVacation;
use App\Models\ShopCustomer;
use App\Models\ShopStaff;
use App\Models\ShopPost;
use App\Models\User;
use App\Models\CompanyStaff;
use App\Models\SystemNotice;
use App\Models\BuyMode;

class HomeController extends Controller
{
    // 拿取管理台上方資料
    public function get_top_info($shop_id)
    {
        if( !auth()->getUser()->id ){
            $data = [
                'status' => false,
                'errors' => ['message'=>['找不到對應使用者資料']],
            ];

            return response()->json($data);
        }

        $shop_info = Shop::find($shop_id);
        if( !$shop_info ){
            $data = [
                'status' => false,
                'errors' => ['message'=>['找不到對應商家資料']],
            ];

            return response()->json($data);
        }

        $user = User::where('id',auth()->user()->id)->with(['permissions.company_info','permissions.shop_info','permissions.shop_staff_info'])->first();

        $shop_info->logo = $shop_info->logo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_info->logo : env('SHOW_PHOTO').'/api/show/default_img/default_logo.jpg';
        $shop_info->type = $user->permissions->where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->first() ? 'boss' : 'staff'; 

        $buy_mode_info = BuyMode::where('id',$shop_info->buy_mode_id)->first();
        $buy_mode_info = [
            'name'     => $shop_info->buy_mode_info->title,
            'deadline' => $shop_info->buy_mode_id == 0 ? '' : substr($shop_info->deadline,0,10),
            'day'      => $shop_info->buy_mode_id == 0 || round((strtotime($shop_info->deadline) - strtotime(date('Y-m-d'))) / 86400) == 0 ? '' : round((strtotime($shop_info->deadline) - strtotime(date('Y-m-d'))) / 86400) 
        ];

        $permission = Permission::where('shop_id',$shop_id)->where('user_id',auth()->getUser()->id)->where('shop_staff_id','!=',NULL)->first();
        $shop_staff = ShopStaff::where('id',$permission->shop_staff_id )->first();

        $user = User::where('id',auth()->user()->id)->first();
        $company_info = Company::find($shop_info->company_info->id);

        $shop_staff->photo = $shop_staff->company_staff_info->photo  ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$shop_staff->company_staff_info->photo : '';
        $shop_staff->name  = $shop_staff->company_staff_info->name;

        $select_shop = Shop::where('id','!=',$shop_info->id)->whereIn('id',$user->permissions->whereNotNull('shop_id')->pluck('shop_id')->toArray() )->get();
        foreach( $select_shop as $shop ){
            $shop->shop_type = $user->permissions->where('shop_id',$shop->id)->where('shop_staff_id',NULL)->first() ? '店長' : '員工'; 
            $shop->type      = $user->permissions->where('shop_id',$shop->id)->where('shop_staff_id',NULL)->first() ? 'boss' : 'staff';
        }

        $data = [
            'status'          => true,
            'buy_mode_info'   => $buy_mode_info,
            'shop_staff_info' => $shop_staff,
            'shop_info'       => $shop_info,
            'user'            => $user,
            'select_shops'    => $select_shop,
            'notice'          => SystemNotice::where('shop_id',$shop_info->id)->where('status','N')->count() ? true : false,
        ];

        return response()->json($data);
    }

    // 拿取首頁資料(舊版本)
    public function home()
    {
        $user = User::where('id',auth()->user()->id)->with(['permissions.company_info','permissions.shop_info','permissions.shop_staff_info'])->first();

        $company_info = Company::find($user->permissions[0]->company_id);

        // 先判斷是不是員工登入
        if( $user->permissions->count() == 1 && $user->permissions[0]->shop_staff_id ){
            // 若是員工，大頭照部分就拿取員工的圖片
            $shop_staff    = ShopStaff::find( $user->permissions[0]->shop_staff_id );
            $company_staff = CompanyStaff::find($shop_staff->company_staff_id); 

            $user->photo = env('SHOW_PHOTO').'/api/show/'. $company_info->companyId.'/'.$company_staff->photo;

            $data = Self::staff_home($shop_staff->shop_id,$user->permissions[0]->shop_staff_id);

            $data['type'] = 'staff';
            
            return response()->json($data);
        }

        // 以下為使用者擁有集團或商家的身分
        // 此使用者有哪些集團與分店
        $company = [];
        $shop    = [];
        foreach( $user->permissions as $permission ){
            if( $permission->shop_id == NULL && $permission->company_info != NULL ){
                // 集團權限
                $permission->company_info->shop_infos;

                // 計算簡訊總量
                $permission->company_info->total_sms = $permission->company_info->gift_sms+$permission->company_info->buy_sms;

                // 計算平均每月使用簡訊數量
                $first_sms_data = $permission->company_info->company_message_logs->first();
                if( !$first_sms_data ){
                    $use_month = 1;
                }else{
                    $first_sms_date = $first_sms_data->created_at;
                    $during_days = ( strtotime(date('Y-m-d')) - strtotime( substr($first_sms_date,0,10) ) ) / (60*60*24);
                    $use_month = ceil($during_days/30);
                }

                // 使用簡訊數量
                $total_use_sms = $permission->company_info->company_message_logs->sum('use');

                $permission->company_info->avg_sms = round($total_use_sms/$use_month,2);

                array_push($company, $permission->company_info);
            }elseif( $permission->shop_id != NULL && $permission->shop_info != NULL && $permission->shop_staff_id == NULL ){
                // 商家權限
                $shop_info = $permission->shop_info;
                $permission->shop_info->company_info;
                array_push($shop, $shop_info);
            }
        }

        // 加入購買方案判斷，決定要前往哪種起始頁面
        if( count( $company ) == 1 ){
            // 如果集團只有一個，需判斷商家是否也是只有一個，若都是一個就要判斷此集團與商家是否有關連
            if( count($shop) == 1 && $company[0]->id == $shop[0]->company_id ){
                $type    = $company_info->buy_mode_id == 0 ? 'free' : 'boss';
                $shop_id = $shop[0]->id;
            }else{
                $type = '';
            }
        }else{
            $type = '';
        }

        if( $type == 'boss' ){
            $data = Self::shop_home($shop_id);
            $data['type'] = 'boss';

            return response()->json($data);
        }elseif( $type == 'free' ){
            $data = Self::free_home($shop_id);
            $data['type'] = 'free';

            return response()->json($data);
        }else{
            $data = [
                'status' => false,
                'errors' => ['message'=>['此帳號權限有多個集團或多個商家，無法拿取首頁資料']],
            ];

            return response()->json($data);
        }
    }

    // 新版本home
    public function new_home($shop_id)
    { 
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $user = User::where('id',auth()->user()->id)
                      ->with(['permissions.company_info','permissions.shop_info','permissions.shop_staff_info'])->first();

        // 計算此帳號有多少集團、商家、員工身分
        $user_staff   = $user->permissions->where('company_id',$company_info->id)->where('shop_id',$shop_id)->where('shop_staff_id','!=',NULL);
        $user_shop    = $user->permissions->where('company_id',$company_info->id)->where('shop_id',$shop_id)->where('shop_staff_id',NULL);

        if( $user_staff->count() >= 1 && $user_shop->count() == 0 ){ // 只有店家員工身分
            // 若是員工，大頭照部分就拿取員工的圖片
            $shop_staff    = ShopStaff::find( $user_staff->first()->shop_staff_id );
            $company_staff = CompanyStaff::find($shop_staff->company_staff_id); 

            $user->photo = env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$company_staff->photo;

            $data = Self::staff_home($shop_staff->shop_id,$user_staff->first()->shop_staff_id);
            $data['type'] = 'staff';

        }else{
            // 店長身分
            $type = $shop_info->buy_mode_id == 0 ? 'free' : 'boss';
            if( $type == 'free' ){
                $data = Self::free_home($shop_id);
                $data['type'] = 'free';
            }else{
                $data = Self::shop_home($shop_id);
                $data['type'] = 'boss';
            }            
        }

        return response()->json($data);
    }

    // 取得系統通知
    public function shop_system_notice($shop_id)
    {
        // $user = auth()->user();

        // 拿取使用者的商家權限
        // $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        // if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $system_notices = SystemNotice::where('shop_id',$shop_info->id)->where('status','N')->orderBy('id','DESC')->get();
        
        $notices = [];
        foreach( $system_notices as $sn ){
            $notices[] = [
                'id'       => $sn->id,
                'date'     => $sn->created_at,
                'content'  => $sn->content,
                'url_data' => json_decode($sn->url_data),
            ];
        }

        $data = [
            'status'     => true,
            'permission' => true,
            'data'       => $notices,
        ];

        return response()->json($data);
    }

    // 系統通知變更已讀(可多可單)
    public function shop_system_notice_read($shop_id)
    {
        SystemNotice::whereIn('id',request('notice_id'))->update(['status'=>'Y']);

        return response()->json(['status'=>true]);
    }

    // Free版首頁
    public function free_home($shop_id)
    {
    	$user = auth()->user();

    	// 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('free_home',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info           = Shop::find($shop_id);
        $company_info        = $shop_info->company_info;
        $shop_set            = $shop_info->shop_set;            // 商家設定資料
        $shop_business_hours = $shop_info->shop_business_hours; // 商家營業時間資料

        // 計算還是空的欄位數量
        $shop_info_percent     = 0; 
        $shop_service_percent  = 0;
        $shop_collection_count = 0;

        // 商家管理進度
        // 基本資料
        if( $shop_info->name != NULL )        $shop_info_percent = 25;
        elseif( $shop_info->phone != NULL )   $shop_info_percent = 25;
        elseif( $shop_info->address != NULL ) $shop_info_percent = 25;

        // 營業時間
        foreach( $shop_business_hours as $business_hours ){
        	if( $business_hours->created_at != $business_hours->updated_at || $business_hours->type == 1 ){
        		$shop_info_percent += 25;
        		break;
        	}
        }

        // 社群資料
        if( $shop_info->line != NULL )              $shop_info_percent += 25;
        elseif( $shop_info->line_url != NULL )      $shop_info_percent += 25;
        elseif( $shop_info->facebook_name != NULL ) $shop_info_percent += 25;
        elseif( $shop_info->facebook_url != NULL )  $shop_info_percent += 25;
        elseif( $shop_info->ig != NULL )            $shop_info_percent += 25;
        elseif( $shop_info->ig_url != NULL )        $shop_info_percent += 25;
        elseif( $shop_info->web_name != NULL )      $shop_info_percent += 25;
        elseif( $shop_info->web_url != NULL )       $shop_info_percent += 25;

        // 環境照片
        $shop_photos = $shop_info->shop_photos;
        if( $shop_photos->count() ) $shop_info_percent += 25;

        // 服務管理進度
        $shop_categories = $shop_info->shop_service_categories;
        if( $shop_categories->count() != 0 )       $shop_service_percent += 33;
        $shop_services = $shop_info->shop_services;
        if( $shop_services->count() != 0 )         $shop_service_percent += 33;
        if( $shop_set->show_service_type != NULL ) $shop_service_percent += 33;
        if( $shop_service_percent == 99 )          $shop_service_percent = 100;

        // 作品集
        $collections = Album::where('shop_id',$shop_id)->where('type','collection')->get();
        $shop_collection_count = 0;
        foreach( $collections as $collection ){
        	if( $collection->photos->count() ){
        		$shop_collection_count = 100;
        		break;
        	}
        }

        // 商家QR
        // if( \DB::table('version_check')->where('store_id',$shop_info->alias)->first() ){
        //     $shop_home_url = 'https://ai.shilipai.com.tw/store/'.$shop_info->alias;
        // }else{
        //     $shop_home_url = 'https://shilip.ai/store/'.$shop_info->alias;
        // }
        
        $data = [
        	'status'               => true,
            'total'                => round( ($shop_info_percent+$shop_service_percent+$shop_collection_count)/3 ),
        	'shop_info_percent'    => $shop_info_percent,
        	'shop_service_percent' => $shop_service_percent,
        	'shop_collections'     => $shop_collection_count,
        	// 'qr_code'              => "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".$shop_home_url."&choe=UTF-8"
        ];

    	return $data;
    }

    // plus員工首頁
    public function staff_home($shop_id,$shop_staff_id)
    {
    	$user = auth()->user();

    	// 拿取使用者的商家權限
        // $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        // if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('staff_home',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 預約資料
        $shop_reservations = CustomerReservation::where('shop_id',$shop_id)->where('shop_staff_id',$shop_staff_id)->orderBy('start','ASC')->get();

        // 今日預約
        $today_reservations = [];
        $reservation_count  = 0;
        foreach( $shop_reservations->whereBetween('start',[date('Y-m-d').' 00:00:00',date('Y-m-d').' 23:59:59'])->where('status','Y')->where('cancel_status',NULL) as $reservation ){
        	// 1到了 / 2爽約 / 3小遲到 / 4大遲到 / 5 提早
            $tag_text_arr = [1=>'到了',2=>'爽約',3=>'小遲到',4=>'大遲到',5=>'提早'];
        	$tags = [
        	    [
        	        'name'        => '提早',
        	        'description' => '(30分鐘以上)',
        	        'selected'    => $reservation->tag == 5 ? true : false,
        	        'value'       => 5,
        	    ],
        	    [
        	        'name'        => '到囉！',
        	        'description' => '',
        	        'selected'    => $reservation->tag == 1 ? true : false,
        	        'value'       => 1,
        	    ],
        	    [
        	        'name'        => '大遲到',
        	        'description' => '(30分鐘以上)',
        	        'selected'    => $reservation->tag == 4 ? true : false,
        	        'value'       => 4,
        	    ],
        	    [
        	        'name'        => '小遲到',
        	        'description' => '(30分鐘以內)',
        	        'selected'    => $reservation->tag == 3 ? true : false,
        	        'value'       => 3,
        	    ],
        	    [
        	        'name'        => '爽約',
        	        'description' => '',
        	        'selected'    => $reservation->tag == 2 ? true : false,
        	        'value'       => 2,
        	    ]
        	];

            $check = false;
            foreach( $today_reservations as $k => $res ){
                if( $res['time'] == date('a H:i',strtotime($reservation->start)) ){
                    $today_reservations[$k]['reservations'][] = [
                        'id'             => $reservation->id,
                        'staff'          => $reservation->staff_info->name,
                        'staff_photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$reservation->staff_info->photo,
                        'service'        => $reservation->service_info->name,
                        'advances'       => $reservation->advances->pluck('name'),
                        'customer'       => $reservation->customer_info->realname,
                        'customer_photo' => preg_match('/http/i', $reservation->customer_info->photo) ? $reservation->customer_info->photo : env('SHOW_PHOTO').'/api/get/customer/'.$reservation->customer_info->photo,
                        'phone'          => $reservation->customer_info->phone,
                        'color'          => $reservation->staff_info->calendar_color.( $reservation->start < date( 'Y-m-d H:i:s') ? '70' : '' ),
                        'date'           => date('a H:i',strtotime($reservation->start)),
                        'tags'           => $tags,
                        'tag_text'       => $reservation->tag ? $tag_text_arr[$reservation->tag] : NULL,
                    ]; 
                    $check = true;
                    $reservation_count++;
                    break;
                }
            }

            if( $check == false ){
                $today_reservations[] = [
                    'time' => date('a H:i',strtotime($reservation->start)),
                    'reservations' => [
                        [
                            'id'             => $reservation->id,
                            'staff'          => $reservation->staff_info->name,
                            'staff_photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$reservation->staff_info->photo,
                            'service'        => $reservation->service_info->name,
                            'advances'       => $reservation->advances->pluck('name'),
                            'customer'       => $reservation->customer_info->realname,
                            'customer_photo' => preg_match('/http/i', $reservation->customer_info->photo) ? $reservation->customer_info->photo : env('SHOW_PHOTO').'/api/get/customer/'.$reservation->customer_info->photo,
                            'phone'          => $reservation->customer_info->phone,
                            'color'          => $reservation->staff_info->calendar_color.( $reservation->start < date( 'Y-m-d H:i:s') ? '70' : '' ),
                            'date'           => date('a H:i',strtotime($reservation->start)),
                            'tags'           => $tags,
                            'tag_text'       => $reservation->tag ? $tag_text_arr[$reservation->tag] : NULL,
                            
                        ]
                    ]
                ]; 
                $reservation_count++;
            }
        }

        // 本月排休記錄
        $vacations = ShopVacation::where('shop_id',$shop_id)
                                        ->where('shop_staff_id',$shop_staff_id)
                                        ->whereBetween('start_date',[date('Y-m-01'),date('Y-m-31')])
                                        ->get(); 
        // 預約出席狀況
        $reservation_status = [
            'last_month' => [
                'arrive' => 0,
                'total'  => 0,
            ],
            'this_month' => [
                'arrive' => 0,
                'total'  => 0,
            ],
            'today'      => [
                'arrive' => 0,
                'total'  => 0,
            ],
            'arrive'      => 0,
            'little_late' => 0,
            'very_late'   => 0,
            'early'       => 0,
            'flake_out'   => 0,
        ];
        foreach( $shop_reservations->where('status','Y')  as $reservation ){
            if( $reservation->cancel_status == NULL ){
                if( date('Y-m',strtotime($reservation->start)) == date('Y-m',strtotime('-1 month')) ){
                    // 上個月
                    $reservation_status['last_month']['arrive'] += $reservation->tag == 2 || $reservation->tag == NULL ? 0 : 1;
                    $reservation_status['last_month']['total']  += 1;
                }elseif( date('Y-m',strtotime($reservation->start)) == date('Y-m') ){
                    // 本月
                    $reservation_status['this_month']['arrive'] += $reservation->tag == 2 || $reservation->tag == NULL ? 0 : 1;
                    $reservation_status['this_month']['total']  += 1;
                    // 出席狀態
                    switch ($reservation->tag){
                        case 1:
                            $reservation_status['arrive'] += 1;
                            break;
                        case 2:
                            $reservation_status['flake_out'] += 1;
                            break;
                        case 3:
                            $reservation_status['little_late'] += 1;
                            break;
                        case 4:
                            $reservation_status['very_late'] += 1;
                            break;
                        case 5:
                            $reservation_status['early'] += 1;
                            break;
                    } 
                    if( date('Y-m-d',strtotime($reservation->start)) == date('Y-m-d') ){
                        // 今日
                        $reservation_status['today']['arrive'] += $reservation->tag == 2 || $reservation->tag == NULL ? 0 : 1;
                        $reservation_status['today']['total']  += 1;
                    } 
                }
            }
        }

        // 歸屬會員
        $staff_customers = ShopCustomer::where('shop_staff_id',$shop_staff_id)->join('customers','customers.id','=','shop_customers.customer_id')->get();
        $current_month_birthday = $three_month_join = [];
        foreach( $staff_customers as $customer ){
            if( $customer->birthday != NULL ){
                // 當月壽星
                if( date('m') == date('m',strtotime($customer->birthday)) && !in_array($customer->id,$current_month_birthday) ){
                    $current_month_birthday[] = $customer->id;
                }
                
            }
            // 近三個月加入會員
            $shop_customer_info = ShopCustomer::where('shop_id',$shop_id)->where('customer_id',$customer->id)->first();
            if( date('Y-m-d',strtotime('-3 month')) <= date('Y-m-d',strtotime($shop_customer_info->created_at)) && date('Y-m-d') >= date('Y-m-d',strtotime($shop_customer_info->created_at)) && !in_array($customer->id,$three_month_join) ){
                $three_month_join[] = $customer->id;
            }
        }

        // 計算近三個月內有預約會員數
        $three_month_reservaton = [];
        foreach( $shop_reservations as $cr ){
            $staff_customer = ShopCustomer::where('shop_staff_id',$shop_staff_id)->where('shop_id',$shop_id)->where('customer_id',$cr->customer_id)->first();
            
            if( $staff_customer ){
                if( date('Y-m-d',strtotime('+3 month')) >= date('Y-m-d',strtotime($cr->start)) && date('Y-m-d') <= date('Y-m-d',strtotime($cr->start)) && !in_array($cr->customer_id,$three_month_reservaton) ){
                    $three_month_reservaton[] = $cr->customer_id;
                }
            }
        }

        $customers = [
            'count'                  => $staff_customers->count(),
            'current_month_birthday' => count($current_month_birthday),
            'three_month_join'       => count($three_month_join),
            'three_month_reservaton' => count($three_month_reservaton),
        ]; 

        // 個人資料完成進度
        $shop_staff = ShopStaff::where('shop_staffs.id',$shop_staff_id)->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id')->first();
        $photo_percent = $representative_work = 0;
        if( $shop_staff->photo )  $photo_percent += 50;
        if( $shop_staff->banner ) $photo_percent += 50;

        // 代表作品進度
        $staff_album = Album::where('type','representative_work')->where('staff_id',$shop_staff_id)->first();
        if( $staff_album ){
            if( $staff_album->photos->count() ) $representative_work = 100;
        }

        $staff_data_percent = [
            'photo_percent'               => $photo_percent.'%',
            'service_item'                => $shop_staff->staff_services->count() ? true : false,
            'calendar_percent'            => ($shop_staff->calendar_token ? 100 : 0).'%',
            'representative_work_percent' => $representative_work.'%',
        ];

        $data = [
            'status'                  => true,
            'today_reservations'      => $today_reservations,
            'today_reservation_count' => $reservation_status['today']['total'],
            'reservation_count'       => $reservation_count,
            'wait_reservations'       => $shop_reservations->where('status','N')->count(),
            'vacation'                => $vacations->count(),
            'reservation_status'      => $reservation_status,
            'customers'               => $customers,
            'staff_data_percent'      => $staff_data_percent,
        ];

        return $data;
    }

    // plus老闆首頁
    public function shop_home($shop_id)
    {
        $user = auth()->user();

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_home',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 預約資料
        $shop_reservations = CustomerReservation::where('shop_id',$shop_id)->orderBy('start','ASC')->get();

        // 今日預約
        $today_reservations = [];
        $reservation_count  = 0; 
        $staff_reservation  = [];
        foreach( $shop_reservations->whereBetween('start',[date('Y-m-d').' 00:00:00',date('Y-m-d').' 23:59:59'])->where('status','Y')->where('cancel_status',NULL) as $reservation ){
            // 1到了 / 2爽約 / 3小遲到 / 4大遲到 / 5 提早
            $tag_text_arr = [1=>'到了',2=>'爽約',3=>'小遲到',4=>'大遲到',5=>'提早'];

            $tags = [
                [
                    'name'        => '提早',
                    'description' => '(30分鐘以上)',
                    'selected'    => $reservation->tag == 5 ? true : false,
                    'value'       => 5,
                ],
                [
                    'name'        => '到囉！',
                    'description' => '',
                    'selected'    => $reservation->tag == 1 ? true : false,
                    'value'       => 1,
                ],
                [
                    'name'        => '大遲到',
                    'description' => '(30分鐘以上)',
                    'selected'    => $reservation->tag == 4 ? true : false,
                    'value'       => 4,
                ],
                [
                    'name'        => '小遲到',
                    'description' => '(30分鐘以內)',
                    'selected'    => $reservation->tag == 3 ? true : false,
                    'value'       => 3,
                ],
                [
                    'name'        => '爽約',
                    'description' => '',
                    'selected'    => $reservation->tag == 2 ? true : false,
                    'value'       => 2,
                ]
            ];

            // 依預約時間製作預約資料排序
            $check = false;
            foreach( $today_reservations as $k => $res ){
                if( $res['time'] == date('a H:i',strtotime($reservation->start)) ){
                    $today_reservations[$k]['reservations'][] = [
                        'id'             => $reservation->id,
                        'staff_id'       => $reservation->shop_staff_id,
                        'staff'          => $reservation->staff_info->name,
                        'staff_photo'    => $reservation->staff_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$reservation->staff_info->photo : '',
                        'color'          => $reservation->staff_info->calendar_color.( $reservation->start < date( 'Y-m-d H:i:s') ? '70' : '' ),
                        'service'        => $reservation->service_info->name,
                        'advances'       => $reservation->advances->pluck('name'),
                        'customer'       => $reservation->customer_info->realname,
                        'customer_photo' => preg_match('/http/i', $reservation->customer_info->photo) ? $reservation->customer_info->photo : env('SHOW_PHOTO').'/api/get/customer/'.$reservation->customer_info->photo,
                        'phone'          => $reservation->customer_info->phone,
                        'date'           => date('a H:i',strtotime($reservation->start)),
                        'tags'           => $tags,
                        'tag_text'       => $reservation->tag ? $tag_text_arr[$reservation->tag] : NULL,
                    ]; 
                    $check = true;
                    $reservation_count++; 
                    break;
                }
            }

            if( $check == false ){

                if( preg_match('/http/i', $reservation->customer_info->photo) ){
                    $customer_photo = $reservation->customer_info->photo;
                }else{
                    if( $reservation->customer_info->photo ){
                        $customer_photo = env('SHOW_PHOTO').'/api/get/customer/'.$reservation->customer_info->photo;
                    }else{
                        $customer_photo = '';
                    }
                }

                $today_reservations[] = [
                    'time' => date('a H:i',strtotime($reservation->start)),
                    'reservations' => [
                        [
                            'id'             => $reservation->id,
                            'staff_id'       => $reservation->shop_staff_id,
                            'staff'          => $reservation->staff_info->name,
                            'staff_photo'    => $reservation->staff_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$reservation->staff_info->photo : '',
                            'color'          => $reservation->staff_info->calendar_color.( $reservation->start < date( 'Y-m-d H:i:s') ? '70' : '' ),
                            'service'        => $reservation->service_info->name,
                            'advances'       => $reservation->advances->pluck('name'),
                            'customer'       => $reservation->customer_info->realname,
                            'customer_photo' => $customer_photo,
                            'phone'          => $reservation->customer_info->phone,
                            'date'           => date('a H:i',strtotime($reservation->start)),
                            'tag_text'       => $reservation->tag ? $tag_text_arr[$reservation->tag] : NULL,
                            'tags'           => $tags,
                        ]
                    ]
                ];
                $reservation_count++; 
            }

            // 依員工製作員工今日的預約資料
            $staff_check = false;
            foreach( $staff_reservation as $k => $sr ){
                if( $sr['staff_id'] == $reservation->shop_staff_id ){
                    $staff_reservation[$k]['reservations'] += 1;
                    $staff_check = true;
                    break;
                }
            }
            if( $staff_check == false ){
                $staff_reservation[] = [
                    'staff_id'     => $reservation->shop_staff_id,
                    'staff_name'   => $reservation->staff_info->name,
                    'staff_photo'  => $reservation->staff_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$reservation->staff_info->photo : '',
                    'reservations' => 1,
                ];
            }
        }
        // 補足商家所有員工
        $shop_staffs = ShopStaff::where('shop_id',$shop_id)->get();
        foreach( $shop_staffs as $staff ){
            if( !$staff->company_staff_info ) continue;
            if( $staff->company_staff_info && $staff->company_staff_info->fire_time != NULL ) continue;
            $check = false;
            foreach( $staff_reservation as $sr ){
                if( $sr['staff_id'] == $staff->id ){
                    $check = true;
                    break;
                }
            }
            if( $check == false ){
                $staff_reservation[] = [
                    'staff_id'     => $staff->id,
                    'staff_name'   => $staff->company_staff_info->name,
                    'staff_photo'  => $staff->company_staff_info->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$staff->company_staff_info->photo : '',
                    'reservations' => 0,
                ];
            }
        }

        // 本月排休記錄
        $vacations = ShopVacation::where('shop_id',$shop_id)
                                        ->whereBetween('start_date',[date('Y-m-01'),date('Y-m-31')])
                                        ->get(); 

        // 預約出席狀況
        $reservation_status = [
            'last_month' => [
                'arrive' => 0,
                'total'  => 0,
            ],
            'this_month' => [
                'arrive' => 0,
                'total'  => 0,
            ],
            'today'      => [
                'arrive' => 0,
                'total'  => 0,
            ],
            'arrive'      => 0,
            'little_late' => 0,
            'very_late'   => 0,
            'early'       => 0,
            'flake_out'   => 0,
        ];
        foreach( $shop_reservations->where('status','Y') as $reservation ){
            if( $reservation->cancel_status == NULL ){
                
                if( date('Y-m',strtotime($reservation->start)) == date('Y-m',strtotime('-1 month')) ){
                    // 上個月
                    $reservation_status['last_month']['arrive'] += $reservation->tag == 2 || $reservation->tag == NULL ? 0 : 1;
                    $reservation_status['last_month']['total']  += 1;
                }elseif( date('Y-m',strtotime($reservation->start)) == date('Y-m') ){
                    
                    // 本月
                    $reservation_status['this_month']['arrive'] += $reservation->tag == 2 || $reservation->tag == NULL ? 0 : 1;
                    $reservation_status['this_month']['total']  += 1;

                    // 出席狀態
                    switch ($reservation->tag){
                        case 1:
                            $reservation_status['arrive'] += 1;
                            break;
                        case 2:
                            $reservation_status['flake_out'] += 1;
                            break;
                        case 3:
                            $reservation_status['little_late'] += 1;
                            break;
                        case 4:
                            $reservation_status['very_late'] += 1;
                            break;
                        case 5:
                            $reservation_status['early'] += 1;
                            break;
                    }  
                    if( date('Y-m-d',strtotime($reservation->start)) == date('Y-m-d') ){
                        // 今日
                        $reservation_status['today']['arrive'] += $reservation->tag == 2 || $reservation->tag == NULL ? 0 : 1;
                        $reservation_status['today']['total']  += 1;
                    } 
                }
            }
        }

        // 歸屬會員
        $shop_customers = ShopCustomer::where('shop_id',$shop_id)->join('customers','customers.id','=','shop_customers.customer_id')->get();
        $current_month_birthday = $three_month_join = [];
        foreach( $shop_customers as $customer ){
            if( $customer->birthday != NULL ){
                // 當月壽星
                if( date('m') == date('m',strtotime($customer->birthday)) && !in_array($customer->id,$current_month_birthday) ){
                    $current_month_birthday[] = $customer->id;
                }
                
            }
            // 近三個月加入會員
            $shop_customer_info = ShopCustomer::where('shop_id',$shop_id)->where('customer_id',$customer->id)->first();
            if( date('Y-m-d',strtotime('-3 month')) <= date('Y-m-d',strtotime($shop_customer_info->created_at)) && date('Y-m-d') >= date('Y-m-d',strtotime($shop_customer_info->created_at)) && !in_array($customer->id,$three_month_join) ){
                $three_month_join[] = $customer->id;
            }
        }
        // 計算近三個月內有預約會員數
        $three_month_reservaton = [];
        foreach( $shop_reservations as $cr ){
            if( date('Y-m-d',strtotime('+3 month')) >= date('Y-m-d',strtotime($cr->start)) && date('Y-m-d') <= date('Y-m-d',strtotime($cr->start)) && !in_array($cr->customer_id,$three_month_reservaton) ){
                $three_month_reservaton[] = $cr->customer_id;
            }
        }
        $customers = [
            'count'                  => $shop_customers->count(),
            'current_month_birthday' => count($current_month_birthday),
            'three_month_join'       => count($three_month_join),
            'three_month_reservaton' => count($three_month_reservaton),
        ]; 

        // 計算還是空的欄位數量
        $shop_info_percent        = 0; 
        $shop_staff_percent       = 0;
        $shop_reservation_percent = 0;
        $shop_service_percent     = 0;

        // 商家管理進度
        // 基本資料
        if( $shop_info->name != NULL )        $shop_info_percent = 25;
        elseif( $shop_info->phone != NULL )   $shop_info_percent = 25;
        elseif( $shop_info->address != NULL ) $shop_info_percent = 25;
        // 營業時間
        foreach( $shop_info->shop_business_hours as $business_hours ){
            if( $business_hours->created_at != $business_hours->updated_at || $business_hours->type == 1 ){
                $shop_info_percent += 25;
                break;
            }
        }
        // 社群資料
        if( $shop_info->line != NULL )              $shop_info_percent += 25;
        elseif( $shop_info->line_url != NULL )      $shop_info_percent += 25;
        elseif( $shop_info->facebook_name != NULL ) $shop_info_percent += 25;
        elseif( $shop_info->facebook_url != NULL )  $shop_info_percent += 25;
        elseif( $shop_info->ig != NULL )            $shop_info_percent += 25;
        elseif( $shop_info->ig_url != NULL )        $shop_info_percent += 25;
        elseif( $shop_info->web_name != NULL )      $shop_info_percent += 25;
        elseif( $shop_info->web_url != NULL )       $shop_info_percent += 25;
        // 環境照片
        $shop_photos = $shop_info->shop_photos;
        if( $shop_photos->count() ) $shop_info_percent += 25;

        // 服務管理進度
        $shop_categories = $shop_info->shop_service_categories;
        if( $shop_categories->count() != 0 )       $shop_service_percent += 33;
        $shop_services = $shop_info->shop_services;
        if( $shop_services->count() != 0 )         $shop_service_percent += 33;
        if( $shop_info->shop_set->show_service_type != NULL ) $shop_service_percent += 33;
        if( $shop_service_percent == 99 ){
            $shop_service_percent = 100;
            $shop_service_percent_check = true;
        }else{
            $shop_service_percent_check = false;
        }         

        // 員工管理進度
        $shop_staff_percent = $shop_info->shop_staffs->count() ? 100 : 0;

        // 預約設定進度
        // if( $shop_info->shop_set->buffer_time != NULL ) $shop_reservation_percent += 33;
        foreach( $shop_info->shop_reservation_tags as $tag ){
            if( $tag->name ){
                $shop_reservation_percent += 50;
                break;
            }
        }
        foreach( $shop_info->shop_reservation_messages as $tag ){
            if( $tag->created_at != $tag->updated_at ){
                $shop_reservation_percent += 50;
                break;
            }
        }
        if( $shop_reservation_percent == 99 ) $shop_reservation_percent = 100;

        // 商家資料完總進度
        $total_percent = round( ( $shop_info_percent + $shop_staff_percent + $shop_reservation_percent + ($shop_service_percent_check?100:0) ) / 4 );

        // 進階推廣進度
        // 貼文進度
        $posts_percent = ShopPost::where('shop_id',$shop_id)->withTrashed()->get()->count() ? 100 : 0; 
        
        // 作品集進度
        $collection_percent = 0;
        $shop_collections = Album::where('shop_id',$shop_id)->where('type','collection')->get();
        if( $shop_collections->count() != 0 ){
            foreach( $shop_collections as $collection ){
                if( $collection->photos ){
                    $collection_percent = 100;
                    break;
                }
            }
        } 

        // 優惠活動進度
        $discount_percent = $shop_info->shop_coupons->count() ? 100 : 0;

        $marketing_total_percent = round( ($posts_percent+$collection_percent+$discount_percent)/3 );

        $data = [
            'status'                  => true,
            'staff_reservation'       => $staff_reservation,
            'today_reservations'      => $today_reservations,
            'reservations_count'      => $reservation_count,
            'wait_reservations'       => $shop_reservations->where('status','N')->count(),
            'vacation'                => $vacations->count(),
            'vacation_info'           => $vacations,
            'reservation_status'      => $reservation_status,
            'customers'               => $customers,
            'today_reservation_count' => $reservation_status['today']['total'],
            'shop_info_percent'  => [
                'total_percent'            => $total_percent,
                'shop_info_percent'        => $shop_info_percent,
                'shop_service_percent'     => $shop_service_percent,
                'shop_staff_percent'       => $shop_staff_percent,
                'shop_reservation_percent' => $shop_reservation_percent,
            ],
            'marketing'          => [
                'total_percent'      => $marketing_total_percent,
                'posts_percent'      => $posts_percent,
                'collection_percent' => $collection_percent,
                'discount_percent'   => $discount_percent,
            ], 
        ];

        return $data;
    }

}
