<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerTopUp extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得帳單資料
     */
    public function bill_info()
    {
        return $this->hasOne(Bill::class, 'id', 'bill_id');
    }

    /**
     * 取得儲值資料
     */
    public function top_up_info()
    {
        return $this->hasOne(ShopTopUp::class, 'id', 'shop_top_up_id');
    }

    /**
     * 取得儲值使用記錄
     */
    public function logs()
    {
        return $this->hasMany(CustomerTopUpLog::class, 'customer_top_up_id', 'id');
    }
}
