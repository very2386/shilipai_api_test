<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerReservation;
use App\Models\ShopReservationTag;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerReservationTag;
use App\Models\ShopSet;

class ResetReservationTag extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset_reservation_tag';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每天重置預約標籤';

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
        
        ShopCustomerReservationTag::truncate();

        // 預約通知與預約標籤轉換
        $tag_arr = [ '1' => '' , '2' => 4 , '3' => 2 , '4' => 3 , '5' => 1 ];

        $shops = Shop::get();
        foreach( $shops as $shop ){
            // 商家顧客
            $shop_customers   = ShopCustomer::where('shop_id',$shop->id)->get();
            // 商家設定
            $shop_set         = $shop->shop_set;
            // 要計算的預約標籤資料
            $reservation_tags = ShopReservationTag::where('shop_id',$shop->id)->orderBy('type','ASC')->orderBy('times','ASC')->get();

            foreach( $shop_customers as $sc ){
                $customer_reservations = CustomerReservation::where('shop_id',$shop->id)
                                                            ->where('customer_id',$sc->customer_id)
                                                            ->where('status','Y')
                                                            ->where('created_at','>=',date('Y-m-d',strtotime('-'.$shop_set->tag_during.' month')))->where('cancel_status',NULL)
                                                            ->get();

                // 將顧客的預約標籤分類
                $tag_type_count = [ 
                    '1' => $customer_reservations->where('tag',5)->count(), // 提早
                    '2' => $customer_reservations->where('tag',3)->count(), // 小遲到
                    '3' => $customer_reservations->where('tag',4)->count(), // 大遲到
                    '4' => $customer_reservations->where('tag',2)->count()  // 爽約
                ];
                
                foreach( $reservation_tags->groupBy('type') as $type => $tags ){
                    foreach( $tags as $tag ){
                        // 有設定才要計算
                        if( $tag->name != NULL && $tag->times != NULL ){
                            if( $tag_type_count[$type] >= $tag->times ){
                                // 記錄標籤
                                $shop_customer_tags = new ShopCustomerReservationTag;
                                $shop_customer_tags->shop_id                 = $shop->id;
                                $shop_customer_tags->shop_customer_id        = $sc->id;
                                $shop_customer_tags->shop_reservation_tag_id = $tag->id;
                                $shop_customer_tags->name                    = $tag->name;
                                $shop_customer_tags->times                   = $tag_type_count[$type];
                                $shop_customer_tags->blacklist               = $tag->blacklist;
                                $shop_customer_tags->save();
                            }
                        }
                    }
                }
            }
        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('每天重置預約標籤完成'.( $end - $start ));
    }
}
