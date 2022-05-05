<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProgramLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 取得商家員工資料
     */
    public function staff_info()
    {
        return $this->hasOne(ShopStaff::class, 'id', 'shop_staff_id')->withTrashed()->join('company_staffs','company_staffs.id','=','shop_staffs.company_staff_id');
    }

    /**
     * 取得會員資料
     */
    public function customer_info()
    {
        return $this->hasOne(Customer::class, 'id', 'transfer_customer_id')->withTrashed();
    }

    /**
     * 取得商家服務資料
     */
    public function shop_service()
    {
        return $this->hasOne(ShopService::class, 'id', 'commodity_id')->withTrashed();
    }

    /**
     * 取得商家產品資料
     */
    public function shop_product()
    {
        return $this->hasOne(ShopProduct::class, 'id', 'commodity_id')->withTrashed();
    }
}
