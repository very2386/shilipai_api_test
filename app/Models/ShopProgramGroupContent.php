<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopProgramGroupContent extends Model
{
    use HasFactory;

    /**
     * 取得組合的服務資料
     */
    public function service_info()
    {
        return $this->hasOne(ShopService::class,'id','commodity_id');
    }

    /**
     * 取得組合的產品資料
     */
    public function product_info()
    {
        return $this->hasOne(ShopProduct::class,'id','commodity_id');
    }
}
