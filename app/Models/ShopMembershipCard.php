<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopMembershipCard extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得會員卡的規則項目
     */
    public function roles()
    {
        return $this->hasMany(ShopMembershipCardRole::class,'shop_membership_card_id','id') ?: [] ;
    }

    /**
     * 取得有購買此會員卡的顧客
     */
    public function customers()
    {
        return $this->hasMany(CustomerMembershipCard::class,'shop_membership_card_id','id');
    }
}
