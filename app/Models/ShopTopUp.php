<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopTopUp extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得儲值的規則項目
     */
    public function roles()
    {
        return $this->hasMany(ShopTopUpRole::class,'shop_top_up_id','id') ?: [] ;
    }

    /**
     * 取得有購買此儲值的顧客
     */
    public function customers()
    {
        return $this->hasMany(CustomerTopUp::class,'shop_top_up_id','id');
    }
}
