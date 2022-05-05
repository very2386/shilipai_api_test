<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlbumPhoto extends Model
{
    use HasFactory;

    /**
     * 取得相簿裡的相片資料
     */
    public function photo_info()
    {
        return $this->hasOne(Photo::class,'id','photo_id');
    }
}
