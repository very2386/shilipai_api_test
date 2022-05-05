<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 取得結帳人員資料
     */
    public function staff_info()
    {
        return $this->hasOne(ShopStaff::class, 'id', 'shop_staff_id')->withTrashed();
    }

    /**
     * 取得會員資料
     */
    public function customer_info()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id')->withTrashed();
    }
}
