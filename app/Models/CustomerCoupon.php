<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCoupon extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得優惠券的資料
     */
    public function coupon_info()
    {
        return $this->hasOne(ShopCoupon::class,'id','shop_coupon_id')->withTrashed();
    }
}
