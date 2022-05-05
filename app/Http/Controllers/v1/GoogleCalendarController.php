<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ShopStaff;
use App\Models\CompanyStaff;
use App\Models\CustomerReservation;
use App\Jobs\InsertGoogleCalendarEvent;
use App\Jobs\DeleteGoogleCalendarEvent;

class GoogleCalendarController extends Controller
{
    // 建立google calendar綁定連結
    static public function get_connect_url($shop_staff_id)
    {
        $state = [
            'shop_staff_id' => $shop_staff_id,
        ];
        $state = base64_encode( json_encode($state) );

        $client = new \Google_Client();
        $client->setApplicationName('Google Calendar API PHP Quickstart');
        $client->setAuthConfig(base_path('config/').'google_calendar_secret.json');
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/calendar');
        $client->setRedirectUri(url('/').'/api/calendar/callback');
        // $client->setRedirectUri('https://api.shilipai.ai/api/calendar/callback');
        $client->setPrompt('select_account consent');
        $client->setState(['state' => $state]);

        $authUrl = $client->createAuthUrl();

        return $authUrl;
    }

    // 解除google calendar綁定
    static public function disconnect_googleCalendar($shop_id,$shop_staff_id)
    {
        $shop_staff_info = ShopStaff::find($shop_staff_id);

        $customer_reservations = CustomerReservation::where('shop_staff_id',$shop_staff_info->id)->get();
        foreach( $customer_reservations as $reservation ){
            $token = $reservation->staff_info->calendar_token;
            if( $reservation->google_calendar_id != '' ){
                $job = new DeleteGoogleCalendarEvent($reservation,$reservation->staff_info,$token);
                dispatch($job);
            }
        }

        CompanyStaff::where('id',$shop_staff_info->company_staff_id)->update(['calendar_token'=>NULL]);

        return redirect(env('DOMAIN_NAME').'/editStaff/'.$shop_staff_id.'/setting');
    }

    // google calendar api 回傳
    public function calendar_callback()
    {
        $state = json_decode(base64_decode(request('state')) );
        $shop_staff_info = ShopStaff::find($state->shop_staff_id);

        // 選擇取消
        if( request('error') && request('error') == 'access_denied' ){
            return redirect(env('DOMAIN_NAME').'/editStaff/'.$shop_staff_info->id.'/setting');
        }

        $client = new \Google_Client();
        // 設定憑證 (前面下載的 json 檔)
        $client->setAuthConfig(base_path('config/').'google_calendar_secret.json');
        $client->setRedirectUri(url('/').'/api/calendar/callback');
        // $client->setRedirectUri('https://api.shilipai.ai/api/calendar/callback');

        // 使用者登入後 redirect 過來附帶的 code
        $authCode = request('code');

        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        $client->setAccessToken($accessToken);

        CompanyStaff::where('id',$shop_staff_info->company_staff_id)->update(['calendar_token'=>$accessToken['refresh_token']]);

        // 將table 事件寫入 google calendar
        $customer_reservations = CustomerReservation::where('shop_staff_id',$shop_staff_info->id)->get();
        foreach( $customer_reservations as $reservation ){
            if( $reservation->google_calendar_id == '' && $reservation->cancel_status == NULL ){
                $job = new InsertGoogleCalendarEvent($reservation,$reservation->staff_info);
                dispatch($job);
            }
        }

        return redirect(env('DOMAIN_NAME').'/editStaff/'.$shop_staff_info->id.'/setting');
    }

