<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopNoticeMode extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得問卷問題
     */
    public function notice_questions()
    {
        return $this->hasMany(ShopNoticeModeQuestion::class,'shop_notice_mode_id','id');
    } 
}
