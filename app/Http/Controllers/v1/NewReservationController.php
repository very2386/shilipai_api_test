<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\CompanyStaff;
use App\Models\CustomerReservation;
use App\Models\Shop;
use App\Models\ShopBusinessHour;
use App\Models\ShopService;
use App\Models\ShopStaff;
use App\Models\ShopVacation;
use Illuminate\Http\Request;
use Validator;

class NewReservationController extends Controller
{
    // 根據多個服務與加值服務資料取得可選擇的服務時間
    public function web_select_time()
    {             
        // 驗證欄位資料
        $rules = [
            'code' => 'required', 
            'date' => 'required',
        ];

        $messages = [
            'code.required' => '缺少服務資料',
            'date.required' => '缺少日期資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $reservation_info = json_decode(base64_decode(request('code')) );

        $reservation_time = Self::get_select_time($reservation_info);

        $open_time  = $reservation_time['open']; 
        $close_time = $reservation_time['close'];

        // dd( $reservation_info );

        $time_arr = [];
        $shop_staff    = ShopStaff::find($reservation_info->staff);
        $shop_service  = ShopService::whereIn('id',$reservation_info->service);
        $shop_info     = Shop::find($shop_staff->shop_id);

        // 加值服務
        $reservation_advance = [];
        $reservation_advance_times = [];
        if( isset($reservation_info->advance) ){
            foreach( $reservation_info->advance as $advance ){
                $tmp = explode('-',$advance);
                $reservation_advance_times[$tmp[1]] = !isset($reservation_advance_times[$tmp[1]]) ? 1 : $reservation_advance_times[$tmp[1]]+1;
                if( !in_array($tmp[1],$reservation_advance) ) $reservation_advance[] = $tmp[1];
            }
        }
        
        $shop_advances = ShopService::whereIn('id',$reservation_advance)->get();

        $need_time = $shop_service->sum('service_time')+$shop_service->sum('lead_time')+$shop_service->sum('buffer_time');
        foreach( $shop_advances as $advance ){
            $need_time += ($advance->service_time+$advance->buffer_time)*$reservation_advance_times[$advance->id];
        }

        foreach( $open_time as $time ){

            // 檢查需要的時間內是否都還有可以預約的時間
            $end_time = date('H:i',strtotime(request('date').' '.$time['time'].' +'.$need_time.' minute'));

            for( $i = strtotime($time['time']) ; $i < strtotime($end_time) ; $i = $i + 60 * 30 ) {
                $check = false;
                $break = false;
                foreach( $open_time as $otime ){
                    if( $otime['time'] == date("H:i", $i) ){
                        $check = true;
                        break;
                    }
                }

                // 確認需要的服務時間區間內有在營業時間選擇中
                if( $check == true ){
                    
                    if( ($shop_info->shop_set->reservation_repeat_time_type == 0 && $otime['use'] != 0 ) 
                        || ( $shop_info->shop_set->reservation_repeat_time_type == 1 && $otime['use'] > $shop_info->shop_set->reservation_repeat_time) ){
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
                $time_arr[] = $time['time']; 
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

        return response()->json([ 'time_arr' => $open ]);
    }

    // 取得指定員工與對應日期的預約時間
    static public function get_select_time($reservation_info)
    {               
        // 找出員工星期x的營業時間
        $businessHours = ShopBusinessHour::where('shop_staff_id',$reservation_info->staff)
                                           ->where('week',date('N',strtotime(request('date'))))
                                           ->get();
        // 找出員工特殊休假日
        $staff_vacations = ShopVacation::where('shop_staff_id',$reservation_info->staff)
                                         ->where('start_date','<=',request('date'))
                                         ->where('end_date','>=',request('date'))
                                         ->get();

        $shop_staff = ShopStaff::find($reservation_info->staff);
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

        $shop_staff_info = CompanyStaff::where('id',$shop_staff->company_staff_id)->first();

        // 先註記已被預約的時間
        $events = CustomerReservation::where('start','like',request('date').'%')
                                            ->where('shop_staff_id',$shop_staff->id)
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

        return [ 'open' => $open_time , 'close' => $close_time ];
    }
}
