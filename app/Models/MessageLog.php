<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use HasFactory;

    /**
     * 取得商家會員資料
     */
    public function customer_info()
    {
        return $this->hasOne(Customer::class,'phone','phone');
    }
}
