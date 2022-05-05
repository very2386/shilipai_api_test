<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Http\Controllers\Controller;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sendword,$phone,$shop_info;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($phone,$sendword,$shop_info)
    {
        $this->phone     = $phone;
        $this->sendword  = $sendword;
        $this->shop_info = $shop_info;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $phone     = $this->phone;
        $sendword  = $this->sendword;
        $shop_info = $this->shop_info;

        Controller::send_phone_message($phone,$sendword,$shop_info);
    }
}
