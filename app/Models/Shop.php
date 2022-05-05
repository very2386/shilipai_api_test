<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 取得店家的購買方案資料
     */
    public function buy_mode_info()
    {
        return $this->hasOne(BuyMode::class,'id','buy_mode_id');
    }

    /**
     * 取得商家權限資料
     */
    public function permission_info()
    {
        return $this->hasOne(Permission::class,'shop_id','id');
    }

    /**
     * 取得集團資料
     */
    public function company_info()
    {
        return $this->belongsTo(Company::class,'company_id','id');
    }

    /**
     * 取得分店設定
     */
    public function shop_set()
    {
        return $this->hasOne(ShopSet::class,'shop_id','id');
    }

    /**
     * 取得分店營業時間
     */
    public function shop_business_hours()
    {
        return $this->hasMany(ShopBusinessHour::class,'shop_id','id');
    }

    /**
     * 取得分店間隔公休日
     */
    public function shop_close()
    {
        return $this->hasOne(ShopClose::class,'shop_id','id');
    }

    /**
     * 取得分店特殊休假日
     */
    public function shop_vacation()
    {
        return $this->hasMany(ShopVacation::class,'shop_id','id');
    }

    /**
     * 取得分店預約資料
     */
    public function shop_reservations()
    {
        return $this->hasMany(CustomerReservation::class,'shop_id','id');
    }

    /**
     * 取得分店預約標籤資料
     */
    public function shop_reservation_tags()
    {
        return $this->hasMany(ShopReservationTag::class,'shop_id','id');
    }

    /**
     * 取得分店預約通知訊息資料
     */
    public function shop_reservation_messages()
    {
        return $this->hasMany(ShopReservationMessage::class,'shop_id','id');
    }

    /**
     * 取得分店環境照片
     */
    public function shop_photos()
    {
        return $this->hasMany(ShopPhoto::class,'shop_id','id');
    }

    /**
     * 取得分店所有服務資料
     */
    public function shop_service_categories()
    {
        return $this->hasMany(ShopServiceCategory::class,'shop_id','id')->where('type','service')->orderBy('sequence');
    }

    /**
     * 取得分店所有服務資料
     */
    public function shop_services()
    {
        return $this->hasMany(ShopService::class,'shop_id','id')->where('type','service')->orderBy('sequence');
    }

    /**
     * 取得分店所有加值服務資料
     */
    public function shop_advances()
    {
        return $this->hasMany(ShopService::class,'shop_id','id')->where('type','advance');
    }

    /**
     * 取得分店員工
     */
    public function shop_staffs()
    {
        return $this->hasMany(ShopStaff::class,'shop_id','id')->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id');
    }

    /**
     * 取得分店會員
     */
    public function shop_customers()
    {
        return $this->hasMany(ShopCustomer::class,'shop_id','id')->join('customers','customers.id','=','shop_customers.customer_id');
    }

    /**
     * 取得分店優惠券
     */
    public function shop_coupons()
    {
        return $this->hasMany(ShopCoupon::class,'shop_id','id')->join('company_coupons','company_coupons.id','=','shop_coupons.company_coupon_id');
    }

}
