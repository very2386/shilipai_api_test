<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Validator;
use App\Models\Shop;
use App\Models\ShopBusinessHour;
use App\Models\ShopClose;
use App\Models\ShopVacation;
use App\Models\ShopService;
use App\Models\CustomerReservation;
use App\Models\CustomerReservationAdvance;
use App\Models\ShopStaff;
use App\Models\CompanyStaff;
use App\Models\SystemNotice;

class ReservationController extends Controller
{
    // 取得可服務的日期
    public function get_highlight_date()
    {    
    	// 驗證欄位資料
        $rules = [
            'staff' => 'required', 
            'date'  => 'required', 
        ];

        $messages = [
            'staff.required' => '缺少服務人員資料',
            'date.required'  => '缺少日期資料'
        ];

        $validator = Validator::make(request()->all(), $rules,$messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }
        
        $year       = date('Y', strtotime(request('date')));
        $month      = date('m', strtotime(request('date')));
        $month_days = cal_days_in_month(CAL_GREGORIAN,  $month,  $year);

        // type 1特殊自定時間 0 同商家營業時間
        $businessHours  = ShopBusinessHour::where('shop_staff_id',request('staff'))->pluck('week')->toArray();
        $businessClosed = ShopClose::where('shop_staff_id',request('staff'))->first();
        $vacations      = ShopVacation::where('shop_staff_id',request('staff'))->get();

        $date_arr = [];
        
        // 先做當年當月的所有日期陣列
        for( $i = 1 ; $i <= $month_days ; $i++ ){
        	// 若有在營業的星期x，就寫入陣列
        	$today_week = date('N', strtotime( $year.'-'.str_pad($month,2,'0',STR_PAD_LEFT).'-'.$i ));
        	if( in_array( $today_week , $businessHours ) ){
    		    $date_arr[] = $year . '-' . str_pad($month,2,'0',STR_PAD_LEFT) . '-' . str_pad($i,2,'0',STR_PAD_LEFT);
    		}
        }

        // 加入休息時間判斷
        $week_date        = Self::get_week($year,$month);
        $closed_date_week = $businessClosed ? explode(',',$businessClosed->week) : [];

        $ready_date = [];// 儲存第一週已休的星期

        $date = [];

        if( $businessClosed ){
            foreach( $date_arr as $darr ){
                // 先確認是不是每週休息
                if( $businessClosed->type == 5 ){
                    // 再比對星期
                    if( !in_array( date('D',strtotime($darr)) , $closed_date_week) ){
                        $date[] = $darr; 
                    } 
                }else{
                    // 第一週休息
                    if( $businessClosed->type == 1 ){
                        // 先檢查此日期的星期有沒有再公休星期內
                        if( in_array( date('D',strtotime($darr)) , $closed_date_week) ){
                            if( !in_array(date('D',strtotime($darr)),$ready_date) ){
                                // 如果不再陣列中表示還未排入公休
                                $ready_date[] = date('D',strtotime($darr));
                            }else{
                                // 因為陣列裡已經被比對到，所以這天不需要休息
                                $date[] = $darr; 
                            } 
                        }else{
                            $date[] = $darr; 
                        }
                        
                    }else{
                        // 若每個月第一天開始不是0 ，則需要加一，找出當天日期為第幾週
                        if( array_keys($week_date)[0] != 0 ){
                            $first_week = array_keys($week_date)[0];

                            if( date('w',strtotime($darr)) < $first_week ){
                                $wk = array_keys( $week_date[date('w',strtotime($darr))] , $darr )[0]+2;
                            }else{
                                $wk = array_keys( $week_date[date('w',strtotime($darr))] , $darr )[0]+1;
                            } 
                            
                        }else{
                            $wk = array_keys( $week_date[date('w',strtotime($darr))] , $darr )[0]+1;
                        }
                        
                        // 先比對第幾週
                        if( $businessClosed->type == $wk ){
                            // 再比對星期
                            if( !in_array( date('D',strtotime($darr)) , $closed_date_week) ){
                                $date[] = $darr; 
                            }         
                        }else{
                            $date[] = $darr; 
                        }

                    }  
                } 
            }
        }
        
        // 加入特殊休假日判斷
        foreach( $vacations as $vacation ){
        	switch ($vacation->type){
        		case 1: // 區段時間
        	        foreach( $date as $k => $d ){
        	        	if( strtotime($d) >= strtotime($vacation->start_date) && strtotime($d) <= strtotime($vacation->end_date) ){
        	        		unset($date[$k]);
        	        	}
        	        }
        		    break;
        		case 3: // 整天
        		    if( date('m',strtotime($vacation->start_date)) == $month ){
        		    	if(($key = array_search($vacation->start_date,$date))){
        		    	    unset($date[$key]);
        		    	}
        		    }
        		    break;
        	}
        }

        $shop_staff = ShopStaff::find(request('staff'));

        // 加入商家營業時間判斷
        $highlight_date = [];
        $shop_info     = Shop::where('id',$shop_staff->shop_id)->first();
        $shop_business = ShopBusinessHour::where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->get();
        foreach( $date as $hd ){
            if( $shop_business->where('week',date('N',strtotime($hd)))->first()->type == 1 ){
                $highlight_date[] = $hd; 
            }
        }

        // if( in_array( date('Y-m-d',strtotime(request('date'))) , array_values($date) ) ){
        //     return response()->json(['status' => true , 'highlight_date' => $highlight_date]);
        // }else{
        //     return response()->json(['status' => true , 'highlight_date' => $highlight_date]);
        // }
        return response()->json(['status' => true , 'highlight_date' => $highlight_date]);
    }

