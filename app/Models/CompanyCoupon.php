<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyCoupon extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得優惠券的限制項目
     */
    public function limit_commodity()
    {
        return $this->hasMany(CompanyCouponLimit::class,'company_coupon_id','id') ?: [] ;
    }

    /**
     * 取得優惠券的服務資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }

    /**
     * 取得優惠券的產品資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }
}
