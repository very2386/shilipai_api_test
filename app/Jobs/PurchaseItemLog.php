<?php

namespace App\Jobs;

use App\Models\BillPuchaseItem;
use App\Models\CustomerMembershipCard;
use App\Models\CustomerProgram;
use App\Models\CustomerProgramGroup;
use App\Models\CustomerProgramLog;
use App\Models\CustomerTopUp;
use App\Models\CustomerTopUpLog;
use App\Models\Shop;
use App\Models\ShopMembershipCard;
use App\Models\ShopProductLog;
use App\Models\ShopProgram;
use App\Models\ShopTopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurchaseItemLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bill, $purchase_item, $shop_customer;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($bill, $purchase_item, $shop_customer)
    {
        $this->bill          = $bill;
        $this->purchase_item = $purchase_item;
        $this->shop_customer = $shop_customer;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $bill          = $this->bill;
        $purchase_item = $this->purchase_item;
        $shop_customer = $this->shop_customer;
        $shop_info     = Shop::find($bill->shop_id);

        BillPuchaseItem::where('bill_id', $bill->id)->delete();
        foreach ($purchase_item as $item) {
            for ($i = 0; $i < $item['count']; $i++) {
                if ($item['type'] == '儲值') {
                    $top_up = ShopTopUp::find($item['item']['id']);
                    $customer_top_up = new CustomerTopUp();
                    $customer_top_up->customer_id    = $shop_customer->customer_id;
                    $customer_top_up->bill_id        = $bill->id;
                    $customer_top_up->company_id     = $shop_info->company_info->id;
                    $customer_top_up->shop_id        = $shop_info->id;
                    $customer_top_up->shop_top_up_id = $top_up->id;
                    $customer_top_up->first_price    = $top_up->price;
                    $customer_top_up->last_price     = $top_up->price;
                    $customer_top_up->save();

                    $log = new CustomerTopUpLog;
                    $log->customer_id         = $shop_customer->customer_id;
                    $log->company_id          = $shop_info->company_info->id;
                    $log->shop_id             = $shop_info->id;
                    $log->customer_top_up_id  = $customer_top_up->id;
                    $log->shop_top_up_role_id = NULL;
                    $log->bill_id             = $bill->id;
                    $log->type                = 1;
                    $log->price               = $top_up->price;
                    $log->shop_staff_id       = $item['shop_staff_id'];
                    $log->save();

                    // 儲存儲值贈送部分
                    foreach ($top_up->roles as $role) {
                        //儲值金類型1贈送儲值2折扣3贈品4免費 1購買2手動調整3使用 4轉出 5轉入 6贈送 7贈品 8免費
                        $type = 6;
                        if ($role->type == 3)     $type = 7;
                        elseif ($role->type == 4) $type = 8;

                        $log = new CustomerTopUpLog;
                        $log->customer_id         = $shop_customer->customer_id;
                        $log->company_id          = $shop_info->company_info->id;
                        $log->shop_id             = $shop_info->id;
                        $log->customer_top_up_id  = $customer_top_up->id;
                        $log->shop_top_up_role_id = $role->id;
                        $log->bill_id             = $bill->id;
                        $log->type                = $type;
                        $log->price               = $type == 6 ? $role->price : NULL;
                        $log->shop_staff_id       = $item['shop_staff_id'];
                        $log->save();
                    }
                } elseif ($item['type'] == '方案') {
                    $shop_program = ShopProgram::find($item['item']['id']);

                    $customer_program = CustomerProgram::where('customer_id', $shop_customer->customer_id)
                        ->where('shop_id', $shop_info->id)
                        ->where('shop_program_id', $item['item']['id'])
                        ->first();
                    if (!$customer_program) {
                        // 沒買過此方案
                        $customer_program = new CustomerProgram;
                        $customer_program->customer_id     = $shop_customer->customer_id;
                        $customer_program->shop_id         = $shop_info->id;
                        $customer_program->company_id      = $shop_info->company_info->id;
                        $customer_program->shop_program_id = $item['item']['id'];
                        $customer_program->price           = $item['item']['price'];
                        $customer_program->bill_id         = $bill->id;
                        $customer_program->save();

                        foreach ($shop_program->groups as $group) {
                            $customer_program_group = new CustomerProgramGroup;
                            $customer_program_group->customer_program_id   = $customer_program->id;
                            $customer_program_group->shop_program_group_id = $group->id;
                            $customer_program_group->count                 = $group->count;
                            $customer_program_group->last_count            = $group->count;
                            $customer_program_group->save();

                            $log = new CustomerProgramLog;
                            $log->customer_program_id       = $customer_program->id;
                            $log->customer_program_group_id = $customer_program_group->id;
                            $log->bill_id                   = $bill->id;
                            $log->count                     = $group->count;
                            $log->type                      = 1;
                            $log->shop_staff_id             = $item['shop_staff_id'];
                            $log->save();
                        }
                    } else {
                        // 已購買過此方案
                        foreach ($customer_program->groups as $group) {
                            $group->last_count += $group->group_info->count;
                            $group->save();

                            $log = new CustomerProgramLog;
                            $log->customer_program_id       = $customer_program->id;
                            $log->customer_program_group_id = $group->id;
                            $log->bill_id                   = $bill->id;
                            $log->count                     = $group->count;
                            $log->type                      = 1;
                            $log->shop_staff_id             = $item['shop_staff_id'];
                            $log->save();
                        }
                    }
                } elseif ($item['type'] == '會員卡') {
                    $shop_membership_card = ShopMembershipCard::find($item['item']['id']);

                    $customer_membership_card = new CustomerMembershipCard;
                    $customer_membership_card->customer_id             = $shop_customer->customer_id;
                    $customer_membership_card->shop_id                 = $shop_info->id;
                    $customer_membership_card->company_id              = $shop_info->company_info->id;
                    $customer_membership_card->shop_membership_card_id = $shop_membership_card->id;
                    $customer_membership_card->bill_id                 = $bill->id;
                    $customer_membership_card->shop_staff_id           = $item['shop_staff_id'];
                    $customer_membership_card->save();
                } elseif ($item['type'] == '產品') {
                    // 寫入產品進出記錄
                    $product_log = new ShopProductLog;
                    $product_log->shop_id         = $shop_info->id;
                    $product_log->bill_id         = $bill->id;
                    $product_log->shop_product_id = $item['item']['id'];
                    $product_log->category        = 'buy';
                    $product_log->commodity_id    = '';
                    $product_log->count           = -1 * $item['count'];
                    $product_log->shop_staff_id   = $bill->shop_staff_id;
                    $product_log->save();
                }
            }

            // 記錄購買項目
            // 先處理項目類別
            $bill_puchase_items = new BillPuchaseItem;
            $bill_puchase_items->shop_id        = $shop_info->id;
            $bill_puchase_items->company_id     = $shop_info->company_info->id;
            $bill_puchase_items->bill_id        = $bill->id;
            $bill_puchase_items->shop_staff_id  = $item['shop_staff_id'];
            $bill_puchase_items->count          = $item['count'];
            $bill_puchase_items->price          = $item['item']['price'];

            if ($item['type'] == '服務') {
                $bill_puchase_items->type            = 'service';
                $bill_puchase_items->shop_service_id = $item['item']['id'];
            } elseif ($item['type'] == '產品') {
                $bill_puchase_items->type            = 'product';
                $bill_puchase_items->shop_service_id = $item['item']['id'];
            } elseif ($item['type'] == '加值服務') {
                $bill_puchase_items->type            = 'advance';
                $bill_puchase_items->shop_service_id = $item['item']['id'];
            } elseif ($item['type'] == '方案') {
                $bill_puchase_items->type            = 'program';
                $bill_puchase_items->shop_program_id = $item['item']['id'];
            } elseif ($item['type'] == '儲值') {
                $bill_puchase_items->type           = 'top_up';
                $bill_puchase_items->shop_top_up_id = $item['item']['id'];
            } elseif ($item['type'] == '會員卡') {
                $bill_puchase_items->type                    = 'card';
                $bill_puchase_items->shop_membership_card_id = $item['item']['id'];
            } elseif ($item['type'] == '定金') {
                $bill_puchase_items->type = 'price';
            } elseif ($item['type'] == '自訂') {
                $bill_puchase_items->type = 'customize';
            }

            $bill_puchase_items->save();
        }
    }
}
