<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyLoyaltyCard extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得集點卡的限制項目
     */
    public function limit_commodity()
    {
        return $this->hasMany(CompanyLoyaltyCardLimit::class,'company_loyalty_card_id','id');
    }

    /**
     * 取得集點卡的服務資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }

    /**
     * 取得集點卡的產品資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }
}
