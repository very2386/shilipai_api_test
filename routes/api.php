<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TestController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\v1\BillController;
use App\Http\Controllers\v1\CheckoutController;
use App\Http\Controllers\v1\CompanyController;
use App\Http\Controllers\v1\CollectionController;
use App\Http\Controllers\v1\GoogleCalendarController;
use App\Http\Controllers\v1\HomeController;
use App\Http\Controllers\v1\LineController;
use App\Http\Controllers\v1\MoneyController;
use App\Http\Controllers\v1\PermissionController;
use App\Http\Controllers\v1\PhotoController;
use App\Http\Controllers\v1\ReservationController;
use App\Http\Controllers\v1\ShopController;
use App\Http\Controllers\v1\ShopCategoryController;
use App\Http\Controllers\v1\ShopServiceController;
use App\Http\Controllers\v1\ShopAdvanceController;
use App\Http\Controllers\v1\ShopCouponController;
use App\Http\Controllers\v1\ShopLoyaltyCardController;
use App\Http\Controllers\v1\ShopReservationController;
use App\Http\Controllers\v1\ShopStaffController;
use App\Http\Controllers\v1\ShopTitleController;
use App\Http\Controllers\v1\ShopPostController;
use App\Http\Controllers\v1\ShopCustomerController;
use App\Http\Controllers\v1\ShopManagementController;
use App\Http\Controllers\v1\ShopAutoManagementController;
use App\Http\Controllers\v1\ShopManagementRefuseController;
use App\Http\Controllers\v1\ShopNoticeController;
use App\Http\Controllers\v1\UserController;
use App\Http\Controllers\v1\NewReservationController;
use App\Http\Controllers\v1\OrderController;
use App\Http\Controllers\v1\ShopAwardNoticeController;
use App\Http\Controllers\v1\ShopFestivalNoticeController;
use App\Http\Controllers\v1\TeachingController;
use App\Http\Controllers\v1\ShopManagementGroupController;
use App\Http\Controllers\v1\ShopMembershipCardController;
use App\Http\Controllers\v1\ShopProductCategoryController;
use App\Http\Controllers\v1\ShopProductCheckController;
use App\Http\Controllers\v1\ShopProductController;
use App\Http\Controllers\v1\ShopProductLogController;
use App\Http\Controllers\v1\ShopProgramController;
use App\Http\Controllers\v1\ShopTopUpController;
use App\Models\ShopCustomer;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// 管理台使用者登入
Route::post('/auth/login', [UserController::class, 'login']);
// 管理台註冊
Route::post('/auth/register', [UserController::class, 'register']);
// 管理台註冊/忘記密碼發送驗證碼
Route::post('/auth/send/verify', [UserController::class, 'auth_send_verification_code']);
// 管理台註冊/忘記密碼簡訊驗證
Route::post('/auth/check/verify', [UserController::class, 'auth_check_verification_code']);
// 管理台忘記密碼修改新密碼
Route::post('/auth/new/password', [UserController::class, 'auth_new_password']);