    // 取不可服務的日期
    public function get_blacklist_date()
    {       
    	// 驗證欄位資料
        $rules = [
            'staff' => 'required', 
            'date'  => 'required', 
        ];

        $messages = [
            'staff.required' => '缺少服務人員資料',
            'date.required'  => '缺少日期資料'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $res = json_decode(json_encode(Self::get_highlight_date()));
        $highlight_date = $res->original->highlight_date;

        $date_arr   = [];
        $year       = date('Y', strtotime(request('date')));
        $month      = date('m', strtotime(request('date')));
        $month_days = cal_days_in_month(CAL_GREGORIAN,  $month,  $year);

        for( $i = 1 ; $i <= $month_days ; $i++ ){
            $check = false;
            foreach( $highlight_date as $hd ){
                if( request('date').'-'.str_pad($i,2,'0',STR_PAD_LEFT) == $hd ){
                    $check = true;
                    break;
                }
            }
            if( $check == false ){
                $date_arr[] = request('date') . '-' . str_pad($i,2,'0',STR_PAD_LEFT);
            }
        }

        return $date_arr;
    }

    // 取得當日為月份的第幾週
    static public function get_week( $year , $month )
    {
        $now_days  = date("t",strtotime(date($year.'-'.$month)."-1")); // 當月有幾天

        for( $i = 1 ; $i <= $now_days ; ++$i ){
            $week_date[ date("w",strtotime($year.'-'.$month.'-'.$i)) ][] = date($year.'-'.$month.'-'.str_pad($i,2,'0',STR_PAD_LEFT));
        }

        return $week_date;
    }

    // 取得指定員工與對應日期的預約時間
    static public function get_reservation_time($type="")
    {               
    	// 驗證欄位資料
        $rules = [
            'staff'   => 'required', 
            'date'    => 'required',
        ];

        $messages = [
            'staff.required' => '缺少服務人員資料',
            'date.required'  => '缺少日期資料'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => implode(',',$validator->getMessageBag()->toArray()) ]); 
        }

        $shop_staff = ShopStaff::find(request('staff'));

