<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopManagementGroup extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得服務通知下的內容
     */
    public function management_details()
    {
        return $this->hasMany(ShopManagement::class,'shop_management_group_id','id');
    } 

    /**
     * 取得服務通知下選擇的服務
     */
    public function shop_services()
    {
        return $this->hasMany(ShopManagementService::class,'shop_management_group_id','id');
    } 
}
