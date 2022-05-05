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
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerReservation;
use App\Models\ShopAwardNotice;

class SendAwardNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send_award_notice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '發送符合條件的獎勵通知';

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
        $notices = ShopAwardNotice::where('use','Y')->get();

        foreach( $notices as $notice ){

            if( $notice->message == '' || $notice->message == NULL ) continue;

            // 檢查活動時間
            if( $notice->during_type == 2 ){
                // 自定時間
                if( $notice->start_date > date('Y-m-d') || $notice->end_date < date('Y-m-d') ) continue;
            }

            $shop_info = Shop::find($notice->shop_id);

            // 商家全部預約
            $shop_reservations = CustomerReservation::where('shop_id',$shop_info->id)
                                                    ->where('status','!=','C')
                                                    ->get();

            // 需拒送訊息通知會員
            $shop_customer_refuse = ShopManagementRefuse::where('shop_id',$notice->shop_id)->pluck('shop_customer_id')->toArray();
            $refuse_customer_id   = ShopCustomer::where('shop_id',$shop_info->id)->whereIn('id',$shop_customer_refuse)->pluck('customer_id')->toArray();

            $continue = false;
            $shop_customer = [];
            switch( $notice->condition_type ){
                case 1 : // 註冊新會員
                    $shop_customer = [];
                    if( $notice->send_cycle == 2 ){
                        // 每日檢查
                        $date = date('Y-m-d',strtotime(date('Y-m-d').'-1 day'));
                        $shop_customer = ShopCustomer::where('shop_id',$shop_info->id)
                                                     ->whereNotIn('customer_id',$refuse_customer_id)
                                                     ->whereBetween('created_at',[$date.' 00:00:00',$date.' 23:59:59'])
                                                     ->get();
                    }else{
                        $continue = true;
                    }
                    break;
                case 2 : // 當月壽星
                    $shop_customer = [];
                    if( $notice->send_cycle == 3 ){
                        // 會員生日前
                        $before_birthday = date('m-d',strtotime(date('Y-m-d').'+'.$notice->send_day.'day'));
                        $customer        = Customer::where('birthday','like','%'.$before_birthday)->get();
                        $shop_customer   = ShopCustomer::where('shop_id',$shop_info->id)
                                                       ->whereNotIn('customer_id',$refuse_customer_id)
                                                       ->whereIn('customer_id',$customer->pluck('id')->toArray())
                                                       ->get();

                        // $this->info( $before_birthday . ' , ' . $notice->id . ' , ' . $notice->send_day  );
                        
                    }elseif( $notice->send_cycle == 4){
                        // 前一個月
                        if( date('j') != $notice->send_day ) $continue = true;

                        $before_birthday = date('n')+1 > 9 ? (date('n')+1==13 ? '01' : date('n')+1) : '0'.(date('n')+1);
                        $customer        = Customer::where('birthday','like','%'.$before_birthday.'-%')->get();
                        $shop_customer   = ShopCustomer::where('shop_id',$shop_info->id)
                                                       ->whereNotIn('customer_id',$refuse_customer_id)
                                                       ->whereIn('customer_id',$customer->pluck('id')->toArray())
                                                       ->get();
                    }
                    break;
                case 3 : // 首次預約
                    if( $notice->send_cycle == 2 ){
                        // 每日檢查
                        $start_time = date('Y-m-d',strtotime(date('Y-m-d').'-1 day'));
                        $customer_reservations = CustomerReservation::where('shop_id',$shop_info->id)
                                                                   ->where('status','!=','C')
                                                                   ->where('start','like',$start_time.'%')
                                                                   ->get();
                        $shop_customer = [];
                        if( $notice->finish_type == 1 ){
                            // 完成預約就獎勵
                            // 檢查是否是第一次預約
                            $notice_customer = [];
                            foreach( $customer_reservations as $reservation ){
                                $check = $shop_reservations->where('shop_id',$shop_info->id)
                                                           ->where('customer_id',$reservation->customer_id) 
                                                           ->where('start','<',$reservation->start)
                                                           ->count();
                                if( $check == 0 ) $notice_customer[] = $reservation->customer_id;
                            }
                            $shop_customer = ShopCustomer::where('shop_id',$shop_info->id)->whereIn('customer_id',$notice_customer)->get();
                        }else if( $notice->finish_type == 2 ){
                            // 預約且出席後獎勵
                            // 檢查是否是第一次預約
                            $notice_customer = [];
                            foreach( $customer_reservations as $reservation ){
                                // 若此筆也是未出席就跳過
                                if( $reservation->tag != NULL && $reservation->tag != 2 ){
                                    $check = $shop_reservations->where('shop_id',$shop_info->id)
                                                                ->where('customer_id',$reservation->customer_id) 
                                                                ->where('start','<',$reservation->start)
                                                                ->whereIn('tag',[1,3,4,5])
                                                                ->count();
                                    if( $check == 0 ) $notice_customer[] = $reservation->customer_id;
                                }
                            }
                            $shop_customer = ShopCustomer::where('shop_id',$shop_info->id)->whereIn('customer_id',$notice_customer)->get();
                        }

                        $this->info( '1 - ' . $notice->id . ' ' . $shop_customer->count() );

                    }else{
                        $continue = true;
                        // $this->info( '2 - ' . $notice->id . ' ' . $shop_customer->count() );
                    }
                    break;
                case 4 : // 預約次數(pro)
                    $shop_customer = [];
                    break;
                case 5 : // 消費金額(pro)
                    $shop_customer = [];
                    break;
            }
                   
            // 時間比對正確才發送
            if( $continue || count($shop_customer) == 0 || ( $notice->send_datetime != NULL && date('H:i') != date('H:i',strtotime($notice->send_datetime)) ) ) continue;

            $insert_lists = [];
            foreach( $shop_customer as $customer ){
                
                // 發送文字內容整理
                $message = $notice->message;
                $coupon  = ShopCoupon::find($notice->shop_coupons);

                $message = str_replace('「"商家名稱"」' , $shop_info->name, $message);
                $message = str_replace('「"會員名稱"」' , $customer->customer_info->realname, $message);
                $message = str_replace('「"下個月份"」' , ( date('n')+1 == 13 ? '01' : (string)date('n')+1 ).'月', $message);

                if( $notice->link ){
                    $message = str_replace('「"連結"」' , ' ' . $notice->link . ' ', $message);
                }else{
                    $message = str_replace('「"連結"」' , '', $message);
                }

                if( $coupon && $coupon->status == 'published' ){
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
                    'shop_award_notice_id' => $notice->id,
                    'shop_customer_id'     => $customer->id,
                    'phone'                => $customer->customer_info->phone,
                    'type'                 => $notice->send_type,
                    'message'              => $message,
                    'created_at'           => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                ];
            }

            ShopManagementCustomerList::insert($insert_lists);

            // 拿出尚未發送的會員
            $management_customers = ShopManagementCustomerList::where('shop_award_notice_id',$notice->id)->where('status','N')->get();

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

                            if ($coupon->get_level == 2) {
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

                switch ($notice->send_type) {
                    case 1: // 手機與line
                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,'','',$notice->id);
                        dispatch($job);

                        // line發送

                        break;

                    case 2: // 手機
                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,'','',$notice->id);
                        dispatch($job);

                        break;

                    case 3: // line

                        break;

                    case 4: // line優先

                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,'','',$notice->id);
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
