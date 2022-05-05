<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use App\Models\CustomerReservation;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopCoupon;
use App\Models\ShopManagement;
use App\Models\ShopManagementRefuse;
use App\Models\ShopManagementCustomerList;
use App\Jobs\SendManagementSms;
use App\Models\CustomerCoupon;
use App\Models\CustomerEvaluate;

class SendNoticeManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send_notice_management';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '發送符合條件的服務通知內容';

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
        // 拿取符合當下時間的訊息｜服務通知資料
        $managements = ShopManagement::whereIn('type',['before','after','back'])->where('use','Y')->get();

        foreach( $managements as $management ){

            if( $management->message == '' || $management->message == null ) continue;

            // 需發送的服務
            $shop_services = $management->match_services->pluck('shop_service_id')->toArray();
            $today         = date('Y-m-d H:i:s');
            $shop_info     = Shop::find($management->shop_id);

            if( empty($shop_services) ) continue;

            // 需拒送訊息通知會員
            $shop_customer_refuse = ShopManagementRefuse::where('shop_id',$management->shop_id)->pluck('shop_customer_id')->toArray();
            $refuse_customer_id = ShopCustomer::whereIn('id',$shop_customer_refuse)->pluck('customer_id')->toArray();

            // 找出需要發送的會員
            if( $management->notice_type == 1 ){
                // 預約前通知
                $customer_resvertions = CustomerReservation::where('shop_id',$management->shop_id)
                                          ->where('start','like',date('Y-m-d',strtotime($today.'+'.$management->notice_day.' day')).'%')
                                          ->whereIn('shop_service_id',$shop_services)
                                          ->where('status','Y')
                                          ->where('cancel_status',NULL)
                                          ->whereNotIn('customer_id',$refuse_customer_id)
                                          ->get();
            }else{
                // 預約後通知
                if( $management->type == 'after' ){
                    // 服務後通知
                    $customer_resvertions = CustomerReservation::where('shop_id',$management->shop_id)
                                          ->whereIn('shop_service_id',$shop_services)
                                          ->where('status','Y')
                                          ->where('cancel_status',NULL)
                                          ->where('tag','!=',2)
                                          ->whereNotIn('customer_id',$refuse_customer_id);

                    switch($management->notice_hour){
                        case 1://一小時
                            $date = date('Y-m-d H:i:00',strtotime($today.'-1 hour'));
                            $customer_resvertions = $customer_resvertions->where('end',$date)->get();
                            break;
                        default: // 隔天|2天以上
                            $day = $management->notice_hour/24;
                            $date = date('Y-m-d',strtotime($today.'-'.$day.' day'));
                            $customer_resvertions = $customer_resvertions->where('start','like',$date.'%')->get();
                            break;
                    }

                }else{
                    // 回訪通知｜訊息通知
                    $customer_resvertions = CustomerReservation::where('shop_id',$management->shop_id)
                                          ->where('start','like',date('Y-m-d',strtotime($today.'-'.$management->notice_day.' day')).'%')
                                          ->whereIn('shop_service_id',$shop_services)
                                          ->where('status','Y')
                                          ->where('cancel_status',NULL)
                                          ->whereNotIn('customer_id',$refuse_customer_id)
                                          ->get();
                }
            }

            // 時間比對正確才發送
            if( $customer_resvertions->count() == 0 ){
                continue;
            }elseif( $customer_resvertions->count() != 0 && $management->notice_hour != 1 && date('H:i') != $management->notice_time ){
                continue;
            }

            $insert_lists = [];
            $old_customer_list = ShopManagementCustomerList::where('shop_management_id',$management->id)->get();
            $category_log = [];

            foreach( $customer_resvertions as $cr ){

                $shop_customer = $shop_customers->where('shop_id',$shop_info->id)->where('customer_id',$cr->customer_id)->first();

                if( $management->notice_cycle == 2 ){
                    // 勾選的服務項目，每款項目只會收到一次通知
                    $continue = false;
                    foreach( $old_customer_list as $ocl ){
                        if( $ocl->shop_customer_id == $shop_customer->id && preg_match('/'.$cr->service_info->id.'/i',$ocl->shop_services) ){
                            $continue = true;
                            break;
                        }
                    }
                    
                    if( $continue ) continue;

                }elseif( $management->notice_cycle == 3 ){
                    // 每款大分類，只會收到一次通知
                    $continue = false;
                    foreach( $old_customer_list as $ocl ){
                        if( $ocl->shop_customer_id == $shop_customer->id && preg_match('/'.$cr->service_info->id.'/i',$ocl->shop_services) ){
                            // $category_log[$cr->customer_id][] = $cr->service_info->category_info->id;
                            $continue = true;
                            break;
                        }
                    }
                    
                    if( $continue ){
                        // 有在列表內找到符合的服務，記錄服務的分類
                        // 記錄分類
                        if( !in_array($cr->service_info->category_info->id,$category_log[$cr->customer_id]) ){
                            $category_log[$cr->customer_id][] = $cr->service_info->category_info->id;
                        }
                        continue;
                    }else{
                        // 有符合訊息通知內設定的服務，但沒有在列表內
                        if( in_array($cr->service_info->category_info->id,$category_log[$cr->customer_id]) ){
                            continue;
                        }else{
                            $category_log[$cr->customer_id][] = $cr->service_info->category_info->id;
                        }
                    }
                }

                // 發送文字內容整理
                $message = $management->message;
                $coupon  = ShopCoupon::find($management->shop_coupons);

                $message = str_replace('「"商家名稱"」' , $shop_info->name, $message);
                $message = str_replace('「"會員名稱"」' , $cr->customer_info->realname, $message);
                $message = str_replace('「"服務名稱"」' , $cr->service_info->name, $message);
                $message = str_replace('「"預約時間"」' , substr($cr->start, 11,5), $message);
                $message = str_replace('「"預約日期"」' , substr($cr->start, 5,5), $message);
                $message = str_replace('「"隔月"」' , ( date('n')+1 == 13 ? '01' : (string)date('n')+1 ).'月', $message);
                $message = str_replace('「"當月"」' , (string)(date('n')).'月', $message);

                // 再次預約的連結
                $url = '/store/'.$shop_info->alias.'/reservation/again/'.$cr->id;
                $transform_url_code = Controller::get_transform_url_code($url);
                $message = str_replace('「"再次預約的連結"」' , ' ' . env('SHILIPAI_WEB').'/T/'.$transform_url_code . ' ', $message);

                // 問卷模組
                $url = '/s/'.$shop_info->alias.'/c/'.$cr->customer_info->id.'/q/'.$management->id;
                $transform_url_code = Controller::get_transform_url_code($url);
                $message = str_replace('「"問卷模組"」' , ' ' . env('SHILIPAI_WEB').'/T/'.$transform_url_code . ' ' , $message);

                if( $management->link ){
                    $message = str_replace('「"連結"」' , ' ' . $management->link . ' ', $message);
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
                                $customer_coupon = CustomerCoupon::where('customer_id', $cr->customer_id)
                                                                ->where('shop_id',$shop_info->id)
                                                                ->where('shop_coupon_id',$coupon->id)
                                                                ->first();
                                if( $customer_coupon ) $add = false;
                            }
                        }

                        if( $add ){
                            // 將該優惠券直接寫入會員裡
                            $customer_coupon = new CustomerCoupon;
                            $customer_coupon->customer_id    = $cr->customer_id;
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

                // 有勾選服務評價
                if( $management->evaluate == 'Y' ){
                    // 建立顧客要填寫的服務評價
                    $customer_evaluate = new CustomerEvaluate();
                    $customer_evaluate->customer_id             = $cr->customer_id;
                    $customer_evaluate->company_id              = $cr->company_id;
                    $customer_evaluate->shop_id                 = $cr->shop_id;
                    $customer_evaluate->customer_reservation_id = $cr->id;
                    $customer_evaluate->save();

                    $url = '/s/'.$shop_info->alias.'/e/'.$customer_evaluate->id;
                    $transform_url_code = Controller::get_transform_url_code($url);
                    $message .= env('SHILIPAI_WEB').'/T/'.$transform_url_code;
                }

                // 建立要寫入推廣的顧客列表
                $insert_lists[] = [
                    'shop_id'            => $shop_info->id,
                    'shop_management_id' => $management->id,
                    'shop_customer_id'   => $shop_customer->id,
                    'phone'              => $cr->customer_info->phone,
                    'type'               => $management->send_type,
                    'message'            => $message,
                    'shop_services'      => $cr->service_info->id,
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s'),
                ];
            }

            ShopManagementCustomerList::insert($insert_lists);

            // 拿出尚未發送的會員
            $management_customers = ShopManagementCustomerList::where('shop_management_id',$management->id)->where('status','N')->get();
            foreach( $management_customers as $customer ){
                // 被刪除的會員不用發送
                if( !$customer->customer_info ) continue;
                // 沒有電話的不用發送
                if( !$customer->phone ) continue;

                switch ($management->send_type) {
                    case 1: // 手機與line
                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,$management->id,'');
                        dispatch($job);

                        // line發送

                        break;

                    case 2: // 手機
                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,$management->id,'');
                        dispatch($job);

                        break;

                    case 3: // line

                        break;

                    case 4: // line優先

                        // 手機發送
                        $job = new SendManagementSms($customer,$customer->message,$shop_info,$management->id,'');
                        dispatch($job);

                        break;
                }
            }

            $management->status = 'Y';
            $management->save();

            $this->info($management->name . ' 已發送');
        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('發送符合條件的訊息通知內容完成'.( $end - $start ));
    }
}
