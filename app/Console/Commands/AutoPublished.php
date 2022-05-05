<?php

namespace App\Console\Commands;

use App\Models\ShopMembershipCard;
use App\Models\ShopProgram;
use App\Models\ShopTopUp;
use Illuminate\Console\Command;

class AutoPublished extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto_published';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自動上架儲值金、方案、會員卡';

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
        // 儲值金上架
        $shop_top_ups = ShopTopUp::where('status','pending')->where('during_type',2)->get();
        foreach( $shop_top_ups as $top_up ){
            // 販售幾天前自動上架顯示1前一個月2一週3一天4當天
            if ($top_up->show_day == 1){
                $date = strtotime($top_up->start_date) - 30 * 86400;
                if (time() >= $date) $top_up->status = 'published';
            }elseif( $top_up->show_day == 2 ){
                $date = strtotime($top_up->start_date) - 7 * 86400;
                if (time() >= $date) $top_up->status = 'published';
            }elseif( $top_up->show_day == 3 ){
                $date = strtotime($top_up->start_date) - 1 * 86400;
                if (time() >= $date) $top_up->status = 'published';
            }else{
                $date = strtotime($top_up->start_date);
                if (time() >= $date) $top_up->status = 'published';
            }
            $top_up->save();
            if ($top_up->status == 'published') {
                $this->info('shop_top_up_id = ' . $top_up->id . '更改上架');
            }
        }

        // 方案上架
        $shop_programs = ShopProgram::where('status', 'pending')->where('during_type', 2)->get();
        foreach ($shop_programs as $program) {
            // 販售幾天前自動上架顯示1前一個月2一週3一天4當天
            if ($program->show_day == 1) {
                $date = strtotime($program->start_date) - 30 * 86400;
                if (time() >= $date) $program->status = 'published';
            } elseif ($program->show_day == 2) {
                $date = strtotime($program->start_date) - 7 * 86400;
                if (time() >= $date) $program->status = 'published';
            } elseif ($program->show_day == 3) {
                $date = strtotime($program->start_date) - 1 * 86400;
                if (time() >= $date) $program->status = 'published';
            } else {
                $date = strtotime($program->start_date);
                if (time() >= $date) $program->status = 'published';
            }
            $program->save();
            if ($program->status == 'published') {
                $this->info('shop_program_id = ' . $program->id . '更改上架');
            }
        }

        // 會員卡上架
        $shop_membership_cards = ShopMembershipCard::where('status', 'pending')->where('during_type', 2)->get();
        foreach ($shop_membership_cards as $membership_card) {
            // 販售幾天前自動上架顯示1前一個月2一週3一天4當天
            if ($membership_card->show_day == 1) {
                $date = strtotime($membership_card->start_date) - 30 * 86400;
                if (time() >= $date) $membership_card->status = 'published';
            } elseif ($membership_card->show_day == 2) {
                $date = strtotime($membership_card->start_date) - 7 * 86400;
                if (time() >= $date) $membership_card->status = 'published';
            } elseif ($membership_card->show_day == 3) {
                $date = strtotime($membership_card->start_date) - 1 * 86400;
                if (time() >= $date) $membership_card->status = 'published';
            } else {
                $date = strtotime($membership_card->start_date);
                if (time() >= $date) $membership_card->status = 'published';
            }
            $membership_card->save();
            if( $membership_card->status == 'published' ){
                $this->info('shop_membership_card_id = ' . $membership_card->id . '更改上架');
            }  
        }
        
        $this->info('上架完畢');

        return 0;
    }
}
