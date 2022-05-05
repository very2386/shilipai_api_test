<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\CustomerReservation;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopCoupon;
use App\Models\ShopManagement;
use App\Models\ShopManagementMode;
use App\Models\ShopManagementRefuse;
use App\Models\ShopManagementCustomerList;
use App\Jobs\SendManagementSms;
use App\Http\Controllers\v1\ShopCustomerController;
use App\Models\CustomerCoupon;

class SendAutoManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send_auto_management';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '發送符合條件的條件通知內容';

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

        // 拿取符合當下時間的自動推廣｜條件通知資料
        $managements = ShopManagement::whereIn('type',['auto'])->where('use','Y')->get();

        foreach( $managements as $management ){

            if( $management->message == '' || $management->message == null ) continue;

            if( $management->during_type == 2 ){
                // 有設定期限
                if( time() < strtotime($management->start_date) || time() > strtotime($management->end_date) ) continue;
            }

            // 判斷時間是否符合
            if( $management->send_cycle == 1 && $management->send_cycle_time != date('H:i')){
                // 每天發送
                continue;
            }elseif( $management->send_cycle == 2 && $management->send_cycle_week == date('N') && $management->send_cycle_time != date('H:i')){
                // 每週
                continue;
            }elseif( $management->send_cycle == 3 && $management->send_cycle_day == date('j') && $management->send_cycle_time != date('H:i')){
                // 每月
                continue;
            }

            $shop_info = Shop::find($management->shop_id);

            // 篩選出符合模組的會員
            $res = Self::shop_management_customer_list($shop_info->id,$management->shop_management_mode_id);

            if( $res['status'] ){
                if( count($res['customer_lists']) == 0 ) continue;
                $management_customers = $res['customer_lists'];
            }else{
                continue;
            }

            // 發送文字內容整理
            $message = $management->message;
            $coupon  = ShopCoupon::find($management->shop_coupons);

            $message = str_replace('「"商家名稱"」' , $shop_info->name, $message);
            $message = str_replace('「"下個月份"」' , (string)(date('n')+1).'月', $message);

            if( $management->link ){
                $message = str_replace('「"連結"」' , ' ' . $management->link . ' ' , $message);
            }else{
                $message = str_replace('「"連結"」' , '', $message);
            }

            if( $coupon && $coupon->status == 'published' ){
                // 建立縮短網址
                $url = '/store/' . $shop_info->alias . '/member/coupon?select=2';
                $transform_url_code = Controller::get_transform_url_code($url);
                $message = str_replace('「"優惠券"」', ' ' . env('SHILIPAI_WEB') . '/T/' . $transform_url_code . ' ', $message);
            }else{
                $message = str_replace('「"優惠券"」' , '', $message);
            }

            $insert = [];
            foreach( $management_customers as $customer ){

                // 判斷是否需要重複發送
                if( $management->send_cycle_type == 2 ){
                    // 只能發送一次，所以要檢查是否已經發送過
                    if( $management->customer_lists->where('shop_customer_id',$customer['id'])->first() ){
                        continue;
                    }
                }

                // 記錄發送列表
                $insert[] = [
                    'shop_id'            => $shop_info->id,
                    'shop_management_id' => $management->id,
                    'shop_customer_id'   => $customer['id'],
                    'phone'              => $customer['phone'],
                    'type'               => $management->send_type,
                    'message'            => $management->message,
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s'),
                ];
            }
            ShopManagementCustomerList::insert($insert);

            // 拿出尚未發送的會員
            $management_customers = ShopManagementCustomerList::where('shop_management_id',$management->id)->where('status','N')->get();

            foreach( $management_customers as $customer ){
                // 被刪除的會員不用發送
                if (!$customer->customer_info) continue;
                // 沒有電話的不用發送
                if (!$customer->phone) continue;

                // 發送文字
                $sendword = str_replace('「"會員名稱"」' , $customer->customer_info->realname, $message);

                // 如果有設定優惠券
                if( $customer->management_info && $customer->management_info->shop_coupons ){
                    $coupon = ShopCoupon::find($customer->management_info->shop_coupons);
                    if( $coupon && $coupon->status == 'published' ){
                        // 先檢查優惠券是否過期
                        if( strtotime($coupon->start_date) <= time() && time() <= strtotime($coupon->end_date) ){

                            if( $coupon->get_level == 2 ){
                                // 特定條件
                                $add = true;
                            }else{
                                // 所有人，需判斷可領取次數，若只能領取一次，需判斷是否可以在給予優惠券
                                $add = true;
                                if( $coupon->use_type == 1 ){
                                    $customer_coupon = CustomerCoupon::where('customer_id', $customer->customer_info->id)
                                                                    ->where('shop_id',$shop_info->id)
                                                                    ->where('shop_coupon_id',$coupon->id)
                                                                    ->first();
                                    if( $customer_coupon ) $add = false;
                                }
                            }

                            if( $add ){
                                // 將該優惠券直接寫入會員裡
                                $customer_coupon = new CustomerCoupon;
                                $customer_coupon->customer_id    = $customer->customer_info->id;
                                $customer_coupon->company_id     = $shop_info->company_info->id;
                                $customer_coupon->shop_id        = $shop_info->id;
                                $customer_coupon->shop_coupon_id = $coupon->id;
                                $customer_coupon->save();
                            }
                        }
                    }
                }

                $customer->message = $sendword;
                $customer->save();

                switch ($management->send_type) {
                    case 1: // 手機與line
                        // 手機發送
                        $job = new SendManagementSms($customer,$sendword,$shop_info,$management->id,'','');
                        dispatch($job);

                        // line發送

                        break;

                    case 2: // 手機
                        // 手機發送
                        $job = new SendManagementSms($customer,$sendword,$shop_info,$management->id,'','');
                        dispatch($job);

                        break;

                    case 3: // line

                        break;

                    case 4: // line優先

                        // 手機發送
                        $job = new SendManagementSms($customer,$sendword,$shop_info,$management->id,'','');
                        dispatch($job);

                        break;
                }
            }

            $management->status = 'Y';
            $management->save();

            $this->info($management->name . ' 已發送');
        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('發送符合條件的自動推廣內容完成'.( $end - $start ));
    }

    // 利用模組篩選出要推廣的人選
    static public function shop_management_customer_list($shop_id,$shop_management_mode_id)
    {
        $customer_lists = [];

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_management_mode = ShopManagementMode::find($shop_management_mode_id);
        if( !$shop_management_mode ){
            return ['status'=>false,'errors'=>['message'=>['找不到推廣模組資料']]];
        }

        // 先判別關鍵字、性別
        $shop_customers = ShopCustomer::where('shop_id',$shop_info->id)->get();
        $customers      = Customer::whereIn('id',$shop_customers->pluck('customer_id'));

        // 有設定關鍵字
        if( $shop_management_mode->keyword ){
            $customers = $customers->where('realname','like','%'.$shop_management_mode->keyword.'%');
        }

        // 有設定性別
        if( $shop_management_mode->sex ){
            $customers = $customers->whereIn('sex',explode(',',$shop_management_mode->sex));
        }

        // 有設定年齡
        $min_birthday_year = $max_birthday_year = 0;
        if( $shop_management_mode->min_age ){
            $min_birthday_year = date('Y') - $shop_management_mode->min_age;
        }
        if( $shop_management_mode->max_age ){
            $max_birthday_year = date('Y') - $shop_management_mode->max_age;
        }

        if( $min_birthday_year == 0 && $max_birthday_year != 0 ){
            $customers = $customers->whereBetween('birthday',[ $max_birthday_year.'-01-01' , date('Y-m-d') ])->get();
        }elseif( $min_birthday_year != 0 && $max_birthday_year == 0 ){
            $customers = $customers->whereBetween('birthday',[  '1911-01-01' , $min_birthday_year.'-12-31' ])->get();
        }elseif( $min_birthday_year != 0 && $max_birthday_year != 0 ){
            $customers = $customers->whereBetween('birthday',[ $max_birthday_year.'-01-01' , $min_birthday_year.'-12-31' ])->get();
        }else{
            $customers = $customers->get();
        }

        if( $shop_management_mode->birthday_or_constellation == 1 ){
            // 篩選生日月份
            if( $shop_management_mode->birthday_month ){
                $birthday_month_arr = explode(',',$shop_management_mode->birthday_month);
                foreach( $customers as $customer ){
                    if( $customer->birthday && in_array(date('n',strtotime($customer->birthday)),$birthday_month_arr) ){
                        $check_in = false;
                        foreach( $customer_lists as $cl ){
                            if( $cl->id == $customer->id ){
                                $check_in = true;
                            }
                        }
                        if( $check_in == false ) $customer_lists[] =  $customer;
                    }
                }
            }
        }else{
            // 篩選星座
            if( $shop_management_mode->constellation ){
                $constellation_arr = explode(',',$shop_management_mode->constellation);
                foreach( $customers as $customer ){
                    if( $customer->birthday 
                        && in_array(ShopCustomerController::constellation($customer->birthday),$constellation_arr) ){
                        $check_in = false;
                        foreach( $customer_lists as $cl ){
                            if( $cl->id == $customer->id ){
                                $check_in = true;
                            }
                        }
                        if( $check_in == false ) $customer_lists[] =  $customer;
                    }
                }
            }
        }

        if( !$shop_management_mode->birthday_month && !$shop_management_mode->constellation ){
            $customer_lists = $customers;
        }

        // 先將符合條件的顧客id取出
        $customer_id_arr = [];
        foreach( $customer_lists as $cl ){
            $customer_id_arr[] = $cl->id; 
        }

        // 有預約且出席的會員(預約)
        $model = CustomerReservation::where('shop_id',$shop_info->id)->whereIn('customer_id',$customer_id_arr)->where('status','Y');
        if( $shop_management_mode->start_date ){
            $model = $model->where('start','>=',$shop_management_mode->start_date.' 00:00:00');
        }
        if( $shop_management_mode->end_date ){
            $model = $model->where('start','<=',$shop_management_mode->end_date.' 23:59:59');
        }

        // 選擇服務項目
        if( $shop_management_mode->shop_services ){
            $shop_management_mode_services = explode(',',$shop_management_mode->shop_services);
            $model = $model->whereIn('shop_service_id',$shop_management_mode_services);
        }

        // 選擇服務人員
        if( $shop_management_mode->shop_staffs ){
            $shop_management_mode_staffs = explode(',',$shop_management_mode->shop_staffs);
            $model = $model->whereIn('shop_staff_id',$shop_management_mode_staffs);
        }

        // plus(預約且有出席區間)
        $reservation_customer_id_arr = $model->whereIn('tag',[1,3,4,5])->groupBy('customer_id')->pluck('customer_id')->toArray();

        $customer_lists = Customer::whereIn('id',$reservation_customer_id_arr)->get();

        // 等級（待補）

        // 標籤（待補）

        // 轉換id成shop_customer_id
        $customer_list_data = [];
        $refuse_customer = ShopManagementRefuse::pluck('shop_customer_id')->toArray();
        foreach( $customer_lists  as $cl ){
            $shop_customer_id = ShopCustomer::where('shop_id',$shop_info->id)->where('customer_id',$cl->id)->value('id');
            if( !in_array( $shop_customer_id , $refuse_customer ) ){
                $customer_list_data[] = [
                    'id'       => $shop_customer_id,
                    'name'     => $cl->realname,
                    'phone'    => $cl->phone,
                    'line'     => '-',
                ];
            }
        }  

        return [ 'status' => true , 'customer_lists' => $customer_list_data ];
    }
}
