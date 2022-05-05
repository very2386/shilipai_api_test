<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopLoyaltyCard extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得集點卡的限制項目
     */
    public function limit_commodity()
    {
        return $this->hasMany(ShopLoyaltyCardLimit::class, 'shop_loyalty_card_id', 'id');
    }

    /**
     * 取得集點卡內容
     */
    public function loyalty_card_info()
    {
        return $this->belongsTo(CompanyLoyaltyCard::class,'company_loyalty_card_id','id');
    }

    /**
     * 取得有拿取此集點卡的顧客
     */
    public function customers()
    {
        return $this->hasMany(CustomerLoyaltyCard::class,'shop_loyalty_card_id','id');
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
