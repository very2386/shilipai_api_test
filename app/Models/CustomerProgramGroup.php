<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerProgramGroup extends Model
{
    use HasFactory;

    /**
     * 取得會員方案的組合資料
     */
    public function group_info()
    {
        return $this->hasOne(ShopProgramGroup::class, 'id', 'shop_program_group_id');
    }

    /**
     * 取得會員方案的組合的使用記錄
     */
    public function use_log()
    {
        return $this->hasMany(CustomerProgramLog::class, 'customer_program_group_id', 'id');
    }
}
