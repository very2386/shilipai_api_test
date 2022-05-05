<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyTopUp extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得儲值的限制項目
     */
    public function limit_commodity()
    {
        return $this->hasMany(CompanyTopUpLimit::class,'company_top_up_id','id') ?: [] ;
    }

    /**
     * 取得儲值的規則項目
     */
    public function roles()
    {
        return $this->hasMany(CompanyTopUpRole::class,'company_top_up_id','id') ?: [] ;
    }

    /**
     * 取得儲值的服務資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }

    /**
     * 取得儲值的產品資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopService::class,'id','commodityId');
    }
}