Route::middleware([AuthLogin::class])->prefix('v1')->group(function () {
    // 使用者登出
    Route::post('/auth/logout', [UserController::class, 'logout']);
    // 使用者管理的集團與商家
    Route::get('/user/permission', [UserController::class, 'user_permission']);
    // 選擇版本
    Route::get('/shop/{shop_id}/register/mode', [MoneyController::class, 'get_buy_mode']);

    /* 首頁 */
    // 拿取首頁資訊(舊版本)
    Route::get('/home', [HomeController::class, 'home']);
    // 拿取首頁資訊(新版本)
    Route::get('/shop/{shop_id}/home', [HomeController::class, 'new_home']);
    // 取得系統通知
    Route::get('/shop/{shop_id}/system/notice', [HomeController::class, 'shop_system_notice']);
    // 系統通知變更已讀(可多可單)
    Route::post('/shop/{shop_id}/system/notice/read', [HomeController::class, 'shop_system_notice_read']);
    // 拿取管理台上方資料
    Route::get('/shop/{shop_id}/get/top/info', [HomeController::class, 'get_top_info']);

    /* 權限 */
    // 取得company/shop權限
    Route::get('/{type}/permission/{id}', [PermissionController::class, 'get_permission']);

    /* 集團資料 */
    // 取得company合約資料
    Route::get('/company/{company_id}/contract', [CompanyController::class, 'company_contract']);
    // 取得company付款記錄資料
    Route::get('/company/{company_id}/order', [CompanyController::class, 'company_order']);
    // 取得集團續費方案
    Route::get('/company/{company_id}/renew/mode', [CompanyController::class, 'company_renew_mode']);
    // 取得方案變更項目(原集團方案為基本版、進階版使用)
    Route::get('/company/{company_id}/change/mode', [CompanyController::class, 'change_mode_list']);

    /* 商家資料 */
    // 確認商家管理的頁籤瀏覽權限
    Route::post('/shop/{shop_id}/tab/permission', [ShopController::class, 'shop_tab_permission']);
    // 取得商家全部關連資料
    Route::get('/shop/{shop_id}', [ShopController::class, 'shop_info']);
    // 取得商家基本資料資料
    Route::get('/shop/{shop_id}/basic', [ShopController::class, 'shop_basic']);
    // 取得商家營業時間資料
    Route::get('/shop/{shop_id}/business/hour', [ShopController::class, 'shop_business_hour']);
    // 取得商家社群資料
    Route::get('/shop/{shop_id}/social/info', [ShopController::class, 'shop_social_info']);
    // 取得商家環境照片資料
    Route::get('/shop/{shop_id}/photo', [ShopController::class, 'shop_photo']);
    // 取得商家所有設定資料
    Route::get('/shop/{shop_id}/set', [ShopController::class, 'shop_set']);
    // 取得商家簡訊發送記錄
    Route::get('/shop/{shop_id}/message/log', [ShopController::class, 'shop_message_log']);
    // 更新商家基本資料
    Route::patch('/shop/{shop_id}/basic', [ShopController::class, 'shop_basic_data_save']);
    // 更新商家營業時間資料
    Route::patch('/shop/{shop_id}/business/hour', [ShopController::class, 'shop_business_hour_save']);
    // 更新商家社群連結資料
    Route::patch('/shop/{shop_id}/social/info', [ShopController::class, 'shop_social_info_save']);
    // 更新商家環境照片資料
    Route::patch('/shop/{shop_id}/photo', [ShopController::class, 'shop_photo_save']);
    // 更新商家設定相關資料
    Route::patch('/shop/{shop_id}/set', [ShopController::class, 'shop_set_save']);
    // 刪除商家指定單張環境照片
    Route::delete('/shop/{shop_id}/photo/{shop_photo_id}', [ShopController::class, 'shop_photo_delete']);
    // 取得商家合約資料(free、plus)
    Route::get('/shop/{shop_id}/contract', [ShopController::class, 'shop_contract']);
    // 取得商家付款記錄資料(free、plus)
    Route::get('/shop/{shop_id}/order', [ShopController::class, 'shop_order']);
    // 取得集團續費方案(free、plus)
    Route::get('/shop/{shop_id}/renew/mode', [ShopController::class, 'shop_renew_mode']);
    // 取得方案變更項目(原集團方案為基本版、進階版使用)
    Route::get('/shop/{shop_id}/change/mode/{buy_mode_id?}', [ShopController::class, 'change_mode_list']);
    // 取得商家點數兌換資料
    Route::get('shop/{shop_id}/direct/points', [ShopController::class, 'shop_direct_points']);
    // 商家使用點數兌換
    Route::post('shop/{shop_id}/exchange/points', [ShopController::class, 'shop_exchange_points']);

    /* 藍新金流 */
    // 確認付款建立訂單回傳藍新設定值
    Route::post('/shop/{shop_id}/get/newebpay', [MoneyController::class, 'make_order']);

    /* 作品集 */
    // 取得商家全部作品集
    Route::get('/shop/{shop_id}/collections', [CollectionController::class, 'shop_collection']);
    // 新增｜編輯 商家作品集資料
    Route::get('/shop/{shop_id}/collection/{album_id?}', [CollectionController::class, 'shop_collection_info']);
    // 儲存建立商家作品集資料
    Route::post('/shop/{shop_id}/collection', [CollectionController::class, 'shop_collection_save']);
    // 刪除商家作品集資料
    Route::delete('/shop/{shop_id}/collection/{album_id}', [CollectionController::class, 'shop_collection_delete']);
    // 刪除商家作品集單張照片資料
    // Route::delete('/shop/{shop_id}/collection/{album_id}/photo/{photo_id}', [CollectionController::class, 'shop_collection_photo_delete']);

    /* 服務分類 */
    // 取得商家服務分類全部資料
    Route::get('/shop/{shop_id}/service/categories', [ShopCategoryController::class, 'shop_service_category']);
    // 新增｜編輯 商家服務分類資料
    Route::get('/shop/{shop_id}/service/category/{shop_category_id?}', [ShopCategoryController::class, 'shop_service_category_info']);
    // 儲存建立商家服務分類資料
    Route::post('/shop/{shop_id}/service/category', [ShopCategoryController::class, 'shop_service_category_save']);
    // 儲存更新商家服務分類資料
    Route::patch('/shop/{shop_id}/service/category/{shop_category_id}', [ShopCategoryController::class, 'shop_service_category_save']);
    // 刪除商家服務分類資料
    Route::delete('/shop/{shop_id}/service/category/{shop_category_id}', [ShopCategoryController::class, 'shop_service_category_delete']);
    // 選擇預設範例後儲存分類資料
    // Route::post('/shop/{shop_id}/category/sample/save', [ShopCategoryController::class, 'shop_sample_category_save']);

    /* 服務管理 */
    // 取得商家全部服務資料
    Route::get('/shop/{shop_id}/services', [ShopServiceController::class, 'shop_service']);
    // 新增｜編輯｜複製 商家服務資料
    Route::get('/shop/{shop_id}/service/{shop_service_id?}/{type?}', [ShopServiceController::class, 'shop_service_info']);
    // 建立商家服務資料
    Route::post('/shop/{shop_id}/service', [ShopServiceController::class, 'shop_service_save']);
    // 更新商家服務資料
    Route::patch('/shop/{shop_id}/service/{shop_service_id}', [ShopServiceController::class, 'shop_service_save']);
    // 刪除商家服務資料
    Route::delete('/shop/{shop_id}/service/{shop_service_id}', [ShopServiceController::class, 'shop_service_delete']);
    // 更改商家服務上下架狀態
    Route::post('/shop/{shop_id}/service/{shop_service_id}/status', [ShopServiceController::class, 'shop_service_status']);

    /* 加值服務 */
    // 取得商家全部加值服務資料
    Route::get('/shop/{shop_id}/advances', [ShopAdvanceController::class, 'shop_advance']);
    // 新增｜編輯｜複製 加值服務資料
    Route::get('/shop/{shop_id}/advance/{shop_advance_id?}/{type?}', [ShopAdvanceController::class, 'shop_advance_info']);
    // 建立商家指定加值服務資料
    Route::post('/shop/{shop_id}/advance', [ShopAdvanceController::class, 'shop_advance_save']);
    // 更新商家指定加值服務資料
    Route::patch('/shop/{shop_id}/advance/{shop_advance_id}', [ShopAdvanceController::class, 'shop_advance_save']);
    // 刪除商家指定加值服務資料
    Route::delete('/shop/{shop_id}/advance/{shop_advance_id}', [ShopAdvanceController::class, 'shop_advance_delete']);
    // 更改商家加值服務上下架狀態
    Route::post('/shop/{shop_id}/advance/{shop_advance_id}/status', [ShopAdvanceController::class, 'shop_advance_status']);

    /* 產品分類 */
    // 取得商家產品分類全部資料
    Route::get('/shop/{shop_id}/product/categories', [ShopProductCategoryController::class, 'shop_product_category']);
    // 新增｜編輯 商家產品分類資料
    Route::get('/shop/{shop_id}/product/category/{shop_product_category_id?}', [ShopProductCategoryController::class, 'shop_product_category_info']);
    // 儲存建立商家產品分類資料
    Route::post('/shop/{shop_id}/product/category', [ShopProductCategoryController::class, 'shop_product_category_save']);
    // 儲存更新商家產品分類資料
    Route::patch('/shop/{shop_id}/product/category/{shop_product_category_id}', [ShopProductCategoryController::class, 'shop_product_category_save']);
    // 刪除商家產品分類資料
    Route::delete('/shop/{shop_id}/product/category/{shop_product_category_id}', [ShopProductCategoryController::class, 'shop_product_category_delete']);

    /* 產品管理 */
    // 取得商家全部產品資料
    Route::get('/shop/{shop_id}/products', [ShopProductController::class, 'shop_products']);
    // 新增｜編輯｜複製 商家產品分類資料
    Route::get('/shop/{shop_id}/product/{shop_product_id?}', [ShopProductController::class, 'shop_product_info']);
    // 建立商家產品資料
    Route::post('/shop/{shop_id}/product', [ShopProductController::class, 'shop_product_save']);
    // 更新商家產品資料
    Route::patch('/shop/{shop_id}/product/{shop_product_id}', [ShopProductController::class, 'shop_product_save']);
    // 刪除商家產品資料
    Route::delete('/shop/{shop_id}/product/{shop_product_id}', [ShopProductController::class, 'shop_product_delete']);
    // 更改商家產品上下架狀態
    Route::post('/shop/{shop_id}/product/{shop_product_id}/status', [ShopProductController::class, 'shop_product_status']);

    /* 產品記錄 */
    // 取得商家單一產品銷售記錄
    Route::get('/shop/{shop_id}/product/{shop_product_id}/logs', [ShopProductLogController::class, 'shop_product_logs']);
    // 商家產品進銷存
    Route::get('shop/{shop_id}/products/psi', [ShopProductLogController::class, 'shop_product_psi']);
    // 商家進貨
    Route::get('shop/{shop_id}/products/purchase', [ShopProductLogController::class, 'shop_product_purchase']);
    // 商家進貨資料儲存
    Route::post('shop/{shop_id}/products/purchase/save', [ShopProductLogController::class, 'shop_product_purchase_save']);
    // 商家銷貨資料儲存
    Route::post('shop/{shop_id}/products/sale/save', [ShopProductLogController::class, 'shop_product_sale_save']);
    // 商家產品進銷存查看
    Route::get('shop/{shop_id}/psi/{shop_product_log_id}/look', [ShopProductLogController::class, 'shop_psi_look']);
    // 商家進銷存記錄作廢
    Route::delete('shop/{shop_id}/psi/{shop_product_log_id}/cancel', [ShopProductLogController::class, 'shop_psi_cancel']);
    
    /* 產品盤點 */
    // 商家盤點列表
    Route::get('shop/{shop_id}/products/check/lists', [ShopProductCheckController::class, 'shop_product_check_list']);
    // 商家產品盤點資料儲存
    Route::post('shop/{shop_id}/product/check/save', [ShopProductCheckController::class, 'shop_product_check_save']);
    // 商家產品盤點異常校正資料儲存
    Route::post('shop/{shop_id}/product/revision/save', [ShopProductCheckController::class, 'shop_product_revision_save']);
    // 商家產品盤點歷史記錄
    Route::get('shop/{shop_id}/product/{shop_product_id}/check/logs', [ShopProductCheckController::class, 'shop_product_check_logs']);
    // 商家產品盤點歷史記錄異常資訊
    Route::get('shop/{shop_id}/product/check/{shop_product_check_id}/error/info', [ShopProductCheckController::class, 'shop_product_check_error_info']);


    /* 預約 */
    // 取得商家指定月份預約行事曆資料
    Route::post('/shop/{shop_id}/calendar', [ShopReservationController::class, 'shop_calendar']);
    // 依據員工與時間拿取行事曆資料
    Route::post('/staff/calendar', [ShopReservationController::class, 'staff_calendar']);
    // 取得商家預約資料
    Route::get('/shop/{shop_id}/reservations', [ShopReservationController::class, 'shop_reservations']);
    // 新增｜編輯 預約資料
    Route::get('/shop/{shop_id}/reservation/{customer_reservation_id?}', [ShopReservationController::class, 'shop_reservation_info']);
    // 建立商家預約資料
    Route::post('/shop/{shop_id}/reservation', [ShopReservationController::class, 'shop_reservation_save']);
    // 更新商家預約資料
    Route::patch('/shop/{shop_id}/reservation', [ShopReservationController::class, 'shop_reservation_save']);
    // 刪除商家指定預約資料
    Route::delete('/shop/{shop_id}/reservation/{customer_reservation_id}', [ShopReservationController::class, 'shop_reservation_delete']);
    // 審核商家預約
    Route::post('/shop/{shop_id}/reservation/check', [ShopReservationController::class, 'shop_reservation_check']);
    // 取得商家指定月份、員工後的營業日期
    Route::post('/get/highlight/date', [ReservationController::class, 'get_highlight_date']);
    // 取得商家指定月份、員工後的不營業日期
    Route::post('/get/blacklist/date', [ReservationController::class, 'get_blacklist_date']);
    // 取得指定員工與對應日期的預約時間
    Route::post('/get/reservation/time', [ReservationController::class, 'get_reservation_time']);
    // 編輯預約設定-條件設定
    Route::get('/shop/{shop_id}/reservations/setting', [ShopReservationController::class, 'shop_reservation_setting']);
    // 儲存預約設定-條件設定
    Route::patch('/shop/{shop_id}/reservations/setting', [ShopReservationController::class, 'shop_reservation_setting_save']);
    // 編輯預約設定-標籤設定
    Route::get('/shop/{shop_id}/reservations/tag', [ShopReservationController::class, 'shop_reservation_tag']);
    // 儲存預約設定-標籤設定
    Route::patch('/shop/{shop_id}/reservations/tag', [ShopReservationController::class, 'shop_reservation_tag_save']);
    // 編輯預約通知設定
    Route::get('/shop/{shop_id}/reservations/message', [ShopReservationController::class, 'shop_reservation_message']);
    // 儲存預約通知設定
    Route::patch('/shop/{shop_id}/reservations/message', [ShopReservationController::class, 'shop_reservation_message_save']);
    // 拿取指定行事曆跳窗預約事件資料
    Route::get('/shop/{shop_id}/calendar/reservation/{customer_reservation_id}', [ShopReservationController::class, 'shop_calendar_reservation_info']);
    // 儲存指定行事曆跳窗預約狀態資料
    Route::patch('/shop/{shop_id}/calendar/reservation/{customer_reservation_id}', [ShopReservationController::class, 'shop_calendar_reservation_info_save']);

    /* 結帳 */
    // 新增結帳會員與員工選項
    Route::get('/shop/{shop_id}/get/checkout/option', [CheckoutController::class, 'checkout_select_option']);
    // 待結帳
    Route::get('/shop/{shop_id}/pending/checkout', [CheckoutController::class, 'pending_checkout']);
    // 預約項目結帳
    Route::get('/shop/{shop_id}/checkout/{customer_reservation_id}', [CheckoutController::class, 'reservation_checkout']);
    // 新增結帳
    Route::post('/shop/{shop_id}/checkout', [CheckoutController::class, 'create_checkout']);
    // 即時計算可使用優惠與消費資訊
    Route::post('/shop/{shop_id}/get/consumption', [CheckoutController::class, 'get_consumption']);
    // 使用儲值金變更消費資訊
    Route::post('/shop/{shop_id}/change/consumption', [CheckoutController::class, 'change_consumption']);
    // 確認結帳內容
    Route::get('/shop/{shop_id}/check/bill/{oid}', [CheckoutController::class, 'check_bill']);
    // 返回結帳
    Route::get('shop/{shop_id}/back/checkout/{oid}',[CheckoutController::class, 'back_checkout']);
    // 儲存結帳資料
    Route::post('/shop/{shop_id}/checkout/save', [CheckoutController::class, 'checkout_save']);
    // 完成結帳
    Route::post('/shop/{shop_id}/finish/checkout', [CheckoutController::class, 'finish_checkout']);
    // 帳單
    Route::get('/shop/{shop_id}/bill/{oid}', [BillController::class, 'bill_info']);
    // 帳單儲存上傳圖片
    Route::post('/shop/{shop_id}/bill/{oid}/upload/photo', [BillController::class, 'bill_upload_photo']);
    // 帳單作廢
    Route::post('/shop/{shop_id}/bill/{oid}/cancel', [BillController::class, 'bill_cancel']);

    /* 方案 */
    // 取得商家全部方案
    Route::get('/shop/{shop_id}/programs', [ShopProgramController::class, 'shop_programs']);
    // 新增｜編輯｜複製 商家方案資料
    Route::get('/shop/{shop_id}/program/{shop_program_id?}/{type?}', [ShopProgramController::class, 'shop_program_info']);
    // 建立商家方案資料
    Route::post('/shop/{shop_id}/program', [ShopProgramController::class, 'shop_program_save']);
    // 更新商家方案資料
    Route::patch('/shop/{shop_id}/program/{shop_program_id}', [ShopProgramController::class, 'shop_program_save']);
    // 刪除商家方案資料
    Route::delete('/shop/{shop_id}/program/{shop_program_id}', [ShopProgramController::class, 'shop_program_delete']);

    /* 儲值 */
    // 取得商家全部儲值
    Route::get('/shop/{shop_id}/TopUps', [ShopTopUpController::class, 'shop_TopUp']);
    // 新增｜編輯｜複製 商家儲值資料
    Route::get('/shop/{shop_id}/TopUp/{shop_top_up_id?}/{type?}', [ShopTopUpController::class, 'shop_top_up_info']);
    // 建立商家儲值資料
    Route::post('/shop/{shop_id}/TopUp', [ShopTopUpController::class, 'shop_top_up_save']);
    // 更新商家儲值資料
    Route::patch('/shop/{shop_id}/TopUp/{shop_TopUp_id}', [ShopTopUpController::class, 'shop_top_up_save']);
    // 刪除商家儲值資料
    Route::delete('/shop/{shop_id}/TopUp/{shop_TopUp_id}', [ShopTopUpController::class, 'shop_top_up_delete']);

    /* 會員卡 */
    // 取得商家全部會員卡
    Route::get('shop/{shop_id}/membershipCards', [ShopMembershipCardController::class, 'shop_membershipCards']);
    // 新增｜編輯｜複製 商家會員卡資料
    Route::get('/shop/{shop_id}/membershipCard/{shop_membership_card_id?}/{type?}', [ShopMembershipCardController::class, 'shop_membershipCard_info']);
    // 建立商家會員卡資料
    Route::post('/shop/{shop_id}/membershipCard', [ShopMembershipCardController::class, 'shop_membershipCard_save']);
    // 更新商家會員卡資料
    Route::patch('/shop/{shop_id}/membershipCard/{shop_membership_card_id}', [ShopMembershipCardController::class, 'shop_membershipCard_save']);
    // 刪除商家優惠券資料
    Route::delete('/shop/{shop_id}/membershipCard/{shop_membership_card_id}', [ShopMembershipCardController::class, 'shop_membershipCard_delete']);

    /* 優惠券 */
    // 取得商家全部優惠券
    Route::get('/shop/{shop_id}/coupons', [ShopCouponController::class, 'shop_coupon']);
    // 新增｜編輯｜複製 商家優惠券資料
    Route::get('/shop/{shop_id}/coupon/{shop_coupon_id?}/{type?}', [ShopCouponController::class, 'shop_coupon_info']);
    // 建立商家優惠券資料
    Route::post('/shop/{shop_id}/coupon', [ShopCouponController::class, 'shop_coupon_save']);
    // 更新商家優惠券資料
    Route::patch('/shop/{shop_id}/coupon/{shop_coupon_id}', [ShopCouponController::class, 'shop_coupon_save']);
    // 刪除商家優惠券資料
    Route::delete('/shop/{shop_id}/coupon/{shop_coupon_id}', [ShopCouponController::class, 'shop_coupon_delete']);

    /* 集點卡 */
    // 取得商家全部集點卡
    Route::get('/shop/{shop_id}/loyaltyCards', [ShopLoyaltyCardController::class, 'shop_loyaltyCard']);
    // 新增｜編輯｜複製 商家集點卡資料
    Route::get('/shop/{shop_id}/loyaltyCard/{shop_loyalty_card_id?}/{type?}', [ShopLoyaltyCardController::class, 'shop_loyaltyCard_info']);
    // 建立商家集點卡資料
    Route::post('/shop/{shop_id}/loyaltyCard', [ShopLoyaltyCardController::class, 'shop_loyaltyCard_save']);
    // 更新商家集點卡資料
    Route::patch('/shop/{shop_id}/loyaltyCard/{shop_loyalty_card_id}', [ShopLoyaltyCardController::class, 'shop_loyaltyCard_save']);
    // 刪除商家集點卡資料
    Route::delete('/shop/{shop_id}/loyaltyCard/{shop_loyalty_card_id}', [ShopLoyaltyCardController::class, 'shop_loyaltyCard_delete']);

    /* 員工管理 */
    // 取得商家全部員工資料
    Route::get('/shop/{shop_id}/staffs', [ShopStaffController::class, 'shop_staffs']);
    // 新增｜編輯 商家員工資料
    Route::get('/shop/{shop_id}/staff/{shop_staff_id?}', [ShopStaffController::class, 'shop_staff_info']);
    // 建立商家員工資料
    Route::post('/shop/{shop_id}/staff', [ShopStaffController::class, 'shop_staff_save']);
    // 更新商家員工資料
    Route::patch('/shop/{shop_id}/staff/{shop_staff_id}', [ShopStaffController::class, 'shop_staff_save']);
    // 解聘員工資料
    Route::patch('/shop/{shop_id}/staff/{shop_staff_id}/fire', [ShopStaffController::class, 'shop_staff_fire']);
    // 復職員工資料
    Route::patch('/shop/{shop_id}/staff/{shop_staff_id}/recover', [ShopStaffController::class, 'shop_staff_recover']);
    // 刪除商家員工資料
    Route::delete('/shop/{shop_id}/staff/{shop_staff_id}', [ShopStaffController::class, 'shop_staff_delete']);
    // 新增｜編輯 員工服務項目
    Route::get('/shop/{shop_id}/staff/{shop_staff_id}/service', [ShopStaffController::class, 'shop_staff_service']);
    // 儲存商家員工服務項目
    Route::patch('/shop/{shop_id}/staff/{shop_staff_id}/service', [ShopStaffController::class, 'shop_staff_service_save']);
    // 編輯員工代表作品
    Route::get('/shop/{shop_id}/staff/{shop_staff_id}/collection', [ShopStaffController::class, 'shop_staff_collect']);
    // 儲存員工代表作品
    Route::patch('/shop/{shop_id}/staff/{shop_staff_id}/collection', [ShopStaffController::class, 'shop_staff_collect_save']);
    // 編輯員工設定資料
    Route::get('/shop/{shop_id}/staff/{shop_staff_id}/set', [ShopStaffController::class, 'shop_staff_set']);
    // 儲存員工設定資料
    Route::patch('/shop/{shop_id}/staff/{shop_staff_id}/set', [ShopStaffController::class, 'shop_staff_set_save']);
    // 修改密碼
    Route::post('/shop/{shop_id}/staff/{shop_staff_id}/change/password', [ShopStaffController::class, 'shop_staff_change_password']);
    // 員工忘記密碼寄送驗證碼
    Route::post('/shop/{shop_id}/staff/{shop_staff_id}/send/verify', [ShopStaffController::class, 'shop_staff_send_verification_code']);
    // 員工忘記密碼確認驗證碼
    Route::post('/shop/{shop_id}/staff/{shop_staff_id}/check/verify', [ShopStaffController::class, 'shop_staff_check_verification_code']);
    // 員工忘記密碼更換新密碼
    Route::post('/shop/{shop_id}/staff/{shop_staff_id}/new/password', [ShopStaffController::class, 'shop_staff_new_password']);

    // 員工解除google calendar綁定(不再這裡使用)
    // Route::get('/shop/{shop_id}/disconect/staff/{shop_staff_id}/googleCalendar', [GoogleCalendarController::class, 'disconnect_googleCalendar']);

    /* 職稱 */
    // 取得商家全部職稱資料
    Route::get('/shop/{shop_id}/titles', [ShopTitleController::class, 'shop_titles']);
    // 儲存商家職稱資料
    Route::patch('/shop/{shop_id}/title', [ShopTitleController::class, 'shop_title_save']);
    // 刪除商家職稱資料
    Route::delete('/shop/{shop_id}/title/{company_title_id}', [ShopTitleController::class, 'shop_title_delete']);

    /* 貼文 */
    // 取得商家全部貼文資料
    Route::get('/shop/{shop_id}/posts', [ShopPostController::class, 'shop_posts']);
    // 新增｜編輯 商家貼文資料
    Route::get('/shop/{shop_id}/post/{shop_post_id?}', [ShopPostController::class, 'shop_post_info']);
    // 建立商家貼文資料
    Route::post('/shop/{shop_id}/post', [ShopPostController::class, 'shop_post_save']);
    // 更新商家貼文資料
    Route::patch('/shop/{shop_id}/post/{shop_post_id}', [ShopPostController::class, 'shop_post_save']);
    // 刪除商家貼文資料
    Route::delete('/shop/{shop_id}/post/{shop_staff_id}', [ShopPostController::class, 'shop_post_delete']);
    // 更新商家貼文置頂狀態
    Route::patch('/shop/{shop_id}/post/{shop_post_id}/top', [ShopPostController::class, 'shop_post_top_save']);

    /* 會員管理 */
    // 取得商家全部會員資料
    Route::get('/shop/{shop_id}/customers', [ShopCustomerController::class, 'shop_customers']);
    // 新增｜編輯 商家會員資料
    Route::get('/shop/{shop_id}/customer/{shop_customer_id?}', [ShopCustomerController::class, 'shop_customer_info']);
    // 建立商家會員資料
    Route::post('/shop/{shop_id}/customer', [ShopCustomerController::class, 'shop_customer_save']);
    // 更新商家會員資料
    Route::patch('/shop/{shop_id}/customer/{shop_customer_id}', [ShopCustomerController::class, 'shop_customer_save']);
    // 刪除商家會員資料
    Route::delete('/shop/{shop_id}/customer/{shop_customer_id}', [ShopCustomerController::class, 'shop_customer_delete']);
    // 儲存批次發送禮物資料
    Route::post('/shop/{shop_id}/give/gift', [ShopCustomerController::class, 'shop_give_gift']);
    // 會員首頁
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/home', [ShopCustomerController::class, 'shop_customer_home']);
    // 會員預約記錄
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/reservation', [ShopCustomerController::class, 'shop_customer_reservation']);
    // 會員已領取未使用優惠記錄
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/coupon', [ShopCustomerController::class, 'shop_customer_coupon']);
    // 會員已領取集點卡記錄
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/loyaltyCard', [ShopCustomerController::class, 'shop_customer_loyaltyCard']);
    // 會員集點卡記錄使用集點卡
    Route::post('/shop/{shop_id}/loyaltyCard/{customer_loyaltyCard_id}/use', [ShopCustomerController::class, 'shop_customer_loyaltyCard_use']);
    // 會員集點卡記錄給予點數
    Route::post('/shop/{shop_id}/loyaltyCard/{customer_loyaltyCard_id}/give', [ShopCustomerController::class, 'shop_customer_loyaltyCard_give']);
    // 會員人格
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/personality', [ShopCustomerController::class, 'shop_customer_personality']);
    // 會員五行
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/traits', [ShopCustomerController::class, 'shop_customer_traits']);
    // 會員問券回復記錄
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/question/answer', [ShopCustomerController::class, 'shop_customer_question_answer']);
    // 會員問券編修
    Route::patch('/shop/{shop_id}/customer/{shop_customer_id}/question/answer/save', [ShopCustomerController::class, 'shop_customer_question_answer_save']);
    // 會員服務評價記錄
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/evaluate', [ShopCustomerController::class, 'shop_customer_evaluate']);
    // 會員儲值記錄
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/topUps', [ShopCustomerController::class, 'shop_customer_topUps']);
    // 會員儲值人為修改
    Route::post('/shop/{shop_id}/customer/{shop_customer_id}/topUp/save', [ShopCustomerController::class, 'shop_customer_topUp_save']);
    // 會員購買方案
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/programs', [ShopCustomerController::class, 'shop_customer_programs']);
    // 會員儲存方案變動資料
    Route::post('/shop/{shop_id}/customer/{shop_customer_id}/program/save', [ShopCustomerController::class, 'shop_customer_save_programs']);
    // 會員方案使用記錄
    Route::get('/shop/{shop_id}/customer/program/{customer_program_id}/log', [ShopCustomerController::class, 'shop_customer_program_log']);
    // 會員可用優惠
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/discount', [ShopCustomerController::class, 'shop_customer_discount']);
    // 會員會員卡記錄
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/membershipCard', [ShopCustomerController::class, 'shop_customer_membership_card_log']);
    // 會員消費明細
    Route::get('/shop/{shop_id}/customer/{shop_customer_id}/bills', [ShopCustomerController::class, 'shop_customer_bills']);

    /* 熟客經營(服務通知) */
    // 取得服務通知列表資料
    Route::get('/shop/{shop_id}/management/group/lists', [ShopManagementGroupController::class, 'shop_management_group_lists']);
    // 新增｜編輯 商家服務通知資料
    Route::get('/shop/{shop_id}/management/group/info/{group_id?}', [ShopManagementGroupController::class, 'shop_management_group_info']);
    // 建立商家服務通知資料
    Route::post('/shop/{shop_id}/management/group', [ShopManagementGroupController::class, 'shop_management_group_save']);
    // 更新商家服務通知資料
    Route::patch('/shop/{shop_id}/management/group/{group_id}', [ShopManagementGroupController::class, 'shop_management_group_save']);
    // 刪除商家服務通知資料
    Route::delete('/shop/{shop_id}/management/group/{group_id}', [ShopManagementGroupController::class, 'shop_management_group_delete']);
    // 服務通知發送清單
    Route::get('/shop/{shop_id}/management/group/{group_id}/send/log', [ShopManagementGroupController::class, 'shop_management_group_send_log']);
    // 取得服務通知(訊息通知)模組列表 
    Route::get('/shop/{shop_id}/management/notice/mode/lists', [ShopNoticeController::class, 'shop_notice_mode_lists']);
    // 新增｜編輯 服務通知(訊息通知)模組資料
    Route::get('/shop/{shop_id}/management/notice/mode/{notice_mode_id?}', [ShopNoticeController::class, 'shop_notice_mode_info']);
    // 建立服務通知(訊息通知)模組資料
    Route::post('/shop/{shop_id}/management/notice/mode', [ShopNoticeController::class, 'shop_notice_mode_save']);
    // 更新商家服務通知(訊息通知)模組資料
    Route::patch('/shop/{shop_id}/management/notice/mode/{notice_mode_id}', [ShopNoticeController::class, 'shop_notice_mode_save']);
    // 刪除商家服務通知(訊息通知)模組資料
    Route::delete('/shop/{shop_id}/management/notice/mode/{notice_mode_id}', [ShopNoticeController::class, 'shop_notice_mode_delete']);

    /* 熟客經營(獎勵通知)*/
    // 取得獎勵通知列表資料
    Route::get('/shop/{shop_id}/award/notice/lists', [ShopAwardNoticeController::class, 'shop_award_notice_lists']);
    // 新增｜編輯 商家獎勵通知資料
    Route::get('/shop/{shop_id}/award/notice/info/{notice_id?}', [ShopAwardNoticeController::class, 'shop_award_notice_info']);
    // 建立商家獎勵通知資料
    Route::post('/shop/{shop_id}/award/notice', [ShopAwardNoticeController::class, 'shop_award_notice_save']);
    // 更新商家獎勵通知資料
    Route::patch('/shop/{shop_id}/award/notice/{notice_id}', [ShopAwardNoticeController::class, 'shop_award_notice_save']);
    // 刪除商家獎勵通知資料
    Route::delete('/shop/{shop_id}/award/notice/{notice_id}', [ShopAwardNoticeController::class, 'shop_award_notice_delete']);
    // 獎勵通知發送清單
    Route::get('/shop/{shop_id}/award/notice/{notice_id}/send/log', [ShopAwardNoticeController::class, 'shop_award_notice_send_log']);

    /* 熟客經營(節慶通知)*/
    // 取得節慶通知列表資料
    Route::get('/shop/{shop_id}/festival/notice/lists', [ShopFestivalNoticeController::class, 'shop_festival_notice_lists']);
    // 新增｜編輯 商家節慶通知資料
    Route::get('/shop/{shop_id}/festival/notice/info/{notice_id?}', [ShopFestivalNoticeController::class, 'shop_festival_notice_info']);
    // 建立商家節慶通知資料
    Route::post('/shop/{shop_id}/festival/notice', [ShopFestivalNoticeController::class, 'shop_festival_notice_save']);
    // 更新商家節慶通知資料
    Route::patch('/shop/{shop_id}/festival/notice/{notice_id}', [ShopFestivalNoticeController::class, 'shop_festival_notice_save']);
    // 刪除商家節慶通知資料
    Route::delete('/shop/{shop_id}/festival/notice/{notice_id}', [ShopFestivalNoticeController::class, 'shop_festival_notice_delete']);
    // 節慶通知發送清單
    Route::get('/shop/{shop_id}/festival/notice/{notice_id}/send/log', [ShopFestivalNoticeController::class, 'shop_festival_notice_send_log']);

    /* 熟客經營(自動推廣｜條件通知) */
    // 自動推廣列表
    Route::get('/shop/{shop_id}/auto/management/lists', [ShopAutoManagementController::class, 'shop_auto_management_lists']);
    // 新增｜編輯 商家 自動推廣｜條件通知 資料
    Route::get('/shop/{shop_id}/auto/management/info/{management_id?}', [ShopAutoManagementController::class, 'shop_auto_management_info']);
    // 建立商家 自動推廣｜條件通知 資料
    Route::post('/shop/{shop_id}/auto/management', [ShopAutoManagementController::class, 'shop_auto_management_save']);
    // 更新商家 自動推廣｜條件通知 資料
    Route::patch('/shop/{shop_id}/auto/management/{management_id}', [ShopAutoManagementController::class, 'shop_auto_management_save']);
    // 自動推廣｜條件通知 發送清單
    Route::get('/shop/{shop_id}/auto/management/{management_id}/send/log', [ShopAutoManagementController::class, 'shop_auto_management_send_log']);
    // 刪除商家 自動推廣｜條件通知 資料
    Route::delete('/shop/{shop_id}/auto/management/{management_id}', [ShopAutoManagementController::class, 'shop_auto_management_delete']);
    // 取得 自動推廣｜條件通知 模組列表 
    Route::get('/shop/{shop_id}/auto/management/mode/lists', [ShopAutoManagementController::class, 'shop_auto_management_mode_lists']);
    // 新增｜編輯 商家 自動推廣｜條件通知 模組資料
    Route::get('/shop/{shop_id}/auto/management/mode/{management_mode_id?}', [ShopAutoManagementController::class, 'shop_auto_management_mode_info']);
    // 建立商家 自動推廣｜條件通知 模組資料
    Route::post('/shop/{shop_id}/auto/management/mode', [ShopAutoManagementController::class, 'shop_auto_management_mode_save']);
    // 更新商家 自動推廣｜條件通知 模組資料
    Route::patch('/shop/{shop_id}/auto/management/mode/{management_mode_id}', [ShopAutoManagementController::class, 'shop_auto_management_mode_save']);
    // 刪除商家自動推廣模組資料
    Route::delete('/shop/{shop_id}/auto/management/mode/{management_mode_id}', [ShopAutoManagementController::class, 'shop_auto_management_mode_delete']);

    /* 熟客經營(拒收名單) */
    // 拒收名單列表
    Route::get('/shop/{shop_id}/management/refuse/lists', [ShopManagementRefuseController::class, 'shop_management_refuse_lists']);
    // 列入拒收名單
    Route::post('/shop/{shop_id}/management/add/refuse', [ShopManagementRefuseController::class, 'management_add_refuse']);
    // 拒收名單復原指定人員
    Route::post('/shop/{shop_id}/management/refuse/recover/{refuse_id}', [ShopManagementRefuseController::class, 'management_refuse_recover']);

    // 熟客經營預估發送數
    Route::post('/shop/{shop_id}/management/calculate/message', [Controller::class, 'management_calculate_message']);
    // 熟客經營發送測試簡訊
    Route::post('/shop/{shop_id}/management/send/message', [Controller::class, 'management_send_message']);
    // 熟客經營重新發送簡訊
    Route::post('/shop/{shop_id}/management/resend/message', [Controller::class, 'management_resend_message']);

    /* 使用教學 */
    // 取得引導模式資料
    Route::get('shop/{shop_id}/get/guide', [TeachingController::class, 'guide']);

    /* 熟客經營(單次推廣) */
    // 單次推廣列表
    Route::get('/shop/{shop_id}/once/management/lists', [ShopManagementController::class, 'shop_once_management_lists']);
    // 新增｜編輯 商家單次推廣資料
    Route::get('/shop/{shop_id}/once/management/info/{management_id?}', [ShopManagementController::class, 'shop_once_management_info']);
    // 建立商家單次推廣資料
    Route::post('/shop/{shop_id}/once/management', [ShopManagementController::class, 'shop_once_management_save']);
    // 更新商家單次推廣資料
    Route::patch('/shop/{shop_id}/once/management/{management_id}', [ShopManagementController::class, 'shop_once_management_save']);
    // 利用模組篩選名單
    Route::get('/shop/{shop_id}/management/mode/{mode}/customer/lists', [ShopManagementController::class, 'shop_once_management_customer_list']);
    // 刪除商家單次推廣資料
    Route::delete('/shop/{shop_id}/once/management/{management_id}', [ShopManagementController::class, 'shop_once_management_delete']);
    // 取得單次推廣模組列表 
    Route::get('/shop/{shop_id}/once/management/mode/lists', [ShopManagementController::class, 'shop_once_management_mode_lists']);
    // 新增｜編輯 商家單次推廣模組資料
    Route::get('/shop/{shop_id}/once/management/mode/{management_mode_id?}', [ShopManagementController::class, 'shop_once_management_mode_info']);
    // 建立商家單次推廣模組資料
    Route::post('/shop/{shop_id}/once/management/mode', [ShopManagementController::class, 'shop_once_management_mode_save']);
    // 更新商家單次推廣模組資料
    Route::patch('/shop/{shop_id}/once/management/mode/{management_mode_id}', [ShopManagementController::class, 'shop_once_management_mode_save']);
    // 刪除商家單次推廣模組資料
    Route::delete('/shop/{shop_id}/once/management/mode/{management_mode_id}', [ShopManagementController::class, 'shop_once_management_mode_delete']);
    // 單次推廣發送清單
    Route::get('/shop/{shop_id}/once/management/{management_id}/send/log', [ShopManagementController::class, 'shop_once_management_send_log']);

    /* 熟客經營(訊息通知) */
    // 取得訊息通知列表資料
    Route::get('/shop/{shop_id}/management/notice/lists', [ShopNoticeController::class, 'shop_notice_lists']);
    // 新增｜編輯 商家訊息通知資料
    Route::get('/shop/{shop_id}/management/notice/info/{management_id?}', [ShopNoticeController::class, 'shop_notice_info']);
    // 建立商家訊息通知資料
    Route::post('/shop/{shop_id}/management/notice', [ShopNoticeController::class, 'shop_notice_save']);
    // 更新商家訊息通知資料
    Route::patch('/shop/{shop_id}/management/notice/{management_id}', [ShopNoticeController::class, 'shop_notice_save']);
    // 訊息通知發送清單
    Route::get('/shop/{shop_id}/management/notice/{management_id}/send/log', [ShopNoticeController::class, 'shop_notice_send_log']);
    // 刪除商家訊息通知資料
    Route::delete('/shop/{shop_id}/management/notice/{management_id}', [ShopNoticeController::class, 'shop_notice_delete']);

    /* 熟客經營(服務評價) */
    // 服務評價設定資料
    // Route::get('/shop/{shop_id}/evaluate',[ShopEvaluateController::class, 'shop_evaluate']);
    // 服務評價設定資料儲存
    // Route::patch('/shop/{shop_id}/evaluate/save',[ShopEvaluateController::class, 'shop_evaluate_save']);

    // 排序
    Route::post('/items/sort', [Controller::class, 'item_sort']);
    // 取得簡訊方案
    Route::get('/shop/{shop_id}/sms/mode', [Controller::class, 'sms_mode']);
});

