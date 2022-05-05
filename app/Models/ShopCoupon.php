<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopCoupon extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得優惠券的限制項目
     */
    public function limit_commodity()
    {
        return $this->hasMany(ShopCouponLimit::class, 'shop_coupon_id', 'id') ?: [];
    }

    /**
     * 取得優惠券內容
     */
    public function coupon_info()
    {
        return $this->belongsTo(CompanyCoupon::class,'company_coupon_id','id');
    }

    /**
     * 取得有拿取此優惠券的顧客
     */
    public function customers()
    {
        return $this->hasMany(CustomerCoupon::class,'shop_coupon_id','id');
    }

    /**
     * 取得服務的資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }

    /**
     * 取得產品的資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }
}
