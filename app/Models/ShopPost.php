<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopPost extends Model
{
    use HasFactory,SoftDeletes;

    
    /**
     * 取得貼文裡的照片
     */
    public function post_album()
    {
        return $this->hasOne(Album::class,'post_id','id');
    }
}
