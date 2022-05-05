<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProgram extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得會員方案的組合資料
     */
    public function groups()
    {
        return $this->hasMany(CustomerProgramGroup::class, 'customer_program_id', 'id');
    }

    /**
     * 取得會員方案的資料
     */
    public function program_info()
    {
        return $this->hasOne(ShopProgram::class, 'id', 'shop_program_id')->withTrashed();
    }
}
