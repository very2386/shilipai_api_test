<?php

namespace App\Jobs;

use App\Models\CustomerLoyaltyCard;
use App\Models\CustomerLoyaltyCardPoint;
use App\Models\Shop;
use App\Models\ShopLoyaltyCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoyaltyCardPointLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bill, $give_point;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($bill,$give_point)
    {
        $this->bill       = $bill;
        $this->give_point = $give_point;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $bill       = $this->bill;
        $give_point = $this->give_point;

        $customer_card = CustomerLoyaltyCard::find($give_point['customer_loyalty_card_id']);
        $card_info = ShopLoyaltyCard::where('shop_loyalty_cards.id', $customer_card->shop_loyalty_card_id)->first();
        $shop_info = Shop::find($card_info->shop_id);
        if ($customer_card->last_point < $give_point['point']) {
            // 溢點
            $over_point = $give_point['point'] - $customer_card->last_point;
            // 第一張還需要幾點集滿
            $last_point = $customer_card->last_point;

            // 先補滿最後一張點數記錄
            $card_point = new CustomerLoyaltyCardPoint;
            $card_point->customer_loyalty_card_id = $customer_card->id;
            $card_point->point                    = $customer_card->last_point;
            $card_point->bill_id                  = $bill->id;
            $card_point->save();

            // 將原卡片剩餘點數修改為0
            $customer_card->last_point = 0;
            $customer_card->save();

            // 需檢查是否還有同種卡片且未集滿點數的
            $same_cards = CustomerLoyaltyCard::where('customer_id', $customer_card->customer_id)
                ->where('shop_loyalty_card_id', $customer_card->shop_loyalty_card_id)
                ->where('shop_id', $customer_card->shop_id)
                ->where('status', 'N')
                ->where('last_point', '!=', 0)
                ->get();

            foreach ($same_cards as $sc) {
                $card_point = new CustomerLoyaltyCardPoint;
                $card_point->customer_loyalty_card_id = $sc->id;

                if ($over_point > $sc->last_point) {
                    // 超過需要補足的卡片
                    $card_point->point   = $sc->last_point;
                    $card_point->bill_id = $bill->id;
                    $card_point->save();

                    // 將補足卡片剩餘點數修改為0
                    $sc->last_point = 0;
                    $sc->save();

                    $over_point = $over_point - $sc->last_point;
                } else {
                    // 沒超過需要補足的卡片
                    $card_point->point   = $over_point;
                    $card_point->bill_id = $bill->id;
                    $card_point->save();

                    // 將補足卡片剩餘點數修改
                    $sc->last_point = $sc->last_point - $over_point;
                    $sc->save();

                    // 補足完後直接歸0
                    $over_point = -1;
                    break;
                }
            }
            if ($over_point >= $card_info->full_point) {
                $card_count = (int)floor($over_point / $card_info->full_point);

                // 製作集滿點數的卡片
                for ($i = 1; $i <= $card_count; $i++) {
                    // 新建立多張集點卡
                    $word = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';   //亂數內容
                    $len  = strlen($word);
                    $rand = '';
                    for ($y = 0; $y < 4; $y++) {
                        $rand .= $word[rand() % $len];
                    }
                    $new_card = new CustomerLoyaltyCard;
                    $new_card->customer_id          = $customer_card->customer_id;
                    $new_card->company_id           = $customer_card->company_id;
                    $new_card->shop_id              = $customer_card->shop_id;
                    $new_card->shop_loyalty_card_id = $customer_card->shop_loyalty_card_id;
                    $new_card->card_no              = $shop_info->alias . str_pad($customer_card->customer_id, 4, "0", STR_PAD_LEFT)  . $rand;
                    $new_card->full_point           = $card_info->full_point;
                    $new_card->last_point           = $over_point > $card_info->full_point ? 0 : $card_info->full_point - $over_point;
                    $new_card->save();

                    // 記錄集點卡點數
                    $card_point = new CustomerLoyaltyCardPoint;
                    $card_point->customer_loyalty_card_id = $new_card->id;
                    $card_point->point                    = $over_point > $card_info->full_point ? $card_info->full_point : $over_point;
                    $card_point->bill_id                  = $bill->id;
                    $card_point->save();

                    if ($over_point > $card_info->full_point) {
                        $over_point -= $card_info->full_point;
                    }
                }
            }

            // 剩餘點數
            $remaining = $over_point;
        } else {
            // 剛好集滿/沒有溢點
            $last_point = $customer_card->last_point;

            // 將修改原卡片剩餘點數
            $customer_card->last_point = $customer_card->last_point - $give_point['point'];
            $customer_card->save();

            // 先寫入點數記錄
            $card_point = new CustomerLoyaltyCardPoint;
            $card_point->customer_loyalty_card_id = $customer_card->id;
            $card_point->point                    = $give_point['point'];
            $card_point->bill_id                  = $bill->id;
            $card_point->save();

            // 剩餘點數
            $remaining = $give_point['point'] - $last_point;
        }

        // 記錄集點卡點數
        if ($remaining >= 0) {
            // 新建集點卡
            $word = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';   //亂數內容
            $len  = strlen($word);
            $rand = '';
            for ($i = 0; $i < 4; $i++) {
                $rand .= $word[rand() % $len];
            }

            $new_card = new CustomerLoyaltyCard;
            $new_card->customer_id          = $customer_card->customer_id;
            $new_card->company_id           = $customer_card->company_id;
            $new_card->shop_id              = $customer_card->shop_id;
            $new_card->shop_loyalty_card_id = $customer_card->shop_loyalty_card_id;
            $new_card->card_no              = $shop_info->alias . str_pad($customer_card->customer_id, 4, "0", STR_PAD_LEFT)  . $rand;
            $new_card->full_point           = $card_info->full_point;
            $new_card->last_point           = $card_info->full_point - $remaining;
            $new_card->save();

            if ($remaining != 0) {
                $card_point = new CustomerLoyaltyCardPoint;
                $card_point->customer_loyalty_card_id = $new_card->id;
                $card_point->point                    = $remaining;
                $card_point->bill_id                  = $bill->id;
                $card_point->save();
            }
        }
    }
}
