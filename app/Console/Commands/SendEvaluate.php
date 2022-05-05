<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\CustomerReservation;
use App\Models\CustomerEvaluate;
use App\Models\ShopEvaluate;
use App\Models\Shop;
use App\Jobs\SendSms;


class SendEvaluate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send_evaluate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '發送符合條件的服務評價';

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

        $evaluates = ShopEvaluate::get();

        foreach( $evaluates as $evaluate ){
            // 找出已經完成的服務
            $customer_reservations = CustomerReservation::where('shop_id',$evaluate->shop_id)
                                                        ->where('status','Y')
                                                        ->where('start','like',date('Y-m-d H:i',strtotime(date('Y-m-d H:i:s').'-'.$evaluate->hour.' hour')).'%')
                                                        // ->where('start','2021-10-30 14:30%')
                                                        ->whereIn('shop_service_id',explode(',',$evaluate->shop_services))
                                                        ->where('cancel_status',NULL)
                                                        ->get();
            $shop_info = Shop::find($evaluate->shop_id);

            foreach( $customer_reservations as $cr ){

                // 建立顧客要填寫的服務評價
                $customer_evaluate = new CustomerEvaluate;
                $customer_evaluate->customer_id             = $cr->customer_id;
                $customer_evaluate->company_id              = $cr->company_id;
                $customer_evaluate->shop_id                 = $cr->shop_id;
                $customer_evaluate->customer_reservation_id = $cr->id;
                $customer_evaluate->save();

                $sendword = "感謝您 " . substr( $cr->start , 0 , 10 ) . " 蒞臨 " 
                           . $shop_info->name . " 消費，為能提供更好的服務給您，希望能撥空回饋建議給我們喔！"
                           .env('SHILIPAI_WEB').'/s/'.$shop_info->alias.'/e/'.$customer_evaluate->id;

                switch ($evaluate->send_type) {
                    case 1: // 手機與line
                        // 手機發送
                        $job = new SendSms($cr->customer_info->phone,$sendword,$shop_info);
                        dispatch($job);

                        // line發送

                        break;

                    case 2: // 手機
                        // 手機發送
                        $job = new SendSms($cr->customer_info->phone,$sendword,$shop_info);
                        dispatch($job);

                        break;

                    case 3: // line

                        break;

                    case 4: // line優先

                        // 手機發送
                        $job = new SendSms($cr->customer_info->phone,$sendword,$shop_info);
                        dispatch($job);

                        break;
                }
            }
        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('完成，耗時：'.( $end - $start ));
    }
}
