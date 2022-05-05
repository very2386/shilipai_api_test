<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopCoupon;
use App\Models\ShopManagementRefuse;
use App\Models\ShopManagementCustomerList;
use App\Jobs\SendManagementSms;
use App\Models\CustomerCoupon;
use App\Models\ShopFestivalNotice;
use Overtrue\ChineseCalendar\Calendar;

class SendFestivalNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send_festival_notice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '發送符合條件的節慶通知';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $start = microtime(date('Y-m-d H:i:s'));
        
        $shop_customers = ShopCustomer::get();
        // 拿取符合當下時間的節慶通知資料
        $notices = ShopFestivalNotice::whereNotNull('shop_id')->where('use','Y')->get();

        foreach( $notices as $notice ){

            if( $notice->message == '' || $notice->message == NULL ) continue;

            // 檢查是否符合時間
            $calendar = new Calendar();
            if( $notice->week != '' ){
                // 母親節
                $sunday = 0;
                for($i = 1 ; $i <= 14 ; $i++ ){
                    $date_trans = $calendar->solar(date('Y'),$notice->month,$i);
                    $sunday += $date_trans['week_no'] == 0 ? 1 : 0;
                    if( $sunday == 2 ){
                        $date = date('Y-05-'). ($i > 9 ? $i : '0'.$i);
                        break;
                    }
                }
            }else{
                // 其他節慶
                if( $notice->type == 1 ){
                    // 國曆
                    $date = date('Y') . '-' . ($notice->month > 9 ? $notice->month : '0'.$notice->month) . '-' . ($notice->day > 9 ? $notice->day : '0'.$notice->day); 
                }else{
                    // 農曆
                    $date_trans = $calendar->lunar(date('Y'),$notice->month,$notice->day);
                    $date       = date('Y') . '-' . $date_trans['gregorian_month'] . '-' . $date_trans['gregorian_day'];
                }
            }
            
            $shop_info = Shop::find($notice->shop_id);

            // 需拒送訊息通知會員
            $shop_customer_refuse = ShopManagementRefuse::where('shop_id',$notice->shop_id)->pluck('shop_customer_id')->toArray();
            $refuse_customer_id = ShopCustomer::where('shop_id',$shop_info->id)->whereIn('id',$shop_customer_refuse)->pluck('customer_id')->toArray();

            $shop_customer = ShopCustomer::where('shop_id',$shop_info->id)
                                         ->whereNotIn('customer_id',$refuse_customer_id)
                                         ->get();

            // 時間比對正確才發送
            if( $shop_customer->count() == 0 
                || date('Y-m-d') != date('Y-m-d',strtotime($date.'-'.$notice->before.' day'))
                || ($notice->send_datetime != NULL && date('H:i') != date('H:i',strtotime($notice->send_datetime))) ){
                $this->info( 'id = ' . $notice->id . ',name = ' . $notice->name . ',send_day = ' . date('Y-m-d',strtotime($date.'-'.$notice->before.' day')) . ',send_time = '.$notice->send_datetime . ',customer = ' . $shop_customer->count() );
                $this->info( date('H:i'). ','. date('H:i',strtotime($notice->send_datetime)));
                continue;
            }

            $insert_lists = [];
            foreach( $shop_customer as $customer ){
                
                // 發送文字內容整理
                $message = $notice->message;
                $coupon  = ShopCoupon::find($notice->shop_coupons);

                $message = str_replace('「"商家名稱"」' , $shop_info->name, $message);
                $message = str_replace('「"會員名稱"」' , $customer->customer_info->realname, $message);
                $message = str_replace('「"下個月份"」' , ( date('n')+1 == 13 ? '01' : (string)date('n')+1 ).'月', $message);

                if( $notice->link ){
                    $message = str_replace('「"連結"」' , ' ' . $notice->link . ' ' , $message);
                }else{
                    $message = str_replace('「"連結"」' , '', $message);
                }

                if( $coupon && $coupon->status == 'published' ){

                    // 先檢查優惠券是否過期
                    if( strtotime($coupon->start_date) <= time() && time() <= strtotime($coupon->end_date) ){

                        if( $coupon->get_level == 2 ){
                            // 特定條件
                            $add = true;
                        }else{
                            // 所有人，需判斷可領取次數，若只能領取一次，需判斷是否可以在給予優惠券
                            $add = true;
                            if( $coupon->use_type == 1 ){
                                $customer_coupon = CustomerCoupon::where('customer_id', $customer->customer_info->id)
                                                                ->where('shop_id',$shop_info->id)
                                                                ->where('shop_coupon_id',$coupon->id)
                                                                ->first();
                                if( $customer_coupon ) $add = false;
                            }
                        }

                        if( $add ){
                            // 將該優惠券直接寫入會員裡
                            $customer_coupon = new CustomerCoupon;
                            $customer_coupon->customer_id    = $customer->customer_info->id;
                            $customer_coupon->company_id     = $shop_info->company_info->id;
                            $customer_coupon->shop_id        = $shop_info->id;
                            $customer_coupon->shop_coupon_id = $coupon->id;
                            $customer_coupon->save();
                        }
                    }
                    
                    // 建立縮短網址
                    $url = '/store/'.$shop_info->alias.'/member/coupon?select=2';
                    $transform_url_code = Controller::get_transform_url_code($url);
                    $message = str_replace('「"優惠券"」' , ' ' . env('SHILIPAI_WEB').'/T/'.$transform_url_code . ' ', $message);

                }else{
                    $message = str_replace('「"優惠券"」' , '', $message);
                }

                $message = str_replace('「"店網址"」' , ' ' . env('SHILIPAI_WEB').'/s/'.$shop_info->alias . ' ' , $message);

                // 建立要寫入推廣的顧客列表
                $insert_lists[] = [
                    'shop_id'                 => $shop_info->id,
                    'shop_festival_notice_id' => $notice->id,
                    'shop_customer_id'        => $customer->id,
                    'phone'                   => $customer->customer_info->phone,
                    'type'                    => $notice->send_type,
                    'message'                 => $message,
                    'created_at'              => date('Y-m-d H:i:s'),
                    'updated_at'              => date('Y-m-d H:i:s'),
                ];
            }

            ShopManagementCustomerList::insert($insert_lists);

            // 拿出尚未發送的會員
            $management_customers = ShopManagementCustomerList::where('shop_festival_notice_id',$notice->id)->where('status','N')->get();

            foreach( $management_customers as $customer ){
                // 被刪除的會員不用發送
                if (!$customer->customer_info) continue;
                // 沒有電話的不用發送
                if (!$customer->phone) continue;

                switch ($notice->send_type) {
                    case 1: // 手機與line
                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,'',$notice->id,'');
                        dispatch($job);

                        // line發送

                        break;

                    case 2: // 手機
                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,'',$notice->id,'');
                        dispatch($job);

                        break;

                    case 3: // line

                        break;

                    case 4: // line優先

                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,'',$notice->id,'');
                        dispatch($job);

                        break;
                }
            }

            $this->info($notice->name . ' 已發送');

        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('發送符合條件的訊息通知內容完成'.( $end - $start ));
    }
}
