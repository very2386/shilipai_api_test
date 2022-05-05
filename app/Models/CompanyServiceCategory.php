<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyServiceCategory extends Model
{
    use HasFactory,SoftDeletes;

    // scope Sort
    public function scopeSort($query)
    {
        return $query->orderBy('sequence');
    }

    /**
     * 取得集團資料
     */
    public function company_info()
    {
        return $this->hasOne(Company::class,'id','company_id');
    }

    /**
     * 取得集團內的分店有的服務資料
     */
    public function shop_services()
    {
        return $this->hasMany(ShopService::class,'company_category_id','id')->where('type','service')->orderBy('sequence','ASC');
    }
}
