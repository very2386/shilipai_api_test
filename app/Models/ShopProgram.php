<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopProgram extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得方案下的組合
     */
    public function groups()
    {
        return $this->hasMany(ShopProgramGroup::class,'shop_program_id','id');
    }

    
    /**
     * 取得有購買此儲值的顧客
     */
    public function customers()
    {
        return $this->hasMany(CustomerProgram::class,'shop_program_id','id');
    }
}
