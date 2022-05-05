<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerTopUpLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 取得儲值規則資料
     */
    public function top_up_role()
    {
        return $this->hasOne(ShopTopUpRole::class, 'id', 'shop_top_up_role_id');
    }

    /**
     * 取得儲值規則資料
     */
    public function staff_info()
    {
        return $this->hasOne(ShopStaff::class, 'id', 'shop_staff_id');
    }

    /**
     * 取得會員資料
     */
    public function customer_info()
    {
        return $this->hasOne(Customer::class, 'id', 'transfer_customer_id');
    }

}
