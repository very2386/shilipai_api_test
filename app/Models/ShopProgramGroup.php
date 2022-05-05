<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopProgramGroup extends Model
{
    use HasFactory;

    /**
     * 取得組合的內容
     */
    public function group_content()
    {
        return $this->hasMany(ShopProgramGroupContent::class,'shop_program_group_id','id');
    }
}