/* 實力派美業官網api */
// 美業官網會員註冊
Route::post('/customer/send/phone_check', [Controller::class, 'send_verification_code']);
// 取得商家指定月份、員工後的營業日期
Route::post('/get/highlight/date', [ReservationController::class, 'web_get_highlight_date']);
// 取得商家指定月份、員工後的不營業日期
Route::post('/get/blacklist/date', [ReservationController::class, 'web_get_blacklist_date']);
// 取得指定員工與對應日期的預約時間
Route::post('/get/reservation/time', [ReservationController::class, 'web_get_reservation_time']);
// 新增指定google calendar事件
Route::post('/insert/google/calendar', [GoogleCalendarController::class, 'web_insert_calendar_event']);
// 刪除指定google calendar事件
Route::post('/delete/google/calendar', [GoogleCalendarController::class, 'web_delete_calendar_event']);
// 會員取消預約
Route::post('/customer/cancel/reservation', [ReservationController::class, 'customer_cancel_reservation']);
// 取得指定員工與對應日期的預約時間(多個服務)
Route::post('/get/select/time', [NewReservationController::class, 'web_select_time']);

/* 美業管理台 */
// 員工解除google calendar綁定
Route::get('/shop/{shop_id}/disconect/staff/{shop_staff_id}/googleCalendar', [GoogleCalendarController::class, 'disconnect_googleCalendar']);
// google calendar 回傳
Route::get('/calendar/callback', [GoogleCalendarController::class, 'calendar_callback']);
// 顯示商家的所有圖片
Route::get('/show/{id}/{photo}', [PhotoController::class, 'show_photo']);
// 顯示商家的顧客圖片
Route::get('/get/customer/{photo}', [PhotoController::class, 'show_customer_photo']);
// 取得line@全部三格圖片
Route::get('/get/shop/{shop_id}/line/pic', [LineController::class, 'get_line_pic']);
// 顯示line@三格圖片
Route::get('/show/linePic/{category}/{photo_name}', [LineController::class, 'show_line_pic']);
// 顯示人格分析圖片
Route::get('/show/customer/personality/{number}', [PhotoController::class, 'show_customer_personality_photo']);
// 顯示五行分析圖片
Route::get('/show/customer/traits/{type}/{word}', [PhotoController::class, 'show_customer_traits_photo']);
// 藍新金流回傳
Route::post('/newebpay/pay/return', [MoneyController::class, 'newebpay_pay_return']);
// 藍新金流背景回傳
Route::post('/newebpay/notify/pay/return', [MoneyController::class, 'newebpay_notify_pay_return']);

