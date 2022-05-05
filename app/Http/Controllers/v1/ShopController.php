<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Http\Controllers\v1\PermissionController;
use App\Models\BuyMode;
use App\Models\Company;
use App\Models\Shop;
use App\Models\ShopPhoto;
use App\Models\ShopSet;
use App\Models\ShopClose;
use App\Models\ShopStaff;
use App\Models\ShopBusinessHour;
use App\Models\ShopVacation;
use App\Models\Permission;
use App\Models\MessageLog;
use App\Models\User;
use App\Models\Order;
use App\Models\DirectSalesPointLog;
use App\Jobs\SetStaffBussine;
use App\Models\Customer;
use App\Models\DirectExchangeMode;
use App\Models\ShopCustomer;

class ShopController extends Controller
{
    // 確認商家管理的頁籤瀏覽權限
    public function shop_tab_permission($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array(request('tab'), $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        return response()->json(['status' => true, 'permission' => true]);
    }

    // 取得指定分店所有關連資料
    public function shop_info($shop_id)
    {
        $user = auth()->user();

        $with = [
            'shop_info.shop_staffs', 'shop_info.shop_services', 'shop_info.shop_set', 'shop_info.shop_reservations', 'shop_info.shop_business_hours', 'shop_info.shop_close'
        ];

        // 確認user對應的分店資料
        $user_shop = Permission::where('user_id', $user->id)->where('shop_id', $shop_id)->with($with)->first();
        if (!$user_shop) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到商家資料']]]);
        }

        $shop_info = $user_shop->shop_info;
        $shop_info->companyId = Company::where('id', $user_shop->shop_info->id)->value('companyId');

        return response()->json(['status' => true, 'data' => $shop_info]);
    }

    // 取得指定分店基本資料
    public function shop_basic($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_basic', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 商家QR
        // if( \DB::table('version_check')->where('store_id',$shop_info->alias)->first() ){
        //     $shop_home_url = 'https://ai.shilipai.com.tw/store/'.$shop_info->alias;
        // }else{
        //     $shop_home_url = env('SHILIPAI_WEB').'/s/'.$shop_info->alias;
        // }
        $shop_home_url = env('SHILIPAI_WEB') . '/s/' . $shop_info->alias;

        $data = [
            'id'                      => $shop_info->id,
            'alias'                   => $shop_info->alias,
            'alias_permission'        => in_array('shop_alias', $user_shop_permission['permission']) ? true : false,
            'name'                    => $shop_info->name,
            'name_permission'         => in_array('shop_name', $user_shop_permission['permission'])  ? true : false,
            'address'                 => $shop_info->address,
            'address_permission'      => in_array('shop_address', $user_shop_permission['permission']) ? true : false,
            'phone'                   => $shop_info->phone,
            'phone_permission'        => in_array('shop_phone', $user_shop_permission['permission']) ? true : false,
            'show_phone'              => $shop_info->shop_set->show_phone,
            'show_phone_permission'   => in_array('shop_show_phone', $user_shop_permission['permission']) ? true : false,
            'logo'                    => $shop_info->logo ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $shop_info->logo : NULL,
            'logo_permission'         => in_array('shop_logo', $user_shop_permission['permission']) ? true : false,
            'banner'                  => $shop_info->banner ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $shop_info->banner : NULL,
            'banner_permission'       => in_array('shop_banner', $user_shop_permission['permission']) ? true : false,
            'color_select'            => $shop_info->shop_set->color_select,
            'color_select_permission' => in_array('shop_color', $user_shop_permission['permission']) ? true : false,
            'color'                   => $shop_info->shop_set->color,
            'color_permission'        => in_array('shop_color', $user_shop_permission['permission']) ? true : false,
            'info'                    => $shop_info->info,
            'info_permission'         => in_array('shop_info', $user_shop_permission['permission']) ? true : false,
            'shop_url'                => $shop_home_url,
            'qrcode'                  => "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $shop_home_url . "&choe=UTF-8",
            'address_url'             => $shop_info->address ? "https://www.google.com/maps/search/?api=1&query=" . $shop_info->address : "NULL",
        ];

        return response()->json(['status' => true, 'permission' => true, 'data' => $data]);
    }

