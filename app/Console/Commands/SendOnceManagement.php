<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerReservation;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerTag;
use App\Models\ShopCoupon;
use App\Models\ShopManagement;
use App\Models\ShopManagementMode;
use App\Models\ShopManagementRefuse;
use App\Models\ShopManagementCustomerList;
use App\Models\ShopServiceCategory;
use App\Models\ShopService;
use App\Models\ShopServiceStaff;
use App\Models\SystemNotice;
use App\Jobs\SendManagementSms;

class SendOnceManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send_once_management';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '發送符合條件的單次推廣內容';

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

        // 拿取符合當下時間的單次推廣資料
        $managements = ShopManagement::where('type','once')->where('send_datetime','like',date('Y-m-d H:i').'%')->get();
        // $managements = ShopManagement::where('type','once')->get();

        foreach( $managements as $management ){
            $management_customers = $management->customer_lists;

            $shop_info = Shop::find($management->shop_id);

            // 發送文字內容整理
            $message = $management->message;
            $coupon  = ShopCoupon::find($management->shop_coupons);

            $message = str_replace('「"商家名稱"」' , $shop_info->name, $message);
            $message = str_replace('「"下個月份"」' , date('n')+1, $message);

            if( $management->link ){
                $message = str_replace('「"連結"」' , $management->link, $message);
            }else{
                $message = str_replace('「"連結"」' , '', $message);
            }

            if( $coupon ){
                // 建立縮短網址
                $url = '/show/'.$shop_info->alias.'/coupon/'.$coupon->id;
                $transform_url_code = Controller::get_transform_url_code($url);

                $message = str_replace('「"優惠券"」' , env('SHIPILAI_WEB').'/T/'.$transform_url_code, $message);
            }else{
                $message = str_replace('「"優惠券"」' , '', $message);
            }

            // $send_buy_sms_notice = false;
            foreach( $management_customers as $customer ){

                // 被刪除的會員不用發送
                if( !$customer->customer_info ) continue;

                // 發送文字
                $sendword = str_replace('「"會員名稱"」' , $customer->customer_info->realname, $message);

                $customer->message = $sendword;
                $customer->save();

                // 判斷是否有足夠的簡訊數量
                // $message_count = (int)ceil(mb_strlen($sendword,'utf-8')/70);
                // if( $shop_info->gift_sms + $shop_info->buy_sms < $message_count ){
                //     // 代表簡訊數量不夠
                //     $send_buy_sms_notice = true;
                // }

                switch ($management->send_type) {
                    case 1: // 手機與line
                        // 手機發送
                        $job = new SendManagementSms($customer,$sendword,$shop_info,$management->id,'','');
                        dispatch($job);

                        // line發送

                        break;

                    case 2: // 手機
                        // 手機發送
                        $job = new SendManagementSms($customer,$sendword,$shop_info,$management->id,'','');
                        dispatch($job);

                        break;

                    case 3: // line

                        break;

                    case 4: // line優先

                        // 手機發送
                        $job = new SendManagementSms($customer,$sendword,$shop_info,$management->id,'','');
                        dispatch($job);

                        break;
                }
            }

            $management->status = 'Y';
            $management->save();

            // // 發送系統簡訊不足通知
            // if( $send_buy_sms_notice ){
            //     $url_data = [
            //         [
            //             'text' => '購買簡訊 GO>',
            //             'url'  => '/storeData/contract',
            //         ],
            //     ];
            //     $notice = new SystemNotice;
            //     $notice->company_id = $shop_info->company_info->id;
            //     $notice->shop_id    = $shop_info->id;
            //     $notice->content    = '簡訊剩餘不足囉，無法發送推廣簡訊';
            //     $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
            //     $notice->save();
            // }
        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('發送符合條件的單次推廣內容完成'.( $end - $start ));
    }
}
