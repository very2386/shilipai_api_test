<?php

namespace App\Http\Controllers\v1;

use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Bill;
use App\Models\BillPuchaseItem;
use App\Models\CustomerCoupon;
use App\Models\CustomerLoyaltyCard;
use App\Models\CustomerLoyaltyCardPoint;
use App\Models\CustomerMembershipCard;
use App\Models\CustomerProgram;
use App\Models\CustomerProgramGroup;
use App\Models\CustomerProgramLog;
use App\Models\CustomerTopUp;
use App\Models\CustomerTopUpLog;
use App\Models\Permission;
use App\Models\Photo;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopProductLog;
use App\Models\ShopStaff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillController extends Model
{
    use HasFactory;

    // 收據
    public function bill_info($shop_id, $oid)
    {
        // 確認頁面瀏覽權限
        if (PermissionController::is_staff($shop_id)) {
            // 員工權限判別
            $shop_staff = Permission::where('user_id', auth()->getUser()->id)->where('shop_id', $shop_id)->first()->shop_staff_id;

            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);
            $pm_text = 'staff';

            // 確認頁面瀏覽權限
            if (!in_array('staff_bill_info', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'upgrade_permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $bill_photo_permission_text  = 'staff_bill_photo';
            $bill_edit_permission_text   = 'staff_bill_edit';
            $bill_cancel_permission_text = 'staff_bill_cancel';

        } else {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            if (!in_array('shop_bill_info', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'upgrade_permission' => true, 'errors' => ['message' => ['使用者沒有權限']]]);

            $bill_photo_permission_text  = 'shop_bill_photo';
            $bill_edit_permission_text   = 'shop_bill_edit';
            $bill_cancel_permission_text = 'shop_bill_cancel';
        }

        $shop_info     = Shop::find($shop_id);
        $bill          = Bill::where('oid', $oid)->first();
        $shop_customer = ShopCustomer::where('shop_id', $shop_info->id)->where('customer_id', $bill->customer_id)->first();
        $shop_staff    = ShopStaff::find($bill->shop_staff_id);

        // 使用優惠資訊
        $use_discount = json_decode($bill->discount);
        $use_discount_info = [];
        foreach ($use_discount as $type => $item) {
            if ($type == 'price_discount' && !empty($item)) {
                $use_discount_info[] = $item;
            } elseif ($type == 'free_discount' && $item != NULL) {
                foreach ($item as $it) {
                    if ($it->selected) $use_discount_info[] = $it;
                }
            }
        }

        $photo = '';
        if ($shop_customer->customer_info && $shop_customer->customer_info->photo) {
            $photo = preg_match('/http/i', $shop_customer->customer_info->photo) ? $shop_customer->customer_info->photo : env('SHOW_PHOTO') . '/api/get/customer/' . $shop_customer->customer_info->photo;
        }

        $bill_info = [
            'id'                => $bill->id,
            'oid'               => $bill->oid,
            'customer_data'     => [
                'name'  => $shop_customer->customer_info ? $shop_customer->customer_info->realname : '單購客',
                'phone' => $shop_customer->customer_info ? $shop_customer->customer_info->phone : '',
                'photo' => $photo,
            ],
            'staff_name'        => $shop_staff->company_staff_info->name,
            'consumption_info'  => json_decode($bill->consumption),
            'purchase_item'     => json_decode($bill->purchase_item),
            'use_discount_info' => $use_discount_info,
            'deduct_item'       => json_decode($bill->deduct),
            'top_up_info'       => json_decode($bill->top_up),
            'note'              => $bill->note,
            'sign_img'          => $bill->sign_img ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $bill->sign_img : '',
            'datetime'          => date('Y-m-d h:i a', strtotime($bill->created_at)),
            'cancel_type'       => $bill->cancel_type,
            'cancel_note'       => $bill->cancel_note,
        ];

        // 拿取對應作品
        $album = Album::where('bill_id',$bill->id)->first();
        if ($album) {
            foreach ($album->photos as $ph) {
                $ph->photo = env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$ph->photo_info->photo;
                unset($ph->photo_info);
            }   
        }

        // // 可不可作廢
        // $cancel = false;
        // if ($bill->status == 'cancel' || $bill->status == 'close') $cancel = true;

        // // 可不可編輯
        // $edit = true;
        // if ($bill->status == 'cancel' || $bill->status == 'close') $edit = false;

        $data = [
            'status'            => true,
            'permission'        => true,
            'photo_peromission' => in_array($bill_photo_permission_text, $permission['permission']) ? true : false,
            'cancel_permission' => in_array($bill_cancel_permission_text, $permission['permission']) ? true : false ,
            'edit_permission'   => in_array($bill_edit_permission_text, $permission['permission']) ? true : false,
            'bill_info'         => $bill_info,
            'photo'             => $album ? $album->photos : [],
        ];

        return response()->json($data);
    }

    // 收據儲存上傳圖片
    public function bill_upload_photo($shop_id,$oid)
    {
        $user_info = auth()->User();
        $shop_info = Shop::find($shop_id);
        $bill      = Bill::where('oid', $oid)->first();

        $shop_collection = Album::where('bill_id',$bill->id)->first();
        if (!$shop_collection) {
            $shop_collection = new Album;
            $shop_collection->shop_id = $shop_id;
            $shop_collection->type    = 'bill';
            $shop_collection->bill_id = $bill->id;
            $shop_collection->name    = '帳單作品';
            $shop_collection->save();
        }

        $old_photo_id  = [];
        $insert        = [];
        foreach (request('photos') as $photo) {
            if ($photo['id']) {
                $old_photo_id[] = $photo['photo_id'];
            } else {
                // 新上傳的照片
                $insert[] = [
                    'photo' => $photo['photo'],
                    'cover' => $photo['cover'],
                ];
            }
        }

        // 先刪除照片
        $delete_photos = AlbumPhoto::where('album_id', $shop_collection->id)->whereNotIn('photo_id', $old_photo_id)->join('photos', 'album_photos.photo_id', '=', 'photos.id')->get();
        foreach ($delete_photos as $dp) {
            // 先檢查此張照片是否有被其他相簿存放，若有就只刪除相簿關連，若都沒有其他相簿存放，就直接刪除照片
            $photo_count = AlbumPhoto::where('photo_id', $dp->photo_id)->get()->count();
            if ($photo_count == 1) {
                // 刪除檔案
                $filePath = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $dp->photo;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                Photo::find($dp->photo_id)->delete();
            }
        }
        // 刪除相簿與照片的關連
        AlbumPhoto::where('album_id', $shop_collection->id)->whereNotIn('photo_id', $old_photo_id)->delete();

        // 儲存新照片
        foreach ($insert as $k => $data) {
            $picName = PhotoController::save_base64_photo($shop_info->alias, $data['photo']);

            $new_photo = new Photo;
            $new_photo->user_id = $user_info->id;
            $new_photo->photo   = $picName;
            $new_photo->save();

            $new_album_photo = new AlbumPhoto;
            $new_album_photo->album_id = $shop_collection->id;
            $new_album_photo->photo_id = $new_photo->id;
            $new_album_photo->cover    = $data['cover'];
            $new_album_photo->save();

            if ($data['cover'] == 'Y') {
                $shop_collection->cover = $picName;
                $shop_collection->save();

                // 其他照片cover設成 N
                AlbumPhoto::where('album_id', $shop_collection->id)->where('photo_id', '!=', $new_photo->id)->update(['cover' => 'N']);
            }
        }

        $data = [
            'status' => true,
        ];

        return response()->json($data);
    }

    // 帳單作廢
    public function bill_cancel($shop_id,$oid)
    {
        $shop_info = Shop::find($shop_id);
        $bill      = Bill::where('oid', $oid)->first();

        $purchase_items = json_decode($bill->purchase_item);

        // 檢查儲值資料是否可退還
        $top_up = json_decode($bill->top_up);
        $customer_top_up = CustomerTopUpLog::where('customer_id', $bill->customer_id)
                                           ->where('shop_id', $shop_info->id)
                                           ->sum('price');
        if ($customer_top_up < $top_up->discount + $top_up->add ) {
            return response()->json(['status' => false, 'errors' => ['message' => ['儲值資料有誤，無法作廢']]]);
        }

        // 檢查方案資料是否可退還
        $deducts = json_decode($bill->deduct);
        $program_group = [];
        if (!empty($deducts)) {
            foreach ($deducts as $deduct) {
                $customer_program_group = CustomerProgramGroup::find($deduct->customer_program_group_id);
                if (!isset($program_group[$customer_program_group->id])) {
                    $program_group[$customer_program_group->id] = $customer_program_group->last_count + $deduct->use_count;
                } else {
                    $program_group[$customer_program_group->id] += $deduct->use_count;
                }
            }
        }
        $customer_buy_program_logs = CustomerProgramLog::where('bill_id', $bill->id)
                                                       ->where('type', 1)
                                                       ->get();

        foreach ($customer_buy_program_logs as $log) {
            $now_program_last_count = CustomerProgramLog::where('customer_program_id', $log->customer_program_id)
                                                         ->where('customer_program_group_id', $log->customer_program_group_id)
                                                         ->sum('count');
            $customer_program_group = CustomerProgramGroup::find($log->customer_program_group_id);
            $first_program_count = $customer_program_group->group_info->count;

            $give_back_count = isset($program_group[$log->customer_program_group_id]) ? $program_group[$log->customer_program_group_id] : 0;                                    
            if ($now_program_last_count + $give_back_count < $first_program_count) {
                return response()->json(['status' => false, 'errors' => ['message' => ['方案資料有誤，無法作廢']]]);
            }
        }

        // 儲值資料刪除與退還
        CustomerTopUp::where('bill_id',$bill->id)->delete();
        CustomerTopUpLog::where('use_bill_id',$bill->id)->delete();
        CustomerTopUpLog::where('bill_id', $bill->id)->delete();

        // 產品使用、購買記錄刪除
        ShopProductLog::where('bill_id', $bill->id)->delete();

        // 方案抵扣退還與會員方案資料扣除
        $logs = CustomerProgramLog::where('bill_id', $bill->id)->where('type', 3)->get();
        foreach ($logs as $log) {
            $data = CustomerProgramGroup::where('id',$log->customer_program_group_id)->first();
            $data->last_count += $log->count * -1;
            $data->save();
        }
        CustomerProgramLog::where('bill_id', $bill->id)->where('type', 3)->delete();

        $customer_buy_program_ids = CustomerProgramLog::where('bill_id', $bill->id)
                                                        ->where('type', 1)
                                                        ->get();
        $customer_buy_programs = CustomerProgram::whereIn('id', $customer_buy_program_ids->pluck('customer_program_id')->toArray())->get();
        foreach ($customer_buy_programs as $program) {
            // 檢查是否跟購買的數量一樣，若組合都完全沒用過就可以全部刪除
            $delete = true;
            foreach ($program->groups as $group) {
                if ($group->last_count > $group->group_info->count) {
                    // 剩餘數量多餘要扣除的數量
                    $delete = false;
                    $group->last_count -= $group->group_info->count;
                    $group->save();
                } else {
                    // 剩餘數量和扣除數量一樣，需檢查使用記錄裡是否有被使用過的記錄
                    if ($group->use_log->where('type', 3)->count()) {
                        $delete = false;
                        $group->last_count -= $group->group_info->count;
                        $group->save();
                    }
                }
            }

            if ($delete) {
                // 買了完全沒用過，直接全部清除
                // 刪除會員方案
                $program->delete();
                // 刪除方案組合資料
                CustomerProgramGroup::where('customer_program_id', $program->id)->delete();
            } 
        }
        $customer_buy_program_ids = CustomerProgramLog::where('bill_id', $bill->id)->where('type', 1)->delete();
        
        // 會員購買會員卡刪除
        CustomerMembershipCard::where('bill_id' ,$bill->id)->delete();

        // 優惠券優惠退還
        CustomerCoupon::where('bill_id', $bill->id)->update(['status' => 'N', 'using_time' => NULL, 'bill_id' => NULL]);

        // 集點卡優惠退還
        CustomerLoyaltyCard::where('bill_id', $bill->id)->update(['status' => 'N', 'using_time' => NULL, 'bill_id' => NULL]);

        // 集點卡點數扣除
        $customer_card_point_logs = CustomerLoyaltyCardPoint::where('bill_id', $bill->id)->get();
        foreach ($customer_card_point_logs as $log) {
            $customer_card = CustomerLoyaltyCard::where('id', $log->customer_loyalty_card_id)->first();
            if ($customer_card->status == 'Y') {
                // 集點卡被用過 
                continue;
            } else {
                if ($log->id == $customer_card_point_logs->last()->id){
                    // 最後一張集點卡，需判斷是否還有同種卡在集點中，或還有則可以刪除此張最後的集點卡
                    $same_cards = CustomerLoyaltyCard::where('customer_id', $bill->customer_id)
                                                     ->where('shop_id', $shop_info->id)
                                                     ->where('shop_loyalty_card_id', $customer_card->shop_loyalty_card_id)
                                                     ->where('last_point','!=',0)
                                                     ->get();
                    if ($same_cards->count()) {
                        $log->delete();
                        $customer_card->last_point = $customer_card->full_point;
                        $customer_card->save();
                        // $customer_card->delete();
                    } else {
                        $log->delete();
                        $customer_card->last_point += $log->point;
                        $customer_card->save();
                    }
                                        
                } else {
                    // 點數分散在其他同種卡中，若扣除點數剛好為0點則需一併刪除集點卡
                    if ($customer_card->full_point == $log->point) {
                        $log->delete();
                        $customer_card->last_point = $customer_card->full_point;
                        $customer_card->save();
                        $customer_card->delete();
                    } else{
                        $log->delete();
                        $customer_card->last_point += $log->point;
                        $customer_card->save();
                    }
                }
            }
        }

        // 帳單狀態修改 
        $bill->status        = 'cancel';
        $bill->cancel_status = 'Y';
        $bill->cancel_type   = request('cancel_type');
        $bill->cancel_note   = request('cancel_note');
        $bill->save();

        BillPuchaseItem::where('bill_id',$bill->id)->delete();

        $data = [
            'status' => true,
        ];
        
        return response()->json($data);
    }

}