<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopProductCheck extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 取得服務人員資料
     */
    public function staff_info()
    {
        return $this->hasOne(ShopStaff::class, 'id', 'shop_staff_id')->withTrashed();
    }

    /**
     * 取得產品資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopProduct::class, 'id', 'shop_product_id')->withTrashed();
    }
}
