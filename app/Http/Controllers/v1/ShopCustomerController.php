<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteGoogleCalendarEvent;
use Illuminate\Http\Request;
use Validator;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Bill;
use App\Models\Photo;
use App\Models\Company;
use App\Models\CompanyCustomer;
use App\Models\CompanyCouponLimit;
use App\Models\CompanyLoyaltyCardLimit;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\User;
use App\Models\ShopCustomer;
use App\Models\CustomerCoupon;
use App\Models\CustomerReservation;
use App\Models\CustomerTag;
use App\Models\Customer;
use App\Models\CustomerLoyaltyCard;
use App\Models\CustomerLoyaltyCardPoint;
use App\Models\CustomerPersonality;
use App\Models\CustomerEvaluate;
use App\Models\CustomerMembershipCard;
use App\Models\CustomerProgram;
use App\Models\CustomerProgramGroup;
use App\Models\CustomerProgramLog;
use App\Models\ShopReservationTag;
use App\Models\CustomerQuestionAnswer;
use App\Models\CustomerTopUp;
use App\Models\CustomerTopUpLog;
use App\Models\CustomerTraits;
use App\Models\ShopCouponLimit;
use App\Models\ShopCustomerReservationTag;
use App\Models\ShopManagement;
use App\Models\ShopManagementCustomerList;
use App\Models\ShopProgram;
use App\Models\ShopProgramGroup;
use App\Models\ShopService;
use App\Models\ShopStaff;
use Overtrue\ChineseCalendar\Calendar;

use function PHPSTORM_META\map;

