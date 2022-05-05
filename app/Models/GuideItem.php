<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuideItem extends Model
{
    use HasFactory;

     /**
     * 取得關連影片資料
     */
    public function video_info()
    {
        return $this->hasMany(Video::class,'id','video');
    }
}
