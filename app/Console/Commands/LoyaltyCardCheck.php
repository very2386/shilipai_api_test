<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Controller;
use App\Models\CustomerLoyaltyCard;
use App\Models\Shop;
use App\Jobs\SendSms;

class LoyaltyCardCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loyalty_card_check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '檢查集點卡失效提醒';

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

        $customer_cards = CustomerLoyaltyCard::where('status','N')->get();
        foreach( $customer_cards as $card ){
            $card_info     = $card->loyalty_card_info;
            $shop_info     = Shop::find($card->shop_id);
            $customer_info = $card->customer_info;

            if( $card_info->notice_day != 0 ){

                if( $card->last_point != 0 ){
                    // 沒集滿快過期提醒
                    if( $card_info->deadline_type != 1 ){
                        // 有效期限1無期限4
                        switch ( $card_info->deadline_type ) {
                            case 2:
                                // 顧客獲得當日
                                $deadline = date('Y-m-d' , strtotime($card->created_at."+".$card_info->year." year +".$card_info->month." month") );
                                break;
                            case 3:
                                // 最後一次集點
                                $deadline = date('Y-m-d' , strtotime($card->created_at."+".$card_info->year." year +".$card_info->month." month") );
                                if( count($card->point_log) ){
                                    $deadline = date('Y-m-d' , strtotime($card->point_log->last()->created_at."+".$card_info->year." year +".$card_info->month." month") );
                                }
                                break;
                            case 4:
                                // 統一起迄
                                $deadline = $card_info->end_date;
                                break;
                        }

                        if( strtotime($deadline) - strtotime(date('Y-m-d')) == $card_info->notice_day * 86400 ){
                            $sendword = '提醒您，集點卡將於'.$deadline.'失效喔！';
                            if( $customer_info->phone ){
                                $job = new SendSms($customer_info->phone,$sendword,$shop_info);
                                dispatch($job);
                            }
                        }
                    }
                    
                }else{
                    // 集滿點數使用快過期
                    if( $card_info->discount_limit_month != 0 ){
                        
                        $deadline = date('Y-m-d',strtotime($card->point_log->last()->created_at.'+'.$card_info->discount_limit_month.'month'));
                        if( strtotime($deadline) - strtotime(date('Y-m-d')) == $card_info->discount_limit_month * 30 * 86400 ){
                            $sendword = '提醒您，集點卡「'.$card_info->name.'」將於'.$deadline.'失效，建議儘快使用喔！';
                            if( $customer_info->phone ){
                                $job = new SendSms($customer_info->phone,$sendword,$shop_info);
                                dispatch($job);
                            }
                        }
                    }

                }
            }

        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('檢查集點卡失效提醒完成'.( $end - $start ));

    }
}
