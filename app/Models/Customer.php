<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得分店會員
     */
    public function shop_customers()
    {
        return $this->hasMany(ShopCustomer::class,'customer_id','id');
    }

    /**
     * 取得分店會員
     */
    public function tags()
    {
        return $this->hasMany(CustomerTag::class,'customer_id','id');
    }

}
