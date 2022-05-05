<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\PermissionMenu;
use App\Models\Permission;
use App\Models\ShopStaff;
use App\Models\ShopReservationMessage;
use App\Models\ShopReservationTag;
use App\Models\ShopBusinessHour;
use App\Models\ShopClose;
use App\Models\CompanyStaff;
use App\Models\SystemNotice;
use App\Models\ShopSet;
use App\Models\CompanyTitle;
use App\Models\ShopFestivalNotice;
use App\Models\ShopPayType;

class GreateDefaultData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $company_info,$shop_info,$user_info;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($company_info,$shop_info,$user_info)
    {
        $this->company_info = $company_info;
        $this->shop_info    = $shop_info;
        $this->user_info    = $user_info;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $company_info = $this->company_info;
        $shop         = $this->shop_info;
        $user         = $this->user_info;

        // 建立預設職稱
        $title_default = ['美容師','美髮師','美體師','美甲師','美睫師','紋繡師'];
        $first_title = '';
        foreach( $title_default as $k => $title ){
            $company_title = new CompanyTitle;
            $company_title->company_id = $company_info->id;
            $company_title->name       = $title;
            $company_title->save();

            if( $first_title == '' ) $first_title = $company_title->id;
        }

        // 建立company_staff
        $company_staff                       = new CompanyStaff;
        $company_staff->user_id              = $user->id;
        $company_staff->company_id           = $company_info->id;
        $company_staff->name                 = $user->name;
        $company_staff->phone                = $user->phone;
        $company_staff->onboard              = date('Y-m-d');
        $company_staff->company_title_id_a   = $first_title;
        $company_staff->show_all_customer    = 'Y';
        $company_staff->show_all_reservation = 'Y';
        $company_staff->edit_all_reservation = 'Y';
        $company_staff->save();

        // 建立shop_staff
        $shop_staff = new ShopStaff;
        $shop_staff->user_id            = $user->id;
        $shop_staff->shop_id            = $shop->id;
        $shop_staff->company_staff_id   = $company_staff->id;
        $shop_staff->master             = 0;
        $shop_staff->company_title_id_a = $first_title;
        $shop_staff->save();

        // if( request('recommend') == '0961331190' ){
        //     $p = implode(',',PermissionMenu::where('plus',1)->pluck('value')->toArray());
        // }else{
        //     $p = implode(',',PermissionMenu::where('basic',1)->pluck('value')->toArray());
        // }
        $p = implode(',',PermissionMenu::where('basic',1)->pluck('value')->toArray());

        // 建立1C權限
        $permission = new Permission;
        $permission->user_id       = $user->id;
        $permission->company_id    = $company_info->id;
        // $permission->buy_mode_id   = request('recommend') == '0961331190' ? 1 : 0;
        $permission->buy_mode_id   = 0;
        $permission->permission    = $p;
        $permission->save();  

        // 建立1S權限
        $permission = new Permission;
        $permission->user_id       = $user->id;
        $permission->company_id    = $company_info->id;
        $permission->shop_id       = $shop->id;
        // $permission->buy_mode_id   = request('recommend') == '0961331190' ? 1 : 0;
        $permission->buy_mode_id   = 0;
        $permission->permission    = $p;
        $permission->save();  

        // 建立員工權限
        $permission = new Permission;
        $permission->user_id       = $user->id;
        $permission->company_id    = $company_info->id;
        $permission->shop_id       = $shop->id;
        $permission->shop_staff_id = $shop_staff->id;
        // $permission->buy_mode_id   = request('recommend') == '0961331190' ? 1 : 0;
        $permission->buy_mode_id   = 0;
        $permission->permission    = implode(',',PermissionMenu::where('value','like','staff_%')->pluck('value')->toArray());
        $permission->save();  

        // 建立商家預設預約發送訊息===================================================================
        // wait待審核check確認/通過預約shop_cancel商家取消/不通過customer_cancel客戶取消change變更預約
        $type = ['wait','check','shop_cancel','customer_cancel','change'];
        foreach( $type as $t ){
            switch($t){
                case 'wait':
                    $msg = '「"會員名稱"」您好，您預約「"商家名稱"」的「"服務名稱"」已送出等待商家確認中，訂單細節：「"訂單連結"」';
                    break;
                case 'check':
                    $msg = '您在「"商家名稱"」預約的服務已確認，「"服務日期"」 「"預約日期時間"」期待您的到來，詳情：「"訂單連結"」';
                    break;
                case 'shop_cancel':
                    $msg = '「"會員名稱"」您好，「"商家名稱"」在您預約的時段無法為您服務，可選擇其他時段，再次預約：「"再次預約連結"」';
                    break;
                case 'customer_cancel':
                    $msg = '「"會員名稱"」您好，您已取消「"商家名稱"」預約「"服務日期"」 「"預約日期時間"」「"服務名稱"」；再次預約：「"再次預約連結"」';
                    break;
                case 'change':
                    $msg = '「"會員名稱"」您好，已將「"服務名稱"」的時間變更為「"服務日期"」 「"預約日期時間"」；「"商家名稱"」期待您的到來，訂單細節：「"訂單連結"」';
                    break;
            }

            $message = new ShopReservationMessage;
            $message->shop_id = $shop->id;
            $message->type    = $t;
            $message->content = $msg;
            $message->status  = 'Y';
            $message->save();
        }

        // 建立預設預約標籤===================================================================
        $type = [1,2,3,4];
        foreach( $type as $t ){
            $data = new ShopReservationTag;
            $data->shop_id = $shop->id;
            $data->type    = $t;
            $data->save();
        }

        // 營業資料===================================================================
        $weeks = [ '星期一' , '星期二' , '星期三' , '星期四' , '星期五' , '星期六' , '星期日' ];
        foreach( $weeks as $k => $week ){
            $new_business = new ShopBusinessHour;
            $new_business->shop_id = $shop->id;
            $new_business->type    = true;
            $new_business->start   = '10:00:00';
            $new_business->end     = '22:00:00';
            $new_business->week    = $k+1;
            $new_business->save();
        }

        // 不營業時間
        $new_close = new ShopClose;
        $new_close->shop_id = $shop->id;
        $new_close->type    = 0;
        $new_close->week    = '';
        $new_close->save(); 

        // 員工的營業時間與不營業時間
        foreach( $shop->shop_staffs as $staff ){
            foreach( $shop->shop_business_hours as $open ){
                $insert_open[] = [
                    'shop_id'       => $shop->id,
                    'shop_staff_id' => $staff->id,
                    'type'          => 0, // 同商家營業時間
                    'week'          => $open->week,
                    'start'         => $open->start,
                    'end'           => $open->end,
                ];
            }

            if( $shop->shop_close ){
                $insert_close[] = [
                    'shop_id'       => $shop->id,
                    'shop_staff_id' => $staff->id,
                    'type'          => $shop->shop_close->type,
                    'week'          => $shop->shop_close->week,
                ]; 
            }
        }

        ShopBusinessHour::insert($insert_open);
        ShopClose::insert($insert_close);

        // 商家設定資料
        $shop_set = new ShopSet;
        $shop_set->shop_id           = $shop->id;
        $shop_set->reservation_check = 1;
        $shop_set->color_select      = 1;
        $shop_set->color             = '#9440a1';
        $shop_set->show_phone        = 1;
        $shop_set->save();

        $url_data = [
            [
                'text' => '商家管理 GO>',
                'url'  => '/storeData/basicData/storeInfo',
            ],
            [
                'text' => '服務管理 GO>',
                'url'  => '/chargeService/service',
            ],
            [
                'text' => '作品集 GO>',
                'url'  => '/portfolio',
            ],
        ];
        $notice = new SystemNotice;
        $notice->company_id = $company_info->id;
        $notice->shop_id    = $shop->id;
        $notice->content    = '恭喜您加入實力派，快來填寫下列3大功能項目，展現您專屬的美業官網喔！';
        $notice->url_data   = json_encode($url_data,JSON_UNESCAPED_UNICODE);
        $notice->save();

        // 建立節慶通知預設資料
        $festival_default = ShopFestivalNotice::where('shop_id',NULL)->where('default','Y')->get();
        foreach( $festival_default as $default ){
            $data = new ShopFestivalNotice;
            $data->default            = 'Y';
            $data->shop_id            = $shop->id;
            $data->name               = $default->name;
            $data->type               = $default->type;
            $data->month              = $default->month;
            $data->day                = $default->day;
            $data->week               = $default->week;
            $data->use                = $default->use;
            $data->default_message_id = $default->default_message_id;
            $data->message            = $default->message;
            $data->link               = $default->link;
            $data->shop_coupons       = $default->shop_coupons;
            $data->send_type          = $default->send_type;
            $data->before             = $default->before;
            $data->send_datetime      = $default->send_datetime;
            $data->save();
        }

        // 建立商家付款預設方式
        $pay_type = ['無收現', '現金', 'Line Pay', '街口支付'];
        foreach ($pay_type as $type) {
            $data = ShopPayType::where('shop_id', $shop->id)->where('name', $type)->first();
            if (!$data) {
                $data = new ShopPayType;
                $data->shop_id = $shop->id;
                $data->name    = $type;
                $data->save();
            }
        }

    }
}
