<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopTopUpRole extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得儲值的限制項目
     */
    public function limit_commodity()
    {
        return $this->hasMany(ShopTopUpRoleLimit::class,'shop_top_up_role_id','id') ?: [] ;
    }

    /**
     * 取得服務的資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class, 'id', 'commodity_id');
    }

    /**
     * 取得服務的資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopProduct::class, 'id', 'commodity_id');
    }
}
