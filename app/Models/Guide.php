<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    use HasFactory;

    /**
     * 取得章節項目
     */
    public function items()
    {
        return $this->hasMany(GuideItem::class,'chapter','chapter');
    }
}
