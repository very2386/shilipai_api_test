<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    use HasFactory;

    /**
     * 取得相簿裡的相片資料
     */
    public function photos()
    {
        return $this->hasMany(AlbumPhoto::class,'album_id','id');//->join('photos', 'album_photos.photo_id', '=', 'photos.id');
    }
}
