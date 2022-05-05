<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopServiceStaff extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'shop_service_staffs';    
}
