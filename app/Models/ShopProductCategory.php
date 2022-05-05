<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    // scope Sort
    public function scopeSort($query)
    {
        return $query->orderBy('sequence');
    }

    /**
     * 取得商家分類裡的產品資料
     */
    public function shop_products()
    {
        return $this->hasMany(ShopProduct::class, 'shop_product_category_id', 'id')->orderBy('sequence', 'ASC');
    }
}
