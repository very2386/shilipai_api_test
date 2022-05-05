<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopFestivalNotice extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得優惠券的資料
     */
    public function coupon_info()
    {
        return $this->hasOne(ShopCoupon::class,'id','shop_coupons');
    }

    /**
     * 取得優惠券的資料
     */
    public function send_log()
    {
        return $this->hasMany(ShopManagementCustomerList::class,'shop_festival_notice_id','id');
    }
}
