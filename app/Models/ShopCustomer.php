<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopCustomer extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得會員資料
     */
    public function customer_info()
    {
        return $this->belongsTo(Customer::class,'customer_id','id');
    }

    /**
     * 取得會員標籤
     */
    public function tags()
    {
        return $this->hasMany(ShopCustomerTag::class,'shop_customer_id','id');
    }

    /**
     * 取得歸屬員工
     */
    public function belongTo()
    {
        return $this->hasOne(ShopStaff::class,'id','shop_staff_id')->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id');
    }

    /**
     * 取得會員的預約資料
     */
    public function customer_reservations()
    {
        return $this->hasMany(CustomerReservation::class,'customer_id','customer_id');
    }

    
}