class ShopCustomerController extends Controller
{
    // 取得商家全部會員資料
    public function shop_customers($shop_id)
    {
        if( !PermissionController::is_staff($shop_id) ){
            // 拿取使用者的商家權限
            $user_shop_permission = PermissionController::user_shop_permission($shop_id);
            if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

            // 確認頁面瀏覽權限
            if( !in_array('shop_customers',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);
        }else{
            // 員工身分
            $user_staff_permission = PermissionController::user_staff_permission($shop_id);
            if ($user_staff_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_staff_permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customers', $user_staff_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        }

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

        $shop_staff_id = Permission::where('user_id',auth()->getUser()->id)->where('shop_id',$shop_id)->whereNotNull('shop_staff_id')->first()->shop_staff_id;

        if( PermissionController::is_staff($shop_id) ){
            // 需判斷員工是否可以看到全部會員
            $shop_staff = ShopStaff::find($shop_staff_id);
            if( $shop_staff->company_staff_info->show_all_customer == 'Y' ){
                $shop_customers = ShopCustomer::where('shop_id', $shop_id)->with('customer_info')->get();
            }else{
                $shop_customers = ShopCustomer::where('shop_id', $shop_id)->where('shop_staff_id', $shop_staff_id)->with('customer_info')->get();
            }
        }else{
            $shop_customers = ShopCustomer::where('shop_id',$shop_id)->with('customer_info')->get();
        }
    	
    	$customer_data = [];
    	$current_month = $three_month_join = $three_month_reservaton = $six_month_reservaton = $feature_reservation = 0;

        if( request('select') ){
            $customer_list = []; 
        }

    	foreach( $shop_customers as $shop_customer ){
            if( !$shop_customer->customer_info ) continue;
    		$customer_reservation = CustomerReservation::where('customer_id',$shop_customer->customer_info->id)
                                            ->where('shop_id',$shop_info->id)
                                            ->where('status','Y')
                                            ->where('cancel_status',NULL)
                                            ->orderBy('start','ASC')->get();

            $sex =  $shop_customer->customer_info->sex == 'M' ? '男' : ($shop_customer->customer_info->sex == 'F' ? '女' : '中性');
            if( $shop_customer->customer_info->sex == '' ) $sex = '';

            $phone = '-';
            if( $shop_customer->customer_info->phone ){
                $phone = substr($shop_customer->customer_info->phone, 0, 4)
                        . '-' . substr($shop_customer->customer_info->phone, 4, 3)
                        . '-' . substr($shop_customer->customer_info->phone, 7, 3);
            }

    		$customer_data[] = [
    			'id'                => $shop_customer->id,
    			'name'              => $shop_customer->customer_info->realname . ($shop_customer->customer_info->nickname ? '('.$shop_customer->customer_info->nickname.')' : ''),
    			'phone'             => $phone,
    			'sex'               => $sex,
    			'age'               => $shop_customer->customer_info->birthday ? Self::getAge($shop_customer->customer_info->birthday) : '-',
    			'birthday'          => $shop_customer->customer_info->birthday?:'-',
    			'constellation'     => $shop_customer->customer_info->birthday ? Self::constellation($shop_customer->customer_info->birthday) : '-',
    			'reservation_count' => $customer_reservation->where('shop_id',$shop_info->id)->count(),
    			'reservation_date'  => $customer_reservation->where('shop_id',$shop_info->id)->last() ? substr($customer_reservation->where('shop_id',$shop_info->id)->where('status','Y')->last()->start,0,10) : '-',
    			// 'tags'              => $shop_customer->tags->pluck('name'),
                'level'             => '',
    			'beloneTo'          => $shop_customer->belongTo ? $shop_customer->belongTo->name : '',
                'photo'             => preg_match('/http/i', $shop_customer->customer_info->photo) ? $shop_customer->customer_info->photo : env('SHOW_PHOTO').'/api/get/customer/'.$shop_customer->customer_info->photo,
    		];

    		// 計算近三/六個月內有消費預約會員數
    		$break = false;
    		foreach( $customer_reservation->where('status','Y') as $cr ){
    			if( date('Y-m-d',strtotime('+3 month')) >= date('Y-m-d',strtotime($cr->start)) 
                        && date('Y-m-d') >= date('Y-m-d',strtotime($cr->start)) ){
                    // 三個月內
    				$three_month_reservaton += 1;
    				$break = true;
                    if( request('select') == 'three_month_reservaton' ){
                        $customer_list[] = [
                            'id'                => $shop_customer->id,
                            'name'              => $shop_customer->customer_info->realname . ($shop_customer->customer_info->nickname ? '('.$shop_customer->customer_info->nickname.')' : ''),
                            'phone'             => substr($shop_customer->customer_info->phone,0,4)
                                                    .'-'.substr($shop_customer->customer_info->phone,4,3)
                                                    .'-'.substr($shop_customer->customer_info->phone,7,3),
                            'sex'               => $shop_customer->customer_info->sex == 'M' ? '男' : ($shop_customer->customer_info->sex == 'F' ? '女' : '中性'),
                            'age'               => $shop_customer->customer_info->birthday ? Self::getAge($shop_customer->customer_info->birthday) : NULL,
                            'birthday'          => $shop_customer->customer_info->birthday,
                            'constellation'     => $shop_customer->customer_info->birthday ? Self::constellation($shop_customer->customer_info->birthday) : NULL,
                            'reservation_count' => $customer_reservation->count(),
                            'reservation_date'  => $customer_reservation->last() ? substr($customer_reservation->where('status','Y')->last()->start,0,10) : NULL,
                            // 'tags'              => $shop_customer->tags->pluck('name'),
                            'level'             => '',
                            'beloneTo'          => $shop_customer->belongTo ? $shop_customer->belongTo->name : '',
                            'photo'             => preg_match('/http/i', $shop_customer->customer_info->photo) ? $shop_customer->customer_info->photo : env('SHOW_PHOTO').'/api/get/customer/'.$shop_customer->customer_info->photo,
                        ];
                    }
    			}

    			if( date('Y-m-d',strtotime('+6 month')) >= date('Y-m-d',strtotime($cr->start)) 
                        && date('Y-m-d') >= date('Y-m-d',strtotime($cr->start)) ){
    				$six_month_reservaton += 1;
    				$break = true;
                    if( request('select') == 'six_month_reservaton' ){
                        $customer_list[] = [
                            'id'                => $shop_customer->id,
                            'name'              => $shop_customer->customer_info->realname . ($shop_customer->customer_info->nickname ? '('.$shop_customer->customer_info->nickname.')' : ''),
                            'phone'             => substr($shop_customer->customer_info->phone,0,4)
                                                    .'-'.substr($shop_customer->customer_info->phone,4,3)
                                                    .'-'.substr($shop_customer->customer_info->phone,7,3),
                            'sex'               => $shop_customer->customer_info->sex == 'M' ? '男' : ($shop_customer->customer_info->sex == 'F' ? '女' : '中性'),
                            'age'               => $shop_customer->customer_info->birthday ? Self::getAge($shop_customer->customer_info->birthday) : NULL,
                            'birthday'          => $shop_customer->customer_info->birthday,
                            'constellation'     => $shop_customer->customer_info->birthday ? Self::constellation($shop_customer->customer_info->birthday) : NULL,
                            'reservation_count' => $customer_reservation->where('shop_id',$shop_info->id)->count(),
                            'reservation_date'  => $customer_reservation->where('shop_id',$shop_info->id)->last() ? substr($customer_reservation->where('shop_id',$shop_info->id)->where('status','Y')->last()->start,0,10) : NULL,
                            // 'tags'              => $shop_customer->tags->pluck('name'),
                            'level'             => '',
                            'beloneTo'          => $shop_customer->belongTo ? $shop_customer->belongTo->name : '',
                            'photo'             => preg_match('/http/i', $shop_customer->customer_info->photo) ? $shop_customer->customer_info->photo : env('SHOW_PHOTO').'/api/get/customer/'.$shop_customer->customer_info->photo,
                        ];
                    }
    			}

    			if( $break ){
                    break;
                } 
    		}

    		// 計算未來有消費預約會員數
    		foreach( $customer_reservation->where('shop_id',$shop_info->id) as $cr ){
    			if( strtotime(date('Y-m-d H:i:s')) < strtotime($cr->start) ){
    				$feature_reservation += 1;

                    if( request('select') == 'feature_reservation' ){
                        $customer_list[] = [
                            'id'                => $shop_customer->id,
                            'name'              => $shop_customer->customer_info->realname . ($shop_customer->customer_info->nickname ? '('.$shop_customer->customer_info->nickname.')' : ''),
                            'phone'             => substr($shop_customer->customer_info->phone,0,4)
                                                    .'-'.substr($shop_customer->customer_info->phone,4,3)
                                                    .'-'.substr($shop_customer->customer_info->phone,7,3),
                            'sex'               => $shop_customer->customer_info->sex == 'M' ? '男' : ($shop_customer->customer_info->sex == 'F' ? '女' : '中性'),
                            'age'               => $shop_customer->customer_info->birthday ? Self::getAge($shop_customer->customer_info->birthday) : NULL,
                            'birthday'          => $shop_customer->customer_info->birthday,
                            'constellation'     => $shop_customer->customer_info->birthday ? Self::constellation($shop_customer->customer_info->birthday) : NULL,
                            'reservation_count' => $customer_reservation->where('shop_id',$shop_info->id)->count(),
                            'reservation_date'  => $customer_reservation->where('shop_id',$shop_info->id)->last() ? substr($customer_reservation->where('shop_id',$shop_info->id)->where('status','Y')->last()->start,0,10) : NULL,
                            // 'tags'              => $shop_customer->tags->pluck('name'),
                            'level'             => '',
                            'beloneTo'          => $shop_customer->belongTo ? $shop_customer->belongTo->name : '',
                            'photo'             => preg_match('/http/i', $shop_customer->customer_info->photo) ? $shop_customer->customer_info->photo : env('SHOW_PHOTO').'/api/get/customer/'.$shop_customer->customer_info->photo,
                        ];
                    }

    				break;
    			}
    		}
    	}
    	
    	$customers = Customer::whereIn('id',$shop_customers->pluck('customer_id')->toArray())->get();
    	foreach( $customers as $customer ){
            $shop_customer_info = ShopCustomer::where('shop_id',$shop_id)->where('customer_id',$customer->id)->first();
    		if( $customer->birthday != NULL ){
    			// 當月壽星
    			if( date('m') == date('m',strtotime($customer->birthday)) ){
    				$current_month += 1;

                    if( request('select') == 'current_month' ){
                        $customer_list[] = [
                            'id'                => $shop_customer_info->id,
                            'name'              => $customer->realname . ($customer->nickname ? '('.$shop_customer->customer_info->nickname.')' : ''),
                            'phone'             => substr($customer->phone,0,4)
                                                    .'-'.substr($customer->phone,4,3)
                                                    .'-'.substr($customer->phone,7,3),
                            'sex'               => $customer->sex == 'M' ? '男' : ($customer->sex == 'F' ? '女' : '中性'),
                            'age'               => $customer->birthday ? Self::getAge($customer->birthday) : NULL,
                            'birthday'          => $customer->birthday,
                            'constellation'     => $customer->birthday ? Self::constellation($customer->birthday) : NULL,
                            'reservation_count' => $customer_reservation->where('shop_id',$shop_info->id)->count(),
                            'reservation_date'  => $customer_reservation->where('shop_id',$shop_info->id)->last() ? substr($customer_reservation->where('shop_id',$shop_info->id)->where('status','Y')->last()->start,0,10) : NULL,
                            // 'tags'              => $shop_customer->tags->pluck('name'),
                            'level'             => '',
                            'beloneTo'          => $shop_customer->belongTo ? $shop_customer->belongTo->name : '',
                            'photo'             => preg_match('/http/i', $customer->photo) ? $customer->photo : env('SHOW_PHOTO').'/api/get/customer/'.$customer->photo,
                        ];
                    }
    			}
    		}

            // 近三個月加入會員
            if( date('Y-m-d',strtotime('-3 month')) <= date('Y-m-d',strtotime($shop_customer_info->created_at)) 
                    && date('Y-m-d') >= date('Y-m-d',strtotime($shop_customer_info->created_at)) ){
                $three_month_join += 1;
                if( request('select') == 'three_month_join' ){
                    $customer_list[] = [
                        'id'                => $shop_customer_info->id,
                        'name'              => $customer->realname . ($customer->nickname ? '('.$shop_customer->customer_info->nickname.')' : ''),
                        'phone'             => substr($customer->phone,0,4)
                                                .'-'.substr($customer->phone,4,3)
                                                .'-'.substr($customer->phone,7,3),
                        'sex'               => $customer->sex == 'M' ? '男' : ($customer->sex == 'F' ? '女' : '中性'),
                        'age'               => $customer->birthday ? Self::getAge($customer->birthday) : NULL,
                        'birthday'          => $customer->birthday,
                        'constellation'     => $customer->birthday ? Self::constellation($customer->birthday) : NULL,
                        'reservation_count' => $customer_reservation->where('shop_id',$shop_info->id)->count(),
                        'reservation_date'  => $customer_reservation->where('shop_id',$shop_info->id)->last() ? substr($customer_reservation->where('shop_id',$shop_info->id)->where('status','Y')->last()->start,0,10) : NULL,
                        // 'tags'              => $shop_customer->tags->pluck('name'),
                        'level'             => '',
                        'beloneTo'          => $shop_customer->belongTo ? $shop_customer->belongTo->name : '',
                        'photo'             => preg_match('/http/i', $customer->photo) ? $customer->photo : env('SHOW_PHOTO').'/api/get/customer/'.$customer->photo,
                    ];
                }
            }
    	}

        // 商家優惠券
        $shop_coupons = $shop_info->shop_coupons->where('status','published')->where('start_date','<=',date('Y-m-d'))->where('end_date','>=',date('Y-m-d'));
        $coupon_data = [];
        foreach( $shop_coupons as $coupon ){
            $coupon_data[] = [
                'id'   => $coupon->id,
                'name' => $coupon->title,
            ];
        }

    	$data = [
            'status'                            => true,
            'permission'                        => true,
            'shop_customer_create_permission'   => !PermissionController::is_staff($shop_id) ? (in_array('shop_customer_create_btn',$user_shop_permission['permission']) ? true : false) : true, 
            'shop_customer_home_permission'     => !PermissionController::is_staff($shop_id) ? (in_array('shop_customer_home_btn',$user_shop_permission['permission']) ? true : false) : true, 
            'shop_customer_delete_permission'   => !PermissionController::is_staff($shop_id) ? (in_array('shop_customer_delete',$user_shop_permission['permission']) ? true : false) : true,
            'shop_customer_transfer_permission' => !PermissionController::is_staff($shop_id) ? (in_array('shop_customer_transfer',$user_shop_permission['permission']) ? true : false) : true,
            'gift_permission'                   => !PermissionController::is_staff($shop_id) ? (in_array('shop_customer_give_gift',$user_shop_permission['permission']) ? true : false) : true,
            'current_month'                     => $current_month,
            'three_month_join'                  => $three_month_join,
            'three_month_reservaton'            => $three_month_reservaton,
            'six_month_reservaton'              => $six_month_reservaton,
            'feature_reservation'               => $feature_reservation,
            'shop_coupons'                      => $coupon_data,
            'data'                              => request('select') && request('select') != 'all' ? $customer_list : $customer_data,
        ];

        return response()->json($data);
    }

    // 會員首頁
    public function shop_customer_home($shop_id,$shop_customer_id)
    {
        if( !PermissionController::is_staff($shop_id) ){
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_home', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_';
        }else{

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_home', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_';
        }

        $shop_info    = Shop::find($shop_id);

        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->first();//join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        // 會員預約資料
        $customer_reservation = CustomerReservation::where('customer_id',$shop_customer->customer_info->id)
                                                   ->where('shop_id',$shop_id)
                                                   ->get();

        // 會員優惠券資料
        $customer_coupons = CustomerCoupon::where('customer_id', $shop_customer->customer_info->id)
                                          ->where('shop_id', $shop_id)
                                          ->where('status', 'N')
                                          ->orderBy('id', 'DESC')
                                          ->get();

        // 會員集點卡資料
        $customer_loyalty_cards = CustomerLoyaltyCard::where('customer_id',$shop_customer->customer_info->id)
                                                     ->where('shop_id',$shop_id)
                                                     ->where('status','N')
                                                    //  ->where('last_point','!=',0)
                                                     ->orderBy('id','DESC')
                                                     ->get();

        // 會員儲值資料
        $customer_top_up = CustomerTopUp::where('customer_id', $shop_customer->customer_id)
                                        ->where('shop_id', $shop_info->id)
                                        ->get();

        // 會員方案資料
        $customer_programs = CustomerProgram::where('customer_id', $shop_customer->customer_id)
                                            ->where('shop_id', $shop_info->id)
                                            ->get();

        // 會員會員卡資料
        $customer_membership_cards = CustomerMembershipCard::where('customer_id', $shop_customer->customer_id)
                                                            ->where('shop_id', $shop_info->id)
                                                            ->get();

        // 會員優惠券區塊       
        $coupons = [];
        foreach( $customer_coupons as $cc ){
            // 需判斷期限內
            if( strtotime(date('Y-m-d')) <= strtotime($cc->coupon_info->end_date) ){
                $coupons[] = [
                    'name' => $cc->coupon_info->title,
                    'date' => (date('Y',strtotime($cc->coupon_info->end_date)) != date('Y') ? date('Y.m.d',strtotime($cc->coupon_info->end_date)) : date('m.d',strtotime($cc->coupon_info->end_date)) ).'止',
                ];
            }
        }

        // 會員集點卡區塊
        $loyalty_cards = [];
        foreach( $customer_loyalty_cards->where('last_point','!=',0) as $lc ){
            $check = true;
            $date  = ''; 
            if( $lc->last_point == 0 ){
                // 已集滿點數需判斷使用期限是否過期
                if( $lc->loyalty_card_info->discount_limit_type != 1 ){
                    // 計算天數
                    if( $lc->loyalty_card_info->discount_limit_month % 12 == 0 ){
                        $day = $lc->loyalty_card_info->discount_limit_month/12 * 365;
                    }else{
                        $day = 30 * $lc->loyalty_card_info->discount_limit_month;
                    }

                    $date = date( 'Y-m-d H:i:s', strtotime($lc->point_log->last()->created_at.' +'.$lc->loyalty_card_info->discount_limit_month.' month') );

                    if( strtotime(date('Y-m-d H:i:s')) > strtotime($date) ){
                        $check = false;
                    }
                }
            }else{
                // 需判斷是否還在集點有效期限
                if( $lc->loyalty_card_info->deadline_type == 4 ){
                    // 統一起迄
                    $date = $lc->loyalty_card_info->end_date;
                    if( strtotime(date('Y-m-d H:i:s')) > strtotime($date) ){
                        $check = false;
                    }
                }elseif( $lc->loyalty_card_info->deadline_type == 2 ){
                    // 獲得集點卡開始計算
                    $date = date( 'Y-m-d H:i:s', strtotime($lc->created_at.' +'.$lc->loyalty_card_info->year.' year +'.$lc->loyalty_card_info->month.' month') );
                    if( strtotime(date('Y-m-d H:i:s')) > strtotime($date) ){
                        $check = false;
                    }
                }elseif( $lc->loyalty_card_info->deadline_type == 3 ){

                    $last = $lc->point_log->last() ? $lc->point_log->last()->created_at : $lc->created_at;
                    $date = date( 'Y-m-d H:i:s', strtotime($last.' +'.$lc->loyalty_card_info->year.' year +'.$lc->loyalty_card_info->month.' month') );
                    // 最後一次獲得點數
                    if( strtotime(date('Y-m-d H:i:s')) > strtotime($date) ){
                        $check = false;
                    }
                }
            }

            if( $check ){
                if( $lc->last_point != 0 ){
                    $point = $lc->full_point-$lc->last_point.'點';
                }else{
                    $point = '集滿/';
                    if( $date ){
                        $point .= date('Y',strtotime($date)) != date('Y') ? date('Y.m.d',strtotime($date)) : date('m.d',strtotime($date)) . '止';
                    }else{
                        $point .= '無期限';
                    }
                    
                }
                $loyalty_cards[] = [
                    'name'     => $lc->loyalty_card_info->name,
                    'point'    => $point,
                    'deadline' => $date,
                ];
            }
        }

        // 會員卡區塊
        $membership_card = [];
        foreach( $customer_membership_cards as $card ){
            $deadline = '無期限';
            if( $card->membership_card_info->card_during_type == 2 ){
                // 顧客購買起
                $deadline = date('Y.m.d',strtotime($card->created_at . "+" . $card->membership_card_info->card_year . "year +" . $card->membership_card_info->card_month . 'month' )).'止';
            }elseif( $card->membership_card_info->card_during_type == 3 ){
                // 統一起迄
                if( time() > $card->membership_card_info->card_end_date ) continue;
                $deadline = date('Y.m.d',strtotime($card->membership_card_info->card_end_date)).'止';
            }

            $membership_card[] = [
                'name'     => $card->membership_card_info->name,
                'deadline' => $deadline,
            ];
        }

        // 會員標籤(待補)

        // 會員可用方案區塊
        if( in_array($shop_info->buy_mode_id,[5,6]) ){
            $program_count = 0;
            foreach ($customer_programs as $program) {
                $program_count += $program->groups->sum('last_count');
            }
            $programs = [
                'count' => $program_count,
            ];
        }else{
            $programs = [
                'count' => '升級後開啟此功能'
            ];
        }
        
        // 會員儲值區塊
        if( in_array($shop_info->buy_mode_id,[5,6]) ){
            $top_up_logs = CustomerTopUpLog::where('shop_id',$shop_info->id)
                                            ->where('customer_id',$shop_customer->customer_id)
                                            ->orderBy('id','ASC')
                                            ->get();

            $top_up = [
                'last'      => $top_up_logs->sum('price'),
                'total'     => $top_up_logs->where('price','>',0)->sum('price'),
                'deduction' => $top_up_logs->where('price','<',0)->sum('price'),
            ];
        }else{
            $top_up = [
                'last'      => '升級後開啟此功能',
                'total'     => '-',
                'deduction' => '-'
            ];
        }
        
        // 會員消費消費明細區塊
        if( in_array($shop_info->buy_mode_id,[5,6]) ){
            $bills = Bill::where('shop_id',$shop_info->id)
                            ->where('customer_id',$shop_customer->customer_id)
                            ->where('status','finish')
                            ->get();
            $total = 0;
            foreach( $bills as $bill ){
                $consumption = json_decode($bill->consumption);
                $total += $consumption->final_total->total;
            }
            $consumer_details = [
                'total' => $total,
                'count' => $bills->count(),
                'level' => '-',
            ];
        }else{
            $consumer_details = [
                'total' => '升級後開啟此功能',
                'count' => '升級後開啟此功能',
                'level' => '-',
            ];
        }

        // 會員可用優惠區塊(優惠券、滿點集點卡、儲值)
        $discount = [];
        foreach( $customer_coupons as $cc ){
            // 需判斷期限內
            if (strtotime(date('Y-m-d')) <= strtotime($cc->coupon_info->end_date)) {

                if($cc->coupon_info->type == 'discount'){
                    if( $cc->coupon_info->limit == 1 ) $name = '全品項'.$cc->coupon_info->discount.'折';
                    if( $cc->coupon_info->limit == 2 ) $name = '全服務'.$cc->coupon_info->discount.'折';
                    if( $cc->coupon_info->limit == 3 ) $name = '全產品'.$cc->coupon_info->discount.'折';
                    if( $cc->coupon_info->limit == 4 ) $name = '部分品項'.$cc->coupon_info->discount.'折';
                } elseif ($cc->coupon_info->type == 'full_consumption') {
                    if($cc->coupon_info->limit == 1) $name = '消費滿'.$cc->coupon_info->consumption.'，全品項'.$cc->coupon_info->discount.'折';
                    if($cc->coupon_info->limit == 2) $name = '消費滿'.$cc->coupon_info->consumption.'，全服務'.$cc->coupon_info->discount . '折';
                    if($cc->coupon_info->limit == 3) $name = '消費滿'.$cc->coupon_info->consumption.'，全產品'.$cc->coupon_info->discount . '折';
                    if($cc->coupon_info->limit == 4) $name = '消費滿'.$cc->coupon_info->consumption.'，部分品項'.$cc->coupon_info->discount . '折';
                } elseif ($cc->coupon_info->type == 'experience') {
                    if(!$cc->coupon_info->service_info) continue;
                    $name = $cc->coupon_info->service_info->name." 體驗價".$cc->coupon_info->price."元";
                } elseif ($cc->coupon_info->type == 'free') {
                    if (!$cc->coupon_info->self_definition && !$cc->coupon_info->service_info) continue;
                    $name = "免費體驗".($cc->coupon_info->self_definition ? $cc->coupon_info->self_definition : $cc->coupon_info->service_info->name);
                } elseif ($cc->coupon_info->type == 'gift') {
                    if (!$cc->coupon_info->self_definition && !$cc->coupon_info->produce_info ) continue;
                    $name = "贈送".($cc->coupon_info->self_definition ? $cc->coupon_info->self_definition : $cc->coupon_info->produce_info->name);
                } elseif ($cc->coupon_info->type == 'cash') {
                    if( $cc->coupon_info->second_type == 5 ){
                        if ($cc->coupon_info->limit == 1) $name = '全品項抵扣' . $cc->coupon_info->price . '元';
                        if ($cc->coupon_info->limit == 2) $name = '全服務抵扣' . $cc->coupon_info->price . '元';
                        if ($cc->coupon_info->limit == 3) $name = '全產品抵扣' . $cc->coupon_info->price . '元';
                        if ($cc->coupon_info->limit == 4) $name = '部分品項抵扣' . $cc->coupon_info->price . '元';
                    }else{
                        if ($cc->coupon_info->limit == 1) $name = '消費滿' . $cc->coupon_info->consumption . '，全品項抵扣' . $cc->coupon_info->price . '元';
                        if ($cc->coupon_info->limit == 2) $name = '消費滿' . $cc->coupon_info->consumption . '，全服務抵扣' . $cc->coupon_info->price . '元';
                        if ($cc->coupon_info->limit == 3) $name = '消費滿' . $cc->coupon_info->consumption . '，全產品抵扣' . $cc->coupon_info->price . '元';
                        if ($cc->coupon_info->limit == 4) $name = '消費滿' . $cc->coupon_info->consumption . '，部分品項抵扣' . $cc->coupon_info->price . '元';
                    }
                }

                $discount[] = [
                    'name'  => $name,
                    'type'  => 'coupon',
                    'date'  => date('Y.m.d', strtotime($cc->coupon_info->end_date)).'止',
                    'order' => $cc->created_at,
                ];
            }
        }

        foreach( $customer_loyalty_cards->where('last_point',0) as $lc ){
            if ($lc->last_point == 0) {
                if ($lc->loyalty_card_info->type == 'free') {
                    if (!$lc->loyalty_card_info->self_definition && !$lc->loyalty_card_info->service_info) continue;
                    $name = "免費體驗" . ($lc->loyalty_card_info->self_definition ? $lc->loyalty_card_info->self_definition : $lc->loyalty_card_info->service_info->name);
                } elseif ($lc->loyalty_card_info->type == 'gift') {
                    if (!$lc->loyalty_card_info->self_definition && !$lc->loyalty_card_info->produce_info) continue;
                    $name = "贈送" . ($lc->loyalty_card_info->self_definition ? $lc->loyalty_card_info->self_definition : $lc->loyalty_card_info->produce_info->name);
                } elseif ($lc->loyalty_card_info->type == 'cash') {
                    if ($lc->loyalty_card_info->second_type == 5) {
                        if ($lc->loyalty_card_info->limit == 1) $name = '全品項抵扣' . $lc->loyalty_card_info->price . '元';
                        if ($lc->loyalty_card_info->limit == 2) $name = '全服務抵扣' . $lc->loyalty_card_info->price . '元';
                        if ($lc->loyalty_card_info->limit == 3) $name = '全產品抵扣' . $lc->loyalty_card_info->price . '元';
                        if ($lc->loyalty_card_info->limit == 4) $name = '部分品項抵扣' . $lc->loyalty_card_info->price . '元';
                    } else {
                        if ($lc->loyalty_card_info->limit == 1) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全品項抵扣' . $lc->loyalty_card_info->price . '元';
                        if ($lc->loyalty_card_info->limit == 2) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全服務抵扣' . $lc->loyalty_card_info->price . '元';
                        if ($lc->loyalty_card_info->limit == 3) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全產品抵扣' . $lc->loyalty_card_info->price . '元';
                        if ($lc->loyalty_card_info->limit == 4) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，部分品項抵扣' . $lc->loyalty_card_info->price . '元';
                    }
                }

                // 已集滿點數需判斷使用期限是否過期
                if ($lc->loyalty_card_info->discount_limit_type != 1) {
                    $date = date('Y-m-d H:i:s', strtotime($lc->point_log->last()->created_at . ' +' . $lc->loyalty_card_info->discount_limit_month . ' month'));

                    if (strtotime(date('Y-m-d H:i:s')) <= strtotime($date)) {
                        $discount[] = [
                            'name'  => $name,
                            'type'  => 'card',
                            'date'  => date('Y.m.d',strtotime($date)).'止',
                            'order' => $lc->point_log->last()->created_at,
                        ];
                    }
                }else{
                    $discount[] = [
                        'name'  => $name,
                        'type'  => 'card',
                        'date'  => '無期限',
                        'order' => $lc->point_log->last()->created_at,
                    ];
                }
            }
        }

        foreach( $customer_top_up as $ctu ){
            foreach( $ctu->logs as $log ){
                // 儲值規則是免費體驗或是贈品
                if ($log->type == 7 || $log->type == 8) {
                    if( $log->status == 'Y' ) continue;
                    $role_info = $log->top_up_role;
                    if( $role_info ){
                        // 檢查期限
                        $date = date('Y-m-d H:i:s', strtotime($log->created_at . ' +' . $role_info->deadline_month . ' month'));

                        if (strtotime(date('Y-m-d H:i:s')) <= strtotime($date)) {

                            if( $log->type == 7 ){
                                // 贈品
                                if ( !$role_info->self_definition && !$role_info->product_info ) continue;
                                $name = "贈送" . ($role_info->self_definition ? $role_info->self_definition : $role_info->product_info->name);
                            }else{
                                // 免費體驗
                                if ( !$role_info->self_definition && !$role_info->service_info ) continue;
                                $name = "免費體驗" . ($role_info->self_definition ? $role_info->self_definition : $role_info->service_info->name);
                            }

                            $discount[] = [
                                'name'  => $name,
                                'type'  => 'top_up',
                                'date'  => date('Y.m.d', strtotime($date)) . '止',
                                'order' => $log->created_at,
                            ];
                        }
                    }
                }
            }
        }

        // 排序
        array_multisort(array_column($discount,'date'),SORT_ASC,$discount);
        
        // customer_reservation 1到了 / 2爽約 / 3小遲到 / 4大遲到 / 5 提早 shop_reservation_tag 1提早 2小遲到 3大遲到 4爽約
        // T旗子 BT黑名單 N無
        $shop_reservation_tags = ShopReservationTag::where('shop_id',$shop_id)->orderBy('type','ASC')->get();
        $tag = [
            'early'       => 'N',
            'little_late' => 'N',
            'vert_late'   => 'N',
            'flake_out'   => 'N',
        ];
        foreach( $shop_reservation_tags as $rt ){
            // 提早
            if( $rt->type == 1 && $rt->times != '' && $rt->times >= $customer_reservation->where('tag',5)->count() ){
                if( $tag['early'] != 'BT' ){
                    $tag['early'] = 'T';
                }

                if( $tag['early'] == 'T' && $rt->blacklist == 'Y' ){
                    $tag['early'] = 'BT';
                }
            }

            // 小遲到
            if( $rt->type == 2 && $rt->times != '' && $rt->times >= $customer_reservation->where('tag',3)->count() ){
                if( $tag['little_late'] != 'BT' ){
                    $tag['little_late'] = 'T';
                }

                if( $tag['little_late'] == 'T' && $rt->blacklist == 'Y' ){
                    $tag['little_late'] = 'BT';
                }
            }

            // 大遲到
            if( $rt->type == 3 && $rt->times != '' && $rt->times >= $customer_reservation->where('tag',4)->count() ){
                if( $tag['vert_late'] != 'BT' ){
                    $tag['vert_late'] = 'T';
                }

                if( $tag['vert_late'] == 'T' && $rt->blacklist == 'Y' ){
                    $tag['vert_late'] = 'BT';
                }
            }

            // 爽約
            if( $rt->type == 4 && $rt->times != '' && $rt->times >= $customer_reservation->where('tag',2)->count() ){
                if( $tag['flake_out'] != 'BT' ){
                    $tag['flake_out'] = 'T';
                }

                if( $tag['flake_out'] == 'T' && $rt->blacklist == 'Y' ){
                    $tag['flake_out'] = 'BT';
                }
            }
        }

        $personality = json_decode(json_encode( Self::shop_customer_personality($shop_info->id,$shop_customer->id) ));

        $photo = '';
        if( $shop_customer->customer_info->photo ){
            if( !preg_match('/http/i', $shop_customer->customer_info->photo) ){
                $photo = env('SHOW_PHOTO').'/api/get/customer/'.$shop_customer->customer_info->photo;
            }else{
                $photo = $shop_customer->customer_info->photo;
            }
        }

        $traits = json_decode(json_encode( Self::shop_customer_traits($shop_info->id,$shop_customer->id) ));

        $shop_customer->personality        = $personality->original->data->top->number;
        $shop_customer->traits             = $traits->original->data->type;
        $shop_customer->realname           = $shop_customer->customer_info->realname;
        $shop_customer->phone              = $shop_customer->customer_info->phone;
        $shop_customer->email              = $shop_customer->customer_info->email;
        $shop_customer->birthday           = $shop_customer->customer_info->birthday;
        $shop_customer->sex                = $shop_customer->customer_info->sex;
        $shop_customer->facebook_id        = $shop_customer->customer_info->facebook_id;
        $shop_customer->facebook_name      = $shop_customer->customer_info->facebook_name;
        $shop_customer->google_id          = $shop_customer->customer_info->google_id;
        $shop_customer->google_name        = $shop_customer->customer_info->google_name;
        $shop_customer->line_id            = $shop_customer->customer_info->line_id;
        $shop_customer->line_name          = $shop_customer->customer_info->line_name;
        $shop_customer->photo              = $photo;
        $shop_customer->banner             = $shop_customer->customer_info->banner;
        $shop_customer->sex_text           = $shop_customer->customer_info->sex == 'M' ? '男' : ($shop_customer->customer_info->sex == 'F' ? '女' : '中性' );
        $shop_customer->birthday_text      = $shop_customer->customer_info->birthday ? date('m.d',strtotime($shop_customer->customer_info->birthday)) : '';
        $shop_customer->age_text           = $shop_customer->customer_info->birthday ? Self::getAge($shop_customer->customer_info->birthday).'歲' : '';
        $shop_customer->constellation_text = $shop_customer->customer_info->birthday ? Self::constellation($shop_customer->customer_info->birthday) : '';

        // 會員的預約標籤
        $customer_reservation_tags = ShopCustomerReservationTag::where('shop_customer_id',$shop_customer->id)->pluck('name');

        $data = [
            'status'          => true,
            'permission'      => true,
            'customer_info'   => $shop_customer,
            'customer_edit_permission'=> in_array($per.'edit',$permission['permission']) ? true : false,
            'tags'            => $customer_reservation_tags,
            'reservation_log' => [
                'total'       => $customer_reservation->count(),
                'arrive'      => $customer_reservation->where('tag','!=',2)->where('tag','!=',NULL)->where('cancel_status',NULL)->count(),
                'on_time'     => [
                    'count' => $customer_reservation->where('tag',1)->count(),
                    'icon'  => 'N',
                ],
                'early'       => [
                    'count' => $customer_reservation->where('tag',5)->count(),
                    'icon'  => $tag['early'],
                ],
                'little_late' => [
                    'count' => $customer_reservation->where('tag',3)->count(),
                    'icon'  => $tag['little_late'],
                ],
                'vert_late'   => [
                    'count' => $customer_reservation->where('tag',4)->count(),
                    'icon'  => $tag['vert_late'],
                ],
                'flake_out'   => [
                    'count' => $customer_reservation->where('tag',2)->count(),
                    'icon'  => $tag['flake_out'],
                ],
                'change'      => $customer_reservation->where('change',1)->count(),
                'cancel'      => $customer_reservation->where('cancel_status','M')->count(),
            ],
            'reservation_log_permission'  => in_array($per.'reservation',$permission['permission']) ? true : false,
            'questionnaire_permission'    => in_array($per.'question_answer',$permission['permission']) ? true : false,
            'body_mark_permission'        => false, // 之後pro補權限
            'evaluation_log_permission'   => in_array($per.'evaluate',$permission['permission']) ? true : false, 
            'customer_tag_permission'     => false, // 之後pro補權限
            'customer_album_permission'   => false, // 之後pro補權限
            'consumer_details'            => $consumer_details,
            'consumer_details_permission' => in_array($per.'consumer_details',$permission['permission']) ? true : false,
            'top_up'                      => $top_up,
            'top_up_permission'           => in_array($per.'top_up',$permission['permission']) ? true : false,
            'programs'                    => $programs,
            'programs_permission'         => in_array($per.'programs',$permission['permission']) ? true : false,
            'membership_cards'            => $membership_card,
            'membership_cards_permission' => in_array($per.'membership_cards',$permission['permission']) ? true : false,
            'loyalty_cards'               => $loyalty_cards,
            'loyalty_cards_permission'    => in_array($per.'loyalty_card',$permission['permission']) ? true : false,
            'coupons'                     => $coupons,
            'coupons_permission'          => in_array($per.'coupon',$permission['permission']) ? true : false,
            'discount'                    => $discount,
            'discount_permission'         => true,
        ];

        return response()->json($data);
    }

    // 會員人格
    static public function shop_customer_personality($shop_id,$shop_customer_id)
    {
        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        $birthday = $shop_customer->birthday ? explode('-', $shop_customer->birthday) : [];

        $shop_info = Shop::find($shop_id);
        $data = [
            'out' => [
                'photo'          => env('SHOW_PHOTO').'/api/show/customer/personality/no',
                'number'         => '',
                'type'           => '',
                'like'           => '',
                'hate'           => '',
                'value_type'     => '',
                'values'         => '',
                'characteristic' => '',
                'recommend'      => '',
            ],
            'in' => [
                'photo'          => env('SHOW_PHOTO').'/api/show/customer/personality/no',
                'number'         => '',
                'type'           => '',
                'like'           => '',
                'hate'           => '',
                'value_type'     => '',
                'values'         => '',
                'characteristic' => '',
                'recommend'      => '',
            ],
            'top' => [
                'photo'          => env('SHOW_PHOTO').'/api/show/customer/personality/no',
                'number'         => '',
                'type'           => '',
                'like'           => '',
                'hate'           => '',
                'value_type'     => '',
                'values'         => '',
                'characteristic' => '',
                'recommend'      => '',
            ],
        ];

        if( !empty($birthday) ){

            $number_arr = [ $birthday[2] , $birthday[1] , substr($birthday[0], 0,2) , substr($birthday[0], 2,2) ];

            // 第一組數字和
            if( substr($number_arr[0],0,1) == 9 && substr($number_arr[0],1,1) == 9 ){
                $tmp = 9;
            }elseif( substr($number_arr[0],0,1) == 9 || substr($number_arr[0],1,1) == 9 ){
                if( substr($number_arr[0],0,1) == 0 || substr($number_arr[0],1,1) == 0 ){
                    $tmp = 9;
                }else{
                    $tmp = substr($number_arr[0],0,1) == 9 ? substr($number_arr[0],1,1) : substr($number_arr[0],0,1);
                }
            }else{
                $tmp = substr($number_arr[0],0,1) + substr($number_arr[0],1,1);
                if( $tmp > 9 ){
                    $tmp = substr($tmp,0,1) + substr($tmp,1,1);
                }
            }

            // 第二組數字和
            if( substr($number_arr[1],0,1) == 9 && substr($number_arr[1],1,1) == 9 ){
                $tmp1 = 9;
            }elseif( substr($number_arr[1],0,1) == 9 || substr($number_arr[1],1,1) == 9 ){
                if( substr($number_arr[1],0,1) == 0 || substr($number_arr[1],1,1) == 0 ){
                    $tmp1 = 9;
                }else{
                    $tmp1 = substr($number_arr[1],0,1) == 9 ? substr($number_arr[1],1,1) : substr($number_arr[1],0,1);
                }
            }else{
                $tmp1 = substr($number_arr[1],0,1) + substr($number_arr[1],1,1);
                if( $tmp1 > 9 ){
                    $tmp1 = substr($tmp1,0,1) + substr($tmp1,1,1);
                }
            }

            // 第三組數字和
            if( substr($number_arr[2],0,1) == 9 && substr($number_arr[2],1,1) == 9 ){
                $tmp2 = 9;
            }elseif( substr($number_arr[2],0,1) == 9 || substr($number_arr[2],1,1) == 9 ){
                if( substr($number_arr[2],0,1) == 0 || substr($number_arr[2],1,1) == 0 ){
                    $tmp2 = 9;
                }else{
                    $tmp2 = substr($number_arr[2],0,1) == 9 ? substr($number_arr[2],1,1) : substr($number_arr[2],0,1);
                }
                
            }else{
                $tmp2 = substr($number_arr[2],0,1) + substr($number_arr[2],1,1);
                if( $tmp2 > 9 ){
                    $tmp2 = substr($tmp2,0,1) + substr($tmp2,1,1);
                }
            }

            // 第四組數字和
            if( substr($number_arr[3],0,1) == 0 && substr($number_arr[3],1,1) == 0 ){
                $tmp3 = 5;
            }else{
                if( substr($number_arr[3],0,1) == 9 && substr($number_arr[3],1,1) == 9 ){
                    $tmp3 = 9;
                }elseif( substr($number_arr[3],0,1) == 9 || substr($number_arr[3],1,1) == 9 ){
                    if( substr($number_arr[3],0,1) == 0 || substr($number_arr[3],1,1) == 0 ){
                        $tmp3 = 9;
                    }else{
                        $tmp3 = substr($number_arr[3],0,1) == 9 ? substr($number_arr[3],1,1) : substr($number_arr[3],0,1);
                    }
                }else{
                    $tmp3 = substr($number_arr[3],0,1) + substr($number_arr[3],1,1);
                    if( $tmp3 > 9 ){
                        $tmp3 = substr($tmp3,0,1) + substr($tmp3,1,1);
                    }
                }
            }
            
            if( $tmp == 9 && $tmp1 == 9 ){
                $tmp4 = 9;
            }elseif( $tmp == 9 || $tmp1 == 9 ){
                $tmp4 = $tmp == 9 ? $tmp1 : $tmp;
            }else{
                $tmp4 = $tmp + $tmp1;
                if( $tmp4 > 9 ){
                    $tmp4 = substr($tmp4,0,1) + substr($tmp4,1,1);
                }
            }

            if( $tmp2 == 9 && $tmp3 == 9 ){
                $tmp5 = 9;
            }elseif( $tmp2 == 9 || $tmp3 == 9 ){
                $tmp5 = $tmp2 == 9 ? $tmp3 : $tmp2;
            }else{
                $tmp5 = $tmp2 + $tmp3;
                if( $tmp5 > 9 ){
                    $tmp5 = substr($tmp5,0,1) + substr($tmp5,1,1);
                }
            }

            $top_value = $tmp4 + $tmp5;
            if( $top_value > 9 ){
                $top_value = substr($top_value,0,1) + substr($top_value,1,1);
            }

            $out = CustomerPersonality::where('number',$tmp)->first();
            $in  = CustomerPersonality::where('number',$tmp3)->first();
            $top = CustomerPersonality::where('number',$top_value)->first();

            $out->photo = env('SHOW_PHOTO').'/api/show/customer/personality/'.$tmp;
            $in->photo = env('SHOW_PHOTO').'/api/show/customer/personality/'.$tmp3;
            $top->photo = env('SHOW_PHOTO').'/api/show/customer/personality/'.$top_value;

            $data = [
                'customer_info' => $shop_customer,
                'out'           => $out,
                'in'            => $in,
                'top'           => $top,
            ];
        }

        return response()->json(['status'=>true,'data'=>$data]);
    }

    // 會員五行
    static public function shop_customer_traits($shop_id,$shop_customer_id)
    {
        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        $birthday = $shop_customer->birthday ? explode('-', $shop_customer->birthday) : [];
        $shop_info = Shop::find($shop_id);
        $data = [
            'icon'        => '',
            'photo'       => '',
            'word'        => '',
            'type'        => '',
            'body'        => '',
            'profession'  => '',
            'personality' => '',
            'consumption' => '',
            'sale'        => '',
        ];

        if( !empty($birthday) ){
            $calendar = new Calendar();
            $trans = $calendar->solar($birthday[0],$birthday[1],$birthday[2]);

            $data = CustomerTraits::where('word',$trans['ganzhi_day'])->first();
            $data->icon  = env('SHOW_PHOTO').'/api/show/customer/traits/icon/'.$data->type.'.png';
            $data->photo = env('SHOW_PHOTO').'/api/show/customer/traits/photo/'.$data->type.'.png';
        }

        return response()->json(['status'=>true,'data'=>$data]);
    }

    // 會員預約記錄
    public function shop_customer_reservation($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_reservation', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_';
        } else {

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_reservation', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        // 會員預約資料
        $customer_reservations = CustomerReservation::where('customer_id',$shop_customer->customer_info->id)->where('shop_id',$shop_id)->orderBy('start','DESC')->get();
        $reservations = [];
        foreach( $customer_reservations as $cr ){
            $status = $cr->status == 'Y' ? '已核准' : '未審核';
            if( $cr->cancel_status != NULL ){
                $status = '取消預約';
            }else{
                if( $cr->tag != NULL ){
                    switch ($cr->tag){
                        case 1:
                            $status = '正常';
                            break;
                        case 2:
                            $status = '爽約';
                            break;
                        case 3:
                            $status = '小遲到';
                            break;
                        case 4:
                            $status = '大遲到';
                            break;
                        case 5:
                            $status = '提早';
                            break;
                    }
                }
            }
            
            $reservations[] = [
                'date'    => $cr->start,
                'service' => $cr->service_info->name,
                'staff'   => $cr->staff_info->name,
                'status'  => $status,
            ];
        }

        // 標籤
        // $customr_tags = CustomerTag::where('customer_id',$shop_customer->customer_info->id)->get();
        // $tags = [];
        // foreach( $customr_tags as $tag ){
        //     $tags[] = $tag->tag.'('.$tag->count.($tag->blacklist=='Y'?'/列黑名單':'').')';
        // }

        // 會員的預約標籤
        $customer_reservation_tags = ShopCustomerReservationTag::where('shop_customer_id',$shop_customer->id)->pluck('name');

        $data = [
            'status'        => true,
            'permission'    => true,
            'customer_info' => $shop_customer,
            'tags'          => $customer_reservation_tags,
            'reservations'  => $reservations,
        ];

        return response()->json($data);
    }

    // 會員已領取尚未使用優惠
    public function shop_customer_coupon($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_coupon', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_';
        } else {

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_coupon', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        // 會員優惠券
        $customer_coupons = CustomerCoupon::where('customer_id',$shop_customer->customer_info->id)->where('shop_id',$shop_id)->where('status','N')->orderBy('id','DESC')->get();
        $coupons = [];
        foreach( $customer_coupons as $cc ){
            // 需判斷期限內
            if( strtotime(date('Y-m-d')) <= strtotime($cc->coupon_info->end_date) ){
                switch ($cc->coupon_info->type){
                    case 'discount':
                        $type = '折扣'.$cc->coupon_info->discount.'折';
                        break;
                    case 'full_consumption':
                        $type = '消費滿'.$cc->coupon_info->consumption.'元，享有'.$cc->coupon_info->discount.'折優惠';
                        break;
                    case 'experience':
                        $type = ($cc->coupon_info->service_info ? $cc->coupon_info->service_info->name : '') . ' 體驗價 ' . $cc->price . '元';
                        break;
                    case 'gift':
                        $type = '贈品 ' . ($cc->self_definition ? $cc->self_definition : ($cc->coupon_info->product_info ? $cc->coupon_info->product_info->name : '') );
                        break;
                    case 'free':
                        $type = '贈送 ' . ($cc->self_definition ? $cc->self_definition : ($cc->coupon_info->service_info ? $cc->coupon_info->service_info->name : '') );
                        break;
                    case 'cash':
                        $type = $cc->second_type == 4 ? '消費滿' . $cc->consumption . '元，現折' . $cc->price . '元' : $cc->price . '元現金券';
                        break;
                    default:
                        $type = '優惠券類型';
                        break;
                }

                $limit_text = '';
                $itme_name  = [];
                switch ($cc->coupon_info->limit) {
                    case 2:
                        $limit_text = '適用項目：全服務品項適用';
                        break;
                    case 3:
                        $limit_text = '適用項目：全產品品項適用';
                        break;
                    case 4:
                        $limit_commodity = CompanyCouponLimit::where('company_coupon_id',$cc->coupon_info->company_coupon_id)->get();
                        $limit_text      = '適用 '.$limit_commodity->count().' 項目，如下：' ;
                        foreach( $limit_commodity as $lc ){
                            if( $lc->type == 'service' ){
                                if( $lc->service_info ){
                                    $itme_name[] = $lc->service_info->name;
                                }
                                
                            }else{
                                // 產品待補
                            }
                        }
                        break;
                }

                $coupons[] = [
                    'name'        => $cc->coupon_info->title,
                    'description' => $cc->coupon_info->description,
                    'start_date'  => $cc->coupon_info->start_date,
                    'end_date'    => $cc->coupon_info->end_date,
                    'type'        => $type,
                    'limit_text'  => $limit_text,
                    'limit_items' => $itme_name,
                    'content'     => $cc->coupon_info->content,
                ];
            }
        }

        $data = [
            'status'        => true,
            'permission'    => true,
            'customer_info' => $shop_customer,
            'coupons'       => $coupons
        ];

        return response()->json($data);
    }

    // 會員已領取集點卡
    public function shop_customer_loyaltyCard($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_loyalty_card', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_';
        } else {

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_loyalty_card', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        // 會員集點卡
        $customer_loyalty_cards = CustomerLoyaltyCard::where('customer_id',$shop_customer->customer_info->id)->where('shop_id',$shop_id)->orderBy('id','DESC')->get();
        $loyalty_cards = [];
        foreach( $customer_loyalty_cards as $lc ){
            $check         = true;
            $deadline      = ''; 
            $text_deadline = '';

            if( $lc->last_point == 0 ){
                // 已集滿點數需判斷使用期限是否過期
                if( $lc->loyalty_card_info->discount_limit_type != 1 ){
                    // 計算天數
                    if( $lc->loyalty_card_info->discount_limit_month % 12 == 0 ){
                        $day = $lc->loyalty_card_info->discount_limit_month/12 * 365;
                    }else{
                        $day = 30 * $lc->loyalty_card_info->discount_limit_month;
                    }
                    $deadline = date( 'Y-m-d H:i:s', strtotime($lc->point_log->last()->created_at.' +'.$lc->loyalty_card_info->discount_limit_month.' month') );
                }
            }else{
                // 需判斷是否還在集點有效期限
                if( $lc->loyalty_card_info->deadline_type == 4 ){
                    // 統一起迄
                    $deadline = $lc->loyalty_card_info->end_date;
                }elseif( $lc->loyalty_card_info->deadline_type == 2 ){
                    // 獲得集點卡開始計算
                    $deadline = date( 'Y-m-d H:i:s', strtotime($lc->created_at.' +'.$lc->loyalty_card_info->year.' year +'.$lc->loyalty_card_info->month.' month') );
                }elseif( $lc->loyalty_card_info->deadline_type == 3 ){
                    // 最後一次獲得點數
                    $last = $lc->point_log->last()->created_at;
                    $deadline = date( 'Y-m-d H:i:s', strtotime($last.' +'.$lc->loyalty_card_info->year.' year +'.$lc->loyalty_card_info->month.' month') );
                }
            }

            // 確認是否要呈現，若超過期限就跳過
            if( $deadline && strtotime(date('Y-m-d H:i:s')) > strtotime($deadline) ){
                $check = false;
            }

            // 點數與點數期限描述
            if( $lc->last_point != 0 ){
                $point      = $lc->full_point - $lc->last_point;
                $point_text = $lc->full_point - $lc->last_point.' / '.$lc->full_point;
            }else{
                $point_text = '已集滿';
                $point      = $lc->full_point;
            }

            // 集點卡類型
            $type = '';
            switch( $lc->loyalty_card_info->type ){
                case'free':
                    $type = ($lc->loyalty_card_info->second_type == 3 ? ($lc->loyalty_card_info->service_info ? $lc->loyalty_card_info->service_info->name : '') : $lc->loyalty_card_info->self_definition );
                    break;
                case'gift':
                    $type = ($lc->loyalty_card_info->second_type == 1 ? ($lc->loyalty_card_info->product_info ? $lc->loyalty_card_info->product_info->name : '') : $lc->loyalty_card_info->self_definition );
                    break;
                case'cash':
                    $type = $lc->loyalty_card_info->second_type == 5 ? $lc->loyalty_card_info->price.'元折價券' : '消費滿'.$lc->loyalty_card_info->consumption.'抵扣'.$lc->loyalty_card_info->price;
                    break;
            }

            // 適用項目
            $limit_text  = '';
            $limit_items = [];
            switch ($lc->loyalty_card_info->limit) {
                case 2:
                    $limit_text = '適用項目：全服務品項適用';
                    break;
                case 3:
                    $limit_text = '適用項目：全產品品項適用';
                    break;
                case 4:
                    $limit_commodity = CompanyLoyaltyCardLimit::where('company_loyalty_card_id',$lc->loyalty_card_info->company_loyalty_card_id)->get();
                    $limit_text      = '適用 '.$limit_commodity->count().' 項目，如下：';
                    foreach( $limit_commodity as $rd ){
                        if( $rd->type == 'service' ){
                            $limit_items[] = $rd->service_info->name;
                        }else{
                            // 產品待補
                        }
                    }
                    break;
            }

            // 使用期限
            $use_deadline = $point_deadline = '-'; 
            if( $lc->last_point == 0 ){
                switch( $lc->loyalty_card_info->discount_limit_type ){
                    case 1:
                        $text_deadline  = '兌換期限：無期限';
                        $use_deadline   = '無期限';
                        $point_deadline = '-';
                        break;
                    case 2:
                    case 3:
                    case 4:
                    case 5:
                    case 6:
                        $text_deadline  = '兌換期限：'.date('Y-m-d' , strtotime($deadline) );
                        $use_deadline   = date('Y-m-d' , strtotime($deadline) );
                        $point_deadline = '-';
                        break;
                }
            }else{
                switch ( $lc->loyalty_card_info->deadline_type ) {
                    case 1:
                        $text_deadline  = '活動期限：無期限';
                        $use_deadline   = '-';
                        $point_deadline = '無期限';
                        break;
                    case 2:
                    case 3:
                        $text_deadline  = '活動期限至 '.date('Y-m-d' , strtotime($deadline) )."止";
                        $use_deadline   = '-';
                        $point_deadline = date('Y-m-d' , strtotime($deadline) );

                        break;
                    case 4:
                        $text_deadline  = $lc->loyalty_card_info->start_date . ' 至 ' . $lc->loyalty_card_info->end_date;
                        $use_deadline   = '-';
                        $point_deadline = $lc->loyalty_card_info->end_date;
                        break;
                }
            }

            $function_btn = '';
            if( $use_deadline != '-' ){
                $function_btn = 'use';
            }elseif( $point_deadline != '-' ){
                $function_btn = 'give';
            }

            // 已過期
            if( $check == false ){
                $function_btn = '';
            }

            if( $lc->using_time ){
                $point_text   = '已使用';
                $function_btn = '';
            }

            // 浮水印
            $photo = '';
            if( $lc->loyalty_card_info->watermark_img ){
                $photo = env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$lc->loyalty_card_info->watermark_img;
            }else{
                $photo =  env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$company_info->logo;
            }

            $loyalty_cards[] = [
                'id'             => $lc->id,
                'name'           => $lc->loyalty_card_info->name,
                'type'           => $type,
                'point'          => $point,
                'full_point'     => $lc->full_point,
                'point_text'     => $point_text,
                'point_deadline' => $point_deadline,
                'use_deadline'   => $use_deadline,
                'using_time'     => $lc->using_time ? substr($lc->using_time,0,10) : '-',
                'deadline'       => $text_deadline,
                'function'       => $function_btn,
                'limit_text'     => $limit_text,
                'limit_items'    => $limit_items,
                'photo'          => $photo,
                'content'        => $lc->loyalty_card_info->content,
            ];
        }

        $data = [
            'status'        => true,
            'permission'    => true,
            'customer_info' => $shop_customer,
            'loyalty_cards' => $loyalty_cards
        ];

        return response()->json($data);
    }

    // 會員可用優惠
    public function shop_customer_discount($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            // if (!in_array('shop_customer_discount', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        } else {

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            // if (!in_array('staff_customer_discount', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        }

        $shop_info = Shop::find($shop_id);

        $shop_customer = ShopCustomer::where('shop_customers.id', $shop_customer_id)->join('customers', 'customers.id', '=', 'shop_customers.customer_id')->first();
        if (!$shop_customer) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);
        }

        // 會員優惠券資料
        $customer_coupons = CustomerCoupon::where('customer_id', $shop_customer->customer_info->id)
                                            ->where('shop_id', $shop_id)
                                            ->orderBy('id', 'DESC')
                                            ->get();

        // 會員集點卡資料
        $customer_loyalty_cards = CustomerLoyaltyCard::where('customer_id', $shop_customer->customer_info->id)
                                                        ->where('shop_id', $shop_id)
                                                        ->where('last_point',0)
                                                        ->orderBy('id', 'DESC')
                                                        ->get();

        // 會員儲值資料
        $customer_top_up = CustomerTopUp::where('customer_id', $shop_customer->customer_id)
                                        ->where('shop_id', $shop_info->id)
                                        ->get();

        $discount = [];

        foreach ($customer_coupons as $cc) {
            if ($cc->coupon_info->type == 'discount') {
                if ($cc->coupon_info->limit == 1) $name = '全品項' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 2) $name = '全服務' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 3) $name = '全產品' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 4) $name = '部分品項' . $cc->coupon_info->discount . '折';
                $type = '折扣';
            } elseif ($cc->coupon_info->type == 'full_consumption') {
                if ($cc->coupon_info->limit == 1) $name = '消費滿' . $cc->coupon_info->consumption . '，全品項' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 2) $name = '消費滿' . $cc->coupon_info->consumption . '，全服務' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 3) $name = '消費滿' . $cc->coupon_info->consumption . '，全產品' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 4) $name = '消費滿' . $cc->coupon_info->consumption . '，部分品項' . $cc->coupon_info->discount . '折';
                $type = '滿額折扣';
            } elseif ($cc->coupon_info->type == 'experience') {
                if (!$cc->coupon_info->service_info) continue;
                $name = $cc->coupon_info->service_info->name . " 體驗價" . $cc->coupon_info->price . "元";
                $type = '體驗價';
            } elseif ($cc->coupon_info->type == 'free') {
                if (!$cc->coupon_info->self_definition && !$cc->coupon_info->service_info) continue;
                $name = "免費體驗" . ($cc->coupon_info->self_definition ? $cc->coupon_info->self_definition : $cc->coupon_info->service_info->name);
                $type = '免費體驗 ';
            } elseif ($cc->coupon_info->type == 'gift') {
                if (!$cc->coupon_info->self_definition && !$cc->coupon_info->produce_info) continue;
                $name = "贈送" . ($cc->coupon_info->self_definition ? $cc->coupon_info->self_definition : $cc->coupon_info->produce_info->name);
                $type = '贈品 ';
            } elseif ($cc->coupon_info->type == 'cash') {
                if ($cc->coupon_info->second_type == 5) {
                    if ($cc->coupon_info->limit == 1) $name = '全品項抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 2) $name = '全服務抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 3) $name = '全產品抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 4) $name = '部分品項抵扣' . $cc->coupon_info->price . '元';
                } else {
                    if ($cc->coupon_info->limit == 1) $name = '消費滿' . $cc->coupon_info->consumption . '，全品項抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 2) $name = '消費滿' . $cc->coupon_info->consumption . '，全服務抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 3) $name = '消費滿' . $cc->coupon_info->consumption . '，全產品抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 4) $name = '消費滿' . $cc->coupon_info->consumption . '，部分品項抵扣' . $cc->coupon_info->price . '元';
                }
                $type = '現金券';
            }

            $limit_text = '';
            $item_name  = [];
            switch ($cc->coupon_info->limit) {
                case 1:
                    $limit_text = '適用項目：全品項';
                    break;
                case 2:
                    $limit_text = '適用項目：全服務品項適用';
                    break;
                case 3:
                    $limit_text = '適用項目：全產品品項適用';
                    break;
                case 4:
                    $limit_commodity = ShopCouponLimit::where('shop_coupon_id', $cc->shop_coupon_id)->get();
                    $limit_text      = '適用 ' . $limit_commodity->count() . ' 項目，如下：';
                    foreach ($limit_commodity as $lc) {
                        if ($lc->type == 'service') {
                            if ($lc->service_info) {
                                $item_name[] = $lc->service_info->name;
                            }
                        } else {
                            // 產品待補
                        }
                    }
                    break;
            }

            if( $cc->status == 'Y' ){
                $status = '已使用';
            }else{
                // 需判斷期限內
                if (strtotime(date('Y-m-d')) <= strtotime($cc->coupon_info->end_date)) {
                    $status = '可使用';
                }else{
                    $status = '已過期';
                }
            }

            $discount[] = [
                'name'        => $cc->coupon_info->title,
                'description' => $name,
                'source'      => '優惠券',
                'date'        => date('Y.m.d', strtotime($cc->coupon_info->end_date)) . '止',
                'status'      => $status,
                'limit_text'  => $limit_text,
                'limit_item'  => $item_name,
                'type'        => $type,
                'content'     => $cc->coupon_info->content,
                'full_point'  => 0,
                'point_img'   => '',
            ];
        }

        foreach ($customer_loyalty_cards->where('last_point', 0) as $lc) {
            // 適用項目
            $limit_text  = '';
            $limit_items = [];
            switch ($lc->loyalty_card_info->limit) {
                case 1:
                    $limit_text = '適用項目：全品項適用';
                    break;
                case 2:
                    $limit_text = '適用項目：全服務品項適用';
                    break;
                case 3:
                    $limit_text = '適用項目：全產品品項適用';
                    break;
                case 4:
                    $limit_commodity = CompanyLoyaltyCardLimit::where('company_loyalty_card_id', $lc->loyalty_card_info->company_loyalty_card_id)->get();
                    $limit_text      = '適用 ' . $limit_commodity->count() . ' 項目，如下：';
                    foreach ($limit_commodity as $rd) {
                        if ($rd->type == 'service') {
                            $limit_items[] = $rd->service_info->name;
                        } else {
                            // 產品待補
                        }
                    }
                    break;
            }

            if ($lc->loyalty_card_info->type == 'free') {
                if (!$lc->loyalty_card_info->self_definition && !$lc->loyalty_card_info->service_info) continue;
                $name = "免費體驗" . ($lc->loyalty_card_info->self_definition ? $lc->loyalty_card_info->self_definition : $lc->loyalty_card_info->service_info->name);
                $type = '免費體驗';
            } elseif ($lc->loyalty_card_info->type == 'gift') {
                if (!$lc->loyalty_card_info->self_definition && !$lc->loyalty_card_info->produce_info) continue;
                $name = "贈送" . ($lc->loyalty_card_info->self_definition ? $lc->loyalty_card_info->self_definition : $lc->loyalty_card_info->produce_info->name);
                $type = '贈品';
            } elseif ($lc->loyalty_card_info->type == 'cash') {
                if ($lc->loyalty_card_info->second_type == 5) {
                    if ($lc->loyalty_card_info->limit == 1) $name = '全品項抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 2) $name = '全服務抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 3) $name = '全產品抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 4) $name = '部分品項抵扣' . $lc->loyalty_card_info->price . '元';
                } else {
                    if ($lc->loyalty_card_info->limit == 1) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全品項抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 2) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全服務抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 3) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全產品抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 4) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，部分品項抵扣' . $lc->loyalty_card_info->price . '元';
                }
                $type = '現金券';
            }

            if( $lc->status == 'Y' ){
                $status = '已使用';
                $date   = '';
            }else{
                // 已集滿點數需判斷使用期限是否過期
                if ($lc->loyalty_card_info->discount_limit_type != 1) {
                    $date = date('Y-m-d H:i:s', strtotime($lc->point_log->last()->created_at . ' +' . $lc->loyalty_card_info->discount_limit_month . ' month'));

                    if (strtotime(date('Y-m-d H:i:s')) <= strtotime($date)) {
                        $status = '可使用';
                        $date   = date('Y.m.d', strtotime($date)) . '止';
                    }else{
                        $status = '已過期';
                        $date   = date('Y.m.d', strtotime($date)) . '止';
                    }
                } else {
                    $status = '可使用';
                    $date   = '無期限';
                }
            }

            // 浮水印
            $point_img = '';
            if ($lc->loyalty_card_info->watermark_img) {
                $point_img = env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $lc->loyalty_card_info->watermark_img;
            } else {
                $point_img =  env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $shop_info->company_info->logo;
            }

            $discount[] = [
                'name'        => $lc->loyalty_card_info->name,
                'description' => $name,
                'source'      => '集點卡',
                'date'        => $date,
                'status'      => $status,
                'limit_text'  => $limit_text,
                'limit_item'  => $limit_items,
                'type'        => $type,
                'content'     => $lc->loyalty_card_info->content,
                'full_point'  => $lc->loyalty_card_info->full_point,
                'point_img'   => $point_img, 
            ];
        }

        foreach ($customer_top_up as $ctu) {
            foreach ($ctu->logs as $log) {
                // 儲值規則是免費體驗或是贈品
                if ($log->type == 7 || $log->type == 8) {
                    $role_info = $log->top_up_role;
                    if ($role_info) {

                        if ($log->type == 7) {
                            // 贈品
                            if (!$role_info->self_definition && !$role_info->product_info) continue;
                            $name = "贈送" . ($role_info->self_definition ? $role_info->self_definition : $role_info->product_info->name);
                            $type = '贈品';
                        } else {
                            // 免費體驗
                            if (!$role_info->self_definition && !$role_info->service_info) continue;
                            $name = "免費體驗" . ($role_info->self_definition ? $role_info->self_definition : $role_info->service_info->name);
                            $type = '免費體驗';
                        }

                        if( $log->status == 'Y' ){
                            $status = '已使用';
                        }else{
                            // 檢查期限
                            $date = date('Y-m-d H:i:s', strtotime($log->created_at . ' +' . $role_info->deadline_month . ' month'));

                            if (strtotime(date('Y-m-d H:i:s')) <= strtotime($date)) {
                                $status = '可使用';
                            }else{
                                $status = '已過期';
                            }
                        }

                        $discount[] = [
                            'name'        => $ctu->top_up_info->name,
                            'description' => $name,
                            'source'      => '儲值',
                            'date'        => date('Y.m.d', strtotime($date)) . '止',
                            'status'      => $status,
                            'limit_text'  => '',
                            'limit_item'  => [],
                            'type'        => $type,
                            'content'     => '',
                            'full_point'  => 0,
                            'point_img'   => '', 
                        ];
                    }
                }
            }
        }

        // 排序
        array_multisort(array_column($discount, 'date'), SORT_DESC, $discount);

        $data = [
            'status'     => true,
            'permission' => true,
            'data'       => $discount,
        ];

        return response()->json($data);
    }

    // 會員問券回覆記錄
    public function shop_customer_question_answer($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_question_answer', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_';
        } else {

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_question_answer', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        // 會員回答的問券
        $shop_management_customer_lists = ShopManagementCustomerList::where('shop_customer_id',$shop_customer_id)->get();
        $shop_notices                   = ShopManagement::where('type','notice')->whereIn('id',$shop_management_customer_lists->pluck('shop_management_id')->toArray())->get();

        $notice_data = [];
        foreach( $shop_notices as $notice ){
            $questions     = $notice->mode_info->notice_questions;
            $question_info = [];
            $status        = '未填寫';
            foreach( $questions as $question ){
                $answer_info =  CustomerQuestionAnswer::where('shop_id',$shop_id)->where('customer_id',$shop_customer->customer_id)->where('shop_notice_mode_question_id',$question->id)->first();

                $question_info[] = [
                    'id'                           => $answer_info ? $answer_info->id : '',
                    'customer_id'                  => $shop_customer->customer_id,
                    'company_id'                   => $shop_info->company_info->id,
                    'shop_id'                      => $shop_info->id,
                    'shop_management_id'           => $notice->id,
                    'shop_notice_mode_id'          => $notice->shop_notice_mode_id,
                    'shop_notice_mode_question_id' => $question->id,
                    'question'                     => $question->question,
                    'type'                         => $question->question_type,
                    'option'                       => explode(',', $question->question_option),
                    'answer'                       => $answer_info ? $answer_info->answer : '',
                ];

                if($answer_info){
                    $status = '已填寫';
                }
            }
            
            $notice_data[] = [
                'id'            => $notice->id,
                'name'          => $notice->name,
                'stauts'        => $status,
                'question_data' => $question_info,
            ]; 
        }

        $data = [
            'status'        => true,
            'permission'    => true,
            'customer_info' => $shop_customer,
            'data'          => $notice_data
        ];

        return response()->json($data);
    }

    // 會員編修問券答覆
    public function shop_customer_question_answer_save($shop_id,$shop_customer_id)
    {
        if( !request('id') ){
            $answer = new CustomerQuestionAnswer;
            $answer->customer_id                  = request('customer_id');
            $answer->company_id                   = request('company_id');
            $answer->shop_id                      = request('shop_id');
            $answer->shop_management_id           = request('shop_management_id');
            $answer->shop_notice_mode_id          = request('shop_notice_mode_id');
            $answer->shop_notice_mode_question_id = request('shop_notice_mode_question_id');
        }else{
            $answer = CustomerQuestionAnswer::find(request('id'));
            if( !$answer ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到對應回答資料']]]);
            }
        }

        $answer->answer = request('answer');
        $answer->save();

        return response()->json(['status'=>true]);
    }

    // 會員服務評價記錄
    public function shop_customer_evaluate($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_evaluate', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_';
        } else {

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_evaluate', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_customer = ShopCustomer::where('shop_customers.id',$shop_customer_id)->join('customers','customers.id','=','shop_customers.customer_id')->first();
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        // 會員的評價記錄
        $evaluates = CustomerEvaluate::where('shop_id',$shop_id)
                                        ->where('customer_id',$shop_customer->customer_id)
                                        ->where('status','Y')
                                        ->orderBy('id','DESC')
                                        ->get();

        $evaluate_data = [];
        foreach( $evaluates as $evaluate ){
            $evaluate_data[] = [
                'id'           => $evaluate->id,
                'date'         => substr($evaluate->reservation_info->start,0,10),
                'checkout'     => $evaluate->reservation_info->bill_info ? $evaluate->reservation_info->bill_info->staff_info->name : '' ,
                'service'      => $evaluate->reservation_info->service_info->name,
                'evaluate'     => $evaluate->satisfaction,
                'satisfaction' => $evaluate->satisfaction,
                'professional' => $evaluate->professional,
                'attitude'     => $evaluate->attitude,
                'environment'  => $evaluate->environment,
                'give_back'    => $evaluate->give_back,
                'staff_name'   => $evaluate->reservation_info->staff_info->name,
            ];
        }

        $data = [
            'status'        => true,
            'permission'    => true,
            'customer_info' => $shop_customer,
            'data'          => $evaluate_data
        ];

        return response()->json($data);

    }

    // 會員集點卡記錄使用集點卡
    public function shop_customer_loyaltyCard_use($shop_id,$customer_loyaltyCard_id)
    {
        $card = CustomerLoyaltyCard::find($customer_loyaltyCard_id);
        if( !$card ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到集點卡資料']]]);
        }

        if( $card->last_point != 0 ){
            return response()->json(['status'=>false,'errors'=>['message'=>['集點卡點數尚未集滿']]]);
        }

        $card->status     = 'Y';
        $card->using_time = date('Y-m-d H:i:s');
        $card->save();

        return response()->json(['status'=>true]);
    }

    // 會員集點卡記錄給予點數
    public function shop_customer_loyaltyCard_give($shop_id,$customer_loyaltyCard_id)
    {
        // 驗證欄位資料
        $rules = [
            'point' => 'required', 
        ];

        $messages = [
            'point.required' => '缺少點數資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        // 點數不可為0
        if( request('point') == 0 ){
            return response()->json(['status'=>false,'errors'=>['message'=>['點數給予不可為0']]]);
        }

        // 會員集點卡
        $customer_card = CustomerLoyaltyCard::find($customer_loyaltyCard_id);
        if( !$customer_card ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到此張集點卡資料']]]);
        }

        // 集點卡內容
        $card_info = $customer_card->loyalty_card_info;
        // 商家資料
        $shop_info = Shop::find($shop_id);

        // 判斷連續給點條件 1無限制 2同一天不重複 3有時間限制不重複
        $can_give = true;
        if( $card_info->get_limit == 2 || $card_info->get_limit == 3 ){
        
            // 此張集點卡最後拿到的點數資訊
            $last_get_point_info = $customer_card->point_log->last();

            if( $card_info->get_limit == 2 && date('Y-m-d') == substr($last_get_point_info->created_at,0,10) ){
                // 無法在給予點數
                $can_give = false;
            }elseif( $card_info->get_limit == 3 && strtotime(date('Y-m-d H:i:s')) - strtotime($last_get_point_info->created_at) < 60*$card_info->get_limit_minute ){
                // 無法在給予點數
                $can_give = false;
            }
        }

        // 期限內不可重複給點
        if( $can_give == false ){
            if( $card_info->get_limit == 2 ){
                $errors = '今日無法再次給予點數';
            }else{
                $card_info->get_limit_minute;
                $minute = (60 * $card_info->get_limit_minute - (strtotime(date('Y-m-d H:i:s')) - strtotime($last_get_point_info->created_at))) / 60;

                $errors = round($minute).'分鐘後才可以再次給予點數';
            }
            return response()->json(['status'=>false,'errors'=>['message'=>[$errors]]]);
        }

        // 給予點數
        if( $customer_card->last_point < request('point') ){
            // 溢點
            $over_point = request('point') - $customer_card->last_point;
            // 第一張還需要幾點集滿
            $last_point = $customer_card->last_point;

            // 先補滿最後一張點數記錄
            $card_point = new CustomerLoyaltyCardPoint;
            $card_point->customer_loyalty_card_id = $customer_card->id;
            $card_point->point                    = $customer_card->last_point;
            $card_point->save();

            // 將原卡片剩餘點數修改為0
            $customer_card->last_point = 0;
            $customer_card->save();

            // 需檢查是否還有同種卡片且未集滿點數的
            $same_cards = CustomerLoyaltyCard::where('customer_id',$customer_card->customer_id)
                                                ->where('shop_loyalty_card_id',$customer_card->shop_loyalty_card_id)
                                                ->where('shop_id',$customer_card->shop_id)
                                                ->where('last_point','!=',0)
                                                ->get();

            foreach( $same_cards as $sc ){
                $card_point = new CustomerLoyaltyCardPoint;
                $card_point->customer_loyalty_card_id = $sc->id;

                if( $over_point > $sc->last_point ){
                    // 超過需要補足的卡片
                    $card_point->point = $sc->last_point;
                    $card_point->save();

                    // 將補足卡片剩餘點數修改為0
                    $sc->last_point = 0;
                    $sc->save();

                    $over_point = $over_point - $sc->last_point;

                }else{
                    // 沒超過需要補足的卡片
                    $card_point->point = $over_point;
                    $card_point->save();

                    // 將補足卡片剩餘點數修改
                    $sc->last_point = $sc->last_point-$over_point;
                    $sc->save();

                    // 補足完後直接歸0
                    $over_point = -1;
                    break;
                }
            }

            if( $over_point >= $card_info->full_point ){
                $card_count = (int)floor($over_point / $card_info->full_point);

                // 製作集滿點數的卡片
                for( $i = 1 ; $i <= $card_count ; $i++ ){
                    // 新建立多張集點卡
                    $word = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';   //亂數內容
                    $len  = strlen($word);
                    $rand = '';
                    for ($y = 0; $y < 4; $y++) {
                        $rand .= $word[rand() % $len];
                    }
                    $new_card = new CustomerLoyaltyCard;
                    $new_card->customer_id          = $customer_card->customer_id;
                    $new_card->company_id           = $customer_card->company_id;
                    $new_card->shop_id              = $customer_card->shop_id;
                    $new_card->shop_loyalty_card_id = $customer_card->shop_loyalty_card_id;
                    $new_card->card_no              = $shop_info->alias . str_pad($customer_card->customer_id,4,"0",STR_PAD_LEFT)  . $rand;
                    $new_card->full_point           = $card_info->full_point;
                    $new_card->last_point           = 0;
                    $new_card->save();

                    // 記錄集點卡點數
                    $card_point = new CustomerLoyaltyCardPoint;
                    $card_point->customer_loyalty_card_id = $new_card->id;
                    $card_point->point                    = $card_info->full_point;
                    $card_point->save();
                } 
            }

            // 剩餘點數
            $remaining = $over_point;

        }else{
            // 剛好集滿/沒有溢點
            $last_point = $customer_card->last_point;

            // 將修改原卡片剩餘點數
            $customer_card->last_point = $customer_card->last_point-request('point');
            $customer_card->save();

            // 先寫入點數記錄
            $card_point = new CustomerLoyaltyCardPoint;
            $card_point->customer_loyalty_card_id = $customer_card->id;
            $card_point->point                    = request('point');
            $card_point->save();            

            // 剩餘點數
            $remaining = request('point') - $last_point;
        }

        // 記錄集點卡點數
        if( $remaining >= 0 ){
            // 新建集點卡
            $word = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';   //亂數內容
            $len  = strlen($word);
            $rand = '';
            for ($i = 0; $i < 4; $i++) {
                $rand .= $word[rand() % $len];
            }

            $new_card = new CustomerLoyaltyCard;
            $new_card->customer_id          = $customer_card->customer_id;
            $new_card->company_id           = $customer_card->company_id;
            $new_card->shop_id              = $customer_card->shop_id;
            $new_card->shop_loyalty_card_id = $customer_card->shop_loyalty_card_id;
            $new_card->card_no              = $shop_info->alias . str_pad($customer_card->customer_id,4,"0",STR_PAD_LEFT)  . $rand;
            $new_card->full_point           = $card_info->full_point;
            $new_card->last_point           = $card_info->full_point - $remaining;
            $new_card->save();

            if( $remaining != 0 ){
                $card_point = new CustomerLoyaltyCardPoint;
                $card_point->customer_loyalty_card_id = $new_card->id;
                $card_point->point                    = $remaining;
                $card_point->save();
            }
        }
        
        return response()->json(['status'=>true]);
    }

    // 會員消費明細
    public function shop_customer_bills($shop_id, $shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_consumer_details', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
            $per = 'shop_customer';
        } else {
            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_consumer_details', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
            $per = 'staff_customer';
        }

        $shop_info = Shop::find($shop_id);

        $shop_customer = ShopCustomer::where('shop_customers.id', $shop_customer_id)->first();
        if (!$shop_customer) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);
        }

        $customer_bills = Bill::where('shop_id', $shop_info->id)
                                ->where('customer_id', $shop_customer->customer_info->id)
                                ->where('status', 'finish')
                                ->get();

        $logs = [];
        foreach ($customer_bills as $bill) {
            $consumption = json_decode($bill->consumption);
            $top_up      = json_decode($bill->top_up);
            $shop_staff  = ShopStaff::find($bill->shop_staff_id);
            $logs[] = [
                'id'         => $bill->oid,
                'datetime'   => date('Y-m-d H:i', strtotime($bill->updated_at)),
                'deduct'     => $bill->deduct == '[]' ? true : false,
                'top_up'     => $top_up->discount,
                'total'      => $consumption->final_total->total,
                'shop_staff' => $shop_staff->company_staff_info->name,
                'sign_img'   => $bill->sign_img ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $bill->sign_img : '',
            ];
        }

        $data = [
            'status'     => true,
            'permission' => true,
            'data'       => $logs
        ];

        return response()->json($data);
    }

    // 會員儲值記錄
    public function shop_customer_topUps($shop_id, $shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_top_up', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
            $per = 'shop_customer';
        } else {
            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_top_up', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
            $per = 'staff_customer';
        }

        $shop_info = Shop::find($shop_id);

        $shop_customer = ShopCustomer::where('shop_customers.id', $shop_customer_id)->first();
        if (!$shop_customer) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);
        }

        $customer_topUps = CustomerTopUp::where('shop_id', $shop_info->id)
            ->where('customer_id', $shop_customer->customer_info->id)
            ->get();

        $logs = [];
        $top_up_logs = CustomerTopUpLog::where('shop_id', $shop_info->id)
            ->where('customer_id', $shop_customer->customer_info->id)
            ->whereIn('type', [1, 2, 3, 4, 5, 6])
            ->orderBy('created_at', 'DESC')
            ->get();
        foreach ($top_up_logs as $tu_log) {

            // 1購買2手動調整3使用 4轉出 5轉入 6贈送 7贈品 8免費
            if ($tu_log->type == 1) {
                $note = '購買儲值';
            } elseif ($tu_log->type == 2) {
                $note = $tu_log->staff_info->company_staff_info->name . ($tu_log->note ? '(' . $tu_log->note . ')' : '(手動調整)');
            } elseif ($tu_log->type == 3) {
                $note = '結帳使用';
            } elseif ($tu_log->type == 4) {
                $note = '轉出給' . $tu_log->customer_info->realname;
            } elseif ($tu_log->type == 5) {
                $note = $tu_log->customer_info->realname . '轉入';
            } elseif ($tu_log->type == 6) {
                $note = '儲值贈送';
            }

            $logs[] = [
                'id'      => $tu_log->id,
                'time'    => substr($tu_log->created_at, 0, 16),
                'staff'   => $note,
                'note'    => $note,
                'top_up'  => $tu_log->price >= 0 ? $tu_log->price : -1 * $tu_log->price,
                'type'    => $tu_log->price >= 0 ? true : false,
                'bill_id' => $tu_log->bill_info ? $tu_log->bill_info->id : '',
            ];
        }

        $data = [
            'status'          => true,
            'permission'      => true,
            'edit_permission' => true,  // in_array($per.'_top_up_edit', $permission['permission']) ? true : false;
            'top_up_total'    => $top_up_logs->where('price', '>=', 0)->sum('price'),
            'use_total'       => -1 * $top_up_logs->where('price', '<', 0)->sum('price'),
            'last_total'      => $top_up_logs->sum('price'),
            'top_up_logs'     => $logs,
        ];

        return response()->json($data);
    }

    // 會員儲值人為修改
    public function shop_customer_topUp_save($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_top_up_save', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        } else {
            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_top_up_save', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        }

        $shop_info = Shop::find($shop_id);

        $shop_customer = ShopCustomer::where('shop_customers.id', $shop_customer_id)->first();
        if (!$shop_customer) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);
        }

        if( request('type') == '' ) return response()->json(['status' => false, 'errors' => ['message' => ['請選擇加或減']]]);
        if( request('price') == '' ) return response()->json(['status' => false, 'errors' => ['message' => ['請輸入金額']]]);

        $log                = new CustomerTopUpLog;
        $log->customer_id   = $shop_customer->customer_id;
        $log->shop_id       = $shop_info->id;
        $log->company_id    = $shop_info->company_info->id;
        $log->type          = 2;
        $log->price         = request('type') == 2 ? (-1)*request('price') : request('price');
        $log->note          = request('note');
        $log->shop_staff_id = ShopStaff::where('shop_id',$shop_info->id)->where('user_id',auth()->getUser()->id)->first()->id; 
        $log->save();

        return response()->json(['status' => true]);
    }

    // 會員可使用方案
    public function shop_customer_programs($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_programs', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_program';
        } else {
            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_programs', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_program';
        }

        $shop_info = Shop::find($shop_id);

        $shop_customer = ShopCustomer::where('shop_customers.id', $shop_customer_id)->first();
        if (!$shop_customer) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);
        }

        $customer_programs = CustomerProgram::where('shop_id', $shop_info->id)
                                        ->where('customer_id', $shop_customer->customer_info->id)
                                        ->get();

        $can_use = $history = [];
        foreach( $customer_programs as $program ){
            $program_groups = $program->groups;
            $groups = [];
            foreach( $program_groups as $group ){
                $groups[] = [
                    'id'                    => $group->id,
                    'shop_program_group_id' => $group->shop_program_group_id,
                    'name'                  => $group->group_info->name,
                    'last_count'            => $group->last_count,
                ];
            }
            if( $program_groups->sum('last_count') == 0 ){
                // 使用完
                $history[] = [
                    'id'              => $program->id,
                    'shop_program_id' => $program->shop_program_id,
                    'name'            => $program->program_info->name,
                    'groups'          => $groups,
                ];
            }else{
                // 未使用完
                $can_use[] = [
                    'id'              => $program->id,
                    'shop_program_id' => $program->shop_program_id,
                    'name'            => $program->program_info->name,
                    'groups'          => $groups,
                ];
            }
        }

        // 商家可給會員新增方案
        $shop_programs = ShopProgram::where('shop_id',$shop_info->id)
                                    ->where('status','published')
                                    ->get();
        $select_programs = [];
        foreach( $shop_programs as $program ){
            if( ($program->during_type == 2 && $program->start_date > date('Y-m-d')) 
                    || ($program->during_type == 2 && $program->end_date < date('Y-m-d')) ) continue;

            $groups = [];
            $id     = '';

            $customer_program = CustomerProgram::where('customer_id',$shop_customer->customer_id)
                                                ->where('shop_program_id',$program->id)
                                                ->first();
            if( !$customer_program ){
                $id = '';
                foreach($program->groups as $group) {
                    $groups[] = [
                        'id'                    => '',
                        'shop_program_group_id' => $group->id,
                        'name'                  => $group->name,
                        'last_count'            => $group->count,
                    ];
                }
            }else{
                // 若使用完，也需要加入選項
                if($customer_program->groups->sum('last_count') == 0){
                    $id = $customer_program->id;
                    foreach( $customer_program->groups as $group ){
                        $groups[] = [
                            'id'                    => $group->id,
                            'shop_program_group_id' => $group->shop_program_group_id,
                            'name'                  => $group->group_info->name,
                            'last_count'            => $group->last_count,
                        ];
                    }
                }else{
                    continue;
                }
            }
            $select_programs[] = [
                'id'              => $id,
                'shop_program_id' => $program->id,
                'name'            => $program->name,
                'groups'          => $groups,
            ];
        }

        $data = [
            'status'            => true,
            'permission'        => true,
            'edit_permission'   => in_array($per.'_edit', $permission['permission']) ? true : false,
            'create_permission' => in_array($per.'_create', $permission['permission']) ? true : false,
            'shop_programs'     => $select_programs,
            'can_use'           => $can_use,
            'history'           => $history,
        ];

        return response()->json($data);
    }

    // 會員儲存方案變動資料
    public function shop_customer_save_programs($shop_id,$shop_customer_id)
    {
        $shop_info     = Shop::find($shop_id);
        $shop_customer = ShopCustomer::find($shop_customer_id);
        $shop_program  = ShopProgram::find(request('shop_program_id'));

        if( request('id') != '' ){
            // 編輯會員已有方案
            $customer_program = CustomerProgram::find(request('id'));
        }else{
            // 新增方案，先檢查是否已經有購買過
            $customer_program = CustomerProgram::where('customer_id',$shop_customer->customer_id)
                                                ->where('shop_program_id',request('shop_program_id'))
                                                ->where('shop_id',$shop_info->id)
                                                ->first();
        }

        if( !$customer_program ) {
            // 都尚未購買過方案，新增方案至會員底下
            $customer_program = new CustomerProgram;
            $customer_program->customer_id     = $shop_customer->customer_id;
            $customer_program->shop_id         = $shop_info->id;
            $customer_program->company_id      = $shop_info->company_info->id;
            $customer_program->shop_program_id = request('shop_program_id');
            $customer_program->price           = $shop_program->price;
            $customer_program->save();

            foreach( request('groups') as $group ) {
                if( $group['last_count'] == '' ) return response()->json(['status' => false, 'errors' => ['message' => ['數量不可以空白']]]);

                $shop_program_group = ShopProgramGroup::find($group['shop_program_group_id']);

                $customer_program_group = new CustomerProgramGroup;
                $customer_program_group->customer_program_id   = $customer_program->id;
                $customer_program_group->shop_program_group_id = $group['shop_program_group_id'];
                $customer_program_group->count                 = $shop_program_group->count;
                $customer_program_group->last_count            = $group['last_count'];
                $customer_program_group->save();

                $customer_program_log = new CustomerProgramLog;
                $customer_program_log->customer_program_id       = $customer_program->id;
                $customer_program_log->customer_program_group_id = $customer_program_group->id;
                $customer_program_log->count                     = $group['last_count'];
                $customer_program_log->type                      = 2;
                $customer_program_log->shop_staff_id             = ShopStaff::where('shop_id',$shop_info->id)->where('user_id',auth()->getUser()->id)->first()->id; 
                $customer_program_log->save();
            }
        }else{
            foreach( request('groups') as $group ) {
                if ($group['last_count'] == '') return response()->json(['status' => false, 'errors' => ['message' => ['數量不可以空白']]]);

                $shop_program_group = ShopProgramGroup::find($group['shop_program_group_id']);

                if( $group['id'] == '' ){
                    $customer_program_group = CustomerProgramGroup::where('customer_program_id', $customer_program->id)
                                                                  ->where('shop_program_group_id', $group['shop_program_group_id'])
                                                                  ->first();
                }else{
                    $customer_program_group = CustomerProgramGroup::find($group['id']);
                }

                $origin_last_count = $customer_program_group->last_count;

                if( $origin_last_count != $group['last_count'] ){
                    $customer_program_group->last_count = $group['last_count'];
                    $customer_program_group->save();

                    $log = new CustomerProgramLog;
                    $log->customer_program_id       = $customer_program->id;
                    $log->customer_program_group_id = $customer_program_group->id;
                    $log->count                     = $group['last_count'] - $origin_last_count;
                    $log->type                      = 2;
                    $log->shop_staff_id             = ShopStaff::where('shop_id',$shop_info->id)->where('user_id',auth()->getUser()->id)->first()->id;
                    $log->save();
                }
            }  
        }

        return response()->json(['status' => true]);
    }

    // 會員方案使用記錄
    public function shop_customer_program_log($shop_id,$customer_program_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_program_log', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_programs';
        } else {
            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_program_log', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_programs';
        }

        $customer_program = CustomerProgram::find($customer_program_id);
        $shop_info        = Shop::find($shop_id);

        $group_logs = [];
        foreach( $customer_program->groups as $group){

            $logs = [];
            $program_logs = CustomerProgramLog::where('customer_program_group_id',$group->id)->orderBy('created_at','DESC')->get();
            foreach($program_logs as $log ){

                $note = '';
                if( in_array($log->type,[1,2,3]) ){
                    if( $log->type == 2 ){
                        $note .= $log->staff_info->name . '人為修改';
                    }elseif( $log->type == 1 ){
                        $note = '購買';
                    }else{
                        $note = '結帳使用';
                        if ($log->commodity_type == 'service' && $log->shop_service) {
                            $note = '結帳使用('.$log->shop_service->name.')';
                        } elseif ($log->commodity_type == 'product' && $log->shop_product) {
                            $note = '結帳使用('.$log->shop_product->name.')';
                        }
                        
                    }
                }else{
                    if ($log->type == 4) {
                        $note = '轉出給'. $log->customer_info->realname;
                    } else {
                        $note = $log->customer_info->realname.'轉入';
                    }
                }

                $logs[] = [
                    'date'    => substr($log->created_at,0,16),
                    'note'    => $note,
                    'count'   => $log->count,
                    'bill_id' => $log->bill_id,
                ];
            }

            $group_logs[] = [
                'name'  => $group->group_info->name,
                'total' => $group->use_log->where('count','>',0)->sum('count'),
                'use'   => $group->use_log->where('count','<',0)->sum('count'),
                'last'  => $group->use_log->sum('count'),
                'logs'  => $logs,
            ]; 
        }

        $data = [
            'status'       => true,
            'permission'   => true,
            'program_info' => [
                'name' => $customer_program->program_info->name,
            ],
            'data'         => $group_logs
        ];

        return response()->json( $data );
    }

    // 會員的會員卡記錄
    public function shop_customer_membership_card_log($shop_id,$shop_customer_id)
    {
        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_membership_cards', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        } else {
            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_membership_cards', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        }

        $shop_info = Shop::find($shop_id);

        $shop_customer = ShopCustomer::where('shop_customers.id', $shop_customer_id)->first();
        if (!$shop_customer) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);
        }

        $customer_membership_cards = CustomerMembershipCard::where('shop_id', $shop_info->id)
                                                            ->where('customer_id', $shop_customer->customer_info->id)
                                                            ->get();
        
        $logs = [];
        foreach( $customer_membership_cards as $card ){
            $deadline = '無期限';
            if ($card->membership_card_info->card_during_type == 2) {
                // 顧客購買起
                $deadline = date('Y-m-d', strtotime($card->created_at . "+" . $card->membership_card_info->card_year . "year +" . $card->membership_card_info->card_month . 'month'));
            } elseif ($card->membership_card_info->card_during_type == 3) {
                // 統一起迄
                if (time() > $card->membership_card_info->card_end_date) continue;
                $deadline = date('Y-m-d', strtotime($card->membership_card_info->card_end_date));
            }

            $roles = [];
            foreach($card->membership_card_info->roles as $role){

                $role_limits = $role->limit_commodity;
                // 檢查此限制項目是否有在商家的服務或產品內
                $limit_service = $role_limits->where('type', 'service');
                $limit_product = $role_limits->where('type', 'product');

                // 會員卡類型1現金折價2折扣3專屬優惠
                if ($role->type == 1) {
                    $type = '現金折價 ' . $role->price . ' 元';
                } elseif ($role->type == 2) {
                    $type = '折扣 ' . $role->discount . ' 折';
                } else {
                    $type = '專屬優惠價 ' . $role->price . ' 元';
                }

                $items = [];
                if ($role->limit == 1) {
                    $item_text = '適用項目：無限制';
                } elseif ($role->limit == 2) {
                    $item_text = '適用項目：全服務品項';
                } elseif ($role->limit == 3) {
                    $item_text = '適用項目：全產品品項';
                } elseif ($role->limit == 4) {
                    $item_text = '適用項目：部分品項';
                    $items = ShopService::whereIn('id', $limit_service->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                } elseif ($role->limit == 5) {
                    $item_text = '適用項目：單一品項';
                    $items = ShopService::whereIn('id', $limit_service->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                } else{
                    $item_text = '適用項目：單一品項';
                    $items = [];
                }

                $roles[] = [
                    'type'      => $type,
                    'item_text' => $item_text,
                    'items'     => $items
                ];
            }

            $logs[] = [
                'tag_name'  => $card->membership_card_info->tag_name,
                'name'      => $card->membership_card_info->name,
                'price'     => $card->membership_card_info->price,
                'discount'  => $card->logs->sum('discount'),
                'deadline'  => $deadline,
                'status'    => $deadline == '無期限' ? '可使用' : (date('Y-m-d') > strtotime($deadline) ? '已過期' : '可使用'),
                'condition' => [
                    $card->membership_card_info->use_coupon ? '優惠券可以抵扣購買' : '優惠券不可以抵扣購買',
                    $card->membership_card_info->use_topUp  ? '儲值金可以抵扣購買' : '儲值金不可以抵扣購買'
                ],
                'roles'     => $roles,
            ];
        }

        $data = [
            'status'     => true,
            'permission' => true,
            'data'       => $logs,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家會員資料
    public function shop_customer_info($shop_id,$shop_customer_id="")
    {
    	// 判斷是新增還編輯還是員工自己登入編輯
    	if( $shop_customer_id ){
            $shop_customer_info = ShopCustomer::find($shop_customer_id);
            if( !$shop_customer_info ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_customer_info = new ShopCustomer;
            $type               = 'create';
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if (!PermissionController::is_staff($shop_id)) {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('shop_customer_' . $type, $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'shop_customer_' . $type;
        } else {

            // 員工身分
            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            // 確認頁面瀏覽權限
            if (!in_array('staff_customer_' . $type, $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $per = 'staff_customer_' . $type;
        }

        // 歸屬員工
        $shop_staff = $shop_info->shop_staffs;
        $shop_staffs = [];
        foreach( $shop_staff as $staff ){
            $shop_staffs[] = [
                'id'   => $staff->id,
                'name' => $staff->name,
            ];
        }

        // 會員基本資料
        $customer_info = $shop_customer_info->customer_info;
        
        $customer = [
            'id'                         => $shop_customer_info->id,
            'nickname'                   => $shop_customer_id ? $customer_info->nickname : '',
            'nickname_permission'        => in_array($per.'_nickname',$permission['permission']) ? true : false,
            'realname'                   => $shop_customer_id ? $customer_info->realname : '',
            'realname_permission'        => in_array($per.'_realname',$permission['permission']) ? true : false,
            'phone'                      => $shop_customer_id ? $customer_info->phone : '',
            'phone_permission'           => in_array($per.'_phone',$permission['permission']) ? true : false,
            'birthday_select'            => $shop_customer_id ? ($customer_info->birthday_select ? true : false ) : true,
            'birthday_select_permission' => in_array($per.'_birthday',$permission['permission']) ? true : false,
            'birthday'                   => $shop_customer_id ? ($customer_info->birthday != '' ? $customer_info->birthday : '') : '',
            'birthday_permission'        => in_array($per.'_birthday',$permission['permission']) ? true : false,
            'sex'                        => $shop_customer_id ? $customer_info->sex : '',
            'sex_permission'             => in_array($per.'_sex',$permission['permission']) ? true : false,
            'email'                      => $shop_customer_id ? $customer_info->email : '',
            'email_permission'           => in_array($per.'_email',$permission['permission']) ? true : false,
            'note'                       => $shop_customer_id ? $customer_info->note : '',
            'note_permission'            => in_array($per.'_note',$permission['permission']) ? true : false,
            'belongTo'                   => $shop_customer_info->shop_staff_id,
            'belongTo_permission'        => in_array($per.'_belongTo',$permission['permission']) ? true : false,
            'introducer'                 => $shop_customer_info->introducer,
            'introducer_permission'      => in_array($per.'_introducer',$permission['permission']) ? true : false,
            'facebook_id'                => $shop_customer_id ? $customer_info->facebook_id : '',
            'google_id'                  => $shop_customer_id ? $customer_info->google_id : '',
            'line_id'                    => $shop_customer_id ? $customer_info->line_id : '',
        ];

        $data = [
		    'status'     => true,
		    'permission' => true,
            'staff'      => $shop_staffs,
		    'data'       => $customer,
        ];

		return response()->json($data);
    }

    // 儲存商家會員資料
    public function shop_customer_save($shop_id,$shop_customer_id="")
    {
        // 驗證欄位資料
        $rules = [
            'realname' => 'required', 
            'phone'    => 'required',
        ];

        $messages = [
            'realname.required' => '請填寫真實姓名',
            'phone.required'    => '請填寫手機號碼',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        if( $shop_customer_id ){
            // 編輯
            $shop_customer = ShopCustomer::find($shop_customer_id);
            if( !$shop_customer ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
            }
            // 會員資料
            $customer_info = Customer::find( $shop_customer->customer_id );

            // 集團會員
            $company_customer = CompanyCustomer::where('company_id',$shop_customer->company_id)->where('customer_id',$shop_customer->customer_id)->first();
        }else{
            // 新增
            $customer_info    = new Customer;
            $company_customer = new CompanyCustomer;
            $shop_customer    = new ShopCustomer;
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 新增/編輯customers資料
        $customer_info->realname        = request('realname');
        $customer_info->nickname        = request('nickname');
        $customer_info->phone           = request('phone');
        $customer_info->sex             = request('sex');
        $customer_info->birthday_select = request('birthday_select');
        $customer_info->birthday        = request('birthday_select') ? request('birthday') : NULL;
        $customer_info->email           = request('email');
        $customer_info->note            = request('note');
        $customer_info->save();

        // 新增/編輯company_customers資料
        $company_customer->customer_id = $customer_info->id;
        $company_customer->company_id  = $company_info->id;
        $company_customer->save();

        // 新增/編輯shop_customers資料
        $shop_customer->shop_id       = $shop_id;
        $shop_customer->company_id    = $company_info->id;
        $shop_customer->customer_id   = $customer_info->id;
        $shop_customer->introducer    = request('introducer');
        $shop_customer->shop_staff_id = request('belongTo');
        $shop_customer->save(); 

        return response()->json(['status'=>true]);
    }

    // 刪除商家會員資料
    public function shop_customer_delete($shop_id,$shop_customer_id)
    {
        $shop_customer = ShopCustomer::find($shop_customer_id);
        if( !$shop_customer ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到會員資料']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        if( in_array($shop_info->buy_mode_id,[0,1,2,5,6]) ){
            // 基本版與進階版則一併刪除集團會員
            CompanyCustomer::where('company_id',$company_info->id)->where('customer_id',$shop_customer->customer_id)->delete();
        }

        // 若有綁定google calendar時，需同時解除綁定事件
        $customer_reservations = CustomerReservation::where('customer_id',$shop_customer->customer_id)->where('shop_id',$shop_id)->get();
        foreach( $customer_reservations as $reservation ){
            $token = $reservation->staff_info->calendar_token;
            if( $token && $reservation->google_calendar_id != '' ){
                $job = new DeleteGoogleCalendarEvent($reservation,$reservation->staff_info,$token);
                dispatch($job);
            }
        }

        // 刪除自己上傳的大頭照與背景
        $customer = Customer::find( $shop_customer->customer_id);
        if( $customer->photo && !preg_match('/http/',$customer->photo) ){
            $filePath = env('UPLOAD_IMG').'/shilipai_customer/'.$customer->photo;
            if(file_exists($filePath)){
                unlink($filePath);
            }
        }
        if( $customer->banner && !preg_match('/http/',$customer->banner) ){
            $filePath = env('UPLOAD_IMG').'/shilipai_customer/'.$customer->banner;
            if(file_exists($filePath)){
                unlink($filePath);
            }
        }

        // 刪除商家會員資料
        $shop_customer->delete();
        // 刪除預約資料
        CustomerReservation::where('customer_id',$shop_customer->customer_id)->where('shop_id',$shop_id)->delete();
        // 刪除優惠券
        CustomerCoupon::where('customer_id',$shop_customer->customer_id)->where('shop_id',$shop_id)->delete();
        // 刪除集點卡
        CustomerLoyaltyCard::where('customer_id',$shop_customer->customer_id)->where('shop_id',$shop_id)->delete();
        // 刪除儲值金
        CustomerTopUp::where('customer_id',$shop_customer->customer_id)->where('shop_id',$shop_id)->delete();
        // 刪除方案
        CustomerProgram::where('customer_id',$shop_customer->customer_id)->where('shop_id',$shop_id)->delete();
        // 刪除會員卡
        CustomerMembershipCard::where('customer_id',$shop_customer->customer_id)->where('shop_id',$shop_id)->delete();

        return response()->json(['status'=>true]);
    }

    // 儲存批次發送禮物資料
    public function shop_give_gift($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'customers' => 'required', 
            'coupon'    => 'required',
        ];

        $messages = [
            'customers.required' => '請選擇會員',
            'coupon.required'    => '請選擇優惠券',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_customers = ShopCustomer::whereIn('shop_customers.id',request('customers'))->get();
        foreach( $shop_customers as $shop_customer ){
            $gift = new CustomerCoupon;
            $gift->customer_id    = $shop_customer->customer_id;
            $gift->company_id     = $company_info->id;
            $gift->shop_id        = $shop_id;
            $gift->shop_coupon_id = request('coupon');
            $gift->save();
        }

        return response()->json(['status'=>true]);
    }

    // 計算年齡
    static public function getAge($birthday){
		//格式化出生時間年月日
		$byear  = date('Y',strtotime($birthday));
		$bmonth = date('m',strtotime($birthday));
		$bday   = date('d',strtotime($birthday));
		//格式化當前時間年月日
		$tyear  = date('Y');
		$tmonth = date('m');
		$tday   = date('d');
		//開始計算年齡
		$age = $tyear-$byear;
		if($bmonth>$tmonth || $bmonth==$tmonth && $bday>$tday){
		    $age--;
		}
		return $age;
    }

    // 取得星座
    static public function constellation($date){
        $month = (int)date('m',strtotime($date));
        $day = (int)date('d',strtotime($date));
        // 所有星座
        $constellations = [
            '摩羯座','水瓶座', '雙鱼座', '白羊座', '金牛座', '雙子座',
            '巨蟹座','獅子座', '處女座', '天秤座', '天蠍座', '射手座',
        ];
        // 設定星座结束日期，對應上面星座
        $endDays = [19, 18, 20, 20, 20, 21, 22, 22, 22, 22, 21, 21];
        if($day <= $endDays[$month - 1]){   // 當前日期 <= 該當前月份的星座结束日期 則為該星座
            $constellation = $constellations[$month - 1];
        }else{
            $constellation = empty($constellations[$month]) ? $constellations[0] : $constellations[$month];
        }
        return $constellation;
    }

}
