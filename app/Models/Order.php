<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得方案資料
     */
    public function buy_mode_info()
    {
        return $this->hasOne(BuyMode::class,'id','buy_mode_id');
    }

    /**
     * 取得使用者資料
     */
    public function user_info()
    {
        return $this->hasOne(User::class,'id','user_id');
    }

}
