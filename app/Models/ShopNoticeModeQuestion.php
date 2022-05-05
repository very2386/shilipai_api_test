<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopNoticeModeQuestion extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得回答資料
     */
    public function question_answer()
    {
        return $this->hasOne(CustomerQuestionAnswer::class,'shop_notice_mode_question_id','id');
    }
}