        // 找出星期x的營業時間
        $businessHours = ShopBusinessHour::where('shop_staff_id',request('staff'))
                                           ->where('week',date('N',strtotime(request('date'))))
                                           ->get();
        // 找出員工特殊休假日
        $staff_vacations = ShopVacation::where('shop_staff_id',request('staff'))
                                         ->where('start_date','<=',request('date'))
                                         ->where('end_date','>=',request('date'))
                                         ->get();

        $shop_vacations = ShopVacation::where('shop_id', $shop_staff->shop_id)
                                         ->where('shop_staff_id', NULL)
                                         ->where('start_date','<=',request('date'))
                                         ->where('end_date','>=',request('date'))
                                         ->get();

        
        $shop_businessHours = ShopBusinessHour::where('shop_id',$shop_staff->shop_id)
                                           ->where('shop_staff_id',NULL)
                                           ->where('week',date('N',strtotime(request('date'))))
                                           ->get();

        $open_time  = [];
        $close_time = []; // 非營業時間
        $time_arr   = []; // 營業時間
        $break_time = []; // 某日的區間休息時間

        // 先利用特殊休假日確認今日是否完全休息
        $open_check = false;
        if( $staff_vacations->count() ){
        	if( !in_array(1,$staff_vacations->pluck('type')->toArray()) 
        		&& !in_array(3,$staff_vacations->pluck('type')->toArray()) 
        		&& in_array(2,$staff_vacations->pluck('type')->toArray()) ){
        		// 表示今日只有某些時段不營業
        		$open_check = true;

        	    // 半小时間隔區段
        	    foreach( $staff_vacations as $vacation ){
        		    for( $i = strtotime($vacation->start_time) ; $i <= strtotime($vacation->end_time) ; $i = $i + 60 * 30 ) {
					    $break_time[] = date("H:i", $i);
					}
        	    }   
        	}
        }else{
            $open_check = true;
        }

        // 判斷商家休假日
        if ($shop_vacations->count()) {
            if (!in_array(1, $shop_vacations->pluck('type')->toArray())
                && !in_array(3, $shop_vacations->pluck('type')->toArray())
                && in_array(2, $shop_vacations->pluck('type')->toArray()) ) {
                // 表示今日只有某些時段不營業
                $open_check = true;

                // 半小时間隔區段
                foreach ($shop_vacations as $vacation) {
                    for ($i = strtotime($vacation->start_time); $i <= strtotime($vacation->end_time); $i = $i + 60 * 30) {
                        if( !in_array(date("H:i", $i), $break_time )) $break_time[] = date("H:i", $i);
                    }
                }
            }
        } else {
            $open_check = true;
        }

        // 再根據個人營業時間找出當天的營業時間與非營業時間
        if( $open_check && ($businessHours->count() >= 1 || $businessHours[0]->type == 1) ){
        	foreach( $businessHours as $hour ){
        		for( $i = strtotime($hour->start) ; $i <= strtotime($hour->end) ; $i = $i + 60 * 30 ) {
				    if( !in_array( date("H:i", $i) , $break_time ) ){

                        // 檢查時間是否在商家營業時間內
                        foreach( $shop_businessHours as $sb ){
                            if( strtotime(date("H:i", $i)) >= strtotime($sb->start) && strtotime(date("H:i", $i)) <= strtotime($sb->end) ){
                                $open_time[] = [
                                    'time'     => date("H:i", $i),
                                    'selected' => false,
                                    'use'      => 0,
                                ];
                                break;
                            }
                        }
	
    		    	}
				}
        	}
        }

        // 非營業時間
        for( $i = strtotime('00:00') ; $i <= strtotime('23:30') ; $i = $i + 60 * 30 ) {
        	$chk = 0;
        	foreach( $open_time as $otime ){
                if( date("H:i", $i) == $otime['time'] ){
                    $chk = 1;
                    break;
                }
            }
            if( $chk == 0 ){
                $close_time[] = [
                    'time'     => date("H:i", $i),
                    'selected' => false,
                    'use'      => 0
                ];
            }
		}

