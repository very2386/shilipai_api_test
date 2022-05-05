<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopServiceAdvance extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'shop_service_advances'; 
}
