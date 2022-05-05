<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\PermissionMenu;
use App\Models\Shop;
use App\Models\ShopFestivalNotice;
use App\Jobs\DeleteGoogleCalendarEvent;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\BuyMode;
use App\Models\Company;
use App\Models\CompanyCoupon;
use App\Models\CompanyCouponLimit;
use App\Models\CompanyCustomer;
use App\Models\CompanyLoyaltyCard;
use App\Models\CompanyLoyaltyCardLimit;
use App\Models\CompanyMembershipCard;
use App\Models\CompanyProduct;
use App\Models\CompanyProductCategory;
use App\Models\CompanyProgram;
use App\Models\CompanyProgramGroup;
use App\Models\CompanyProgramGroupContent;
use App\Models\CompanyService;
use App\Models\CompanyServiceCategory;
use App\Models\CompanyStaff;
use App\Models\CompanyTitle;
use App\Models\CompanyTopUp;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerEvaluate;
use App\Models\CustomerLoyaltyCard;
use App\Models\CustomerLoyaltyCardPoint;
use App\Models\CustomerMembershipCard;
use App\Models\CustomerProgram;
use App\Models\CustomerProgramGroup;
use App\Models\CustomerProgramLog;
use App\Models\CustomerQuestionAnswer;
use App\Models\CustomerReservation;
use App\Models\CustomerReservationAdvance;
use App\Models\CustomerTopUp;
use App\Models\CustomerTopUpLog;
use App\Models\MessageLog;
use App\Models\Order;
use App\Models\Photo;
use App\Models\ShopAwardNotice;
use App\Models\ShopBusinessHour;
use App\Models\ShopClose;
use App\Models\ShopCoupon;
use App\Models\ShopCouponLimit;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerReservationTag;
use App\Models\ShopCustomerTag;
use App\Models\ShopEvaluate;
use App\Models\ShopLoyaltyCard;
use App\Models\ShopLoyaltyCardLimit;
use App\Models\ShopManagement;
use App\Models\ShopManagementCustomerList;
use App\Models\ShopManagementGroup;
use App\Models\ShopManagementMode;
use App\Models\ShopManagementRefuse;
use App\Models\ShopManagementService;
use App\Models\ShopMembershipCard;
use App\Models\ShopMembershipCardRole;
use App\Models\ShopMembershipCardRoleLimit;
use App\Models\ShopNoticeMode;
use App\Models\ShopNoticeModeQuestion;
use App\Models\ShopPayType;
use App\Models\ShopPhoto;
use App\Models\ShopPost;
use App\Models\ShopProduct;
use App\Models\ShopProductCategory;
use App\Models\ShopProgram;
use App\Models\ShopProgramGroup;
use App\Models\ShopProgramGroupContent;
use App\Models\ShopReservationMessage;
use App\Models\ShopReservationTag;
use App\Models\ShopService;
use App\Models\ShopServiceAdvance;
use App\Models\ShopServiceCategory;
use App\Models\ShopServiceStaff;
use App\Models\ShopSet;
use App\Models\ShopStaff;
use App\Models\ShopTopUp;
use App\Models\ShopTopUpRole;
use App\Models\ShopTopUpRoleLimit;
use App\Models\ShopVacation;
use App\Models\SystemNotice;
use App\Models\User;
use DB;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    // 利用檔案新增會員資料
    public function customer_file_upload()
    {
        return view('customer_file');
    }

    public function customer_file_upload_save()
    {
        $file      = request()->file('file');
        $file_name = $file->getClientOriginalName();

        $file_ext  = $file->getClientOriginalExtension();
        $file_name = str_replace('.' . $file_ext, '', $file->getClientOriginalName());
        $uuid      = date('Y-m-d') . sha1(uniqid('', true)) . '.' . $file_ext;
        $file->move(public_path('upload/files/'), $uuid);

        $filePath  = public_path('upload/files/') . $uuid;
        $handle    = fopen($filePath, "r");

        $shop_info = Shop::find(request('shop_id'));

        $customers         = Customer::where('phone','!=',NULL)->pluck('phone','id')->toArray();
        $company_customers = CompanyCustomer::where('company_id',$shop_info->company_info->id)->pluck('customer_id')->toArray();
        $shop_customers    = ShopCustomer::where('shop_id', $shop_info->id)->pluck('customer_id')->toArray();

        $i = 0;
        $insert_company_customer = $insert_shop_customer = [];
        while (($data = fgetcsv($handle, 0, ','))) {
            if ($i++ == 0) continue;

            // 0姓名1生日2電話3備註
            if( !in_array($data[2],$customers) ){
                $birthday = '';
                if( $data[1] != '' ){
                    $birthday = substr($data[1],0,4) . '-' . substr($data[1], 4, 2) . '-' . substr($data[1], 6, 2);
                }

                $customer = new Customer;
                $customer->realname        = $data[0];
                $customer->phone           = $data[2];
                $customer->birthday        = $birthday;
                $customer->birthday_select = $birthday ? 1 : 0;
                $customer->note            = $data[3];
                $customer->save();
                $customer_id = $customer->id;
            }else{
                $customer_id = array_search($data[2],$customers);
            }

            // 建立集團的會員
            if( !in_array($customer_id, $company_customers) ){
                // $company_customer = new CompanyCustomer;
                // $company_customer->customer_id = $customer_id;
                // $company_customer->company_id  = $shop_info->company_info->id;
                // $company_customer->save();
                $insert_company_customer[] = [
                    'customer_id' => $customer_id,
                    'company_id'  => $shop_info->company_info->id,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ];
            }

            // 建立商家的會員
            if (  !in_array($customer_id, $shop_customers) ) {
                // $shop_customer = new ShopCustomer;
                // $shop_customer->customer_id = $customer_id;
                // $shop_customer->company_id  = $shop_info->company_info->id;
                // $shop_customer->shop_id     = $shop_info->id;
                // $shop_customer->save();

                $insert_shop_customer[] = [
                    'customer_id' => $customer_id,
                    'company_id'  => $shop_info->company_info->id,
                    'shop_id'     => $shop_info->id,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ];
            }
        }

        // 寫入資料
        if(!empty($insert_company_customer)) CompanyCustomer::insert($insert_company_customer);
        if(!empty($insert_shop_customer))    ShopCustomer::insert($insert_shop_customer);

        return response()->json(['status'=>true,'message'=>'上傳成功']);
    }

    // 補齊商家優惠券資料
    public function write_shop_coupons()
    {
        $company_coupons = CompanyCoupon::withTrashed()->get();
        foreach( $company_coupons as $coupon ){
            $shop_coupon = ShopCoupon::where('company_coupon_id', $coupon->id)->withTrashed()->first();
            if( $shop_coupon ){
                $shop_coupon->type            = $coupon->type;
                $shop_coupon->title           = $coupon->title;
                $shop_coupon->description     = $coupon->description;
                $shop_coupon->start_date      = $coupon->start_date;
                $shop_coupon->end_date        = $coupon->end_date;
                $shop_coupon->consumption     = $coupon->consumption;
                $shop_coupon->discount        = $coupon->discount;
                $shop_coupon->price           = $coupon->price;
                $shop_coupon->count_type      = $coupon->count_type;
                $shop_coupon->count           = $coupon->count;
                $shop_coupon->second_type     = $coupon->second_type;
                $shop_coupon->commodityId     = $coupon->commodityId;
                $shop_coupon->self_definition = $coupon->self_definition;
                $shop_coupon->photo_type      = $coupon->photo_type;
                $shop_coupon->photo           = $coupon->photo;
                $shop_coupon->use_type        = $coupon->use_type;
                $shop_coupon->get_level       = $coupon->get_level;
                $shop_coupon->customer_level  = $coupon->customer_level;
                $shop_coupon->show_type       = $coupon->show_type;
                $shop_coupon->limit           = $coupon->limit;
                $shop_coupon->content         = $coupon->content;
                $shop_coupon->save();

                // 補上使用限制
                if( $coupon->limit == 4 ){
                    $coupon_limits = CompanyCouponLimit::where('company_coupon_id',$coupon->id)->get();
                    ShopCouponLimit::where('shop_coupon_id', $shop_coupon->id)->delete();
                    foreach( $coupon_limits as $limit ){
                        $commodity_id = NULL;
                        if ($limit->type == 'service') {
                            $commodity_id = ShopService::where('shop_id', $shop_coupon->shop_id)->where('company_service_id', $limit->commodity_id)->first();
                        }

                        $shop_coupon_limit = new ShopCouponLimit;
                        $shop_coupon_limit->shop_id        = $shop_coupon->shop_id;
                        $shop_coupon_limit->shop_coupon_id = $shop_coupon->id;
                        $shop_coupon_limit->type           = $limit->type;
                        $shop_coupon_limit->commodity_id   = $commodity_id;
                        $shop_coupon_limit->save();
                    }
                }
            }
        }

        return response()->json(['status'=>true,'message'=>'寫入完成']);
    }

    // 補齊商家集點卡資料
    public function write_shop_loyalty_cards()
    {
        $company_loyalty_cards = CompanyLoyaltyCard::withTrashed()->get();
        foreach ($company_loyalty_cards as $loyalty_card) {
            $shop_loyalty_card = ShopLoyaltyCard::where('company_loyalty_card_id', $loyalty_card->id)->withTrashed()->first();
            if ($shop_loyalty_card) {
                $shop_loyalty_card->name                 = $loyalty_card->name;
                $shop_loyalty_card->condition_type       = $loyalty_card->condition_type;
                $shop_loyalty_card->condition            = $loyalty_card->condition;
                $shop_loyalty_card->full_point           = $loyalty_card->full_point;
                $shop_loyalty_card->first_point          = $loyalty_card->first_point;
                $shop_loyalty_card->deadline_type        = $loyalty_card->deadline_type;
                $shop_loyalty_card->year                 = $loyalty_card->year;
                $shop_loyalty_card->month                = $loyalty_card->month;
                $shop_loyalty_card->start_date           = $loyalty_card->start_date;
                $shop_loyalty_card->end_date             = $loyalty_card->end_date;
                $shop_loyalty_card->content              = $loyalty_card->content;
                $shop_loyalty_card->background_type      = $loyalty_card->background_type;
                $shop_loyalty_card->background_color     = $loyalty_card->background_color;
                $shop_loyalty_card->background_img       = $loyalty_card->background_img;
                $shop_loyalty_card->watermark_type       = $loyalty_card->watermark_type;
                $shop_loyalty_card->watermark_img        = $loyalty_card->watermark_img;
                $shop_loyalty_card->type                 = $loyalty_card->type;
                $shop_loyalty_card->second_type          = $loyalty_card->second_type;
                $shop_loyalty_card->commodityId          = $loyalty_card->commodityId;
                $shop_loyalty_card->self_definition      = $loyalty_card->self_definition;
                $shop_loyalty_card->price                = $loyalty_card->price;
                $shop_loyalty_card->consumption          = $loyalty_card->consumption;
                $shop_loyalty_card->limit                = $loyalty_card->limit;
                $shop_loyalty_card->get_limit            = $loyalty_card->get_limit;
                $shop_loyalty_card->get_limit_minute     = $loyalty_card->get_limit_minute;
                $shop_loyalty_card->discount_limit_type  = $loyalty_card->discount_limit_type;
                $shop_loyalty_card->discount_limit_month = $loyalty_card->discount_limit_month;
                $shop_loyalty_card->notice_day           = $loyalty_card->notice_day;
                $shop_loyalty_card->save();

                // 補上使用限制
                if ($loyalty_card->limit == 4) {
                    $loyalty_card_limits = CompanyLoyaltyCardLimit::where('company_loyalty_card_id', $loyalty_card->id)->get();
                    ShopLoyaltyCardLimit::where('shop_loyalty_card_id', $shop_loyalty_card->id)->delete();
                    foreach ($loyalty_card_limits as $limit) {
                        $shop_coupon_limit = new ShopLoyaltyCardLimit;
                        $shop_coupon_limit->shop_id              = $shop_loyalty_card->shop_id;
                        $shop_coupon_limit->shop_loyalty_card_id = $shop_loyalty_card->id;
                        $shop_coupon_limit->type                 = $limit->type;
                        $shop_coupon_limit->commodity_id         = $limit->commodity_id;
                        $shop_coupon_limit->save();
                    }
                }
            }
        }

        return response()->json(['status' => true, 'message' => '寫入完成']);
    }

    // 補齊商家收款方式資料
    public function write_shop_pay_type()
    {
        $shops = Shop::get();
        $pay_type = ['無收現','現金','Line Pay','街口支付'];
        foreach( $shops as $shop ){
            foreach( $pay_type as $type ){
                $data = ShopPayType::where('shop_id',$shop->id)->where('name',$type)->first();
                if( !$data ){
                    $data = new ShopPayType;
                    $data->shop_id = $shop->id;
                    $data->name    = $type;
                    $data->save();
                }
            } 
        }
        return response()->json(['status' => true, 'message' => '寫入完成']);
    }

    // 補齊商家單購客資料
    public function write_shop_onlyBuy()
    {
        $shops = Shop::get();
        foreach ($shops as $shop) {
            $data = ShopCustomer::where('shop_id', $shop->id)->where('customer_id',NULL)->first();
            if (!$data) {
                if(!$shop->company_info) continue;
                $data             = new ShopCustomer;
                $data->shop_id    = $shop->id;
                $data->company_id = $shop->company_info->id;
                $data->note       = '單購客';
                $data->save();
            }
        }
        return response()->json(['status' => true, 'message' => '寫入完成']);
    }

    // 節慶通知幫商家寫入預設
    public function add_festival_data()
    {
        $festival_default = ShopFestivalNotice::where('shop_id', NULL)->where('default', 'Y')->get();
        $shops = Shop::get();
        foreach ($shops as $shop) {
            $shop_festivals = ShopFestivalNotice::where('shop_id', $shop->id)->get();
            $check_data = [];
            foreach ($shop_festivals as $festival) {
                if ($festival->default == 'Y' && in_array($festival->name, $festival_default->pluck('name')->toArray())) $check_data[] = $festival->name;
            }

            foreach ($festival_default->whereNotIn('name', $check_data) as $default) {
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
        }

        return response()->json(['status' => true, 'message' => '寫入成功']);
    }

    // 管理台權限更新
    public function permission_update()
    {
        $shops = Shop::get();

        foreach ($shops as $shop) {
            $permissions = Permission::where('shop_id', $shop->id)->get();
            foreach ($permissions as $permission) {
                if ($permission->shop_staff_id == '') {
                    switch ($shop->buy_mode_id) {
                        case 0: // 基本版
                            $pn = implode(',', PermissionMenu::where('basic', 1)->pluck('value')->toArray());
                            break;
                        case 1: // 進階單人年繳
                            $pn = implode(',', PermissionMenu::where('plus', 1)->pluck('value')->toArray());
                            break;
                        case 2: // 進階多人年繳
                            $pn = implode(',', PermissionMenu::where('plus_m', 1)->pluck('value')->toArray());
                            break;
                        case 5: // 專業單人年繳
                            $pn = implode(',', PermissionMenu::where('pro', 1)->pluck('value')->toArray());
                            break;
                        case 6: // 專業多人年繳
                            $pn = implode(',', PermissionMenu::where('pro_m', 1)->pluck('value')->toArray());
                            break;
                    }
                } else {

                    switch ($shop->buy_mode_id) {
                        case 0: // 基本版
                            $pn = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->where('basic', 1)->pluck('value')->toArray());
                            break;
                        case 1: // 進階單人年繳
                        case 2: // 進階多人年繳
                            $pn = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->where('plus',1)->pluck('value')->toArray());
                            break;
                        case 5: // 專業單人年繳
                        case 6: // 專業多人年繳
                            $pn = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->where('pro',1)->pluck('value')->toArray());
                            break;
                    }
                }
                $permission->permission  = $pn;
                $permission->buy_mode_id = $shop->buy_mode_id;
                $permission->save();
            }
        }

        return response()->json(['status' => true, 'message' => '權限更新完畢']);
    }

    // 刪除集團所有資料
    public function delete_company($company_id)
    {
        $company = Company::where('companyId', $company_id)->first();
        $shops   = Shop::where('alias', $company_id)->withTrashed()->get();

        foreach ($shops as $shop) {
            // 刪除商家所有預約
            $customer_reservations = CustomerReservation::where('shop_id', $shop->id)->withTrashed()->get();
            foreach ($customer_reservations as $reservation) {
                $token = $reservation->staff_info->calendar_token;
                if( $reservation->google_calendar_id != '' ){
                    $job = new DeleteGoogleCalendarEvent($reservation,$reservation->staff_info,$token);
                    dispatch($job);
                }
            }
            CustomerReservation::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopReservationMessage::where('shop_id', $shop->id)->delete();
            ShopReservationTag::where('shop_id', $shop->id)->withTrashed()->forceDelete();

            // 刪除熟客經營(服務通知、條件通知、節慶通知、獎勵通知、拒收名單)
            $service_notices = ShopManagementGroup::where('shop_id', $shop->id)->withTrashed()->get();
            foreach ($service_notices as $notice) {
                // 通知服務
                ShopManagementService::whereIn('shop_management_group_id', $notice->id)->delete();
            }
            // 服務通知｜條件通知
            ShopManagementGroup::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopManagement::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopManagementMode::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopManagementCustomerList::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopManagementRefuse::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 獎勵通知
            ShopAwardNotice::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 節慶通知
            ShopFestivalNotice::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 模組
            ShopNoticeMode::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopNoticeModeQuestion::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 服務評價｜問卷回復
            ShopEvaluate::where('shop_id', $shop->id)->delete();
            CustomerEvaluate::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            CustomerQuestionAnswer::where('shop_id', $shop->id)->delete();

            // 刪除優惠活動（優惠券、集點卡、儲值金、方案、會員卡）
            // 優惠券
            ShopCoupon::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            CompanyCoupon::where('company_id', $company->id)->withTrashed()->forceDelete();
            CompanyCouponLimit::where('company_id', $company->id)->delete();
            CustomerCoupon::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 集點卡
            ShopLoyaltyCard::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            CompanyLoyaltyCard::where('company_id', $company->id)->withTrashed()->forceDelete();
            CompanyLoyaltyCardLimit::where('company_id', $company->id)->delete();
            $customer_cards = CustomerLoyaltyCard::where('shop_id', $shop->id)->withTrashed()->get();
            foreach ($customer_cards as $card) {
                CustomerLoyaltyCardPoint::where('customer_loyalty_card_id', $card->id)->withTrashed()->forceDelete();
            }
            CustomerLoyaltyCard::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 方案
            ShopProgram::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopProgramGroup::where('shop_id', $shop->id)->delete();
            ShopProgramGroupContent::where('shop_id', $shop->id)->delete();
            CompanyProgram::where('company_id', $company->id)->withTrashed()->forceDelete();
            $customer_programs = CustomerProgram::where('shop_id', $shop->id)->withTrashed()->get();
            // 儲值記錄尚未完成（待補）
            // foreach( $customer_programs as $program ){
            //     CustomerProgramLog::where('customer_program_id',$program->id)->withTrashed()->forceDelete();
            // }
            CustomerProgram::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 儲值金
            ShopTopUp::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopTopUpRole::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopTopUpRoleLimit::where('shop_id', $shop->id)->delete();
            CompanyTopUp::where('company_id', $company->id)->withTrashed()->forceDelete();
            CustomerTopUp::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            // 會員卡
            ShopMembershipCard::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopMembershipCardRole::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopMembershipCardRoleLimit::where('shop_id', $shop->id)->delete();
            CompanyMembershipCard::where('company_id', $company->id)->withTrashed()->forceDelete();
            CustomerMembershipCard::where('shop_id', $shop->id)->withTrashed()->forceDelete();

            // 貼文
            ShopPost::where('shop_id', $shop->id)->withTrashed()->forceDelete();

            // 照片|作品集
            ShopPhoto::where('shop_id', $shop->id)->get();
            $albums = Album::where('shop_id', $shop->id)->get();
            foreach ($albums as $album) {
                $album_photos = AlbumPhoto::where('album_id', $album->id)->get();
                Photo::whereIn('id',$album_photos->pluck('photo_id')->toArray())->delete();
                AlbumPhoto::where('album_id', $album->id)->delete();
            }
            Album::where('shop_id', $shop->id)->delete();

            // 商家環境照片
            ShopPhoto::where('shop_id', $shop->id)->delete();

            // 刪除會員
            $shop_customers = ShopCustomer::where('shop_id', $shop->id)->withTrashed()->get();
            foreach ($shop_customers as $shop_customer) {
                $customers = Customer::where('id', $shop_customer->customer_id)->withTrashed()->get();

                foreach ($customers as $customer) {
                    if (
                        $customer->photo && !preg_match('/http/', $customer->photo)
                    ) {
                        $filePath = env('UPLOAD_IMG') . '/shilipai_customer/' . $customer->photo;
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    if (
                        $customer->banner && !preg_match('/http/', $customer->banner)
                    ) {
                        $filePath = env('UPLOAD_IMG') . '/shilipai_customer/' . $customer->banner;
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                }
            }
            ShopCustomer::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopCustomerTag::where('shop_id', $shop->id)->delete();
            ShopCustomerReservationTag::where('shop_id', $shop->id)->delete();
            CompanyCustomer::where('company_id', $company->id)->withTrashed()->forceDelete();

            // 刪除服務
            $shop_services = ShopService::where('shop_id', $shop->id)->withTrashed()->get();
            foreach ($shop_services as $service) {
                // 刪除關連資料
                ShopServiceStaff::where('shop_service_id', $service->id)->forceDelete();
                ShopServiceAdvance::where('shop_service_id', $service->id)->forceDelete();
            }
            ShopService::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            ShopServiceCategory::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            CompanyStaff::where('company_id', $company->id)->withTrashed()->forceDelete();
            CompanyServiceCategory::where('company_id', $company->id)->withTrashed()->forceDelete();
            CompanyService::where('company_id', $company->id)->withTrashed()->forceDelete();

            // 刪除員工
            $shop_staffs = ShopStaff::where('shop_id', $shop->id)->withTrashed()->get();
            foreach ($shop_staffs as $shop_staff) {
                $company_staff_info = CompanyStaff::find($shop_staff->company_staff_id);
            }
            CompanyTitle::where('company_id', $company->id)->withTrashed()->forceDelete();
            ShopStaff::where('shop_id', $shop->id)->withTrashed()->forceDelete();
            CompanyStaff::where('company_id', $company->id)->withTrashed()->forceDelete();

            // 系統通知
            SystemNotice::where('shop_id', $shop->id)->delete();

            // 營業時間
            ShopBusinessHour::where('shop_id', $shop->id)->delete();
            ShopClose::where('shop_id', $shop->id)->delete();
            ShopVacation::where('shop_id', $shop->id)->delete();

            // 刪除指定資料夾
            $path = env('OLD_OTHER') . '/' . $company_id . '/';
            if (is_dir($path)) {
                // 掃描一個資料夾內的所有資料夾和檔案並返回陣列
                $p = scandir($path);
                $cant = 0;
                foreach ($p as $val) {
                    // 排除目錄中的.和..
                    if ($val != "." && $val != "..") {
                        // 如果是檔案直接刪除
                        if (file_exists($path . $val)) {
                            $perms = fileperms($path . $val);
                            $check_permission = substr(sprintf('%o', $perms), -4);

                            if (preg_match('/777/', $check_permission) || preg_match('/775/', $check_permission)) {
                                unlink($path . $val);
                            } else {
                                $cant += 1;
                            }
                        }
                    }
                }
                if ($cant == 0) rmdir($path . '/');
            }

            // 商家設定檔
            ShopSet::where('shop_id', $shop->id)->withTrashed()->forceDelete();
        }

        if ( $company ) {
            Permission::where('company_id', $company->id)->withTrashed()->forceDelete();
        }

        Company::where('companyId', $company_id)->withTrashed()->forceDelete();
        Shop::where('alias', $company_id)->withTrashed()->forceDelete();

        return response()->json(['status' => true , 'message' => '刪除完畢' ]);
    }

    // 搬移指定商家資料
    public function move_data()
    {
        // 拿取舊實力派資料
        $insert_users = DB::connection('mysql2')->table('users')->whereIn('companyId', request('company_id'))->get();

        // 新增使用者
        $user_insert = [];
        foreach ($insert_users as $user) {
            $new_user = User::where('phone',$user->phone)->first();
            if (!$new_user) $new_user = new User;
            $new_user->name       = $user->real_name;
            $new_user->phone      = $user->phone;
            $new_user->password   = $user->password;
            $new_user->code       = $user->code ? User::withTrashed()->where('phone', $user->code)->value('id') : NULL;
            $new_user->created_at = $user->created_at;
            $new_user->updated_at = $user->updated_at;
            $new_user->deleted_at = $user->deleted_at;
            $new_user->save();
        }

        // 新增集團===================================================================
        $insert_companys = DB::connection('mysql2')->table('tb_company')->whereIn('id', request('company_id'))->get();

        // 新增
        $insert = [];
        foreach ($insert_companys as $company) {
    
            $company_info = DB::connection('mysql2')->table('company_infos')->where('companyId', $company->id)->first();

            $new_company = Company::where('old_companyId', $company_info->companyId)->first();
            if (!$new_company) $new_company = new Company;
            $new_company->companyId = $company_info->companyId;
            switch ($company_info->mode) {
                case 3: // 原實力派管家三年份
                case 1: // 原實力派管家一年份
                    $new_company->buy_mode_id = 2;
                    break;
                case 7: // 原美業官網進階方案（年繳）
                    $new_company->buy_mode_id = 1;
                    break;
                case 8: // 原美業官網進階方案（月繳）
                case 9: // 原美業官網基本方案
                    $new_company->buy_mode_id = 0;
                    break;
            }

            $new_company->name        = $company->name;
            $new_company->deadline    = $company_info->end_date;
            $new_company->gift_sms    = $company_info->gift_letter;
            $new_company->buy_sms     = $company_info->buy_letter;
            $new_company->terms       = $company_info->terms;
            $new_company->logo        = $company_info->store_logo ? str_replace('/upload/images/', '', $company_info->store_logo) : NULL;
            $new_company->banner      = $company_info->store_pic ? str_replace('/upload/images/', '', $company_info->store_pic) : NULL;
            $new_company->created_at  = $company_info->created_at;
            $new_company->updated_at  = $company_info->updated_at;
            $new_company->save();

            $new_shop = Shop::where('alias',$company_info->companyId)->first();
            if (!$new_shop) $new_shop = new Shop;
            $new_shop->company_id          = $new_company->id;
            $new_shop->deadline            = $company_info->end_date;
            $new_shop->buy_mode_id         = $new_company->buy_mode_id;
            $new_shop->gift_sms            = $company_info->gift_letter;
            $new_shop->buy_sms             = $company_info->buy_letter;
            $new_shop->alias               = $company_info->companyId;
            $new_shop->name                = $company->name;
            $new_shop->phone               = $company->phone;
            $new_shop->address             = $company_info->store_addr;
            $new_shop->logo                = $company_info->store_logo ? str_replace('/upload/images/', '', $company_info->store_logo) : NULL;
            $new_shop->banner              = $company_info->store_pic ? str_replace('/upload/images/', '', $company_info->store_pic) : NULL;
            $new_shop->info                = strip_tags($company_info->content);
            $new_shop->line                = $company_info->line_id;
            $new_shop->facebook_name       = $company_info->facebook_name;
            $new_shop->facebook_url        = $company_info->facebook_href;
            $new_shop->ig                  = $company_info->ig_name;
            $new_shop->web_name            = $company_info->web_name;
            $new_shop->web_url             = $company_info->web_href;
            $new_shop->operating_status_id = $company_info->status;
            $new_shop->created_at          = $company_info->created_at;
            $new_shop->updated_at          = $company_info->updated_at;
            $new_shop->save();

            // 新增設定檔
            $new_set = ShopSet::where('shop_id',$new_shop->id)->first();
            if (!$new_set) $new_set = new ShopSet;
            $new_set->shop_id           = $new_shop->id;
            $new_set->reservation_check = $company_info->reservation_check;
            $new_set->color_select      = 1;
            $new_set->color             = '#9440a1';
            $new_set->show_phone        = $company_info->show_phone;
            $new_set->save();
        }

        // 建立預設預約發送訊息===================================================================
        // wait待審核check確認/通過預約shop_cancel商家取消/不通過customer_cancel客戶取消change變更預約
        $type = ['wait', 'check', 'shop_cancel', 'customer_cancel', 'change'];
        $shops = Shop::whereIn('alias', request('company_id'))->get();
        foreach ($shops as $shop) {
            foreach ($type as $t) {
                switch ($t) {
                    case 'wait':
                        $msg = '「"會員名稱"」您好，您預約「"商家名稱"」的「"服務名稱"」已送出等待商家確認中，訂單細節：「"訂單連結"」';
                        break;
                    case 'check':
                        $msg = '您在「"商家名稱"」預約的服務已確認，「"預約日期時間"」期待您的到來，詳情：「"訂單連結"」';
                        break;
                    case 'shop_cancel':
                        $msg = '「"會員名稱"」您好，「"商家名稱"」在您預約的時段無法為您服務，可選擇其他時段，再次預約：「"再次預約連結"」';
                        break;
                    case 'customer_cancel':
                        $msg = '「"會員名稱"」您好，您已取消「"商家名稱"」預約「"預約日期時間"」「"服務名稱"」；再次預約：「"再次預約連結"」';
                        break;
                    case 'change':
                        $msg = '「"會員名稱"」您好，已將「"服務名稱"」的時間變更為「"預約日期時間"」；「"商家名稱"」期待您的到來，訂單細節：「"訂單連結"」';
                        break;
                }

                $message = ShopReservationMessage::where('shop_id',$shop->id)->where('type',$t)->first();
                if (!$message) $message = new ShopReservationMessage;
                $message->shop_id = $shop->id;
                $message->type    = $t;
                $message->content = $msg;
                $message->status  = 'N';
                $message->save();
            }
        }

        // 建立預設預約標籤===================================================================
        $type = [1, 2, 3, 4];
        $shops = Shop::whereIn('alias', request('company_id'))->get();
        foreach ($shops as $shop) {
            foreach ($type as $t) {
                $data = ShopReservationTag::where('shop_id',$shop->id)->where('type',$t)->first();
                if (!$data) $data = new ShopReservationTag;
                $data->shop_id = $shop->id;
                $data->type    = $t;
                $data->save();
            }
        }

        // 建立permission===================================================================
        foreach ($insert_users as $user) {
            $company = Company::where('old_companyId', $user->companyId)->first();
            if (!$company) continue;
            $shop = Shop::where('company_id', $company->id)->first();
            $user_id = User::where('password', $user->password)->value('id');
            if (!$user_id) {
                // 刪除公司與商家資料
                $company->delete();
                $shop->delete();
                continue;
            }

            switch ($company->buy_mode_id) {
                case 0: // 基本版
                    $permission = implode(',', PermissionMenu::where('basic', 1)->pluck('value')->toArray());
                    break;
                case 1: // 進階單人年繳
                    $permission = implode(',', PermissionMenu::where('plus', 1)->pluck('value')->toArray());
                    break;
                case 2: // 進階多人年繳
                    $permission = implode(',', PermissionMenu::where('plus_m', 1)->pluck('value')->toArray());
                    break;
                case 5: // 專業單人年繳
                    $permission = implode(',', PermissionMenu::where('pro', 1)->pluck('value')->toArray());
                    break;
                case 6: // 專業多人年繳
                    $permission = implode(',', PermissionMenu::where('pro_m', 1)->pluck('value')->toArray());
                    break;
            }

            // 建立公司權限
            $company_permission = Permission::where('user_id', $user_id)->where('company_id', $company->id)->first();
            if (!$company_permission) $company_permission = new Permission;
            $company_permission->user_id     = $user_id;
            $company_permission->company_id  = $company->id;
            $company_permission->buy_mode_id = $company->buy_mode_id;
            $company_permission->permission  = $permission;
            $company_permission->save();

            // 建立分店權限
            $shop_permission = Permission::where('user_id', $user_id)->where('shop_id', $shop->id)->first();
            if (!$shop_permission) $shop_permission = new Permission;
            $shop_permission->user_id     = $user_id;
            $shop_permission->company_id  = $company->id;
            $shop_permission->shop_id     = $shop->id;
            $shop_permission->buy_mode_id = $company->buy_mode_id;
            $shop_permission->permission  = $permission;
            $shop_permission->save();
        }

        // 營業資料===================================================================
        $weeks = ['星期一', '星期二', '星期三', '星期四', '星期五', '星期六', '星期日'];
        $shops = Shop::whereIn('alias', request('company_id'))->get();
        foreach ($shops as $shop) {
            $old_business = DB::connection('mysql2')->table('tb_businesshours')->where('companyId', $shop->alias)->where('deleteTime', NULL)->orderBy('startTime')->get();
            if (!$old_business) {
                foreach ($weeks as $k => $week) {
                    $new_business = ShopBusinessHour::where('shop_id',$shop->id)->where('week',$k+1)->first();
                    if (!$new_business) $new_business = new ShopBusinessHour;

                    $new_business->shop_id = $shop->id;
                    $new_business->type    = true;
                    $new_business->week    = $k + 1;
                    $new_business->start   = '10:00:00';
                    $new_business->end     = '22:00:00';
                    $new_business->save();
                }
            } else {
                foreach ($weeks as $k => $week) {
                    $check_week = 0;
                    foreach ($old_business as $old) {
                        if ($week == $old->selectedDay) {
                            $new_business = ShopBusinessHour::where('shop_id', $shop->id)->where('week', $k + 1)->first();
                            if (!$new_business) $new_business = new ShopBusinessHour;

                            $new_business->shop_id = $shop->id;
                            $new_business->type    = true;
                            $new_business->week    = $k + 1;
                            $new_business->start   = $old->startTime;
                            $new_business->end     = $old->endTime;
                            $new_business->save();
                            $check_week = 1;
                        }
                    }
                    if ($check_week == 0) {
                        $new_business = ShopBusinessHour::where('shop_id', $shop->id)->where('week', $k + 1)->first();
                        if (!$new_business) $new_business = new ShopBusinessHour;
                        $new_business->shop_id = $shop->id;
                        $new_business->type    = false;
                        $new_business->start   = NULL;
                        $new_business->end     = NULL;
                        $new_business->week    = $k + 1;
                        $new_business->save();
                    }
                }
            }
        }

        $shops = Shop::whereIn('alias', request('company_id'))->get();
        foreach ($shops as $shop) {
            $old_close = DB::connection('mysql2')->table('tb_closed')->where('companyId', $shop->alias)->where('deleteTime', NULL)->first();
            if ($old_close) {
                if ($old_close->weekType != '' && $old_close->monthType != '每週') {
                    $weekType = str_replace('星期一', 'Mon', $old_close->weekType);
                    $weekType = str_replace('星期二', 'Tue', $weekType);
                    $weekType = str_replace('星期三', 'Web', $weekType);
                    $weekType = str_replace('星期四', 'Thu', $weekType);
                    $weekType = str_replace('星期五', 'Fri', $weekType);
                    $weekType = str_replace('星期六', 'Sat', $weekType);
                    $weekType = str_replace('星期日', 'Sun', $weekType);


                    $type = str_replace('每個月第1週', 1, $old_close->monthType);
                    $type = str_replace('每個月第2週', 2, $type);
                    $type = str_replace('每個月第3週', 3, $type);
                    $type = str_replace('每個月第4週', 4, $type);

                    $new_close = ShopClose::where('shop_id',$shop->id)->where('type', (int)$type)->where('week', $weekType)->first();
                    if (!$new_close) $new_close = new ShopClose;
                    $new_close->shop_id = $shop->id;
                    $new_close->type    = (int)$type;
                    $new_close->week    = $weekType;
                    $new_close->save();
                } else {
                    $new_close = ShopClose::where('shop_id', $shop->id)->where('type', 6)->first();
                    if (!$new_close) $new_close = new ShopClose;
                    $new_close->shop_id = $shop->id;
                    $new_close->type    = 6;
                    $new_close->week    = NULL;
                    $new_close->save();
                }
            }
        }

        // 節慶通知預設資料============================================================
        $festival_default = ShopFestivalNotice::where('shop_id', NULL)->where('default', 'Y')->get();
        $shops = Shop::whereIn('alias', request('company_id'))->get();
        foreach ($shops as $shop) {
            $shop_festivals = ShopFestivalNotice::where('shop_id', $shop->id)->get();
            $check_data = [];
            foreach ($shop_festivals as $festival) {
                if ($festival->default == 'Y' && in_array($festival->name, $festival_default->pluck('name')->toArray())) $check_data[] = $festival->name;
            }

            foreach ($festival_default->whereNotIn('name', $check_data) as $default) {
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
        }

        // 環境照片===================================================================
        $shops = Shop::whereIn('alias', request('company_id'))->get();
        foreach ($shops as $shop) {
            $old_photos = DB::connection('mysql2')->table('company_photos')->where('companyId', $shop->alias)->get();
            foreach ($old_photos as $photo) {
                $new_photo = ShopPhoto::where('shop_id',$shop->id)->where('photo', $photo->path.'.jpg')->first();
                if (!$new_photo) $new_photo = new ShopPhoto;
                $new_photo->shop_id = $shop->id;
                $new_photo->photo   = $photo->path ? $photo->path . '.jpg' : NULL;
                $new_photo->save();
            }
        }

        // 產品
        $companies = Company::whereIn('companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $shop    = Shop::where('company_id', $company->id)->first();
            $old_set = DB::connection('mysql2')->table('company_infos')->where('companyId', $company->companyId)->first();

            CompanyProductCategory::where('company_id', $company->id)->forceDelete();
            CompanyProduct::where('company_id', $company->id)->forceDelete();
            ShopProduct::where('shop_id', $shop->id)->forceDelete();
            ShopProductCategory::where('shop_id', $shop->id)->forceDelete();

            // 分類
            $old_product_category = DB::connection('mysql2')
                                        ->table('tb_productcategories')
                                        ->where('companyId', $company->companyId)
                                        ->where('classification', 1)
                                        ->where('deleteTime', NULL)
                                        ->get();
            foreach ($old_product_category as $old_category) {
                $new_category = new CompanyProductCategory;
                $new_category->company_id = $company->id;
                $new_category->name       = $old_category->name;
                $new_category->info       = strip_tags($old_category->info);
                $new_category->photo      = $old_category->imageUrl ? $old_category->imageUrl . '.jpg' : NULL;
                $new_category->sequence   = $old_category->sequence;
                $new_category->save();

                $new_shop_category = new ShopProductCategory;
                $new_shop_category->company_product_category_id = $new_category->id;
                $new_shop_category->shop_id                     = $shop->id;
                $new_shop_category->name                        = $old_category->name;
                $new_shop_category->info                        = strip_tags($old_category->info);
                $new_shop_category->photo                       = $old_category->imageUrl ? $old_category->imageUrl . '.jpg' : NULL;
                $new_shop_category->sequence                    = $old_category->sequence;
                $new_shop_category->save();

                // 此分類下的服務
                $old_products = DB::connection('mysql2')
                                    ->table('tb_commodity')
                                    ->where('companyId', $company->companyId)
                                    ->where('pcId', $old_category->id)
                                    ->where('deleteTime', NULL)
                                    ->get();

                foreach ($old_products as $o_product) {
                    $new_product = new CompanyProduct;
                    $new_product->company_id                  = $company->id;
                    $new_product->company_product_category_id = $new_category->id;
                    $new_product->name                        = $o_product->name;
                    $new_product->info                        = strip_tags($o_product->info);
                    $new_product->photo                       = $o_product->imageUrl ? $o_product->imageUrl . '.jpg' : NULL;
                    $new_product->sequence                    = $o_product->sequence;
                    $new_product->price                       = $o_product->price;
                    $new_product->basic_price                 = $o_product->lprice;
                    $new_product->status                      = $o_product->web_show == 1 ? 'published' : 'pending';
                    $new_product->save();

                    $new_shop_service = new ShopProduct;
                    $new_shop_service->shop_id                  = $shop->id;
                    $new_shop_service->shop_product_category_id = $new_shop_category->id;
                    $new_shop_service->company_product_id       = $new_product->id;
                    $new_shop_service->name                     = $o_product->name;
                    $new_shop_service->info                     = strip_tags($o_product->info);
                    $new_shop_service->photo                    = $o_product->imageUrl ? $o_product->imageUrl . '.jpg' : NULL;
                    $new_shop_service->sequence                 = $o_product->sequence;
                    $new_shop_service->price                    = $o_product->price;
                    $new_shop_service->basic_price              = $o_product->lprice;
                    $new_shop_service->status                   = $o_product->web_show == 1 ? 'published' : 'pending';
                    $new_shop_service->old_id                   = $o_product->id;
                    $new_shop_service->save();
                }
            }
        }

        // 服務項目/加值項目===========================================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $shop    = Shop::where('company_id', $company->id)->first();
            $old_set = DB::connection('mysql2')->table('company_infos')->where('companyId', $company->companyId)->first();

            CompanyServiceCategory::where('company_id', $company->id)->forceDelete();
            CompanyService::where('company_id',$company->id)->forceDelete();
            ShopService::where('shop_id', $shop->id)->forceDelete();
            ShopServiceCategory::where('shop_id', $shop->id)->forceDelete();

            // 分類
            $old_service_category = DB::connection('mysql2')
                                        ->table('tb_productcategories')
                                        ->where('companyId', $company->companyId)
                                        ->where('classification', 2)
                                        ->where('deleteTime', NULL)
                                        ->get();
            foreach ($old_service_category as $old_category) {
                $new_category = new CompanyServiceCategory;
                $new_category->company_id = $company->id;
                $new_category->type       = 'service';
                $new_category->name       = $old_category->name;
                $new_category->info       = $old_category->info;
                $new_category->photo      = $old_category->imageUrl ? $old_category->imageUrl . '.jpg' : NULL;
                $new_category->sequence   = $old_category->sequence;
                $new_category->save();

                $new_shop_category = new ShopServiceCategory;
                $new_shop_category->company_category_id = $new_category->id;
                $new_shop_category->shop_id             = $shop->id;
                $new_shop_category->type                = 'service';
                $new_shop_category->name                = $old_category->name;
                $new_shop_category->info                = $old_category->info;
                $new_shop_category->photo               = $old_category->imageUrl ? $old_category->imageUrl . '.jpg' : NULL;
                $new_shop_category->sequence            = $old_category->sequence;
                $new_shop_category->save();

                // 此分類下的服務
                $old_services = DB::connection('mysql2')
                                    ->table('tb_commodity')
                                    ->where('companyId', $company->companyId)
                                    ->where('pcId', $old_category->id)
                                    ->where('deleteTime', NULL)
                                    ->get();

                foreach ($old_services as $o_service) {
                    $new_service = new CompanyService;
                    $new_service->company_id          = $company->id;
                    $new_service->company_category_id = $new_category->id;
                    $new_service->type                = 'service';
                    $new_service->name                = $o_service->name;
                    $new_service->info                = $o_service->info;
                    $new_service->photo               = $o_service->imageUrl ? $o_service->imageUrl . '.jpg' : NULL;
                    $new_service->sequence            = $o_service->sequence;
                    $new_service->price               = $o_service->price;
                    $new_service->basic_price         = $o_service->lprice;

                    if ($o_service->up == 'Y') {
                        $new_service->show_type = 3;
                        $new_service->show_text = $o_service->price;
                    } elseif ($o_service->text_price) {
                        $new_service->show_type = 4;
                        $new_service->show_text = $o_service->text_price;
                    } elseif ($old_set->show_price == 1) {
                        $new_service->show_type = 1;
                    } else {
                        $new_service->show_type = 2;
                    }

                    $new_service->show_time    = $old_set->show_time;
                    $new_service->service_time = $o_service->needTime;
                    $new_service->status       = $o_service->web_show == 1 ? 'published' : 'pending';
                    $new_service->save();

                    $new_shop_service = new ShopService;
                    $new_shop_service->shop_id                  = $shop->id;
                    $new_shop_service->shop_service_category_id = $new_shop_category->id;
                    $new_shop_service->company_service_id       = $new_service->id;
                    $new_shop_service->type                     = 'service';
                    $new_shop_service->name                     = $o_service->name;
                    $new_shop_service->info                     = $o_service->info;
                    $new_shop_service->photo                    = $o_service->imageUrl ? $o_service->imageUrl . '.jpg' : NULL;
                    $new_shop_service->sequence                 = $o_service->sequence;
                    $new_shop_service->price                    = $o_service->price;
                    $new_shop_service->basic_price              = $o_service->lprice;

                    if ($o_service->up == 'Y') {
                        $new_shop_service->show_type = 3;
                        $new_shop_service->show_text = $o_service->price;
                    } elseif ($o_service->text_price) {
                        $new_shop_service->show_type = 4;
                        $new_shop_service->show_text = $o_service->text_price;
                    } elseif ($old_set->show_price == 1) {
                        $new_shop_service->show_type = 1;
                    } else {
                        $new_shop_service->show_type = 2;
                    }

                    $new_shop_service->show_time    = $old_set->show_time;
                    $new_shop_service->service_time = $o_service->needTime;
                    $new_shop_service->status       = $o_service->web_show == 1 ? 'published' : 'pending';
                    $new_shop_service->old_id       = $o_service->id;
                    $new_shop_service->save();
                }
            }

            // 加值項目
            $old_add_items = DB::connection('mysql2')
                                ->table('tb_commodity')
                                ->where('companyId', $company->companyId)
                                ->where('classId', 5)
                                ->where('deleteTime', NULL)
                                ->get();
            foreach ($old_add_items as $item) {
                $new_advance = new CompanyService;
                $new_advance->company_id  = $company->id;
                $new_advance->type        = 'advance';
                $new_advance->name        = $item->name;
                $new_advance->info        = $item->info;
                $new_advance->photo       = $item->imageUrl ? $item->imageUrl . '.jpg' : NULL;
                $new_advance->sequence    = $item->sequence;
                $new_advance->price       = $item->price;
                $new_advance->basic_price = $item->lprice;

                if ($item->up == 'Y') {
                    $new_advance->show_type = 3;
                    $new_advance->show_text = $item->price;
                } elseif ($item->text_price) {
                    $new_advance->show_type = 4;
                    $new_advance->show_text = $item->text_price;
                } elseif ($old_set->show_price == 1) {
                    $new_advance->show_type = 1;
                } else {
                    $new_advance->show_type = 2;
                }

                $new_advance->show_time    = $old_set->show_time;
                $new_advance->service_time = $item->needTime;
                $new_advance->status       = $item->web_show == 1 ? 'published' : 'pending';
                $new_advance->save();

                $new_shop_advance = new ShopService;
                $new_shop_advance->shop_id            = $shop->id;
                $new_shop_advance->company_service_id = $new_advance->id;
                $new_shop_advance->type               = 'advance';
                $new_shop_advance->name               = $item->name;
                $new_shop_advance->info               = $item->info;
                $new_shop_advance->photo              = $item->imageUrl ? $item->imageUrl . '.jpg' : NULL;
                $new_shop_advance->sequence           = $item->sequence;
                $new_shop_advance->price              = $item->price;
                $new_shop_advance->basic_price        = $item->lprice;
                $new_shop_advance->old_id             = $item->id;

                if ($item->up == 'Y') {
                    $new_shop_advance->show_type = 3;
                    $new_shop_advance->show_text = $item->price;
                } elseif ($item->text_price) {
                    $new_shop_advance->show_type = 4;
                    $new_shop_advance->show_text = $item->text_price;
                } elseif ($old_set->show_price == 1) {
                    $new_shop_advance->show_type = 1;
                } else {
                    $new_shop_advance->show_type = 2;
                }

                $new_shop_advance->show_time    = $old_set->show_time;
                $new_shop_advance->service_time = $item->needTime;
                $new_shop_advance->status       = $item->web_show == 1 ? 'published' : 'pending';
                $new_shop_advance->save();
            }
        }

        // 服務與加值項目關連
        $old_datas = DB::connection('mysql2')->table('match_services')->get();
        foreach ($old_datas as $o_data) {
            if (ShopService::where('old_id', $o_data->serviceId)->value('id')) {
                $new_shop_service_advance = ShopServiceAdvance::where('shop_service_id', ShopService::where('old_id', $o_data->serviceId)->value('id'))
                                                              ->where('shop_advance_id', ShopService::where('old_id', $o_data->addId)->value('id'))
                                                              ->first();
                if (!$new_shop_service_advance) $new_shop_service_advance = new ShopServiceAdvance;
                $new_shop_service_advance->shop_service_id = ShopService::where('old_id', $o_data->serviceId)->value('id');
                $new_shop_service_advance->shop_advance_id = ShopService::where('old_id', $o_data->addId)->value('id');
                $new_shop_service_advance->touch();
            }
        }

        // 員工資料===================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $old_staffs = DB::connection('mysql2')
                                ->table('tb_staff')
                                ->where('companyId', $company->companyId)
                                ->where('deleteTime', NULL)
                                ->get();
            foreach ($old_staffs as $o_staff) {
                $new_company_staff = CompanyStaff::where('company_id', $company->id)
                                                 ->where('name', $o_staff->name)
                                                 ->first();
                if (!$new_company_staff) $new_company_staff = new CompanyStaff;
                $new_company_staff->company_id          = $company->id;
                $new_company_staff->name                = $o_staff->name;
                $new_company_staff->info                = $o_staff->info;
                $new_company_staff->photo               = $o_staff->photoUrl ? $o_staff->photoUrl . '.jpg' : NULL;
                $new_company_staff->line_id             = $o_staff->lineId;
                $new_company_staff->calendar_token      = $o_staff->calendar_token;
                $new_company_staff->phone               = $o_staff->phone;
                $new_company_staff->email               = $o_staff->email;
                $new_company_staff->calendar_color      = $o_staff->color ?: '#AC8CD5';
                $new_company_staff->calendar_color_type = $o_staff->color ? 2 : 1;

                // 職稱
                if ($o_staff->position) {
                    // 建立集團用的職稱
                    $title = CompanyTitle::where('company_id', $company->id)->where('name', $o_staff->position)->first();
                    if (!$title) {
                        $title = new CompanyTitle;
                        $title->company_id = $company->id;
                        $title->name       = $o_staff->position;
                        $title->save();
                    }
                    $new_company_staff->company_title_id_a = $title->id;
                }

                // 利用電話找出user
                if ($o_staff->phone && $o_staff->master == 0) {
                    // 此員工身分是老闆，判斷是否需建立帳號
                    $user = User::where('phone', $o_staff->phone)->first();
                    if ($user) {
                        $new_company_staff->user_id = $user->id;

                        $user->photo = $o_staff->photoUrl ? $o_staff->photoUrl . '.jpg' : NULL;
                        $user->save();
                    } else {
                        $user = new User;
                        $user->name     = $o_staff->name;
                        $user->phone    = $o_staff->phone;
                        $user->photo    = $o_staff->photoUrl ? $o_staff->photoUrl . '.jpg' : NULL;
                        $user->password = password_hash('123456', PASSWORD_DEFAULT);
                        $user->save();

                        // company_staff資料加入user_id
                        $new_company_staff->user_id = $user->id;
                        $new_company_staff->save();

                        switch ($company->buy_mode_id) {
                            case 0:
                                $permission = implode(',', PermissionMenu::where('basic', 1)->pluck('value')->toArray());
                                break;
                            case 1:
                                $permission = implode(',', PermissionMenu::where('plus', 1)->pluck('value')->toArray());
                                break;
                            case 2:
                                $permission = implode(',', PermissionMenu::where('pro_cs', 1)->pluck('value')->toArray());
                                break;
                        }

                        // 建立公司權限
                        $company_permission = new Permission;
                        $company_permission->user_id     = $user->id;
                        $company_permission->company_id  = $company->id;
                        $company_permission->buy_mode_id = $company->buy_mode_id;
                        $company_permission->permission  = $permission;
                        $company_permission->save();

                        // 建立分店權限
                        $shop_permission = Permission::where('user_id', $user_id)->where('shop_id', $shop->id)->first();
                        if (!$shop_permission) $shop_permission = new Permission;
                        $shop_permission->user_id     = $user_id;
                        $shop_permission->company_id  = $company->id;
                        $shop_permission->shop_id     = $shop->id;
                        $shop_permission->buy_mode_id = $company->buy_mode_id;
                        $shop_permission->permission  = $permission;
                        $shop_permission->save();
                    }

                    $new_company_staff->save();

                    $new_shop_staff = ShopStaff::where('shop_id', $company->shop_infos->where('alias', $company->companyId)->first()->id)
                                               ->where('company_staff_id', $new_company_staff->id)
                                               ->where('company_title_id_a', $new_company_staff->company_title_id_a)
                                               ->where('old_id', $o_staff->id)
                                               ->first();
                    if (!$new_shop_staff) $new_shop_staff = new ShopStaff;
                    $new_shop_staff->user_id            = $user->id;
                    $new_shop_staff->shop_id            = $company->shop_infos->where('alias', $company->companyId)->first()->id;
                    $new_shop_staff->company_staff_id   = $new_company_staff->id;
                    $new_shop_staff->company_title_id_a = $new_company_staff->company_title_id_a;
                    $new_shop_staff->old_id             = $o_staff->id;
                    $new_shop_staff->master             = Permission::where('user_id', $user->id)->where('company_id', $company->id)->where('shop_id', NULL)->first() ? 0 : 1;
                    $new_shop_staff->save();

                    // 建立員工權限
                    $permission = Permission::where('user_id', $user->id)
                                            ->where('company_id', $company->id)
                                            ->where('shop_id', $company->shop_infos->where('alias', $company->companyId)->first()->id)
                                            ->where('shop_staff_id', $new_shop_staff->id)
                                            ->first();
                    if (!$permission) $permission = new Permission;
                    $permission->user_id       = $user->id;
                    $permission->company_id    = $company->id;
                    $permission->shop_id       = $company->shop_infos->where('alias', $company->companyId)->first()->id;
                    $permission->shop_staff_id = $new_shop_staff->id;
                    $permission->buy_mode_id   = $company->buy_mode_id;
                    $permission->permission    = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->pluck('value')->toArray());
                    $permission->save();
                } else {
                    // 員工建立帳號
                    if ($o_staff->phone) {
                        $user = User::where('phone', $o_staff->phone)->first();
                        if (!$user) $user = new User;
                        $user->name     = $o_staff->name;
                        $user->phone    = $o_staff->phone;
                        $user->photo    = $o_staff->photoUrl ? $o_staff->photoUrl . '.jpg' : NULL;
                        $user->password = password_hash('123456', PASSWORD_DEFAULT);
                        $user->save();

                        // company_staff資料加入user_id
                        $new_company_staff->user_id = $user->id;
                        $new_company_staff->save();

                        $new_shop_staff = ShopStaff::where('shop_id', $company->shop_infos->where('alias', $company->companyId)->first()->id)
                                                   ->where('company_staff_id', $new_company_staff->id)
                                                   ->where('company_title_id_a', $new_company_staff->company_title_id_a)
                                                   ->where('old_id', $o_staff->id)
                                                   ->first();
                        if (!$new_shop_staff) $new_shop_staff = new ShopStaff;
                        $new_shop_staff->user_id            = $user->id;
                        $new_shop_staff->shop_id            = $company->shop_infos->where('alias', $company->companyId)->first()->id;
                        $new_shop_staff->company_staff_id   = $new_company_staff->id;
                        $new_shop_staff->company_title_id_a = $new_company_staff->company_title_id_a;
                        $new_shop_staff->old_id             = $o_staff->id;
                        $new_shop_staff->save();

                        // 建立員工權限
                        $permission = Permission::where('user_id', $user->id)
                                            ->where('company_id', $company->id)
                                            ->where('shop_id', $company->shop_infos->where('alias', $company->companyId)->first()->id)
                                            ->where('shop_staff_id', $new_shop_staff->id)
                                            ->first();
                        if (!$permission) $permission = new Permission;
                        $permission->user_id       = $user->id;
                        $permission->company_id    = $company->id;
                        $permission->shop_id       = $company->shop_infos->where('alias', $company->companyId)->first()->id;
                        $permission->shop_staff_id = $new_shop_staff->id;
                        $permission->buy_mode_id   = $company->buy_mode_id;
                        $permission->permission    = implode(',', PermissionMenu::where('value', 'like', 'staff_%')->pluck('value')->toArray());
                        $permission->save();
                    }
                }
            }
        }

        // 服務與員工關連
        $old_datas = DB::connection('mysql2')->table('staff_services')->get();
        foreach ($old_datas as $o_data) {
            if (ShopService::where('old_id', $o_data->commodity_id)->value('id') && ShopStaff::where('old_id', $o_data->staff_id)->value('id')) {
                $id = ShopStaff::where('old_id', $o_data->staff_id)->value('id');
                $new_shop_service_advance = ShopServiceStaff::where('shop_service_id', ShopService::where('old_id', $o_data->commodity_id)->value('id'))
                                                            ->where('shop_staff_id', ShopStaff::where('old_id', $o_data->staff_id)->value('id'))
                                                            ->first();
                if (!$new_shop_service_advance) $new_shop_service_advance = new ShopServiceStaff;
                $new_shop_service_advance->shop_service_id = ShopService::where('old_id', $o_data->commodity_id)->value('id');
                $new_shop_service_advance->shop_staff_id   = ShopStaff::where('old_id', $o_data->staff_id)->value('id');
                $new_shop_service_advance->touch();
            }
        }

        // 新增員工營業時間
        $shops = Shop::whereIn('alias', request('company_id'))->get();
        $insert_open  = [];
        $insert_close = [];
        foreach ($shops as $shop) {
            foreach ($shop->shop_staffs as $staff) {
                foreach ($shop->shop_business_hours as $open) {
                    $staff_open = ShopBusinessHour::where('shop_id', $shop->id)
                                                  ->where('shop_staff_id', $staff->id)
                                                  ->where('week', $open->week)
                                                  ->first();
                    if (!$staff_open) {
                        $insert_open[] = [
                            'shop_id'       => $shop->id,
                            'shop_staff_id' => $staff->id,
                            'type'          => $open->type,
                            'week'          => $open->week,
                            'start'         => $open->start,
                            'end'           => $open->end,
                        ];
                    }
                }

                if ($shop->shop_close) {
                    $staff_close = ShopClose::where('shop_id', $shop->id)
                                            ->where('shop_staff_id', $staff->id)
                                            ->where('week', $shop->shop_close->week)
                                            ->first();
                    if (!$staff_close) {
                        $insert_close[] = [
                            'shop_id'       => $shop->id,
                            'shop_staff_id' => $staff->id,
                            'type'          => $shop->shop_close->type,
                            'week'          => $shop->shop_close->week,
                        ];
                    }
                }
            }
        }
        ShopBusinessHour::insert($insert_open);
        ShopClose::insert($insert_close);

        // 付款記錄===================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $old_orders = DB::connection('mysql2')
                                    ->table('orders')
                                    ->where('store_id', $company->companyId)
                                    ->get();
            foreach ($old_orders as $old) {
                $new_order = Order::where('oid', $old->oid)->first();
                if (!$new_order) $new_order = new Order;
                $new_order->oid         = $old->oid;
                $new_order->user_id     = Permission::where('company_id', $company->id)->value('user_id');
                $new_order->company_id  = $company->id;
                switch ($old->buy_mode_id) {
                    case 3: // 原實力派管家三年份
                    case 1: // 原實力派管家一年份
                        $new_order->buy_mode_id = 2;
                        $new_order->note = BuyMode::where('id', $new_order->buy_mode_id)->value('title');
                        break;
                    case 7: // 原美業官網進階方案（年繳）
                        $new_order->buy_mode_id = 1;
                        $new_order->note = BuyMode::where('id', $new_order->buy_mode_id)->value('title');
                        break;
                    case 8: // 原美業官網進階方案（月繳）
                    case 9: // 原美業官網基本方案
                        $new_order->buy_mode_id = 0;
                        $new_order->note = BuyMode::where('id', $new_order->buy_mode_id)->value('title');
                        break;
                    default:
                        if ($old->buy_mode_id == '') $new_order->buy_mode_id = 50;
                        $new_order->note = $old->note;
                        break;
                }

                $new_order->member_addresses_id = $old->member_addresses_id;
                $new_order->code                = User::withTrashed()->where('phone', $old->recommend)->value('id');
                $new_order->discount_id         = $old->discount_id;
                $new_order->price               = $old->price;
                $new_order->order_note          = $old->order_note;
                $new_order->pay_return          = $old->pay_return;
                $new_order->message             = $old->message;
                $new_order->pay_status          = $old->pay_status;
                $new_order->pay_date            = $old->pay_date;
                $new_order->pay_type            = $old->pay_type;
                $new_order->created_at          = $old->created_at;
                $new_order->updated_at          = $old->updated_at;
                $new_order->save();
            }

            $delete_order = DB::connection('mysql2')
                                        ->table('orders')
                                        ->where('store_id', $company->companyId)
                                        ->where('deleted_at', '!=', NULL)
                                        ->get();
            foreach ($delete_order as $old) {
                $new_order = Order::where('oid', $old->oid)->first();
                if (!$new_order) $new_order = new Order;
                $new_order->oid         = $old->oid;
                $new_order->user_id     = Permission::where('company_id', $company->id)->value('user_id');
                $new_order->company_id  = $company->id;
                switch ($old->buy_mode_id) {
                    case 3: // 原實力派管家三年份
                    case 1: // 原實力派管家一年份
                        $new_order->buy_mode_id = 2;
                        $new_order->note = BuyMode::where('id', $new_order->buy_mode_id)->value('title');
                        break;
                    case 7: // 原美業官網進階方案（年繳）
                        $new_order->buy_mode_id = 1;
                        $new_order->note = BuyMode::where('id', $new_order->buy_mode_id)->value('title');
                        break;
                    case 8: // 原美業官網進階方案（月繳）
                    case 9: // 原美業官網基本方案
                        $new_order->buy_mode_id = 0;
                        $new_order->note = BuyMode::where('id', $new_order->buy_mode_id)->value('title');
                        break;
                    default:
                        $new_order->note = $old->note;
                        break;
                }

                $new_order->member_addresses_id = $old->member_addresses_id;
                $new_order->code                = User::withTrashed()->where('phone', $old->recommend)->value('id');
                $new_order->discount_id         = $old->discount_id;
                $new_order->price               = $old->price;

                $new_order->order_note          = $old->order_note;
                $new_order->pay_return          = $old->pay_return;
                $new_order->message             = $old->message;
                $new_order->pay_status          = $old->pay_status;
                $new_order->pay_date            = $old->pay_date;
                $new_order->pay_type            = $old->pay_type;
                $new_order->created_at          = $old->created_at;
                $new_order->updated_at          = $old->updated_at;
                $new_order->save();
            }
        }

        // 優惠券=======================================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $old_coupons = DB::connection('mysql2')
                                ->table('coupons')
                                ->where('companyId', $company->companyId)
                                ->get();
            $shop = $company->shop_infos->first();
            foreach ($old_coupons as $o_coupon) {
                $new_company_coupon = CompanyCoupon::where('company_id', $company->id)
                                                   ->where('type', $o_coupon->type)
                                                   ->where('title', $o_coupon->title)
                                                   ->withTrashed()
                                                   ->first();
                if (!$new_company_coupon) $new_company_coupon = new CompanyCoupon;
                $new_company_coupon->company_id      = $company->id;
                $new_company_coupon->type            = $o_coupon->type;
                $new_company_coupon->title           = $o_coupon->title;
                $new_company_coupon->description     = $o_coupon->description;
                $new_company_coupon->start_date      = $o_coupon->start_date;
                $new_company_coupon->end_date        = $o_coupon->end_date;
                $new_company_coupon->consumption     = $o_coupon->consumption;
                $new_company_coupon->discount        = $o_coupon->discount;
                $new_company_coupon->price           = $o_coupon->price;
                $new_company_coupon->count_type      = $o_coupon->count_type;
                $new_company_coupon->count           = $o_coupon->count;

                if ($o_coupon->type == 'gift') {
                    $new_company_coupon->second_type = $o_coupon->second_type;
                } elseif ($o_coupon->type == 'free') {
                    $new_company_coupon->second_type = $o_coupon->second_type == 1 ? 3 : 4;
                } elseif ($o_coupon->type == 'cash') {
                    $new_company_coupon->second_type = $o_coupon->second_type == 1 ? 5 : 6;
                }

                if ($o_coupon->type == 'gift') {
                    $new_company_coupon->commodityId = $o_coupon->commodityId ? '' : NULL;
                } else {
                    $new_company_coupon->commodityId = $o_coupon->commodityId ? ShopService::where('old_id', $o_coupon->commodityId)->value('id') : NULL;
                }
                $new_company_coupon->self_definition = $o_coupon->self_definition;
                $new_company_coupon->photo_type      = $o_coupon->photo_type;
                $new_company_coupon->photo           = $o_coupon->photo ? $o_coupon->photo . '.jpg' : NULL;
                $new_company_coupon->use_type        = $o_coupon->use_type;
                $new_company_coupon->get_level       = $o_coupon->get_level;
                $new_company_coupon->customer_level  = $o_coupon->customer_level;
                $new_company_coupon->show_type       = $o_coupon->show_type;
                $new_company_coupon->limit           = $o_coupon->limit;
                $new_company_coupon->content         = $o_coupon->content;
                $new_company_coupon->status          = $o_coupon->status;
                $new_company_coupon->view            = $o_coupon->view;
                $new_company_coupon->created_at      = $o_coupon->created_at;
                $new_company_coupon->updated_at      = $o_coupon->updated_at;
                $new_company_coupon->deleted_at      = $o_coupon->deleted_at;
                $new_company_coupon->save();

                $new_coupon = ShopCoupon::where('shop_id', $shop->id)
                                        ->where('company_coupon_id', $new_company_coupon->id)
                                        ->where('old_id', $o_coupon->id)
                                        ->withTrashed()
                                        ->first();
                if (!$new_coupon) $new_coupon = new ShopCoupon;
                $new_coupon->shop_id           = $shop->id;
                $new_coupon->company_coupon_id = $new_company_coupon->id;
                $new_coupon->view              = $o_coupon->view;
                $new_coupon->status            = $o_coupon->status;
                $new_coupon->created_at        = $o_coupon->created_at;
                $new_coupon->updated_at        = $o_coupon->updated_at;
                $new_coupon->deleted_at        = $o_coupon->deleted_at;
                $new_coupon->old_id            = $o_coupon->id;
                $new_coupon->save();

                $old_limits = DB::connection('mysql2')
                                ->table('coupon_limits')
                                ->where('coupon_id', $o_coupon->id)
                                ->get();
                CompanyCouponLimit::where('company_id', $company->id)->delete();
                ShopCouponLimit::where('shop_id', $shop->id)->delete();
                foreach ($old_limits as $o_limit) {
                    $new_company_limit = new CompanyCouponLimit;
                    $new_company_limit->company_id        = $company->id;
                    $new_company_limit->company_coupon_id = $new_company_coupon->id;

                    $old_commodity = DB::connection('mysql2')
                        ->table('tb_commodity')
                        ->where('id', $o_limit->commodityId)
                        ->first();
                    $new_company_limit->type = $old_commodity->classId == 1 ? 'product' : 'service';
                    if ($old_commodity->classId == 1) {
                        // 產品
                        $new_company_limit->commodity_id = NULL;
                    } else {
                        // 服務
                        $new_company_limit->commodity_id = ShopService::where('old_id', $old_commodity->id)->value('id');
                    }
                    $new_company_limit->created_at = $new_coupon->created_at;
                    $new_company_limit->updated_at = $new_coupon->updated_at;
                    $new_company_limit->save();
                }
            }
        }

        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        $company_coupons = CompanyCoupon::whereIn('company_id', $companies->pluck('id')->toArray())->withTrashed()->get();
        foreach ($company_coupons as $coupon) {
            $shop_coupon = ShopCoupon::where('company_coupon_id', $coupon->id)->withTrashed()->first();
            if ($shop_coupon) {
                $shop_coupon->type            = $coupon->type;
                $shop_coupon->title           = $coupon->title;
                $shop_coupon->description     = $coupon->description;
                $shop_coupon->start_date      = $coupon->start_date;
                $shop_coupon->end_date        = $coupon->end_date;
                $shop_coupon->consumption     = $coupon->consumption;
                $shop_coupon->discount        = $coupon->discount;
                $shop_coupon->price           = $coupon->price;
                $shop_coupon->count_type      = $coupon->count_type;
                $shop_coupon->count           = $coupon->count;
                $shop_coupon->second_type     = $coupon->second_type;
                $shop_coupon->commodityId     = $coupon->commodityId;
                $shop_coupon->self_definition = $coupon->self_definition;
                $shop_coupon->photo_type      = $coupon->photo_type;
                $shop_coupon->photo           = $coupon->photo;
                $shop_coupon->use_type        = $coupon->use_type;
                $shop_coupon->get_level       = $coupon->get_level;
                $shop_coupon->customer_level  = $coupon->customer_level;
                $shop_coupon->show_type       = $coupon->show_type;
                $shop_coupon->limit           = $coupon->limit;
                $shop_coupon->content         = $coupon->content;
                $shop_coupon->save();

                // 補上使用限制
                if ($coupon->limit == 4) {
                    $coupon_limits = CompanyCouponLimit::where('company_coupon_id', $coupon->id)->get();
                    ShopCouponLimit::where('shop_coupon_id', $shop_coupon->id)->delete();
                    foreach ($coupon_limits as $limit) {
                        $commodity_id = NULL;
                        if ($limit->type == 'service') {
                            $commodity_id = ShopService::where('shop_id', $shop_coupon->shop_id)->where('company_service_id', $limit->commodity_id)->first();
                        }

                        $shop_coupon_limit = new ShopCouponLimit;
                        $shop_coupon_limit->shop_id        = $shop_coupon->shop_id;
                        $shop_coupon_limit->shop_coupon_id = $shop_coupon->id;
                        $shop_coupon_limit->type           = $limit->type;
                        $shop_coupon_limit->commodity_id   = $commodity_id;
                        $shop_coupon_limit->save();
                    }
                }
            }
        }

        // 集點卡=======================================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $old_cards = DB::connection('mysql2')
                                    ->table('reward_cards')
                                    ->where('companyId', $company->companyId)
                                    ->get();

            $shop = $company->shop_infos->first();
            foreach ($old_cards as $o_card) {
                $new_company_card = CompanyLoyaltyCard::where('company_id', $company->id)
                                                      ->where('name', $o_card->name)
                                                      ->first();
                if (!$new_company_card) $new_company_card = new CompanyLoyaltyCard;
                $new_company_card->company_id           = $company->id;
                $new_company_card->name                 = $o_card->name;
                $new_company_card->condition_type       = $o_card->condition_type;
                $new_company_card->condition            = $o_card->condition;
                $new_company_card->full_point           = $o_card->full_point;
                $new_company_card->first_point          = $o_card->first_point;
                $new_company_card->deadline_type        = $o_card->deadline_type;
                $new_company_card->year                 = $o_card->year;
                $new_company_card->month                = $o_card->month;
                $new_company_card->start_date           = $o_card->start_date;
                $new_company_card->end_date             = $o_card->end_date;
                $new_company_card->content              = $o_card->content;
                $new_company_card->background_type      = $o_card->background_type;
                $new_company_card->background_color     = $o_card->background_color;
                $new_company_card->background_img       = $o_card->background_img ? $o_card->background_img . '.jpg' : NULL;
                $new_company_card->watermark_type       = $o_card->watermark_type;
                $new_company_card->watermark_img        = $o_card->watermark_img ? $o_card->watermark_img . '.jpg' : NULL;
                $new_company_card->type                 = $o_card->type;

                if ($o_card->type == 'gift') {
                    $new_company_card->second_type = $o_card->second_type;
                } elseif ($o_card->type == 'free') {
                    $new_company_card->second_type = $o_card->second_type == 1 ? 3 : 4;
                } else {
                    $new_company_card->second_type = $o_card->second_type == 3 ? 5 : 6;
                }

                if ($o_card->type == 'gift') {
                    $new_company_card->commodityId = $o_card->commodityId ? '' : NULL;
                } else {
                    $new_company_card->commodityId = $o_card->commodityId ? ShopService::where('old_id', $o_card->commodityId)->value('id') : NULL;
                }
                $new_company_card->self_definition      = $o_card->self_definition;
                $new_company_card->price                = $o_card->price;
                $new_company_card->consumption          = $o_card->consumption;
                $new_company_card->limit                = $o_card->limit;
                $new_company_card->get_limit            = $o_card->get_limit;
                $new_company_card->get_limit_minute     = $o_card->get_limit_minute;
                $new_company_card->discount_limit_type  = $o_card->discount_limit_type;
                $new_company_card->discount_limit_month = $o_card->discount_limit_month;
                $new_company_card->notice_day           = $o_card->notice_day;
                $new_company_card->status               = $o_card->status;
                $new_company_card->view                 = $o_card->view;
                $new_company_card->created_at           = $o_card->created_at;
                $new_company_card->updated_at           = $o_card->updated_at;
                $new_company_card->deleted_at           = $o_card->deleted_at;
                $new_company_card->save();

                $new_card = ShopLoyaltyCard::where('shop_id',$shop->id)
                                           ->where('company_loyalty_card_id', $new_company_card->id)
                                           ->first();
                if (!$new_card) $new_card = new ShopLoyaltyCard;
                $new_card->shop_id                 = $shop->id;
                $new_card->company_loyalty_card_id = $new_company_card->id;
                $new_card->view                    = $o_card->view;
                $new_card->status                  = $o_card->status;
                $new_card->created_at              = $o_card->created_at;
                $new_card->updated_at              = $o_card->updated_at;
                $new_card->deleted_at              = $o_card->deleted_at;
                $new_card->old_id                  = $o_card->id;
                $new_card->save();

                $old_limits = DB::connection('mysql2')
                                ->table('reward_limits')
                                ->where('reward_id', $o_card->id)
                                ->get();
                CompanyLoyaltyCardLimit::where('company_id', $company->id)->delete();
                ShopLoyaltyCardLimit::where('shop_id', $shop->id)->delete();
                foreach ($old_limits as $o_limit) {
                    $new_company_limit = new CompanyLoyaltyCardLimit;
                    $new_company_limit->company_id              = $company->id;
                    $new_company_limit->company_loyalty_card_id = $new_company_card->id;

                    $old_commodity = DB::connection('mysql2')
                                            ->table('tb_commodity')
                                            ->where('id', $o_limit->commodityId)
                                            ->first();
                    $new_company_limit->type = $old_commodity->classId == 1 ? 'product' : 'service';
                    if ($old_commodity->classId == 1) {
                        // 產品
                        $new_company_limit->commodity_id = NULL;
                    } else {
                        // 服務
                        $new_company_limit->commodity_id = ShopService::where('old_id', $old_commodity->id)->value('id');
                    }
                    $new_company_limit->created_at = $new_company_card->created_at;
                    $new_company_limit->updated_at = $new_company_card->updated_at;
                    $new_company_limit->save();
                }
            }
        }

        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        $company_loyalty_cards = CompanyLoyaltyCard::whereIn('company_id', $companies->pluck('id')->toArray())->withTrashed()->get();
        foreach ($company_loyalty_cards as $loyalty_card) {
            $shop_loyalty_card = ShopLoyaltyCard::where('company_loyalty_card_id', $loyalty_card->id)->withTrashed()->first();
            if ($shop_loyalty_card) {
                $shop_loyalty_card->name                 = $loyalty_card->name;
                $shop_loyalty_card->condition_type       = $loyalty_card->condition_type;
                $shop_loyalty_card->condition            = $loyalty_card->condition;
                $shop_loyalty_card->full_point           = $loyalty_card->full_point;
                $shop_loyalty_card->first_point          = $loyalty_card->first_point;
                $shop_loyalty_card->deadline_type        = $loyalty_card->deadline_type;
                $shop_loyalty_card->year                 = $loyalty_card->year;
                $shop_loyalty_card->month                = $loyalty_card->month;
                $shop_loyalty_card->start_date           = $loyalty_card->start_date;
                $shop_loyalty_card->end_date             = $loyalty_card->end_date;
                $shop_loyalty_card->content              = $loyalty_card->content;
                $shop_loyalty_card->background_type      = $loyalty_card->background_type;
                $shop_loyalty_card->background_color     = $loyalty_card->background_color;
                $shop_loyalty_card->background_img       = $loyalty_card->background_img;
                $shop_loyalty_card->watermark_type       = $loyalty_card->watermark_type;
                $shop_loyalty_card->watermark_img        = $loyalty_card->watermark_img;
                $shop_loyalty_card->type                 = $loyalty_card->type;
                $shop_loyalty_card->second_type          = $loyalty_card->second_type;
                $shop_loyalty_card->commodityId          = $loyalty_card->commodityId;
                $shop_loyalty_card->self_definition      = $loyalty_card->self_definition;
                $shop_loyalty_card->price                = $loyalty_card->price;
                $shop_loyalty_card->consumption          = $loyalty_card->consumption;
                $shop_loyalty_card->limit                = $loyalty_card->limit;
                $shop_loyalty_card->get_limit            = $loyalty_card->get_limit;
                $shop_loyalty_card->get_limit_minute     = $loyalty_card->get_limit_minute;
                $shop_loyalty_card->discount_limit_type  = $loyalty_card->discount_limit_type;
                $shop_loyalty_card->discount_limit_month = $loyalty_card->discount_limit_month;
                $shop_loyalty_card->notice_day           = $loyalty_card->notice_day;
                $shop_loyalty_card->save();

                // 補上使用限制
                if ($loyalty_card->limit == 4) {
                    $loyalty_card_limits = CompanyLoyaltyCardLimit::where('company_loyalty_card_id', $loyalty_card->id)->get();
                    ShopLoyaltyCardLimit::where('shop_loyalty_card_id', $shop_loyalty_card->id)->delete();
                    foreach ($loyalty_card_limits as $limit) {
                        $commodity_id = NULL;
                        if ($limit->type == 'service') {
                            $commodity_id = ShopService::where('shop_id', $shop_loyalty_card->shop_id)->where('company_service_id', $limit->commodity_id)->first();
                        }

                        $shop_card_limit = new ShopLoyaltyCardLimit;
                        $shop_card_limit->shop_id              = $shop_loyalty_card->shop_id;
                        $shop_card_limit->shop_loyalty_card_id = $shop_loyalty_card->id;
                        $shop_card_limit->type                 = $limit->type;
                        $shop_card_limit->commodity_id         = $commodity_id;
                        $shop_card_limit->save();
                    }
                }
            }
        }

        // 會員資料========================================================================================================================
        $old_customers = DB::connection('mysql2')->table('shilipai_customers')->where('deleted_at', NULL)->get();
        foreach ($old_customers as $o_customer) {
            $new_customer = Customer::where('phone', $o_customer->phone)->first();
            if (!$new_customer) $new_customer = new Customer;
            $new_customer->realname      = $o_customer->name;
            $new_customer->phone         = $o_customer->phone;
            $new_customer->email         = $o_customer->email;
            $new_customer->sex           = $o_customer->sex == 0 ? 'M' : ($o_customer->sex == 1 ? 'F' : 'C');
            $new_customer->birthday      = $o_customer->birthday;
            $new_customer->facebook_id   = $o_customer->facebook_id;
            $new_customer->facebook_name = $o_customer->facebook_name;
            $new_customer->google_id     = $o_customer->google_id;
            $new_customer->google_name   = $o_customer->google_name;
            $new_customer->line_id       = $o_customer->line_id;
            $new_customer->line_name     = $o_customer->line_name;
            $new_customer->login_date    = $o_customer->login_date;
            $new_customer->photo         = $o_customer->photo ? (preg_match('/http/i', $o_customer->photo) ? $o_customer->photo : str_replace('/upload/images/', '', $o_customer->photo)) : NULL;
            $new_customer->banner        = $o_customer->banner ? (preg_match('/http/i', $o_customer->banner) ? $o_customer->banner : str_replace('/upload/images/', '', $o_customer->banner)) : NULL;
            $new_customer->created_at    = $o_customer->created_at;
            $new_customer->updated_at    = $o_customer->updated_at;
            $new_customer->old_id        = $o_customer->id;
            $new_customer->save();

            // 找出在舊有company下的顧客
            $old_company_customers = DB::connection('mysql2')
                                        ->table('tb_customer')
                                        ->whereIn('companyId',request('company_id'))
                                        ->where('shilipai_customer_id', $o_customer->id)
                                        ->where('deleteTime', NULL)
                                        ->get();
            foreach ($old_company_customers as $oc_customer) {
                if (Company::where('old_companyId', $oc_customer->companyId)->value('id') == '') continue;

                $new_company_customer = CompanyCustomer::where('company_id', Company::where('old_companyId', $oc_customer->companyId)->value('id'))
                                                       ->where('customer_id', $new_customer->id)
                                                       ->first();
                if (!$new_company_customer) $new_company_customer = new CompanyCustomer;
                $new_company_customer->customer_id = $new_customer->id;
                $new_company_customer->company_id  = Company::where('old_companyId', $oc_customer->companyId)->value('id');
                $new_company_customer->created_at  = $oc_customer->joinTime;
                $new_company_customer->updated_at  = $oc_customer->lastUpdate;
                $new_company_customer->save();

                $new_shop_customer = ShopCustomer::where('customer_id', $new_customer->id)
                                                 ->where('shop_id', Shop::where('company_id', $new_company_customer->company_id)->value('id'))
                                                 ->first();
                if (!$new_shop_customer) $new_shop_customer = new ShopCustomer;
                $new_shop_customer->customer_id = $new_customer->id;
                $new_shop_customer->company_id  = $new_company_customer->company_id;
                $new_shop_customer->shop_id     = Shop::where('company_id', $new_company_customer->company_id)->value('id');
                $new_shop_customer->created_at  = $oc_customer->joinTime;
                $new_shop_customer->updated_at  = $oc_customer->lastUpdate;
                $new_shop_customer->save();
            }
        }

        $old_company_customers = DB::connection('mysql2')
                                            ->table('tb_customer')
                                            ->whereIn('companyId', request('company_id'))
                                            ->where('shilipai_customer_id', NULL)
                                            ->where('deleteTime', NULL)
                                            ->get();
        foreach ($old_company_customers as $o_customer) {
            if ($o_customer->email == '' && $o_customer->name == '' && $o_customer->phone == '') continue;

            if (!Company::where('old_companyId', $o_customer->companyId)->value('id')) continue;

            $new_customer = Customer::where('phone', $o_customer->phone)->first();
            if (!$new_customer) $new_customer = new Customer;
            $new_customer->phone      = $o_customer->phone;
            $new_customer->login_date = $o_customer->joinTime;
            $new_customer->sex        = $o_customer->sex == 0 ? 'M' : ($o_customer->sex == 1 ? 'F' : 'C');
            $new_customer->realname   = preg_match('/@/i', $o_customer->name) ? $o_customer->email : $o_customer->name;
            $new_customer->email      = preg_match('/@/i', $o_customer->name) ? $o_customer->name : $o_customer->email;
            $new_customer->birthday   = substr($o_customer->birthDay, 0, 10);
            $new_customer->created_at = $o_customer->joinTime;
            $new_customer->updated_at = $o_customer->lastUpdate;
            $new_customer->old_id     = $o_customer->id;
            $new_customer->save();

            $new_company_customer = CompanyCustomer::where('company_id', Company::where('old_companyId', $oc_customer->companyId)->value('id'))
                                                       ->where('customer_id', $new_customer->id)
                                                       ->first();
            if (!$new_company_customer) $new_company_customer = new CompanyCustomer;
            $new_company_customer->customer_id = $new_customer->id;
            $new_company_customer->company_id  = Company::where('old_companyId', $o_customer->companyId)->value('id');
            $new_company_customer->created_at  = $o_customer->joinTime;
            $new_company_customer->updated_at  = $o_customer->lastUpdate;
            $new_company_customer->save();

            $new_shop_customer = ShopCustomer::where('customer_id', $new_customer->id)
                                            ->where('shop_id', Shop::where('company_id', $new_company_customer->company_id)->value('id'))
                                            ->first();
            if (!$new_shop_customer) $new_shop_customer = new ShopCustomer;
            $new_shop_customer->customer_id = $new_customer->id;
            $new_shop_customer->company_id  = $new_company_customer->company_id;
            $new_shop_customer->shop_id     = Shop::where('company_id', $new_company_customer->company_id)->value('id');
            $new_shop_customer->created_at  = $o_customer->joinTime;
            $new_shop_customer->updated_at  = $o_customer->lastUpdate;
            $new_shop_customer->save();
        }

        // 會員優惠券========================================================================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        CustomerCoupon::whereIn('company_id', $companies->pluck('id')->toArray())->delete();
        $old_data = DB::connection('mysql2')->table('customer_coupons')->whereIn('companyId', request('company_id'))->get();
        foreach ($old_data as $o_data) {
            if (Customer::where('old_id', $o_data->shilipai_customer_id)->value('id')) {
                $shop_coupon = ShopCoupon::where('old_id', $o_data->coupon_id)->first();
                if (!$shop_coupon) continue;

                $new_customer_coupon = new CustomerCoupon;
                $new_customer_coupon->shop_id        = Shop::where('alias', $o_data->companyId)->value('id');
                $new_customer_coupon->company_id     = Company::where('old_companyId', $o_data->companyId)->value('id');
                $new_customer_coupon->customer_id    = Customer::where('old_id', $o_data->shilipai_customer_id)->value('id');
                $new_customer_coupon->shop_coupon_id = ShopCoupon::where('old_id', $o_data->coupon_id)->value('id');
                $new_customer_coupon->status         = $o_data->status;
                $new_customer_coupon->using_time     = $o_data->using_time;
                $new_customer_coupon->created_at     = $o_data->created_at;
                $new_customer_coupon->updated_at     = $o_data->updated_at;
                $new_customer_coupon->save();
            }
        }

        // 會員集點卡========================================================================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        $customer_cards = CustomerLoyaltyCard::whereIn('company_id', $companies->pluck('id')->toArray())->get();
        CustomerLoyaltyCardPoint::whereIn('customer_loyalty_card_id', $customer_cards->pluck('id')->toArray())->delete();
        CustomerLoyaltyCard::whereIn('company_id', $companies->pluck('id')->toArray())->delete();
        $old_data = DB::connection('mysql2')->table('customer_rewards')->whereIn('companyId', request('company_id'))->get();
        foreach ($old_data as $o_data) {
            if (Customer::where('old_id', $o_data->shilipai_customer_id)->value('id')) {
                $shopLoyaltyCard = ShopLoyaltyCard::where('old_id', $o_data->reward_card_id)->first();
                if (!$shopLoyaltyCard) continue;

                // 建立會員集點卡資料
                $new_customer_card = new CustomerLoyaltyCard;
                $new_customer_card->shop_id              = Shop::where('alias', $o_data->companyId)->value('id');
                $new_customer_card->company_id           = Company::where('old_companyId', $o_data->companyId)->value('id');
                $new_customer_card->customer_id          = Customer::where('old_id', $o_data->shilipai_customer_id)->value('id');
                $new_customer_card->shop_loyalty_card_id = $shopLoyaltyCard->id;
                $new_customer_card->card_no              = $o_data->card_no;
                $new_customer_card->full_point           = CompanyLoyaltyCard::where('id', $shopLoyaltyCard->company_loyalty_card_id)->value('full_point');
                $new_customer_card->last_point           = $new_customer_card->full_point;
                $new_customer_card->status               = $o_data->status;
                $new_customer_card->using_time           = $o_data->using_time;
                $new_customer_card->created_at           = $o_data->created_at;
                $new_customer_card->updated_at           = $o_data->updated_at;
                $new_customer_card->save();

                // 集點記錄
                $old_points = DB::connection('mysql2')->table('customer_reward_points')->where('customer_reward_id', $o_data->id)->get();
                $point = 0;
                foreach ($old_points as $o_point) {
                    $new_point = new CustomerLoyaltyCardPoint;
                    $new_point->customer_loyalty_card_id = $new_customer_card->id;
                    $new_point->point                    = $o_point->point;
                    $new_point->created_at               = $o_point->created_at;
                    $new_point->updated_at               = $o_point->updated_at;
                    $new_point->save();

                    $point += $o_point->point;
                    $new_customer_card->last_point = $new_customer_card->full_point - $point;
                    $new_customer_card->save();
                }
            }
        }

        // 作品集資料 ==================================================================================================================================
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        $old_collection_name = [];
        foreach ($companies as $company) {
            $shop = Shop::where('company_id', $company->id)->first();

            // 刪除資料
            $shop_album  = Album::where('shop_id',$shop->id)->get();
            $album_photo = AlbumPhoto::whereIn('album_id', $shop_album->pluck('id')->toArray())->get();
            Photo::whereIn('id', $album_photo->pluck('photo_id')->toArray())->delete();
            AlbumPhoto::whereIn('album_id', $shop_album->pluck('id')->toArray())->delete();
            Album::where('shop_id', $shop->id)->delete();

            $old_collections = DB::connection('mysql2')->table('photo_tags')->where('companyId', $company->companyId)->orderBy('sequence', 'ASC')->get();
            $sequence = 1;
            foreach ($old_collections->groupBy('name') as $name => $photo_data) {
                $shop_id = Permission::where('company_id', $company->id)->where('shop_id', '!=', NULL)->value('shop_id');
                if (!$shop_id) continue;

                // 先建立相本
                $new_album = new Album;
                $new_album->shop_id  = $shop_id;
                $new_album->name     = $name;
                $new_album->type     = 'collection';
                $new_album->sequence = $photo_data->first()->sequence;
                $new_album->save();

                $old_photo_ids = $photo_data->pluck('albumDetail_id')->toArray();
                $old_photos = DB::connection('mysql2')->table('tb_albumDetails')->whereIn('id', $old_photo_ids)->get();
                $photo_insert = [];
                foreach ($old_photos as $k => $o_photo) {
                    $new_photo = new Photo;
                    $new_photo->user_id = Permission::where('company_id', $company->id)->value('user_id');
                    $new_photo->photo   = $o_photo->path . '.jpg';
                    $new_photo->save();

                    $new_album_photo = new AlbumPhoto;
                    $new_album_photo->album_id   = $new_album->id;
                    $new_album_photo->photo_id   = $new_photo->id;
                    $new_album_photo->cover      = $k == 0 ? 'Y' : 'N';
                    $new_album_photo->created_at = $o_photo->lastUpdate;
                    $new_album_photo->updated_at = $o_photo->lastUpdate;
                    $new_album_photo->save();

                    if ($k == 0) {
                        $new_album->cover = $o_photo->path . '.jpg';
                        $new_album->save();
                    }
                }
            }
        }

        // 預約資料
        $shop_services = ShopService::pluck('id', 'old_id')->toArray();
        $shop_staffs   = ShopStaff::pluck('id', 'old_id')->toArray();
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $shop = Shop::where('company_id', $company->id)->first();

            $customer_reservation = CustomerReservation::where('company_id',$company->id)->get();
            CustomerReservationAdvance::where('customer_reservation_id', $customer_reservation->pluck('id')->toArray())->forceDelete();
            CustomerReservation::where('company_id', $company->id)->forceDelete();

            $old_events = DB::connection('mysql2')->table('tb_events')->where('companyId', $company->companyId)->where('deleteTime', NULL)->get();
            foreach ($old_events as $o_event) {

                $old_reservation = DB::connection('mysql2')->table('reservations')->where('event_id', $o_event->id)->first();
                $old_customer    = DB::connection('mysql2')->table('tb_customer')->where('id', $o_event->customerId)->first();

                if (!$old_customer || !isset($shop_services[$o_event->serviceId]) || !isset($shop_staffs[$o_event->staffId])) continue;

                $customer = '';
                if ($old_customer->shilipai_customer_id != '') {
                    $customer = Customer::where('old_id', $old_customer->shilipai_customer_id)->value('id');
                } else {
                    $customer = Customer::where('old_id', $old_customer->id)->value('id');
                }

                if ($customer == '') continue;

                $new_reservation = new CustomerReservation;
                $new_reservation->customer_id        = $customer;
                $new_reservation->company_id         = $company->id;
                $new_reservation->shop_id            = $shop->id;
                $new_reservation->shop_service_id    = $shop_services[$o_event->serviceId];
                $new_reservation->shop_staff_id      = $shop_staffs[$o_event->staffId];
                $new_reservation->start              = $o_event->start;
                $new_reservation->end                = $o_event->end;
                $new_reservation->need_time          = (strtotime($o_event->end) - strtotime($o_event->start)) / 60;
                $new_reservation->google_calendar_id = $o_event->google_calendar_id ?: NULL;
                $new_reservation->status             = $old_reservation && in_array($old_reservation->status, ['Y', 'N']) ? $old_reservation->status : 'Y';
                $new_reservation->cancel_status      = $old_reservation && in_array($old_reservation->status, ['C', 'M']) ? $old_reservation->status : NULL;
                $new_reservation->tag                = $o_event && $o_event->status && $o_event->status != 'N' ? $o_event->status : NULL;
                $new_reservation->created_at         = $o_event->lastUpdate;
                $new_reservation->updated_at         = $o_event->lastUpdate;
                $new_reservation->old_event_id       = $o_event->id;
                $new_reservation->save();

                // 加值項目
                $old_add_items = DB::connection('mysql2')->table('event_add_items')->where('event_id', $o_event->id)->get();
                foreach ($old_add_items as $old_add_item) {
                    $new_reservation_add = new CustomerReservationAdvance;
                    $new_reservation_add->customer_reservation_id = $new_reservation->id;
                    $new_reservation_add->shop_service_id         = $shop_services[$old_add_item->commodity_id];
                    $new_reservation_add->created_at              = $old_add_item->created_at;
                    $new_reservation_add->updated_at              = $old_add_item->updated_at;
                    $new_reservation_add->save();
                }
            }
        }

        foreach ($companies as $company) {
            $old_reservations = DB::connection('mysql2')->table('reservations')->where('companyId', $company->companyId)->where('deleted_at', NULL)->get();
            foreach ($old_reservations as $o_reservation) {
                $customer_reservation = CustomerReservation::where('old_event_id', $o_reservation->event_id)->first();
                if (!$customer_reservation) {

                    $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id', $o_reservation->customer_id)->first();
                    $old_event    = DB::connection('mysql2')->table('tb_events')->where('id', $o_reservation->event_id)->first();

                    if (!$old_customer || !isset($shop_services[$o_reservation->product_item]) || !isset($shop_staffs[$o_reservation->service_personnel])) continue;

                    $customer = '';
                    if ($old_customer->shilipai_customer_id != '') {
                        $customer = Customer::where('old_id', $old_customer->shilipai_customer_id)->value('id');
                    } else {
                        $customer = Customer::where('old_id', $old_customer->id)->value('id');
                    }

                    if ($customer == '') continue;

                    $new_reservation = new CustomerReservation;
                    $new_reservation->customer_id        = $customer;
                    $new_reservation->company_id         = $company->id;
                    $new_reservation->shop_id            = Shop::where('alias', $o_reservation->companyId)->value('id');
                    $new_reservation->shop_service_id    = $shop_services[$o_reservation->product_item];
                    $new_reservation->shop_staff_id      = $shop_staffs[$o_reservation->service_personnel];
                    $new_reservation->start              = $o_reservation->date;
                    $new_reservation->end                = date('Y-m-d H:i:s', strtotime($o_reservation->date . "+" . $o_reservation->service_time . " minute"));
                    $new_reservation->need_time          = $o_reservation->service_time;
                    $new_reservation->google_calendar_id = $old_event ? $old_event->google_calendar_id : NULL;
                    $new_reservation->status             = $o_reservation && in_array($o_reservation->status, ['Y', 'N']) ? $o_reservation->status : 'Y';
                    $new_reservation->cancel_status      = $o_reservation && in_array($o_reservation->status, ['C', 'M']) ? $o_reservation->status : NULL;
                    $new_reservation->tag                = $old_event && $old_event->status && $old_event->status != 'N' ? $old_event->status : NULL;
                    $new_reservation->created_at         = $o_reservation->created_at;
                    $new_reservation->updated_at         = $o_reservation->updated_at;
                    $new_reservation->old_event_id       = $old_event ? $old_event->id : NULL;
                    $new_reservation->save();


                    // 加值項目
                    $old_add_items = explode(',', $o_reservation->add_items);
                    foreach ($old_add_items as $old_add_item) {
                        if (!isset($shop_services[$old_add_item])) continue;
                        $new_reservation_add = new CustomerReservationAdvance;
                        $new_reservation_add->customer_reservation_id = $new_reservation->id;
                        $new_reservation_add->shop_service_id         = $shop_services[$old_add_item];
                        $new_reservation_add->created_at              = $o_reservation->created_at;
                        $new_reservation_add->updated_at              = $o_reservation->updated_at;
                        $new_reservation_add->save();
                    }
                }
            }
        }

        // 簡訊記錄===================================================================
        $shops = Shop::whereIn('alias', request('company_id'))->get();
        foreach ($shops as $shop) {
            MessageLog::where('shop_id',$shop->id)->delete();
            $old_log = DB::connection('mysql2')->table('message_logs')->where('companyId', $shop->alias)->get();
            foreach ($old_log as $o_log) {
                $new_log = new MessageLog;
                $new_log->company_id              = $shop->company_info->id;
                $new_log->shop_id                 = $shop->id;
                $new_log->phone                   = $o_log->phone;
                $new_log->content                 = $o_log->content;
                $new_log->use                     = $o_log->use;
                $new_log->reservation             = $o_log->reservation;
                $new_log->customer_reservation_id = $o_log->event_id ? CustomerReservation::where('old_event_id', $o_log->event_id)->value('id') : NULL;
                $new_log->created_at              = $o_log->created_at;
                $new_log->updated_at              = $o_log->updated_at;
                $new_log->save();
            }
        }

        // 儲值資料
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $shop = Shop::where('company_id', $company->id)->first();
            CompanyTopUp::where('company_id', $company->id)->forceDelete();
            ShopTopUp::where('shop_id', $shop->id)->forceDelete();
            // 舊儲值資料
            $old_top_ups = DB::connection('mysql2')
                                    ->table('tb_commodity')
                                    ->where('companyId', $company->companyId)
                                    ->where('classId', 4)
                                    ->where('deleteTime', NULL)
                                    ->get();
            foreach ($old_top_ups as $o_top_up) {
                $new_company_top_up = new CompanyTopUp;
                $new_company_top_up->company_id  = $company->id;
                $new_company_top_up->name        = $o_top_up->name;
                $new_company_top_up->price       = $o_top_up->lprice;
                $new_company_top_up->during_type = 1;
                $new_company_top_up->use_coupon  = 0;
                $new_company_top_up->status      = 'pending';
                $new_company_top_up->save();

                $new_shop_top_up = new ShopTopUp();
                $new_shop_top_up->company_top_up_id = $new_company_top_up->id;
                $new_shop_top_up->shop_id           = $shop->id;
                $new_shop_top_up->name              = $o_top_up->name;
                $new_shop_top_up->price             = $o_top_up->lprice;
                $new_shop_top_up->during_type       = 1;
                $new_shop_top_up->use_coupon        = 0;
                $new_shop_top_up->status            = 'pending';
                $new_shop_top_up->save();
            }  
        }

        // 方案資料
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $shop = Shop::where('company_id', $company->id)->first();
            CompanyProgram::where('company_id', $company->id)->forceDelete();
            CompanyProgramGroup::where('company_id', $company->id)->delete();
            CompanyProgramGroupContent::where('company_id', $company->id)->delete();
            ShopProgram::where('shop_id', $shop->id)->forceDelete();
            ShopProgramGroup::where('shop_id', $shop->id)->delete();
            ShopProgramGroupContent::where('shop_id', $shop->id)->delete();

            // 舊儲值資料
            $old_programs = DB::connection('mysql2')
                                    ->table('tb_commodity')
                                    ->where('companyId', $company->companyId)
                                    ->where('classId', 3)
                                    ->where('deleteTime', NULL)
                                    ->get();
            foreach ($old_programs as $o_program) {
                $new_company_program = new CompanyProgram;
                $new_company_program->company_id  = $company->id;
                $new_company_program->name        = $o_program->name;
                $new_company_program->price       = $o_program->lprice;
                $new_company_program->during_type = 1;
                $new_company_program->use_coupon  = 0;
                $new_company_program->status      = 'pending';
                $new_company_program->save();

                $new_shop_program = new ShopProgram();
                $new_shop_program->shop_id            = $shop->id;
                $new_shop_program->company_program_id = $new_company_program->id;
                $new_shop_program->name               = $o_program->name;
                $new_shop_program->price              = $o_program->lprice;
                $new_shop_program->during_type        = 1;
                $new_shop_program->use_coupon         = 0;
                $new_shop_program->status             = 'pending';
                $new_shop_program->old_id             = $o_program->id;
                $new_shop_program->save();

                $old_groups = DB::connection('mysql2')
                                    ->table('tb_commoditydetails')
                                    ->where('pcommodityId', $o_program->id)
                                    ->where('deleteTime', NULL)
                                    ->get();
                foreach ($old_groups as $o_group) {

                    $old_commodity = DB::connection('mysql2')
                                            ->table('tb_commodity')
                                            ->where('id', $o_group->commodityId)
                                            ->first();

                    if ($old_commodity->classId == 2 && ShopService::where('old_id', $o_group->commodityId)->first()) {

                        $new_shop_program_group = new ShopProgramGroup;
                        $new_shop_program_group->shop_id         = $shop->id;
                        $new_shop_program_group->shop_program_id = $new_shop_program->id;
                        $new_shop_program_group->type            = 1;
                        $new_shop_program_group->name            = $o_program->name;
                        $new_shop_program_group->count           = $o_group->canUseTime;
                        $new_shop_program_group->save();

                        

                        $new_shop_program_group_content = new ShopProgramGroupContent;
                        $new_shop_program_group_content->shop_id               = $shop->id;
                        $new_shop_program_group_content->shop_program_group_id = $new_shop_program_group->id;
                        $new_shop_program_group_content->commodity_type        = 'service';
                        $new_shop_program_group_content->commodity_id          = ShopService::where('old_id', $o_group->commodityId)->first()->id ;
                        $new_shop_program_group_content->save();
                    }
                }
            }
        }

        // 會員儲值
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        foreach ($companies as $company) {
            $shop = Shop::where('company_id', $company->id)->first();
            $shop_staff = ShopStaff::where('shop_id',$shop->id)->where('master',0)->first();

            CustomerTopUpLog::where('company_id', $company->id)->forceDelete();

            $old_customer_top_ups = DB::connection('mysql2')
                                    ->table('tb_checkoutdetails_commodity')
                                    ->where('companyId', $company->companyId)
                                    ->where('classificationId',4)
                                    ->where('deleteTime', NULL)
                                    ->get();
            // 購買記錄
            foreach ($old_customer_top_ups as $o_log) {

                $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id',$o_log->customer)->where('deleteTime', NULL)->first();
                if (!$old_customer) continue;
                $customer = Customer::where('phone', $old_customer->phone)->first();

                $old_commodity = DB::connection('mysql2')->table('tb_commodity')->where('id',$o_log->commodityId)->first();

                $customer_top_up = new CustomerTopUpLog();
                $customer_top_up->customer_id   = $customer->id;
                $customer_top_up->company_id    = $company->id;
                $customer_top_up->shop_id       = $shop->id;
                $customer_top_up->type          = 1;
                $customer_top_up->price         = $old_commodity->price;
                $customer_top_up->shop_staff_id = $shop_staff->id;
                $customer_top_up->created_at    = $o_log->lastUpdate;
                $customer_top_up->updated_at    = $o_log->lastUpdate;
                $customer_top_up->save();
            }

            // 手動使用記錄
            $old_customer_top_ups = DB::connection('mysql2')
                                    ->table('tb_oldDataInsertHistory')
                                    ->where('companyId', $company->companyId)
                                    ->where('deleteTime', NULL)
                                    ->whereIn('insertType',[0,2])
                                    ->get();
            foreach ($old_customer_top_ups as $o_log) {
                $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id', $o_log->customerId)->where('deleteTime', NULL)->first();
                if (!$old_customer) continue;
                $customer = Customer::where('phone', $old_customer->phone)->first();

                $customer_top_up = new CustomerTopUpLog();
                $customer_top_up->customer_id   = $customer->id;
                $customer_top_up->company_id    = $company->id;
                $customer_top_up->shop_id       = $shop->id;
                $customer_top_up->type          = 2;
                $customer_top_up->price         = $o_log->insertType == 0 ? $o_log->point : -1 * $o_log->point;
                $customer_top_up->shop_staff_id = $shop_staff->id;
                $customer_top_up->created_at    = $o_log->createTime;
                $customer_top_up->updated_at    = $o_log->lastUpdate;
                $customer_top_up->save();
            }

            // 一般使用記錄
            $old_customer_top_ups = DB::connection('mysql2')
                                    ->table('tb_checkout')
                                    ->where('companyId', $company->companyId)
                                    ->where('deleteTime', NULL)
                                    ->where('point','!=',0)
                                    ->get();
            foreach ($old_customer_top_ups as $o_log) {
                $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id', $o_log->customer)->where('deleteTime', NULL)->first();
                if (!$old_customer) continue;
                $customer = Customer::where('phone', $old_customer->phone)->first();

                $customer_top_up = new CustomerTopUpLog();
                $customer_top_up->customer_id   = $customer->id;
                $customer_top_up->company_id    = $company->id;
                $customer_top_up->shop_id       = $shop->id;
                $customer_top_up->type          = 3;
                $customer_top_up->price         = -1 * $o_log->point;
                $customer_top_up->shop_staff_id = $shop_staff->id;
                $customer_top_up->created_at    = $o_log->time;
                $customer_top_up->updated_at    = $o_log->lastUpdate;
                $customer_top_up->save();
            }
        }

        // 會員方案
        $old_customer_programs = DB::connection('mysql2')
                                        ->table('tb_checkoutdetails_commodity')
                                        ->whereIn('companyId', request('company_id'))
                                        ->where('deleteTime', NULL)
                                        ->where('classificationId', 3)
                                        ->get();
        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        $customer_programs = CustomerProgram::whereIn('company_id', $companies->pluck('id')->toArray())->get();
        CustomerProgramGroup::whereIn('customer_program_id', $customer_programs->pluck('id')->toArray())->delete();
        CustomerProgramLog::whereIn('customer_program_id', $customer_programs->pluck('id')->toArray())->forceDelete();
        CustomerProgram::whereIn('company_id', $companies->pluck('id')->toArray())->forceDelete();

        foreach ($old_customer_programs as $o_program) {
            $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id', $o_program->customer)->where('deleteTime', NULL)->first();
            if (!$old_customer) continue;
            $customer = Customer::where('phone', $old_customer->phone)->first();

            $new_shop_program = ShopProgram::where('old_id',$o_program->commodityId)->first();
            if (!$new_shop_program) continue;

            $shop = Shop::where('alias',$o_program->companyId)->first();
            $shop_staff = ShopStaff::where('shop_id', $shop->id)->where('master', 0)->first();

            // 會員購買的方案
            $customer_program = new CustomerProgram;
            $customer_program->customer_id     = $customer->id;
            $customer_program->company_id      = $shop->company_info->id;
            $customer_program->shop_id         = $shop->id;
            $customer_program->shop_program_id = $new_shop_program->id; 
            $customer_program->price           = $new_shop_program->price;
            $customer_program->save();

            // 會員購買方案的內容記錄
            foreach ($new_shop_program->groups as $group) {
                $customer_program_group = new CustomerProgramGroup;
                $customer_program_group->customer_program_id   = $customer_program->id;
                $customer_program_group->shop_program_group_id = $group->id;
                $customer_program_group->count                 = $group->count;
                $customer_program_group->last_count            = $group->count;
                $customer_program_group->created_at            = $o_program->lastUpdate;
                $customer_program_group->updated_at            = $o_program->lastUpdate;
                $customer_program_group->save();

                // 方案使用記錄
                $log = new CustomerProgramLog;
                $log->customer_program_id       = $customer_program->id;
                $log->customer_program_group_id = $customer_program_group->id;
                $log->count                     = $group->count;
                $log->type                      = 1;
                $log->shop_staff_id             = $shop_staff->id;
                $log->created_at                = $o_program->lastUpdate;
                $log->updated_at                = $o_program->lastUpdate;          
                $log->save();
            }
        }

        // 手動使用記錄
        $old_customer_program_logs = DB::connection('mysql2')
                                                ->table('tb_oldDataInsertHistory')
                                                ->where('companyId', request('company_id'))
                                                ->where('deleteTime', NULL)
                                                ->whereIn('insertType', [1, 3])
                                                ->get();
        foreach ($old_customer_program_logs as $o_log) {
            $old_details = DB::connection('mysql2')
                ->table('tb_oldDataInsertHistoryDetails')
                ->where('odihId', $o_log->id)
                ->where('deleteTime', NULL)
                ->get();

            $company = Company::where('old_companyId', $o_log->companyId)->first();
            $shop    = Shop::where('company_id', $company->id)->first();
            $new_shop_program = ShopProgram::where('old_id', $o_log->commodityId)->first();

            if (!$new_shop_program) continue;

            $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id',
                $o_log->customerId
            )->where('deleteTime', NULL)->first();
            if (!$old_customer) continue;
            $customer = Customer::where('phone', $old_customer->phone)->first();

            $shop_staff = ShopStaff::where('shop_id', $shop->id)->where('master', 0)->first();

            $customer_program = CustomerProgram::where('customer_id', $customer->id)->where('shop_id', $shop->id)->where('shop_program_id', $new_shop_program->id)->first();
            if (!$customer_program) {
                $customer_program = new CustomerProgram;
                $customer_program->customer_id     = $customer->id;
                $customer_program->company_id      = $shop->company_info->id;
                $customer_program->shop_id         = $shop->id;
                $customer_program->shop_program_id = $new_shop_program->id;
                $customer_program->price           = $new_shop_program->price;
                $customer_program->save();

                // 會員購買方案的內容記錄
                foreach ($new_shop_program->groups as $group) {
                    $customer_program_group = new CustomerProgramGroup;
                    $customer_program_group->customer_program_id   = $customer_program->id;
                    $customer_program_group->shop_program_group_id = $group->id;
                    $customer_program_group->count                 = $group->count;
                    $customer_program_group->last_count            = $group->count;
                    $customer_program_group->created_at            = $o_log->lastUpdate;
                    $customer_program_group->updated_at            = $o_log->lastUpdate;
                    $customer_program_group->save();
                }
            }

            foreach ($old_details as $o_detail) {
                $count = $o_detail->count;

                // 是不是產品
                if (!ShopService::where('old_id', $o_detail->commodityId)->first()) continue;

                $new_shop_program = $customer_program->program_info;
                foreach ($new_shop_program->groups as $group) {
                    $shop_program_group_content = ShopProgramGroupContent::where('shop_program_group_id', $group->id)
                        ->where('commodity_id', ShopService::where('old_id', $o_detail->commodityId)->first()->id)
                        ->first();
                    if ($shop_program_group_content) break;
                }

                $log = new CustomerProgramLog;
                $log->customer_program_id       = $customer_program->id;
                $log->customer_program_group_id =
                    CustomerProgramGroup::where('customer_program_id', $customer_program->id)->where('shop_program_group_id', $shop_program_group_content->shop_program_group_id)->first()->id;
                $log->count                     = $o_log->insertType == 1 ? $count : -1 * $count;
                $log->type                      = 2;
                $log->shop_staff_id             = $shop_staff->id;
                $log->created_at                = $o_detail->lastUpdate;
                $log->updated_at                = $o_detail->lastUpdate;
                $log->save();
            }
        }

        // 使用記錄
        // 方案使用記錄
        $old_customer_program_logs = DB::connection('mysql2')
                                                        ->table('tb_checkoutdetails_service')
                                                        ->where('companyId', request('company_id'))
                                                        ->where('deleteTime', NULL)
                                                        ->get();
        foreach ($old_customer_program_logs as $o_logs) {

            // 是不是產品
            if (!ShopService::where('old_id', $o_logs->commodityId)->first()) {
                continue;
            }

            $company = Company::where('old_companyId', $o_logs->companyId)->first();
            $shop    = Shop::where('company_id', $company->id)->first();
            $new_shop_program = ShopProgram::where('old_id', $o_logs->packageId)->first();

            if (!$new_shop_program) continue;

            foreach ($new_shop_program->groups as $group) {
                $shop_program_group_content = ShopProgramGroupContent::where('shop_program_group_id', $group->id)
                                                                     ->where('commodity_id', ShopService::where('old_id', $o_logs->commodityId)->first()->id)
                                                                     ->first();
                if ($shop_program_group_content) break;
            }

            $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id',$o_logs->customer)->where('deleteTime', NULL)->first();
            if (!$old_customer) continue;
            $customer = Customer::where('phone', $old_customer->phone)->first();

            $shop_staff = ShopStaff::where('shop_id', $shop->id)->where('master', 0)->first();

            $customer_program = CustomerProgram::where('customer_id', $customer->id)->where('shop_id', $shop->id)->where('shop_program_id', $new_shop_program->id)->first();
            
            $count = $o_logs->thisTimeUse;

            if (!CustomerProgramGroup::where('shop_program_group_id', $shop_program_group_content->shop_program_group_id)->first()) continue;

            $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id', $o_logs->customer)->where('deleteTime', NULL)->first();
            if (!$old_customer) continue;
            $customer = Customer::where('phone', $old_customer->phone)->first();

            $log = new CustomerProgramLog;
            $log->customer_program_id       = $customer_program->id;
            $log->customer_program_group_id = CustomerProgramGroup::where('customer_program_id', $customer_program->id)->where('shop_program_group_id', $shop_program_group_content->shop_program_group_id)->first()->id;
            $log->count                     = -1 * $count;
            $log->type                      = 3;
            $log->shop_staff_id             = $shop_staff->id;
            $log->created_at                = $o_logs->lastUpdate;
            $log->updated_at                = $o_logs->lastUpdate;
            $log->save();
        }

        $companies = Company::whereIn('old_companyId', request('company_id'))->get();
        $customer_programs = CustomerProgram::where('company_id',$companies->pluck('id')->toArray())->get();
        foreach ($customer_programs as $program) {
            foreach ($program->groups as $group) {
                $group->last_count = $group->use_log->sum('count');
                $group->save();
            }
        }

        // 收款方式預設資料
        $shops = Shop::whereIn('alias',request('company_id'))->get();
        $pay_type = ['無收現', '現金', 'Line Pay', '街口支付'];
        foreach ($shops as $shop) {
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

        return response()->json(['status' => true]);
    }

}
