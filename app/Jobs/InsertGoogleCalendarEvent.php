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

class InsertGoogleCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reservation_info,$shop_staff;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($reservation_info,$shop_staff)
    {
        $this->reservation_info = $reservation_info;
        $this->shop_staff       = $shop_staff;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $staff       = $this->shop_staff;
        $reservation = $this->reservation_info;

        $staff->calendar_token = CompanyStaff::where('id',$staff->company_staff_id)->value('calendar_token');

        $client = new \Google_Client();
        // 設定憑證 (前面下載的 json 檔)
        $client->setAuthConfig(base_path('config/').'google_client_secret.json');
        // 回傳後要記住AccessToken，之後利用這個就可以一直查詢
        // $client->setAccessToken($staff->calendar_token);
        $client->refreshToken($staff->calendar_token);

        $service = new \Google_Service_Calendar($client);

        $description = '';
        // 添加會員生日與電話號碼
        if( $reservation->customer_info->phone ){
            $description = "<br>會員電話：".$reservation->customer_info->phone."<br>"; 
        }
        if( $reservation->customer_info->birthday ){
            $description .= "會員生日：".substr($reservation->customer_info->birthday,0,10)."<br>"; 
        }

        // 加值項目
        if( count($reservation->advances) ){
            $description .= "加值項目："; 
            foreach( $reservation->advances as $k => $item ){
                $description .= $item->name . ($k==$reservation->advances->count()-1 ? '' : '，');
            }
        }

        // 寫入日曆
        $sdate = $reservation->start;
        $edate = $reservation->end;

        $calendar_event = new \Google_Service_Calendar_Event(array(
          'summary' => $reservation->customer_info->realname . ' - ' . $reservation->service_info->name,
          'start' => array(
            'dateTime' => date(DATE_ATOM, strtotime($sdate)),
            'timeZone' => 'Asia/Taipei',
          ),
          'end' => array(
            'dateTime' => date(DATE_ATOM, strtotime($edate)),
            'timeZone' => 'Asia/Taipei',
          ),
          'transparency' => null,
          'description' => $description,
        ));

        $ret = $service->events->insert('primary', $calendar_event);

        CustomerReservation::where('id',$reservation->id)->update(['google_calendar_id'=>$ret->id]);
    }
}