        // 若今天是休假日就直接回傳
        // if( $businessHours[0]->type == 0 || $open_check == false ){
        // 	return response()->json([ 'open' => $open_time , 'close' => $close_time ]);
        // }

        $shop_staff      = ShopStaff::where('id',request('staff'))->first();
        $shop_staff_info = CompanyStaff::where('id',$shop_staff->company_staff_id)->first();

        // 先註記已被預約的時間
        $events = CustomerReservation::where('start','like',request('date').'%')
                                            ->where('shop_staff_id',request('staff'))
                                            ->where('status','Y')
                                            ->where('cancel_status',NULL)
                                            ->get();
        foreach( $events as $event ){
            $i = 0 ;
            $start_time = $event->start;
            $end_time   = $event->end;
            do {
                $start_time_add = date("i", strtotime($start_time."+".$i." minute"));
                $m = $start_time_add >= 0 && $start_time_add < 30 ? '00' : '30';

                if( date("H:".$m, strtotime($start_time."+".$i." minute")) != date("H:i", strtotime($end_time)) ){
                    foreach( $open_time as $k => $time_arr ){
                    	if( $time_arr['time'] == date("H:".$m, strtotime($start_time."+".$i." minute")) ){
                    		$open_time[$k]['selected'] = true;
                            $open_time[$k]['use']     += 1;
                    	}
                    }
                    foreach( $close_time as $k => $time_arr ){
                    	if( $time_arr['time'] == date("H:".$m, strtotime($start_time."+".$i." minute")) ){
                    		$close_time[$k]['selected'] = true;
                            $close_time[$k]['use']      += 1;
                    	}
                    }
                } 

                $i += 30;
            } while ( $i <= $event->need_time );
        }

        // 拿取員工的google行事曆
        if( $shop_staff_info->calendar_token != NULL ){

            $google_events = GoogleCalendarController::staff_calendar_events( $shop_staff_info , date('c',strtotime(request('date').' 00:00')) , date('c',strtotime(request('date').' 23:59')) );

            foreach( $google_events as $event ){
                // 若google的行事曆事件與預約有關就跳過
                if( CustomerReservation::where('google_calendar_id',$event['id'])->first() ) continue;

                $check_date = [];

                if( date('Y-m-d',strtotime($event['start'])) == date('Y-m-d',strtotime($event['end'])) ){
                    // 當天的資料
                	$i = 0 ;
                	$start_time = $event['start'];
                    $end_time   = $event['end'];
                	$needTime   = (strtotime($end_time) - strtotime($start_time)) / 60 ;
                	do {
                	    $start_time_add = date("i", strtotime($start_time."+".$i." minute"));
                	    $m = $start_time_add >= 0 && $start_time_add < 30 ? '00' : '30';

                	    if( date("H:".$m, strtotime($start_time."+".$i." minute")) != date("H:i", strtotime($end_time)) ){
                	        foreach( $open_time as $k => $time_arr ){
                	        	if( $time_arr['time'] == date("H:".$m, strtotime($start_time."+".$i." minute")) ){
                	        		$open_time[$k]['selected'] = true;
                                    $open_time[$k]['use']     += 1;
                	        	}
                	        }
                	        foreach( $close_time as $k => $time_arr ){
		                    	if( $time_arr['time'] == date("H:".$m, strtotime($start_time."+".$i." minute")) ){
		                    		$close_time[$k]['selected'] = true;
                                    $close_time[$k]['use']     += 1;
		                    	}
                            }
                	    } 

                	    $i += 30;
                	} while ( $i <= $needTime );

                }else{
                    // 跨天
                    $star_y = date("Y", strtotime($event['start']));
                    $end_y  = date("Y", strtotime($event['end']));
                    $star_m = date("m", strtotime($event['start']));
                    $end_m  = date("m", strtotime($event['end']));
                    $star_d = date("d", strtotime($event['start']));
                    $end_d  = date("d", strtotime($event['end']));

                    if( request('date') == date("Y-m-d", strtotime($event['start'])) ){
                        // 第一天
                        $start_time_add = date("i", strtotime($event['start']));
                	    $m = date("i", strtotime($event['start'])) >= 0 && date("i", strtotime($event['start'])) < 30 ? '00' : '30';
                	    
                	    $stime = date("H:".$m, strtotime($event['start']));
                        $etime = "23:30";

                    }else if( request('date') != date("Y-m-d", strtotime($event['start'])) && request('date') != date("Y-m-d", strtotime($event['end'])) ){
                        // 中間天
                        $stime = '00:00';
                        $etime = "23:30";
                    }else if( request('date') == date("Y-m-d", strtotime($event['end'])) ) {
                        // 最後一天
                        $stime = '00:00';

                	    $m = date("i", strtotime($event['end'])) >= 0 && date("i", strtotime($event['end'])) < 30 ? '00' : '30';
                        $etime = date("H:".$m, strtotime($event['end']));
                    }

                    for( $i = strtotime($stime) ; $i <= strtotime($etime) ; $i = $i + 60 * 30 ) {
                    	foreach( $open_time as $k => $time_arr ){
                    		if( $time_arr['time'] == date("H:i", $i) ){
                    			$open_time[$k]['selected'] = true;
                                $open_time[$k]['use']     += 1;
                    		}
                    	}
                    	foreach( $close_time as $k => $time_arr ){
                    		if( $time_arr['time'] == date("H:i", $i) ){
                    			$close_time[$k]['selected'] = true;
                                $close_time[$k]['use']     += 1;
                    		}
                    	}
					}
                }
            }
        }

