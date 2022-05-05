<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Models\ShopCustomer;
use App\Models\ShopManagementRefuse;

class ShopManagementRefuseController extends Controller
{
    // 拒收名單
    public function shop_management_refuse_lists($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_refuse_management_lists',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $lists = ShopManagementRefuse::where('shop_id',$shop_id)->get();
        $refuse_lists = [];
        foreach( $lists as $list ){

        	$reson = '';
        	switch ($list->reason_type) {
        		case 1:
        			$reson = '客人自提';
        			break;
        		case 2:
        			$reson = '一直發送失敗，手動列入';
        			break;
        		case 3:
        			$reson = $list->reason;
        			break;
        	}

        	$refuse_lists[] = [
        		'id'         => $list->id,
        		'name'       => $list->customer_info->realname,
        		'phone'      => $list->customer_info->phone,
        		'reason'     => $reson,
        		'undertaker' => $list->user_info->name,
                'date'       => substr($list->created_at,0,10),
        	];
        }

        // 可設定拒收的商家會員
        $customers       = ShopCustomer::where('shop_id', $shop_id)->whereNotIn('id',$lists->pluck('shop_customer_id')->toArray())->get();
        $customer_select = [];
        foreach( $customers as $customer ){
            if( !$customer->customer_info ) continue;
        	$customer_select[] = [
        		'id'   => $customer->id,
        		'name' => $customer->customer_info->realname,
        	];
        }

    	$data = [
            'status'                               => true,
            'permission'                           => true,
            'refuse_management_create_permission'  => in_array('refuse_management_create_btn',$user_shop_permission['permission']) ? true : false, 
            'refuse_management_recover_permission' => in_array('refuse_management_recover',$user_shop_permission['permission']) ? true : false, 
            'refuse_customer_permission'           => in_array('refuse_management_create_customer',$user_shop_permission['permission']) ? true : false, 
            'refuse_reason_permission'             => in_array('refuse_management_create_reason',$user_shop_permission['permission']) ? true : false, 
            'customer_select'                      => $customer_select,
            'data'                                 => $refuse_lists,
        ];

        return response()->json($data);
    }

    // 列入拒收名單
    public function management_add_refuse($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'shop_customer_id' => 'required',
        ];

        $messages = [
            'shop_customer_id.required' => '請選擇會員',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        $data = ShopManagementRefuse::where('shop_id',$shop_id)->where('shop_customer_id',request('shop_customer_id'))->first();
        if( !$data ) $data = new ShopManagementRefuse;
        $data->shop_id          = $shop_id;
        $data->shop_customer_id = request('shop_customer_id');
        $data->reason_type      = request('reason_type') ? request('reason_type') : 2;
        $data->reason           = request('reason');
        $data->undertaker       = auth()->getUser()->id;
        $data->save();

        return response()->json(['status' => true ]);
    }

    // 拒收名單復原指定人員
    public function management_refuse_recover($shop_id,$refuse_id)
    {
    	$data = ShopManagementRefuse::where('id',$refuse_id)->first();
    	if( !$data ){
    		return response()->json(['status'=>false,'errors'=>'shop management refuse info not found']);
    	}

    	$data->delete();

		return response()->json(['status' => true ]);    	
    }
}
