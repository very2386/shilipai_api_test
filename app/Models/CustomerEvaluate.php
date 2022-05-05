<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerEvaluate extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得預約的資料
     */
    public function reservation_info()
    {
        return $this->hasOne(CustomerReservation::class,'id','customer_reservation_id');
    }
}
