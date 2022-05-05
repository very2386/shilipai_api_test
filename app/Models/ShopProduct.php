<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopProduct extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 取得集團產品所屬分類資料
     */
    public function category_info()
    {
        return $this->belongsTo(ShopProductCategory::class, 'shop_product_category_id', 'id');
    }

    /**
     * 取得集團產品資料
     */
    public function company_product_info()
    {
        return $this->belongsTo(CompanyProduct::class, 'company_product_id', 'id');
    }

    /**
     * 取得商家產品使用記錄
     */
    public function product_logs()
    {
        return $this->hasMany(ShopProductLog::class, 'shop_product_id', 'id');
    }

    /**
     * 取得商家產品盤點記錄
     */
    public function check_info()
    {
        return $this->hasMany(ShopProductCheck::class, 'shop_product_id', 'id');
    }

    
}
