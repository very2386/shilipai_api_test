<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得權限內的公司資料
     */
    public function company_info()
    {
        return $this->hasOne(Company::class,'id','company_id');
    }

    /**
     * 取得權限內的分店資料
     */
    public function shop_info()
    {
        return $this->hasOne(Shop::class,'id','shop_id');
    }

    /**
     * 取得權限內的員工資料
     */
    public function shop_staff_info()
    {
        return $this->hasOne(ShopStaff::class,'id','shop_staff_id');
    }
}
