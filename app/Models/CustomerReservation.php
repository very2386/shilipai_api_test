<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReservation extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得預約的審核者資料
     */
    public function check_user_info()
    {
        return $this->hasOne(User::class,'id','check_user');
    }

    /**
     * 取得預約的顧客資料
     */
    public function customer_info()
    {
        return $this->hasOne(Customer::class,'id','customer_id')->withTrashed();
    }

    /**
     * 取得預約的服務資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class,'id','shop_service_id')->withTrashed();
    }

    /**
     * 取得預約的服務人員資料
     */
    public function staff_info()
    {
        return $this->hasOne(ShopStaff::class,'id','shop_staff_id')->withTrashed()->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id');
    }

    /**
     * 取得預約的加值服務資料
     */
    public function advances()
    {
        return $this->hasMany(CustomerReservationAdvance::class,'customer_reservation_id','id')->join('shop_services','shop_services.id','=','customer_reservation_advances.shop_service_id');
    }

    /**
     * 取得預約的帳單資料
     */
    public function bill_info()
    {
        return $this->hasOne(Bill::class, 'id', 'bill_id')->withTrashed();
    }

    
}
