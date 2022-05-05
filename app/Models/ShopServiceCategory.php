<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopServiceCategory extends Model
{
    use HasFactory,SoftDeletes;

    // scope Sort
    public function scopeSort($query)
    {
        return $query->orderBy('sequence');
    }

    /**
     * 取得商家分類裡的服務資料
     */
    public function shop_services()
    {
        return $this->hasMany(ShopService::class,'shop_service_category_id','id')->where('type','service')->orderBy('sequence','ASC');
    }
}
