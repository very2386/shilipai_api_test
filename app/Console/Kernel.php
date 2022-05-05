<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\AutoCancelReservation::class,
        Commands\ResetReservationTag::class,
        Commands\LoyaltyCardCheck::class,
        Commands\ClearPosts::class,
        Commands\SendAutoManagement::class,
        Commands\SendNoticeManagement::class,
        Commands\SendFestivalNotice::class,
        Commands\SendAwardNotice::class,
        // Commands\SendOnceManagement::class,
        // Commands\SendEvaluate::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 每天固定時間取消當天未確認預約
        $schedule->command('auto_cancel_reservation')->dailyAt('23:59');

        // 每天固定時間更新會員預約標籤
        $schedule->command('reset_reservation_tag')->dailyAt('00:00');

        // 清除過期的貼文
        $schedule->command('clear_posts')->dailyAt('00:00');

        // 每分鐘條件通知發送
        $schedule->command('send_auto_management')->everyMinute();

        // 每分鐘訊息服務通知發送
        $schedule->command('send_notice_management')->everyMinute();

        // 每分鐘節慶通知發送
        $schedule->command('send_festival_notice')->everyMinute();

        // 每分鐘獎勵通知發送
        $schedule->command('send_award_notice')->everyMinute();

        // 每分鐘檢查需要自動上架的
        $schedule->command('auto_published')->everyMinute();

        // 集點卡失效提醒
        // $schedule->command('loyalty_card_check')->dailyAt('01:00');

        // 每天固定時間檢查簡訊數量
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
