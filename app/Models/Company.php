<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'companys';

    /**
     * 取得集團權限資料
     */
    public function permission_info()
    {
        return $this->hasOne(Permission::class,'company_id','id')->where('shop_id',NULL);
    }

    /**
     * 取得集團的購買方案資料
     */
    public function buy_mode_info()
    {
        return $this->hasOne(BuyMode::class,'id','buy_mode_id');
    }

    /**
     * 取得集團內的分店資料
     */
    public function shop_infos()
    {
        return $this->hasMany(Shop::class,'company_id','id');
    }

    /**
     * 取得集團內的服務分類資料
     */
    public function service_categories()
    {
        return $this->hasMany(CompanyCategory::class,'company_id','id')->where('type','service')->orderBy('sequence','ASC');
    }

    /**
     * 取得集團內的員工資料
     */
    public function company_staffs()
    {
        return $this->hasMany(CompanyStaff::class,'company_id','id');
    }

    /**
     * 取得集團內的付款記錄資料
     */
    public function company_orders()
    {
        return $this->hasMany(Order::class,'company_id','id');
    }

    /**
     * 取得集團內的訊息發送記錄資料
     */
    public function company_message_logs()
    {
        return $this->hasMany(MessageLog::class,'company_id','id');
    }

    /**
     * 取得集團內的職稱資料
     */
    public function company_titles()
    {
        return $this->hasMany(CompanyTitle::class,'company_id','id');
    }

}
