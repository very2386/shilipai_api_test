<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopManagementCustomerList extends Model
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
     * 取得是否被列入拒收名單
     */
    public function refuse_status()
    {
        return $this->hasOne(ShopManagementRefuse::class,'shop_customer_id','shop_customer_id');
    }

    /**
     * 取得通知設定資料
     */
    public function management_info()
    {
        return $this->hasOne(ShopManagement::class,'id','shop_management_id');
    }

    /**
     * 取得獎勵通知設定資料
     */
    public function award_info()
    {
        return $this->hasOne(ShopAwardNotice::class, 'id', 'shop_award_notice_id');
    }
}
