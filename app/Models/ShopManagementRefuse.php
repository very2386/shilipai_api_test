<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopManagementRefuse extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得會員資料
     */
    public function customer_info()
    {
        return $this->hasOne(ShopCustomer::class,'id','shop_customer_id')->join('customers','customers.id','=','shop_customers.customer_id');
    }

    /**
     * 取得User資料
     */
    public function user_info()
    {
        return $this->hasOne(User::class,'id','undertaker');
    }
}
