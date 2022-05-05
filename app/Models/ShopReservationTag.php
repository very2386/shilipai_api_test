<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopReservationTag extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得符合此商家標籤的人員資料
     */
    public function customers()
    {
        return $this->hasMany(ShopCustomerReservationTag::class,'shop_reservation_tag_id','id');
    }   
}
