<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopProductLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 取得產品資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopProduct::class, 'id', 'shop_product_id')->withTrashed();
    }

    /**
     * 取得服務人員資料
     */
    public function staff_info()
    {
        return $this->hasOne(ShopStaff::class, 'id', 'shop_staff_id')->withTrashed();
    }

    /**
     * 取得優惠券資料
     */
    public function shop_coupon()
    {
        return $this->hasOne(ShopCoupon::class, 'id', 'commodity_id')->withTrashed();
    }

    /**
     * 取得集點卡資料
     */
    public function shop_loyalty_card()
    {
        return $this->hasOne(ShopLoyaltyCard::class, 'id', 'commodity_id')->withTrashed();
    }

    /**
     * 取得儲值資料
     */
    public function shop_top_up()
    {
        return $this->hasOne(ShopTopUp::class, 'id', 'commodity_id')->withTrashed();
    }

    /**
     * 取得方案資料
     */
    public function shop_program()
    {
        return $this->hasOne(ShopProgram::class, 'id', 'commodity_id')->withTrashed();
    }

    /**
     * 取得進貨下的異動記錄
     */
    public function change_logs()
    {
        return $this->hasMany(ShopProductLog::class, 'shop_product_log_id', 'id')->withTrashed();
    }

    
}
