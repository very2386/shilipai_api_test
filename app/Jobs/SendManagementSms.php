<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\ShopManagementCustomerList;
use App\Models\SystemNotice;
use App\Models\MessageLog;
use App\Models\Customer;
use App\Models\ShopCustomer;

class SendManagementSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sendword,$customer,$shop_info,$management_id,$festival_id,$award_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($customer,$sendword,$shop_info,$management_id,$festival_id="",$award_id="")
    {
        $this->customer      = $customer;
        $this->sendword      = $sendword;
        $this->shop_info     = $shop_info;
        $this->management_id = $management_id;
        $this->festival_id   = $festival_id;
        $this->award_id      = $award_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $customer      = $this->customer;
        $sendword      = $this->sendword;
        $shop_info     = $this->shop_info;
        $management_id = $this->management_id;
        $festival_id   = $this->festival_id;
        $award_id      = $this->award_id;

        $shop_customer = ShopCustomer::where('id',$customer->shop_customer_id)->first();
        $customer_info = Customer::where('id',$shop_customer->customer_id)->first();

        // 先確認商家是否有足夠的簡訊發送數量
        $message_count = (int)ceil(mb_strlen($sendword,'utf-8')/70);
        $company = $shop_info->company_info;
        if( $shop_info->gift_sms + $shop_info->buy_sms < $message_count ){
            // 餘額不足，先寫入簡訊發送記錄
            $log = new MessageLog;
            $log->company_id              = $company->id;
            $log->shop_id                 = $shop_info->id;
            $log->phone                   = $customer_info->phone;
            $log->content                 = '發送推廣簡訊，簡訊剩餘不足';
            $log->shop_management_id      = $management_id?:NULL;
            $log->shop_festival_notice_id = $festival_id?:NULL;
            $log->shop_award_notice_id    = $award_id?:NULL;
            $log->use                     = 0;
            $log->save();

            // 修改推廣顧客列表的發送狀態
            if( $management_id != '' || $management_id != NULL ){
                // 服務通知
                ShopManagementCustomerList::where('shop_customer_id',$customer->shop_customer_id)
                                        ->where('shop_management_id',$management_id)
                                        ->update([
                                            'status' => 'N',
                                            'sms'    => 'N',
                                        ]);
            }elseif( $festival_id != '' || $festival_id != NULL ){
                // 節慶通知
                ShopManagementCustomerList::where('shop_customer_id',$customer->shop_customer_id)
                                        ->where('shop_festival_notice_id',$festival_id)
                                        ->update([
                                            'status' => 'N',
                                            'sms'    => 'N',
                                        ]);
            }else{
                // 獎勵通知
                ShopManagementCustomerList::where('shop_customer_id',$customer->shop_customer_id)
                                        ->where('shop_award_notice_id',$award_id)
                                        ->update([
                                            'status' => 'N',
                                            'sms'    => 'N',
                                        ]);
            }
            
            if( $shop_info->sms_notice == 0 ){
                $url_data = [
                    [
                        'text' => '購買簡訊 GO>',
                        'url'  => '/storeData/contract',
                    ],
                ];
                $notice = new SystemNotice;
                $notice->company_id = $company->id;
                $notice->shop_id    = $shop_info->id;
                $notice->content    = '發送推廣簡訊，簡訊剩餘不足囉';
                $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
                $notice->save();

                $shop_info->sms_notice = 2;
                $shop_info->save();
            }

            exit();
        }

        // 先取得剩餘簡訊數
        $url   = 'http://smsapi.mitake.com.tw/api/mtk/SmQuery'; 
        $data  = 'username='.config('services.phone.username');
        $data .= '&password='.config('services.phone.password');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);
        $before = (int)explode("=",$output)[1];
        
        // url
        $url  = 'http://smsapi.mitake.com.tw/api/mtk/SmSend'; 
        $url .= '?CharsetURL=UTF-8';
        
        // parameters
        $data  = 'username='.config('services.phone.username'); 
        $data .= '&password='.config('services.phone.password');
        $data .= '&dstaddr='.$customer_info->phone; 
        $data .= '&smbody='.$sendword;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);

        // 先取得剩餘簡訊數
        $url  = 'http://smsapi.mitake.com.tw/api/mtk/SmQuery'; 
        $data = 'username='.config('services.phone.username');
        $data .= '&password='.config('services.phone.password');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);

        $after = (int)explode("=",$output)[1];

        $use   = $before - $after;

        if( !empty($shop_info) ){
            $company = $shop_info->company_info;
            // 扣除簡訊
            if( $shop_info->gift_sms >= $use ){
                $shop_info->gift_sms = $shop_info->gift_sms - $use;
            }else{
                // 先將贈送的簡訊扣光
                $used = $use - $shop_info->gift_sms;
                if( $shop_info->gift_sms != 0 ){
                    $shop_info->gift_sms = 0 ;    
                }

                $shop_info->buy_sms = $shop_info->buy_sms - $used;
            }
            $shop_info->save();

            // 簡訊不足50 20則通知
            if( $shop_info->buy_sms + $shop_info->gift_sms < 50 || $shop_info->buy_sms + $shop_info->gift_sms < 20 ){
                // 將shop的sms_notice狀態修改
                $sys_notice = false;
                if( $shop_info->buy_sms + $shop_info->gift_sms < 50 
                        && $shop_info->buy_sms + $shop_info->gift_sms >= 20 
                        && $shop_info->sms_notice == 0 ){
                    $shop_info->sms_notice = 1;
                    $shop_info->save(); 
                    $sys_notice = true;  
                }elseif( $shop_info->buy_sms + $shop_info->gift_sms < 20 && $shop_info->sms_notice != 0 ){
                    $shop_info->sms_notice = 2;
                    $shop_info->save(); 
                    $sys_notice = true;  
                }

                if( $sys_notice ){
                    $url_data = [
                        [
                            'text' => '購買簡訊 GO>',
                            'url'  => '/storeData/contract',
                        ],
                    ];
                    $notice = new SystemNotice;
                    $notice->company_id = $company->id;
                    $notice->shop_id    = $shop_info->id;
                    $notice->content    = '簡訊剩餘不足「'.( $shop_info->buy_sms + $shop_info->gift_sms < 20 ? 20 : 50 ).'則」囉！';
                    $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
                    $notice->save();
                } 
            }

            // 簡訊發送記錄
            $log = new MessageLog;
            $log->company_id              = $company->id;
            $log->shop_id                 = $shop_info->id;
            $log->phone                   = $customer_info->phone;
            $log->content                 = $sendword;
            $log->use                     = $use;
            $log->shop_management_id      = $management_id ?:NULL;
            $log->shop_festival_notice_id = $festival_id?:NULL;
            $log->shop_award_notice_id    = $award_id?:NULL;
            $log->save();

            // 修改推廣顧客列表的發送狀態
            if( $management_id != '' || $management_id != NULL ){
                // 服務通知
                ShopManagementCustomerList::where('shop_customer_id',$customer->shop_customer_id)
                                        ->where('shop_management_id',$management_id)
                                        ->update([
                                            'status' => 'Y',
                                            'sms'    => $use != 0 ? 'Y' : 'F',
                                        ]);
            }elseif( $festival_id != '' || $festival_id != NULL ){
                // 節慶通知
                ShopManagementCustomerList::where('shop_customer_id',$customer->shop_customer_id)
                                        ->where('shop_festival_notice_id',$festival_id)
                                        ->update([
                                            'status' => 'Y',
                                            'sms'    => $use != 0 ? 'Y' : 'F',
                                        ]);
            }else{
                // 獎勵通知
                ShopManagementCustomerList::where('shop_customer_id',$customer->shop_customer_id)
                                        ->where('shop_award_notice_id',$award_id)
                                        ->update([
                                            'status' => 'Y',
                                            'sms'    => $use != 0 ? 'Y' : 'F',
                                        ]);
            }
        }
    }
}