/* line bot */
// 取得已確認/未確認預約
Route::post('/check/today/reservation', [ShopReservationController::class, 'check_today_reservation']);
// 預約事件確認/取消
Route::post('/check/reservation', [ShopReservationController::class, 'check_reservation']);
// 新增員工
Route::post('/lineBot/newStaff', [ShopStaffController::class, 'linebot_newStaff']);

/* 內部系統訂單記錄 */
Route::get('/get/orders', [OrderController::class, 'orders']);

// 移除fb連結應用程式
Route::post('/facebook/remove/callback', [ApiController::class, 'member_remove_fb']);

/* 測試項目 */
// JWT利用token回傳拿取使用者資料
Route::post('/me', [UserController::class, 'me']);
// 測試金流回傳
Route::post('/test/pay/return', [TestController::class, 'test_pay_return']);
Route::post('/test/pay/notify/return', [TestController::class, 'test_pay_return']);

/* 快速工具 */
// 節慶通知幫商家寫入預設
Route::get('/add/festival/data', [ToolController::class, 'add_festival_data']);
// 管理台權限更新
Route::get('/permission/update', [ToolController::class, 'permission_update']);
// 刪除指定集團與商家資料
Route::get('/delete/company/{company_id}', [ToolController::class, 'delete_company']);
// 補齊商家優惠券資料
Route::get('/write/shop/coupons', [ToolController::class, 'write_shop_coupons']);
// 補齊商家集點卡資料
Route::get('/write/shop/loyalty_cards', [ToolController::class, 'write_shop_loyalty_cards']);
// 補齊商家預設收款方式
Route::get('/write/shop/payType', [ToolController::class, 'write_shop_pay_type']);
// 補齊商家單購客資料
Route::get('/write/shop/onlyBuy', [ToolController::class, 'write_shop_onlyBuy']);
// 搬移指定舊系統至新系統
Route::post('/move/data', [ToolController::class, 'move_data']);