    // 取得指定分店營業時間資料 
    public function shop_business_hour($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('business_hour', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info      = Shop::find($shop_id);
        $business_hour  = ShopBusinessHour::where('shop_id', $shop_info->id)->where('shop_staff_id', NULL)->orderBy('start')->get();
        $business_hours = [];

        for ($i = 1; $i <= 7; $i++) {
            $business_hours[$i - 1] = [
                'type' => true,
                'week' => $i,
                'time' => [],
            ];
            foreach ($business_hour->where('week', $i)  as $hour) {
                if ($hour->type == 0) {
                    $business_hours[$i - 1]['type'] = false;
                }
                $business_hours[$i - 1]['time'][] = [
                    'start' => date('c', strtotime(date('Y-m-d H:i:s', strtotime($hour->start)))),
                    'end'   => date('c', strtotime(date('Y-m-d H:i:s', strtotime($hour->end)))),
                ];
            }
        }
        $business_hours_permission = in_array('shop_business_hour', $user_shop_permission['permission']) ? true : false;

        // 間隔公休日
        $close = ShopClose::where('shop_id', $shop_info->id)->where('shop_staff_id', NULL)->first();
        if (!$close) {
            $close = [
                'type' => '',
                'week' => '',
            ];
        }else{
            $close->type = (string)$close->type;
            $close->week = '';
        }
        $close_permission = in_array('shop_close', $user_shop_permission['permission']) ? true : false;

        // 特殊休假日
        $vacation = ShopVacation::where('shop_id', $shop_info->id)->where('shop_staff_id', NULL)->get();
        foreach ($vacation as $v) {
            $v->start_time = $v->start_time ? date('c', strtotime(date('Y-m-d H:i:s', strtotime($v->start_time)))) : NULL;
            $v->end_time   = $v->end_time ? date('c', strtotime(date('Y-m-d H:i:s', strtotime($v->end_time)))) : NULL;
        }
        if ($vacation->count() == 0) {
            $vacation[] = [
                "id"            => null,
                "shop_id"       => 1,
                "shop_staff_id" => 4,
                "type"          => null,
                "start_date"    => null,
                "start_time"    => null,
                "end_date"      => null,
                "end_time"      => null,
                "note"          => null,
            ];
        }
        $vacation_permission = in_array('shop_vacation', $user_shop_permission['permission']) ? true : false;

        return response()->json(['status' => true, 'permission' => true, 'data' => compact('business_hours', 'business_hours_permission', 'close', 'close_permission', 'vacation', 'vacation_permission')]);
    }

    // 取得shop社群資料
    public function shop_social_info($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_social', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);
        $data = [
            'line'                     => $shop_info->line,
            'line_permission'          => in_array('shop_line', $user_shop_permission['permission']) ? true : false,
            'line_name'                => $shop_info->line_name,
            'line_name_permission'     => in_array('shop_line_name', $user_shop_permission['permission']) ? true : false,
            'facebook_name'            => $shop_info->facebook_name,
            'facebook_name_permission' => in_array('shop_facebook', $user_shop_permission['permission']) ? true : false,
            'facebook_url'             => $shop_info->facebook_url,
            'facebook_url_permission'  => in_array('shop_facebook', $user_shop_permission['permission']) ? true : false,
            'ig'                       => $shop_info->ig,
            'ig_permission'            => in_array('shop_ig', $user_shop_permission['permission']) ? true : false,
            'ig_name'                  => $shop_info->ig_name,
            'ig_name_permission'       => in_array('shop_ig_name', $user_shop_permission['permission']) ? true : false,
            'web_name'                 => $shop_info->web_name,
            'web_name_permission'      => in_array('shop_web', $user_shop_permission['permission']) ? true : false,
            'web_url'                  => $shop_info->web_url,
            'web_url_permission'       => in_array('shop_web', $user_shop_permission['permission']) ? true : false,
        ];

        return response()->json(['status' => true, 'permission' => true, 'data' => $data]);
    }

    // 取得shop環境照片資料
    public function shop_photo($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_photo', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $shop_photos  = $shop_info->shop_photos;
        $company_info = $shop_info->company_info;
        foreach ($shop_photos as $photo) {
            $photo->photo = env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $photo->photo;
        }

        $upload_permission = in_array('shop_photo_edit', $user_shop_permission['permission']) ? true : false;
        $delete_permission = in_array('shop_photo_delete', $user_shop_permission['permission']) ? true : false;

        $data = [
            'status'            => true,
            'upload_permission' => $upload_permission,
            'delete_permission' => $delete_permission,
            'photo_upload'      => $shop_photos->count() >= 6 ? false : true,
            'data'              => $shop_photos
        ];

        return response()->json($data);
    }

    // 取得shop所有設定資料
    public function shop_set($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        $shop_info = Shop::find($shop_id);

        $shop_set = $shop_info->shop_set;

        return response()->json(['status' => true, 'data' => $shop_set]);
    }

    // 取得商家簡訊發送記錄
    public function shop_message_log($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_message_log',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $logs = MessageLog::where('shop_id', $shop_id)->orderBy('id', 'DESC')->get();
        foreach ($logs as $log) {

            if( !$log->customer_info ){
                $log->customer_info = Customer::where('phone',$log->phone)->withTrashed()->first();
            }

            $log->status        = $log->use == 0 ? '失敗' : '成功';
            $log->customer_name = $log->customer_info ? $log->customer_info->realname : '';
        }

        return response()->json(['status' => true, 'permission' => true, 'last_message' => $shop_info->gift_sms + $shop_info->buy_sms, 'data' => $logs]);
    }

    // 儲存分店基本資料
    public function shop_basic_data_save($shop_id)
    {
        // 驗證欄位資料
        $rules     = ['name' => 'required', 'address' => 'required', 'phone' => 'required'];
        $messages = [
            'name.required'    => '請填寫商家名稱',
            'address.required' => '請填寫地址',
            'phone.required'   => '請填寫預約電話',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        // 分店資料儲存
        if (!request('id')) $shop = new Shop;
        $shop          = Shop::find($shop_id);
        $shop->name    = request('name');
        $shop->alias   = request('alias') ?: $shop->alias;
        $shop->address = request('address');
        $shop->phone   = request('phone');
        $shop->info    = request('info');
        $shop->save();

        // 分店設定資料儲存
        $set = ShopSet::where('shop_id', $shop->id)->first();
        if (!$set) $set = new ShopSet;
        $set->shop_id      = $shop->id;
        $set->color_select = request('color_select');
        $set->color        = request('color');
        $set->show_phone   = request('show_phone');
        $set->save();

        // 圖片處理
        $company = Company::where('id', $shop->company_id)->first();

        if (request('logo') && preg_match('/base64/i', request('logo'))) {
            $picName = PhotoController::save_base64_photo($company->companyId, request('logo'), $shop->logo);
            $shop->logo = $picName;
            $shop->save();
        }

        if (request('banner') && preg_match('/base64/i', request('banner'))) {
            $picName = PhotoController::save_base64_photo($company->companyId, request('banner'), $shop->banner);
            $shop->banner = $picName;
            $shop->save();
        }

        return response()->json(['status' => true]);
    }

    // 儲存分店營業時間資料
    public function shop_business_hour_save($shop_id)
    {
        // 營業時間
        $insert = [];
        ShopBusinessHour::where('shop_id', $shop_id)->where('shop_staff_id', NULL)->delete();
        foreach (request('business_hours') as $business_hour) {
            foreach ($business_hour['time'] as $time) {
                $insert[] = [
                    'shop_id' => $shop_id,
                    'type'    => $business_hour['type'],
                    'week'    => $business_hour['week'],
                    'start'   => strtotime($time['start']) < strtotime($time['end']) ? date('H:i:s', strtotime($time['start'])) : date('H:i:s', strtotime($time['end'])),
                    'end'     => strtotime($time['start']) > strtotime($time['end']) ? date('H:i:s', strtotime($time['start'])) : date('H:i:s', strtotime($time['end'])),
                ];
            }
        }
        ShopBusinessHour::insert($insert);

        // 找出營業時間設定與商家相同的員工營業時間資料
        $shop_staffs = ShopStaff::where('shop_id', $shop_id)->get();
        foreach ($shop_staffs as $staff) {
            $job = new SetStaffBussine($insert, $staff, $shop_id);
            dispatch($job);
        }

        // 間隔公休日
        ShopClose::where('shop_id', $shop_id)->where('shop_staff_id', NULL)->delete();
        $close_data = request('close');
        $weeks = explode(',', $close_data['week']);
        $weeks = array_filter($weeks);

        $close = new ShopClose;
        $close->shop_id = $shop_id;
        $close->type    = $close_data['type'];
        $close->week    = $close_data['type'] != 0 ? implode(',', $weeks) : NULL;
        $close->save();

        // 特殊休假日
        ShopVacation::where('shop_id', $shop_id)->where('shop_staff_id', NULL)->delete();
        $insert = [];
        foreach (request('vacation') as $vacation) {
            if ($vacation['type'] != '') {
                $insert[] = [
                    'shop_id'    => $shop_id,
                    'type'       => $vacation['type'],
                    'start_date' => $vacation['start_date'],
                    'start_time' => $vacation['type'] == 2 ? ($vacation['start_time'] > $vacation['end_time'] ? date('H:i', strtotime($vacation['end_time'])) : date('H:i', strtotime($vacation['start_time']))) : NULL,
                    'end_date'   => $vacation['type'] == 3 || $vacation['type'] == 2 ? $vacation['start_date'] : $vacation['end_date'],
                    'end_time'   => $vacation['type'] == 2 ? ($vacation['start_time'] < $vacation['end_time'] ? date('H:i', strtotime($vacation['end_time'])) : date('H:i', strtotime($vacation['start_time']))) : NULL,
                    'note'       => $vacation['note'],
                ];
            }
        }
        ShopVacation::insert($insert);

        return response()->json(['status' => true]);
    }

    // 儲存shop社群連結資料
    public function shop_social_info_save($shop_id)
    {
        // 分店資料儲存
        $shop                = Shop::find($shop_id);
        $shop->line          = request('line');
        $shop->line_url      = request('line_url');
        $shop->facebook_name = request('facebook_name');
        $shop->facebook_url  = request('facebook_url');
        $shop->ig            = request('ig');
        $shop->ig_url        = request('ig_url');
        $shop->web_name      = request('web_name');
        $shop->web_url       = request('web_url');
        $shop->save();

        return response()->json(['status' => true]);
    }

    // 儲存shop環境照片資料
    public function shop_photo_save($shop_id)
    {
        $shop    = Shop::find($shop_id);
        $company = Company::where('id', $shop->company_id)->first();

        // $shop_photo_count = $shop->shop_photos->count();
        // if( $shop_photo_count == 6 ){
        //     return response()->json(['status'=>false,'errors'=>['message'=>['照片上傳超過6張數量']]]);
        // }

        $return_data  = request()->all();
        $old_photo_id = [];
        $insert       = [];
        foreach ($return_data as $data) {
            if ($data['id']) $old_photo_id[] = $data['id'];
            else              $insert[]       = $data['photo'];
        }

        // 先刪除照片
        $delete_photos = ShopPhoto::where('shop_id', $shop_id)->whereNotIn('id', $old_photo_id)->get();
        foreach ($delete_photos as $dp) {
            // 先判斷資料夾是否存在
            $file_path = env('OLD_OTHER') . '/' . $shop->alias;
            if (!file_exists($file_path)) {
                $old = umask(0);
                mkdir($file_path, 0775, true);
                umask($old);
            }

            // 刪除舊的照片
            $filePath = env('OLD_OTHER') . '/' . $shop->alias . '/' . $dp->photo;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        ShopPhoto::where('shop_id', $shop_id)->whereNotIn('id', $old_photo_id)->delete();

        // 儲存新照片
        $inser_photo = [];
        foreach ($insert as $k => $photo) {
            $picName = PhotoController::save_base64_photo($shop->alias, $photo);
            $inser_photo[] = [
                'shop_id' => $shop_id,
                'photo'   => $picName,
            ];
        }

        ShopPhoto::insert($inser_photo);

        return response()->json(['status' => true]);
    }

    // 儲存shop設定相關資料
    public function shop_set_save($shop_id)
    {
        // 分店資料儲存
        $shop_set = ShopSet::find($shop_id);
        // 預約審核0不審核1需審核
        if (request('reservation_check')) {
            $shop_set->reservation_check = request('reservation_check');
        }
        // 網頁主色選項1預設2自定
        if (request('color_select')) {
            $shop_set->color_select = request('color_select');
        }
        // 主題顏色
        if (request('color')) {
            $shop_set->color = request('color');
        }
        // 預約服務顯示樣式1圖文2條列
        if (request('show_service_type')) {
            $shop_set->show_service_type = request('show_service_type');
        }
        // 預約電話顯示1顯示0不顯示
        if (request('show_phone')) {
            $shop_set->show_phone = request('show_phone');
        }
        // line@圖片
        if (request('line_photo')) {
            $shop_set->line_photo = request('line_photo');
        }

        $shop_set->save();

        return response()->json(['status' => true]);
    }

    // 刪除商家指定單張環境照片
    public function shop_photo_delete($shop_id, $shop_photo_id)
    {
        $shop_photo = ShopPhoto::find($shop_photo_id);
        if (!$shop_photo) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到商家環境照片']]]);
        }

        $shop    = Shop::find($shop_id);
        $company = $shop->company_info;

        // 移除照片
        $filePath = env('OLD_OTHER') . '/' . $company->companyId . '/' . $shop_photo->photo;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $shop_photo->delete();

        return response()->json(['status' => true]);
    }

    // 取得商家合約資料(free、plus)
    public function shop_contract($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('contract', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        // 方案內容
        $contact = [
            'buy_mode'       => $shop_info->buy_mode_info->title,
            'change_mode'    => in_array('change_mode', $user_shop_permission['permission']) ? true : false,
            'mode_terms'     => in_array('mode_terms', $user_shop_permission['permission']) ? true : false,
            'deadline'       => substr($shop_info->deadline, 0, 10),
            'renew'          => $shop_info->buy_mode_id == 0 ? false : true,//in_array('renew', $user_shop_permission['permission']) ? true : false,
            'point_exchange' => false,//in_array('point_exchange', $user_shop_permission['permission']) ? true : false,
            'last_day'       => (strtotime(substr($shop_info->deadline, 0, 10)) - strtotime(date('Y-m-d'))) / (60 * 60 * 24),
            'price'          => $shop_info->buy_mode_info->price,
        ];

        // 簡訊方案
        $sms = [
            'last_sms'    => $shop_info->gift_sms + $shop_info->buy_sms,
            'buy_sms'     => in_array('buy_sms', $user_shop_permission['permission']) ? true : false,
            'message_log' => in_array('message_log', $user_shop_permission['permission']) ? true : false,
        ];

        $data = [
            'status'                  => true,
            'permission'              => true,
            'mode_change_permission'  => true,
            'terms_permission'        => true,
            'mode_renews_permisssion' => $shop_info->buy_mode_id == 0 ? false : true,
            'extend_point_permission' => false,
            'buy_sms_permission'      => true,
            'message_log_permission'  => true,
            'data'                    => compact('contact', 'sms')
        ];

        return response()->json($data);
    }

    // 方案變更項目(free、plus)
    public function change_mode_list($shop_id, $buy_mode_id = "")
    {
        // 拿取使用者的集團權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);

        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('change_mode_read', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        $shop_info = Shop::find($shop_id);

        switch ($shop_info->buy_mode_id) {
            case 0:
            case 1:
                $buy_modes = BuyMode::where('status', 'published')->whereIn('id', [1, 2, 5, 6])->get();
                break;
            case 2:
                $buy_modes = BuyMode::where('status', 'published')->whereIn('id', [2, 5, 6])->get();
                break;
            case 5:
                $buy_modes = BuyMode::where('status', 'published')->whereIn('id', [5,6])->get();
                // $buy_modes = BuyMode::whereIn('id', [5, 6])->get();
                break;
            case 6:
                $buy_modes = BuyMode::where('status', 'published')->whereIn('id', [6])->get();
                // $buy_modes = BuyMode::whereIn('id', [6])->get();
                break;
        }

        foreach ($buy_modes as $mode) {
            $today = date('Y-m-d');
            if ($shop_info->buy_mode_id != 0) {
                $deadline = $shop_info->company_info->deadline ? substr($shop_info->company_info->deadline, 0, 10) : date('Y-m-d');
                $last_day = (strtotime($deadline) - strtotime(date('Y-m-d'))) / (60 * 60 * 24); // 剩餘天數
                switch ($mode->id) {
                    case 1:
                    case 2:
                    case 5:
                    case 6:
                        $days = ($mode->during / 12) * 365;
                        $deadline = '';
                        if ($shop_info->deadline == NULL || strtotime($shop_info->deadline) < time()) {
                            $deadline = date("Y-m-d 23:59:59", strtotime("+" . $days . " day", date('Y-m-d H:i:s')));
                        } elseif (strtotime($shop_info->deadline) >= time()) {
                            $deadline = date("Y-m-d 23:59:59", strtotime("+" . $days . " day", strtotime($shop_info->deadline)));
                        }

                        $mode->deadline = $deadline;
                        break;
                        // case 2:
                        //     $add_day = $last_day > 0 ? ceil($last_day/3) : 0; 
                        //     $mode->deadline = date('Y-m-d',strtotime($today."+1 year +".$add_day." day"));
                        //     break;
                        // case 3:
                        //     $add_day = $last_day > 0 ? ceil($last_day/5.5) : 0; 
                        //     $mode->deadline = date('Y-m-d',strtotime($today."+1 year +".$add_day." day"));
                        //     break;
                }
            } else {
                $mode->deadline = date('Y-m-d', strtotime($today . "+1 year"));
            }
        }

        $user_info = auth()->getUser();

        return response()->json([
            'status'      => true,
            'permission'  => true,
            'buy_mode_id' => $buy_mode_id,
            'data'        => $buy_modes,
            'default'     => [
                'id'       => $buy_mode_id ? (int)$buy_mode_id : ($shop_info->buy_mode_id == 0 ? 1 : $shop_info->buy_mode_id),
                'pay_type' => 'CREDIT',
                'phone'    => $user_info->code ? (User::find($user_info->code) ? User::find($user_info->code)->phone : '')  : '',
            ],
        ]);
    }

    // 取得商家續費方案(free、plus)
    public function shop_renew_mode($shop_id)
    {
        // 拿取使用者的集團權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('renew_read', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::where('id', $shop_id)->first();

        if ($shop_info->buy_mode_id == 0) {
            $shop_info->buy_mode_info = BuyMode::find(1);
        }

        $shop_info->buy_mode_info->deadline = date('Y-m-d', strtotime($shop_info->deadline . "+1 year"));

        $permission = Permission::where('shop_id', $shop_id)->where('shop_staff_id', NULL)->first();

        $user_info = auth()->getUser();
        $shop_info->buy_mode_info->phone = $user_info->code ? (User::find($user_info->code) ? User::find($user_info->code)->phone : '')  : '';

        return response()->json(['status' => true, 'permission' => true, 'data' => $shop_info->buy_mode_info]);
    }

    // 取得商家付款記錄資料(free、plus)
    public function shop_order($shop_id)
    {
        // 拿取使用者的集團權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('order_log', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $orders = Order::where('shop_id', $shop_id)->where('pay_status', '!=', 'N')->get();
        $data = [];
        foreach ($orders as $order) {
            $data[] = [
                'oid'        => $order->oid,
                'name'       => $order->note,
                'date'       => date('Y-m-d', strtotime($order->created_at)),
                'pay_date'   => $order->pay_date,
                'pay_status' => $order->pay_status == 'Y' ? '已付款' : ($order->pay_status == 'N' ? '待付款' : '付款逾期'),
                'price'      => $order->price,
            ];
        }

        return response()->json(['status' => true, 'permission' => true, 'data' => $data]);
    }

    // 取得商家的點數兌換資料
    public function shop_direct_points($shop_id)
    {
        // 拿取使用者的集團權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('point_exchange', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $direct_points = DirectSalesPointLog::where('user_id', auth()->getUser()->id)->sum('point');

        $mode = 'pro';
        if (in_array($shop_info->buy_mode_id, [1, 2, 3, 4, 5, 6, 11, 12])) $mode = 'plus';

        $direct_exchange_mode = DirectExchangeMode::where('mode', $mode)->where('status', 'published')->orderBy('sequence', 'ASC')->get();

        $data = [
            'status'               => true,
            'permission'           => true,
            'direct_exchange_mode' => $direct_exchange_mode,
            'direct_points'        => $direct_points,
        ];

        return response()->json($data);
    }

    // 商家使用點數兌換
    public function shop_exchange_points($shop_id)
    {
        $exange_mode = DirectExchangeMode::find(request('direct_exchange_mode_id'));

        // 先取得有多少點數
        $direct_points = DirectSalesPointLog::where('user_id', auth()->getUser()->id)->sum('point');

        // 檢查點數是否足夠兌換
        if ($direct_points < $exange_mode->point) {
            return response()->json(['status' => true, 'errors' => ['message' => ['兌換點數不足，無法兌換']]]);
        }

        if ($exange_mode->type == 'extend') {
            // 兌換使用期限
            $shop_info = Shop::find($shop_id);

            $old_deadline = $shop_info->deadline;
            $shop_info->deadline = date("Y-m-d 23:59:59", strtotime("+" . $exange_mode->extend . " month", strtotime($shop_info->deadline)));
            $shop_info->save();

            // 點數活動紀錄
            $point               = new DirectSalesPointLog;
            $point->user_id      = auth()->getUser()->id;
            $point->type         = 'out';
            $point->point        = '-' . $exange_mode->point;
            $point->extend_mode  = $shop_info->buy_mode_id;
            $point->extend_month = $exange_mode->extend;
            $point->content      = '使用點數：' . $exange_mode->point . '點，展延使用期限' . ($exange_mode->extend) . '個月(' . $old_deadline . ' -> ' . $shop_info->deadline . ')';
            $point->save();
        } else {
            // 兌換現金(未完成)
            // 點數活動紀錄
            $point               = new DirectSalesPointLog;
            $point->user_id      = auth()->getUser()->id;
            $point->type         = 'out';
            $point->money        = $exange_mode->dollar;
            $point->extend_mode  = $shop_info->buy_mode_id;
            $point->content      = '使用點數：' . $exange_mode->point . '點，兌換$' . $exange_mode->dollar;
            $point->save();
        }

        return response()->json(['status' => true]);
    }
}
