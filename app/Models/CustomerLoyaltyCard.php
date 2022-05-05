<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLoyaltyCard extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得會員的資料
     */
    public function customer_info()
    {
        return $this->hasOne(Customer::class,'id','customer_id')->withTrashed();
    }

    /**
     * 取得集點卡的資料
     */
    public function loyalty_card_info()
    {
        return $this->hasOne(ShopLoyaltyCard::class,'id','shop_loyalty_card_id')->withTrashed();
    }

    /**
     * 取得集點卡的集點記錄
     */
    public function point_log()
    {
        return $this->hasMany(CustomerLoyaltyCardPoint::class,'customer_loyalty_card_id','id');
    }
}
