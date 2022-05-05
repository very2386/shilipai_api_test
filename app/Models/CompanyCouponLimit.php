<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyCouponLimit extends Model
{
    use HasFactory;

    /**
     * 取得優惠券限制的服務資料
     */
    public function service_info()
    {
        return $this->hasOne(CompanyService::class,'id','commodity_id');
    }
}
