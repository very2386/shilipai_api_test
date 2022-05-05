<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopService extends Model
{
    use HasFactory, SoftDeletes;

    // scope Sort
    public function scopeSort($query)
    {
        return $query->orderBy('sequence');
    }

    /**
     * 取得分店資料
     */
    public function shop_info()
    {
    	return $this->belongsTo(Shop::class,'shop_id','id');
    }

    /**
     * 取得集團服務所屬分類資料
     */
    public function category_info()
    {
        return $this->belongsTo(ShopServiceCategory::class,'shop_service_category_id','id');
    }

    /**
     * 取得集團服務資料
     */
    public function company_service_info()
    {
        return $this->belongsTo(CompanyService::class,'company_service_id','id');
    }

    /**
     * 取得集團加值服務資料
     */
    public function company_advance_info()
    {
        return $this->belongsTo(CompanyService::class,'company_service_id','id');
    }

    /**
     * 取得商家服務搭配的人員資料
     */
    public function service_staffs()
    {
        return $this->hasMany(ShopServiceStaff::class,'shop_service_id','id');
    }

    /**
     * 取得商家服務搭配的加值服務資料
     */
    public function service_advances()
    {
        return $this->hasMany(ShopServiceAdvance::class,'shop_service_id','id')->join('shop_services','shop_services.id','=','shop_service_advances.shop_advance_id');
    }

    /**
     * 取得商家加值服務搭配的服務資料
     */
    public function match_services()
    {
        return $this->hasMany(ShopServiceAdvance::class,'shop_advance_id','id');
    }

    /**
     * 取得此項服務被綁訂的服務通知資料
     */
    public function management_group()
    {
        return $this->hasOne(ShopManagementService::class,'shop_service_id','id');
    }

    
}
