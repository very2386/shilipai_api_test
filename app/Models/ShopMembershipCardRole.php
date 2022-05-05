<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopMembershipCardRole extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * 取得儲值的限制項目
     */
    public function limit_commodity()
    {
        return $this->hasMany(ShopMembershipCardRoleLimit::class, 'shop_membership_card_role_id', 'id') ?: [];
    }
}
