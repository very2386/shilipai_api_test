<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;

class OrderController extends Controller
{
    // 給內部系統用的訂單資料
    public function orders()
    {
    	$orders = Order::orderBy('created_at','DESC')->get();

    	$data = [];
    	foreach( $orders as $order ){

    		if( $order->user_info ){
    			$data[] = [
    				'oid'         => $order->oid,
    				'create_date' => $order->created_at,
    				'pay_date'    => $order->pay_date,
    				'pay_status'  => $order->pay_status == 'Y' ? '已付款' : '未付款',
    				'pay_type'    => $order->pay_type == 'VACC' ? '銀行轉帳' : '信用卡',
    				'buyer'       => $order->user_info->name,
    				'buy_info'    => $order->buy_mode_info->title,
    				'price'       => $order->price ? $order->price : $order->buy_mode_info->price,
    			];
    		}
    		
    	} 

    	return ['status'=>true,'data'=>$data];
    }
}
