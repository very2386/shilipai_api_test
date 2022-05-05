<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerMembershipCard extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得會員卡的資料
     */
    public function membership_card_info()
    {
        return $this->hasOne(ShopMembershipCard::class, 'id', 'shop_membership_card_id')->withTrashed();
    }

    /**
     * 取得會員卡的使用優惠記錄資料
     */
    public function logs()
    {
        return $this->hasMany(CustomerMembershipCardLog::class, 'customer_membership_card_id','id');
    }
}
