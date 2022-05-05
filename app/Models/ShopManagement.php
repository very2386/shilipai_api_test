<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopManagement extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'shop_managements'; 

    /**
     * 取得符合條件的會員
     */
    public function customer_lists()
    {
        return $this->hasMany(ShopManagementCustomerList::class,'shop_management_id','id');
    } 

    /**
     * 取得問卷問題
     */
    public function mode_info()
    {
        return $this->hasOne(ShopNoticeMode::class,'id','shop_notice_mode_id');
    }
    
    /**
     * 取得綁訂的服務
     */
    public function match_services()
    {
        return $this->hasMany(ShopManagementService::class,'shop_management_group_id','shop_management_group_id');
    } 

    /**
     * 取得優惠券資料
     */
    public function shop_coupon_info()
    {
        return $this->hasOne(ShopCoupon::class,'id','shop_coupons');
    } 

    
}