        if( $type == 'check' ){
            return array_merge( $open_time , $close_time);
        }else{
            return response()->json([ 'open' => $open_time , 'close' => $close_time ]);
        }
        
    }

    // 美業官網取得可服務的日期
    public function web_get_highlight_date()
    { 
        // 驗證欄位資料
        $rules = [
            'staff' => 'required', 
            'date'  => 'required', 
        ];

        $messages = [
            'staff.required' => '缺少服務人員資料',
            'date.required'  => '缺少日期資料'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $highlight_date_data = Self::get_highlight_date();
        $highlight_date = json_decode(json_encode($highlight_date_data))->original->highlight_date;

        // 根據商家可提前幾天預約做判斷
        $shop_staff = ShopStaff::find(request('staff'));
        $shop_info  = Shop::find($shop_staff->shop_id);
        if( strtotime(date('Y-m')) < strtotime(request('date')) ){
            // 先檢查開放可預約的期間 1每月固定 2只能約近 3不限制
            if( $shop_info->shop_set->reservation_during_type == 1 ){
                if( date('d') < $shop_info->shop_set->reservation_during_open ){
                    // 尚未超過固定日
                    return response()->json(['status' => true , 'data' => []]);
                }else{
                    // 等於或超過固定日，檢查開放後幾個月是否符合
                    $check = false;
                    for( $i = 1 ; $i <= $shop_info->shop_set->reservation_during_month_after ; $i++ ){
                        $check_month = date('n')+$i > 12 ? date('n')+$i - 12 : date('n')+$i;
                        if( $check_month == date('n',strtotime(request('date'))) ){
                            $check = true;
                        }
                    }
                    if( $check == false ){
                        return response()->json(['status' => true , 'data' => []]);
                    }
                }
            }elseif( $shop_info->shop_set->reservation_during_type == 2 ){
                // 2只能約近幾天
                $h_date = $highlight_date;
                $highlight_date = [];
                foreach( $h_date as $date ){
                    if( strtotime($date) - strtotime(date('Y-m-d')) <= 86400*($shop_info->shop_set->reservation_during_day_close+1) ){
                        $highlight_date[] = $date;
                    }
                }

                // for( $i = 0 ; $i < $shop_info->shop_set->reservation_during_month_close ; $i++ ){
                //     $check_month = date('n')+$i > 12 ? date('n')+$i - 12 : date('n')+$i;
                //     if( $check_month == date('n',strtotime(request('date'))) ){
                //         $check = true;
                //     }
                // }
                // if( $check == false ){
                //     return response()->json(['status' => true , 'data' => []]);
                // }
            } 
        }elseif( strtotime(date('Y-m')) > strtotime(request('date')) ){
            // 小於當月
            return response()->json(['status' => true , 'data' => []]);
        }else{
            // 等於當月，需替除過去日期
            $h_date = $highlight_date;
            $highlight_date = [];

            foreach( $h_date as $date ){
                // 預約限制  1.當日不可預約2距當日3不限制
                if( $shop_info->shop_set->reservation_limit_type == 1 && $date > date('Y-m-d') ){
                    // if( $shop_info->shop_set->reservation_during_day_close ){
                    //     if( strtotime($date) - strtotime(date('Y-m-d')) <= 86400*($shop_info->shop_set->reservation_during_day_close) ){
                    //         $highlight_date[] = $date;
    
                    //     }
                    // }else{
                    //     $highlight_date[] = $date;
                    // }

                    if( $date != date('Y-m-d') ){
                        $highlight_date[] = $date;
                    }
                    
                }elseif( $shop_info->shop_set->reservation_limit_type == 2 ){
                    if( strtotime($date) - strtotime(date('Y-m-d')) > 86400*($shop_info->shop_set->reservation_limit_day-1) ){
                        if($shop_info->shop_set->reservation_during_day_close == NULL ){
                            $highlight_date[] = $date;
                        }else{
                            if (strtotime($date) - strtotime(date('Y-m-d')) <= 86400 * ($shop_info->shop_set->reservation_during_day_close)) {
                                $highlight_date[] = $date;
                            }
                        }
                    }
                }elseif( $shop_info->shop_set->reservation_limit_type == 3 && $date >= date('Y-m-d') ){
                    $highlight_date[] = $date;
                } 
            }
        }

        // 加入商家營業時間判斷
        // $date = [];
        // $shop_business = ShopBusinessHour::where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->get();
        // foreach( $highlight_date as $hd ){
        //     if( $shop_business->where('week',date('N',strtotime($hd)))->first()->type == 1 ){
        //         $date[] = $hd; 
        //     }
        // }
    
        return response()->json(['status' => true , 'data' => $highlight_date]);
    }

    // 美業官網取不可服務的日期
    public function web_get_blacklist_date()
    {       
        // 驗證欄位資料
        $rules = [
            'staff' => 'required', 
            'date'  => 'required', 
        ];

        $messages = [
            'staff.required' => '缺少服務人員資料',
            'date.required'  => '缺少日期資料'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $res = json_decode(json_encode(Self::web_get_highlight_date()));
        $highlight_date = $res->original->data;

        $date_arr   = [];
        $year       = date('Y', strtotime(request('date')));
        $month      = date('m', strtotime(request('date')));
        $month_days = cal_days_in_month(CAL_GREGORIAN,  $month,  $year);

        for( $i = 1 ; $i <= $month_days ; $i++ ){
            $check = false;
            foreach( $highlight_date as $hd ){
                if( request('date').'-'.str_pad($i,2,'0',STR_PAD_LEFT) == $hd ){
                    $check = true;
                    break;
                }
            }
            if( $check == false ){
                $date_arr[] = request('date') . '-' . str_pad($i,2,'0',STR_PAD_LEFT);
            }
        }

        return response()->json(['status' => true , 'data' => $date_arr]);
    }

    // 取得指定員工與對應日期的預約時間
    static public function web_get_reservation_time()
    {               
        // 驗證欄位資料
        $rules = [
            'staff'    => 'required', 
            'date'     => 'required',
            'service'  => 'required',
            // 'advances' => 'required',
        ];

        $messages = [
            'staff.required'   => '缺少服務人員資料',
            'date.required'    => '缺少日期資料',
            'service.required' => '缺少服務資料'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $reservation_time = Self::get_reservation_time();
        $open_time  = json_decode(json_encode($reservation_time))->original->open; 
        $close_time = json_decode(json_encode($reservation_time))->original->close;

        $time_arr = [];
        $shop_staff    = ShopStaff::find(request('staff'));
        $shop_service  = ShopService::find(request('service'));
        $shop_advances = ShopService::whereIn('id',explode(',',request('advances')))->get();
        $shop_info     = Shop::find($shop_staff->shop_id);

        $need_time = $shop_service->service_time+$shop_service->lead_time+$shop_service->buffer_time;
        foreach( $shop_advances as $advance ){
            $need_time += $advance->service_time+$advance->buffer_time;
        }

        foreach( $open_time as $time ){

            // 檢查需要的時間內是否都還有可以預約的時間
            $end_time = date('H:i',strtotime(request('date').' '.$time->time.' +'.$need_time.' minute'));

            for( $i = strtotime($time->time) ; $i < strtotime($end_time) ; $i = $i + 60 * 30 ) {
                $check = false;
                $break = false;
                foreach( $open_time as $otime ){
                    if( $otime->time == date("H:i", $i) ){
                        $check = true;
                        break;
                    }
                }

                // 確認需要的服務時間區間內有在營業時間選擇中
                if( $check == true ){
                    
                    if( ($shop_info->shop_set->reservation_repeat_time_type == 0 && $otime->use != 0 ) 
                        || ( $shop_info->shop_set->reservation_repeat_time_type == 1 && $otime->use > $shop_info->shop_set->reservation_repeat_time) ){
                        $break = true;
                        break;
                    }

                }else{
                    // 代表時間沒有連續
                    $break = true;
                    break;
                }
            } 

            if( $break == false ){
                $time_arr[] = $time->time; 
            }
        }

        // 加入商家營業時間判斷
        $shop_business = ShopBusinessHour::where('shop_id',$shop_info->id)->where('shop_staff_id',NULL)->where('week',date('N',strtotime(request('date'))) )->get();
        $open = [];
        foreach( $time_arr as $ta ){
            foreach( $shop_business as $sb ){
                if( strtotime($ta) >= strtotime($sb->start) && strtotime($ta) <= strtotime($sb->end) ){
                    $open[] = $ta;
                    break;
                }
            }  
        }

        // 加入今日過期時間判斷
        if( request('date') == date('Y-m-d') ){
            $check_open = [];
            foreach( $open as $time ){
                if( $time >= date('H:i') ){
                    $check_open[] = $time;
                }
            }
        }else{
            $check_open = $open;
        }
    
        return response()->json([ 'time_arr' => $check_open, 'open' => $open_time , 'close' => $close_time ]);
    }

    // 會員取消預約
    public function customer_cancel_reservation()
    {
        if( !request('reservation_id') ){
            return [ 'status' => false , 'msg' => '請輸入reservation_id' ];
        }

        $reservation = CustomerReservation::where('id',request('reservation_id'))->first();
        if( !$reservation ){
            return [ 'status' => false , 'msg' => "找不到對應預約單資料" ];
        }

        if( $reservation->status == 'M' || $reservation->status == 'C' ){
            return [ 'status' => true ];
        }

        $reservation->status = 'M';
        $reservation->save();

        // 預約單已經被確認過
        // 將google calendar事件刪除
        if( $reservation->google_calendar_id && $reservation->staff_info->calendar_token ){
            GoogleCalendarController::delete_calendar_event($reservation);
        }
        $reservation->google_calendar_id = NULL;
        $reservation->save();

        CustomerReservationAdvance::where('customer_reservation_id',$reservation->id)->delete();

        // 客戶取消已確認的預約單，需通知商家、對應服務人員
        $master = ShopStaff::where('shop_id',$reservation->shop_id)->where('master',0)->value('id'); 

        $shop_info = Shop::find($reservation->shop_id);

        $post_data = [
            "type"            => 'new',
            "shop_name"       => $shop_info->name,
            "shop_id"         => $shop_info->id,
            "customer_cancel" => true,
            "id"              => $reservation->id,
            "staffId"         => $reservation->shop_staff_id.','.$master,
            "serviceName"     => $reservation->service_info->name,
            "customer_name"   => $reservation->customer_info->realname,
            "staffName"       => $reservation->staff_info->name,
            "item_names"      => '',
            "date"            => $reservation->start,
            "link"            => '',
            "message"         => "喔喔～".$reservation->customer_info->realname."預約取消了\n"
                                ."取消時間：".substr($reservation->start,5,2)."月".substr($reservation->start,8,2)."日".substr($reservation->start,11,2)."點".substr($reservation->start,14,2)."分\n"
                                ."服務人員：".$reservation->staff_info->name."\n"
                                ."預約項目：".$reservation->service_info->name."\n，點擊下方按鈕看詳細。",
        ];

        $shop_info = Shop::find($reservation->shop_id);

        ApiController::line_message($post_data); 

        $url_data = [
            [
                'text' => '看詳細 GO>',
                'url'  => '/reservation/verify/block',
            ],
        ];
        $notice = new SystemNotice;
        $notice->company_id = $shop_info->company_info->id;
        $notice->shop_id    = $shop_info->id;
        $notice->content    = $post_data['message'];
        $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
        $notice->save(); 

        // 使用簡訊通知客戶
        if( $shop_info->shop_reservation_messages->where('type','customer_cancel')->first()->status == 'Y' 
            && ( $shop_info->company_info->gift_sms>0 || $shop_info->company_info->buy_sms>0 ) ){

            $store_name    = $shop_info->name;
            $serviceName   = $reservation->service_info->name;
            $staffName     = $reservation->staff_info->name;
            $customer_name = $reservation->customer_info->realname;

            // 確認字數長度
            if( mb_strwidth($customer_name) >= 8  || (!preg_match("/^([A-Za-z]+)$/", $customer_name)) ) $customer_name = Controller::cut_str( $customer_name , 0 , 8 );
            if( mb_strwidth($store_name)    >= 24 || (!preg_match("/^([A-Za-z]+)$/", $store_name)) )    $store_name    = Controller::cut_str( $store_name , 0 , 24 );
            if( mb_strwidth($serviceName)   >= 20 || (!preg_match("/^([A-Za-z]+)$/", $serviceName)) )   $serviceName   = Controller::cut_str( $serviceName , 0 , 20 );
            if( mb_strwidth($staffName)     >= 16 || (!preg_match("/^([A-Za-z]+)$/", $staffName)) )     $staffName     = Controller::cut_str( $staffName , 0 , 16 );

            $sendword = $shop_info->shop_reservation_messages->where('type','customer_cancel')->first()->content;
            $sendword = str_replace('「"商家名稱"」'    , $store_name, $sendword);
            $sendword = str_replace('「"服務名稱"」'    , $serviceName, $sendword);
            $sendword = str_replace('「"會員名稱"」'    , $customer_name, $sendword);
            $sendword = str_replace('「"服務日期"」'    , substr($reservation->start,0,10), $sendword);
            $sendword = str_replace('「"預約日期時間"」' , substr($reservation->start,11,5), $sendword);

            // 訂單連結
            $url = '/store/'.$shop_info->alias.'/member/reservation';
            $transform_url_code = Controller::get_transform_url_code($url); 
            $sendword = str_replace('「"訂單連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);

            // 再次預約連結
            $url = '/store/'.$shop_info->alias.'/reservation/again/'.$reservation->id;
            $transform_url_code = Controller::get_transform_url_code($url); 
            $sendword = str_replace('「"再次預約連結"」' , env('SHILIPAI_WEB').'/T/'.$transform_url_code, $sendword);

            Controller::send_phone_message($reservation->customer_info->phone,$sendword,$shop_info);
        }

        return [ 'status' => true ];
    }

}