    // 拿取指定員工的google calendar事件
    static public function staff_calendar_events( $staff , $timeMin = "", $timeMax = "" )
    {
        $event_data = [];

        $client = new \Google_Client();
        // 設定憑證 (前面下載的 json 檔)
        $client->setAuthConfig(base_path('config/').'google_client_secret.json');
        // 回傳後要記住AccessToken，之後利用這個就可以一直查詢
        // $client->setAccessToken($staff->calendar_token);
        $client->refreshToken($staff->calendar_token);

        $service = new \Google_Service_Calendar($client);

        // 讀取未來日曆上的事件
        $calendarId = 'primary';
        $optParams = array(
          'maxResults'   => 1000,
          'orderBy'      => 'startTime',
          'singleEvents' => true,
          'timeMin'      => $timeMax != '' ? $timeMin : date('c'),
          'timeMax'      => $timeMax != '' ? $timeMax : date('c'),
        );

        try {
            $results = $service->events->listEvents('primary', $optParams);
        } catch (\Google_Service_Exception  $e) {
            $res = json_decode($e->getMessage());
            return [];
        }

        $events = $results->getItems();

        foreach ($events as $event) {
            $event_data[] = [
                'title'  => $event->summary,
                'color'  => $staff->color,
                'id'     => $event->id,
                'start'  => $event->start->dateTime ? date('Y/m/d H:i', strtotime($event->start->dateTime)) : date('Y/m/d 00:00', strtotime($event->start->date)),
                'end'    => $event->end->dateTime ? date('Y/m/d H:i', strtotime($event->end->dateTime)) : date('Y/m/d 24:00', strtotime($event->end->date)),
            ];
        }

        return $event_data;
    }

    // 指定員工寫入google calendar事件
    static public function insert_calendar_event($reservation,$staff)
    {
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

        return $ret->id;
    }

    // 刪除指定員工的指定google calendar事件
    static public function delete_calendar_event($reservation)
    {
        $client = new \Google_Client();
        // 設定憑證 (前面下載的 json 檔)
        $client->setAuthConfig(base_path('config/').'google_client_secret.json');
        // 回傳後要記住AccessToken，之後利用這個就可以一直查詢
        $client->refreshToken($reservation->staff_info->calendar_token);

        $service = new \Google_Service_Calendar($client);

        try {
            $ret = $service->events->delete('primary', $reservation->google_calendar_id);
        } catch (\Google_Service_Exception  $e) {
            return ;
        }

        return $ret;
    }

    // 實力派美業官網預約寫入google calendar事件
    static public function web_insert_calendar_event()
    {
        $reservation = CustomerReservation::find(request('customer_reservation'));
        $staff = $reservation->staff_info;

        $client = new \Google_Client();
        // 設定憑證 (前面下載的 json 檔)
        $client->setAuthConfig(base_path('config/') . 'google_client_secret.json');
        // 回傳後要記住AccessToken，之後利用這個就可以一直查詢
        // $client->setAccessToken($staff->calendar_token);
        $client->refreshToken($staff->calendar_token);

        $service = new \Google_Service_Calendar($client);

        $description = '';
        // 添加會員生日與電話號碼
        if ($reservation->customer_info->phone) {
            $description = "<br>會員電話：" . $reservation->customer_info->phone . "<br>";
        }
        if ($reservation->customer_info->birthday) {
            $description .= "會員生日：" . substr($reservation->customer_info->birthday, 0, 10) . "<br>";
        }

        // 加值項目
        if (count($reservation->advances)) {
            $description .= "加值項目：";
            foreach ($reservation->advances as $k => $item) {
                $description .= $item->name . ($k == $reservation->advances->count() - 1 ? '' : '，');
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

        $reservation->google_calendar_id = $ret->id;
        $reservation->save();

        return ['status'=>true ,'id' => $ret->id];
    }

    // 實力派美業官網預約刪除指定google calendar
    public function web_delete_calendar_event()
    {
        $reservation = CustomerReservation::find(request('customer_reservation'));

        $client = new \Google_Client();
        // 設定憑證 (前面下載的 json 檔)
        $client->setAuthConfig(base_path('config/').'google_client_secret.json');
        // 回傳後要記住AccessToken，之後利用這個就可以一直查詢
        $client->refreshToken($reservation->staff_info->calendar_token);

        $service = new \Google_Service_Calendar($client);

        try {
            $ret = $service->events->delete('primary', $reservation->google_calendar_id);
            $reservation->google_calendar_id = NULL;
            $reservation->save();
            return ['status'=>true];
        } catch (\Google_Service_Exception  $e) {
            return ['status'=>false];
        }

        return ['status'=>false];
    }
}
