<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReservationAdvance extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得預約的服務資料
     */
    public function advance_info()
    {
        return $this->hasOne(ShopService::class, 'id', 'shop_service_id')->withTrashed();
    }
}
