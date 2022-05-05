<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\CustomerReservation;
use App\Models\CompanyStaff;

class DeleteGoogleCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reservation_info,$shop_staff,$google_token;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($reservation_info,$shop_staff,$google_token)
    {
        $this->reservation_info = $reservation_info;
        $this->shop_staff       = $shop_staff;
        $this->google_token     = $google_token;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $staff        = $this->shop_staff;
        $reservation  = $this->reservation_info;
        $google_token = $this->google_token;

        $staff->calendar_token = CompanyStaff::where('id',$staff->company_staff_id)->value('calendar_token');

        $client = new \Google_Client();
        // 設定憑證 (前面下載的 json 檔)
        $client->setAuthConfig(base_path('config/').'google_calendar_secret.json');
        // 回傳後要記住AccessToken，之後利用這個就可以一直查詢
        // $client->setAccessToken($staff->calendar_token);
        $client->refreshToken($google_token);

        $service = new \Google_Service_Calendar($client);

        try {
            if( $reservation->google_calendar_id ){
                $service->events->delete('primary', $reservation->google_calendar_id);
            }
        } catch (\Google\Service\Exception $e) {
            
        }

        CustomerReservation::where('id',$reservation->id)->update(['google_calendar_id'=>NULL]);
    }
}
