<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopStaff extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'shop_staffs'; 

    /**
     * 取得商家服務搭配的人員資料
     */
    public function staff_services()
    {
        return $this->hasMany(ShopServiceStaff::class,'shop_staff_id','id')->join('shop_services','shop_services.id','=','shop_service_staffs.shop_service_id');
    } 

    /**
     * 取得商家員工可預約時間資料
     */
    public function business_hours()
    {
        return $this->hasMany(ShopBusinessHour::class,'shop_staff_id','id');
    }   

    /**
     * 取得商家員工固定公休時間資料
     */
    public function close()
    {
        return $this->hasOne(ShopClose::class,'shop_staff_id','id');
    }   

    /**
     * 取得商家員工特定休假日資料
     */
    public function vacations()
    {
        return $this->hasMany(ShopVacation::class,'shop_staff_id','id');
    }   

    /**
     * 取得集團員工原始資料
     */
    public function company_staff_info()
    {
        return $this->belongsTo(CompanyStaff::class,'company_staff_id','id');
    }

    /**
     * 取得集團員工職稱資料
     */
    public function company_title_a_info()
    {
        return $this->belongsTo(CompanyTitle::class,'company_title_id_a','id');
    }

    /**
     * 取得集團員工職稱資料
     */
    public function company_title_b_info()
    {
        return $this->belongsTo(CompanyTitle::class,'company_title_id_b','id');
    }
}
