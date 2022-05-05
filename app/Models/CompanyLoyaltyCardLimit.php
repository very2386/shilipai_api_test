<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyLoyaltyCardLimit extends Model
{
    use HasFactory;

    /**
     * 取得集點卡限制的服務資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class,'id','commodity_id');
    }

    /**
     * 取得集點卡限制的產品資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopProduct::class, 'id', 'commodity_id');
    }
}
