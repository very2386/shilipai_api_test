<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Jobs\LoyaltyCardPointLog;
use App\Jobs\PurchaseItemLog;
use App\Models\Bill;
use App\Models\BillPuchaseItem;
use App\Models\CompanyCustomer;
use App\Models\CompanyLoyaltyCardLimit;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerLoyaltyCard;
use App\Models\CustomerLoyaltyCardPoint;
use App\Models\CustomerMembershipCard;
use App\Models\CustomerMembershipCardLog;
use App\Models\CustomerProgram;
use App\Models\CustomerProgramGroup;
use App\Models\CustomerProgramLog;
use App\Models\CustomerReservation;
use App\Models\CustomerTopUp;
use App\Models\CustomerTopUpLog;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopCouponLimit;
use App\Models\ShopCustomer;
use App\Models\ShopLoyaltyCard;
use App\Models\ShopManagement;
use App\Models\ShopMembershipCard;
use App\Models\ShopMembershipCardRole;
use App\Models\ShopPayType;
use App\Models\ShopProduct;
use App\Models\ShopProductCategory;
use App\Models\ShopProductLog;
use App\Models\ShopProgram;
use App\Models\ShopService;
use App\Models\ShopStaff;
use App\Models\ShopTopUp;
use Illuminate\Http\Request;
use Validator;

class CheckoutController extends Controller
{
    // 新增結帳會員與員工選項
    public function checkout_select_option($shop_id)
    {
        $shop_info = Shop::find($shop_id);

        // 當前的商家員工
        $now_staff = ShopStaff::where('shop_id', $shop_info->id)
                                ->where('user_id', auth()->getUser()->id)
                                ->first();

        $shop_staffs = ShopStaff::where('shop_id', $shop_id)->get();
        $staff_data = [];
        foreach ($shop_staffs as $staff) {
            if (!$staff->company_staff_info) continue;
            if ($staff->company_staff_info->fire_time == NULL) {
                $staff_data[] = [
                    'id'       => $staff->id,
                    'name'     => $staff->company_staff_info->name,
                    'selected' => $now_staff->id == $staff->id ? true : false,
                ];
            }
        }

        $shop_customers = ShopCustomer::where('shop_id', $shop_id)->where('customer_id', '!=', NULL)->get();
        $customer_data = [
            // [
            //     'id'    => '',
            //     'type'  => 'new',
            //     'name'  => '非會員(快速加會員)',
            //     'phone' => '',
            // ],
            // [
            //     'id'    => '',
            //     'type'  => 'only',
            //     'name'  => '單購客(不加會員)',
            //     'phone' => '',
            // ]
        ];
        foreach ($shop_customers as $customer) {
            if (!$customer->customer_info) continue;
            $customer_data[] = [
                'id'    => $customer->id,
                'type'  => 'old',
                'name'  => $customer->customer_info->realname,
                'phone' => $customer->customer_info->phone,
            ];
        }

        $data = [
            'status'         => true,
            'shop_staffs'    => $staff_data,
            'shop_customers' => $customer_data,
        ];

        return response()->json($data);
    }

    // 待結帳
    public function pending_checkout($shop_id)
    {
        $shop_info = Shop::find($shop_id);

        // 確認頁面瀏覽權限
        if (PermissionController::is_staff($shop_id)) {
            // 員工權限判別
            $shop_staff = Permission::where('user_id', auth()->getUser()->id)->where('shop_id', $shop_id)->first()->shop_staff_id;

            $permission = PermissionController::user_staff_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);
            $pm_text = 'staff';

            // 確認頁面瀏覽權限
            if (!in_array('staff_checkouts', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'upgrade_permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

            $checkout_permission_text = 'staff_checkout_btn';
        } else {
            // 拿取使用者的商家權限
            $permission = PermissionController::user_shop_permission($shop_id);
            if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

            if (!in_array('shop_checkouts', $permission['permission'])) return response()->json(['status' => true, 'permission' =>false, 'upgrade_permission' => true, 'errors' => ['message' => ['使用者沒有權限']]]);

            $checkout_permission_text = 'shop_checkout_btn';
        }

        $customer_reservations = CustomerReservation::where('shop_id', $shop_id)
                                                    ->whereBetween('start',[date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
                                                    ->where('status', 'Y')
                                                    ->where('cancel_status',NULL)
                                                    ->get();

        // 當前的商家員工
        $now_staff = ShopStaff::where('shop_id', $shop_info->id)
                                ->where('user_id', auth()->getUser()->id)
                                ->first();

        $pending_checkout = [];
        $pending_count = 0;
        foreach ($customer_reservations as $reservation) {
            $date = date('a H:i', strtotime($reservation->start));

            // 時間格式
            $weeks     = ['星期一', '星期二', '星期三', '星期四', '星期五', '星期六', '星期日'];
            $text_arr  = ['am' => '上午', 'pm' => '下午'];
            $date_text = date('n月d日', strtotime($reservation->start))
                . ' (' . $weeks[date('N', strtotime($reservation->start)) - 1] . ') '
                . $text_arr[date('a', strtotime($reservation->start))] . date('h:i', strtotime($reservation->start)) . ' - '
                . $text_arr[date('a', strtotime($reservation->end))] . date('h:i', strtotime($reservation->end));

            $tags = [
                [
                    'name'        => '提早',
                    'description' => '(30分鐘以上)',
                    'selected'    => $reservation->tag == 5 ? true : false,
                    'value'       => 5,
                ],
                [
                    'name'        => '到囉！',
                    'description' => '',
                    'selected'    => $reservation->tag == 1 ? true : false,
                    'value'       => 1,
                ],
                [
                    'name'        => '大遲到',
                    'description' => '(30分鐘以上)',
                    'selected'    => $reservation->tag == 4 ? true : false,
                    'value'       => 4,
                ],
                [
                    'name'        => '小遲到',
                    'description' => '(30分鐘以內)',
                    'selected'    => $reservation->tag == 3 ? true : false,
                    'value'       => 3,
                ],
                [
                    'name'        => '爽約',
                    'description' => '',
                    'selected'    => $reservation->tag == 2 ? true : false,
                    'value'       => 2,
                ]
            ];

            if ($reservation->bill_id == '' || $reservation->bill_id == NULL) {
                // 尚未結帳
                $phone = substr($reservation->customer_info->phone, 0, 4) . '-XXX-' . substr($reservation->customer_info->phone, 7, 3);
                if (!empty($pending_checkout)) {
                    $check = false;
                    foreach ($pending_checkout as $k => $data) {
                        if ($data['time'] == $date){
                            $check = true;
                            $pending_checkout[$k]['reservations'][] = [
                                'id'          => $reservation->id,
                                'customer_id' => $reservation->customer_info->id,
                                'customer'    => $reservation->customer_info->realname,
                                'phone'       => $phone,
                                'service'     => $reservation->service_info->name,
                                'advances'    => $reservation->advances->pluck('name'),
                                'staff'       => $reservation->staff_info->name,
                                'staff_photo' => $reservation->staff_info->photo ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $reservation->staff_info->photo : '',
                                'color'       => $reservation->staff_info->calendar_color,
                                'date'        => $date_text,
                                'tags'        => $tags,
                            ];
                            $pending_count++;
                            break;
                        }
                    }

                    if (!$check){
                        $pending_checkout[] = [
                            'time'         => $date,
                            'reservations' => [
                                [
                                    'id'          => $reservation->id,
                                    'customer_id' => $reservation->customer_info->id,
                                    'customer'    => $reservation->customer_info->realname,
                                    'phone'       => $phone,
                                    'service'     => $reservation->service_info->name,
                                    'advances'    => $reservation->advances->pluck('name'),
                                    'staff'       => $reservation->staff_info->name,
                                    'staff_photo' => $reservation->staff_info->photo ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $reservation->staff_info->photo : '',
                                    'color'       => $reservation->staff_info->calendar_color,
                                    'date'        => $date_text,
                                    'tags'        => $tags,
                                ]
                            ],
                           
                        ];

                        $pending_count++;
                    }
                } else {
                    $pending_checkout[] = [
                        'time'         => $date,
                        'reservations' => [
                            [
                                'id'          => $reservation->id,
                                'customer_id' => $reservation->customer_info->id,
                                'customer'    => $reservation->customer_info->realname,
                                'phone'       => $phone,
                                'service'     => $reservation->service_info->name,
                                'advances'    => $reservation->advances->pluck('name'),
                                'staff'       => $reservation->staff_info->name,
                                'staff_photo' => $reservation->staff_info->photo ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $reservation->staff_info->photo : '',
                                'color'       => $reservation->staff_info->calendar_color,
                                'date'        => $date_text,
                                'tags'        => $tags,
                            ]
                        ],
                        
                    ];
                    $pending_count++;
                }
            }
        }

        // 已結帳單據
        $today_checkout = $process_checkout = [];
        $bills = Bill::where('shop_id', $shop_info->id)->orderBy('id','DESC')->get();
        foreach ($bills as $bill) {
            $arr = [
                'id'            => $bill->id,
                'oid'           => $bill->oid,
                'customer_name' => $bill->customer_info ? $bill->customer_info->realname : '單購客',
                'staff_name'    => $bill->staff_info->company_staff_info->name,
                'time'          => date('H:i a', strtotime($bill->created_at)),
                'color'         => $bill->staff_info->company_staff_info->calendar_color
            ];
            
            if ($bill->status == 'finish' && date('Y-m-d',strtotime($bill->updated_at)) == date('Y-m-d')) {
                // 簽名完成
                $today_checkout[] = $arr;
            } elseif ($bill->status == 'pending') {
                // 尚未簽名
                $process_checkout[] = $arr;
            }
        }

        $data = [
            'status'                 => true,
            'permission'             => true,
            'upgrade_permission'     => true,
            'checkout_permission'    => in_array($checkout_permission_text, $permission['permission']) ? true : false,
            'pending_count'          => $pending_count,
            'pending_checkout'       => $pending_checkout,
            'today_checkout_count'   => count($today_checkout),
            'today_checkout'         => $today_checkout,
            'process_checkout_count' => count($process_checkout),
            'process_checkout'       => $process_checkout,
        ];

        return response()->json($data);
    }

    // 新增結帳
    public function create_checkout($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'type'                   => 'required',
            'checkout_shop_staff_id' => 'required',
        ];

        $messages = [
            'customer_data.required'          => '缺少新增類別資料',
            'checkout_shop_staff_id.required' => '請選擇服務人員'
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        // 確認頁面瀏覽權限
        // if (PermissionController::is_staff($shop_id)) {
        //     // 拿取使用者的商家權限
        //     $permission = PermissionController::user_staff_permission($shop_id);
        //     if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

        //     if (!in_array('staff_create_checkout', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        // }else{
        //     // 拿取使用者的商家權限
        //     $permission = PermissionController::user_shop_permission($shop_id);
        //     if ($permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$permission['errors']]]]);

        //     if (!in_array('shop_create_checkout', $permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);
        // }

        // 商家資料
        $shop_info = Shop::find($shop_id);

        // 製作商家會員資料
        if (request('type') == 'old') {
            // 選擇已有的會員
            $shop_customer = ShopCustomer::find(request('id'));
            if (!$shop_customer) return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);
        } elseif (request('type') == 'new') {

            if (request('phone') == '') return response()->json(['status' => true, 'errors' => ['message' => ['缺少電話資料']]]);

            // 選擇非會員快速新增
            // 先確認是否在customers裡有資料，建立集團和商家會員
            $customer = Customer::where('phone', request('phone'))->first();
            if (!$customer) {
                $customer           = new Customer;
                $customer->phone    = request('phone');
                $customer->realname = '新會員';
                $customer->save();
            }

            $company_customer = CompanyCustomer::where('customer_id', $customer->id)->where('company_id', $shop_info->company_info->id)->first();
            if (!$company_customer) $company_customer = new CompanyCustomer;
            $company_customer->customer_id = $customer->id;
            $company_customer->company_id  = $shop_info->company_info->id;
            $company_customer->save();

            $shop_customer = ShopCustomer::where('customer_id', $customer->id)->where('shop_id', $shop_info->id)->first();
            if (!$shop_customer) $shop_customer = new ShopCustomer;
            $shop_customer->shop_id     = $shop_info->id;
            $shop_customer->company_id  = $shop_info->company_info->id;
            $shop_customer->customer_id = $customer->id;
            $shop_customer->save();
        } else {
            // 選擇單購客
            $shop_customer = ShopCustomer::where('shop_id', $shop_info->id)->where('customer_id', NULL)->first();
        }

        // 消費總額
        $comsumption_total = 0;

        if (request('type') != 'only') {
            $personality = json_decode(json_encode(ShopCustomerController::shop_customer_personality($shop_info->id, $shop_customer->id)));
            $traits      = json_decode(json_encode(ShopCustomerController::shop_customer_traits($shop_info->id, $shop_customer->id)));

            $phone = '';
            if ($shop_customer->customer_info->phone) {
                $phone = substr($shop_customer->customer_info->phone, 0, 4)
                    . '-' . substr($shop_customer->customer_info->phone, 4, 3)
                    . '-' . substr($shop_customer->customer_info->phone, 7, 3);
            }
            
            $photo = '';
            if ($shop_customer->customer_info->photo) {
                $photo = preg_match('/http/i', $shop_customer->customer_info->photo) ? $shop_customer->customer_info->photo : env('SHOW_PHOTO') . '/api/get/customer/' . $shop_customer->customer_info->photo;
            }

            // 製作會員資料
            $customer_data = [
                'shop_customer_id' => $shop_customer->id,
                'name'             => $shop_customer->customer_info->realname,
                'personality'      => $personality->original->data->top->number,
                'traits'           => $traits->original->data->type,
                'sex'              => $shop_customer->customer_info->sex == 'F' ? '女' : ($shop_customer->customer_info->sex == 'M' ? '男' : '中性'),
                'birthday'         => $shop_customer->customer_info->birthday ? date('m.d', strtotime($shop_customer->customer_info->birthday)) : '',
                'age'              => $shop_customer->customer_info->birthday ? ShopCustomerController::getAge($shop_customer->customer_info->birthday) . '歲' : '',
                'constellation'    => $shop_customer->customer_info->birthday ? ShopCustomerController::constellation($shop_customer->customer_info->birthday) : '',
                'phone'            => $phone,
                'photo'            => $photo,
            ];
        } else {
            $customer_data = [
                'shop_customer_id' => $shop_customer->id,
                'name'             => '單購客',
                'personality'      => '',
                'traits'           => '',
                'sex'              => '',
                'birthday'         => '',
                'age'              => '',
                'constellation'    => '',
                'phone'            => '',
                'photo'            => '',
            ];
        }

        // 拿取結帳內容 (商家資料、商家會員、預約資料、顧客資料、被選擇的服務人員、購買項目)
        $data = Self::get_checkout_info($shop_info, $shop_customer, '', $customer_data, request('checkout_shop_staff_id'),[]);

        $data['status']                    = true;
        $data['permission']                = true;
        $data['note']                      = request('note');
        $data['bill_info']                 = [];
        // $data['purchase_item_permission']  = in_array('shop_purchase_item', $user_shop_permission['permission']) ? true : false;           // 購買項目權限
        // $data['discount_items_permission'] = in_array('shop_discount_items', $user_shop_permission['permission']) ? true : false;          // 抵扣項目權限
        // $data['use_discount_permission']   = in_array('shop_use_discount', $user_shop_permission['permission']) ? true : false;            // 使用優惠權限
        // $data['self_defintion_permission'] = in_array('shop_self_definition_price', $user_shop_permission['permission']) ? true : false;   // 自訂金額權限
        // $data['pay_type_permission']       = in_array('shop_select_pay_type', $user_shop_permission['permission']) ? true : false;         // 付款方式權限

        return response()->json($data);
    }

    // 預約項目結帳
    public function reservation_checkout($shop_id, $customer_reservation_id)
    {
        // // 拿取使用者的商家權限
        // $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        // if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // // 確認頁面瀏覽權限
        // if (!in_array('shop_create_checkout', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        // 商家資料
        $shop_info = Shop::find($shop_id);

        // 客戶預約資料
        $customer_reservation = CustomerReservation::find($customer_reservation_id);
        if (!$customer_reservation) return response()->json(['status' => false, 'errors' => ['message' => ['找不到預約項目資料']]]);

        // 會員資料
        $customer_info = Customer::find($customer_reservation->customer_id);
        if (!$customer_info) return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);

        // 商家會員資料
        $shop_customer = ShopCustomer::where('shop_id', $shop_info->id)->where('customer_id', $customer_info->id)->first();
        if (!$shop_customer) return response()->json(['status' => false, 'errors' => ['message' => ['找不到會員資料']]]);

        // 製作會員資料
        $personality = json_decode(json_encode(ShopCustomerController::shop_customer_personality($shop_info->id, $shop_customer->id)));
        $traits      = json_decode(json_encode(ShopCustomerController::shop_customer_traits($shop_info->id, $shop_customer->id)));

        $phone = '';
        if ($shop_customer->customer_info->phone) {
            $phone = substr($shop_customer->customer_info->phone, 0, 4)
            . '-' . substr($shop_customer->customer_info->phone, 4, 3)
            . '-' . substr($shop_customer->customer_info->phone, 7, 3);
        }

        $photo = '';
        if ($shop_customer->customer_info->photo) {
            $photo = preg_match('/http/i', $shop_customer->customer_info->photo) ? $shop_customer->customer_info->photo : env('SHOW_PHOTO') . '/api/get/customer/' . $shop_customer->customer_info->photo;
        }

        $customer_data = [
            'shop_customer_id' => $shop_customer->id,
            'name'             => $shop_customer->customer_info->realname,
            'personality'      => $personality->original->data->top->number,
            'traits'           => $traits->original->data->type,
            'sex'              => $shop_customer->customer_info->sex == 'F' ? '女' : ($shop_customer->customer_info->sex == 'M' ? '男' : '中性'),
            'birthday'         => $shop_customer->customer_info->birthday ? date('m.d', strtotime($shop_customer->customer_info->birthday)) : '',
            'age'              => $shop_customer->customer_info->birthday ? ShopCustomerController::getAge($shop_customer->customer_info->birthday) . '歲' : '',
            'constellation'    => $shop_customer->customer_info->birthday ? ShopCustomerController::constellation($shop_customer->customer_info->birthday) : '',
            'phone'            => $phone,
            'photo'            => $photo,
        ];

        // 拿取結帳內容 (商家資料、商家會員、預約資料、顧客資料、被選擇的服務人員、購買項目)
        $data = Self::get_checkout_info($shop_info, $shop_customer, $customer_reservation, $customer_data, '', []);

        $data['status']                    = true;
        $data['permission']                = true;
        $data['note']                      = '';
        $data['bill_info']                 = [];
        // $data['purchase_item_permission']  = in_array('shop_purchase_item', $user_shop_permission['permission']) ? true : false;           // 購買項目權限
        // $data['discount_items_permission'] = in_array('shop_discount_items', $user_shop_permission['permission']) ? true : false;          // 抵扣項目權限
        // $data['use_discount_permission']   = in_array('shop_use_discount', $user_shop_permission['permission']) ? true : false;            // 使用優惠權限
        // $data['self_defintion_permission'] = in_array('shop_self_definition_price', $user_shop_permission['permission']) ? true : false;   // 自訂金額權限
        // $data['pay_type_permission']       = in_array('shop_select_pay_type', $user_shop_permission['permission']) ? true : false;         // 付款方式權限

        return response()->json($data);
    }

    // 儲存結帳內容
    public function checkout_save($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'customer_data'          => 'required',
            'consumption_info'       => 'required',
            'pay_type'               => 'required',
            'checkout_shop_staff_id' => 'required',
        ];

        $messages = [
            'customer_data.required'          => '缺少客戶資料',
            'consumption_info.required'       => '缺少消費資訊',
            'pay_type.required'               => '請選擇付款方式',
            'checkout_shop_staff_id.required' => '缺少結帳人員資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        // 自訂
        if (request('consumption_info')['checkout_total']['self_definition'] === '') {
            return response()->json(['status' => false, 'errors' => ['message' => ['自訂金額錯誤！']]]);
        }

        $shop_info        = Shop::find($shop_id);
        $shop_customer    = ShopCustomer::find(request('customer_data')['shop_customer_id']);
        $customer_data    = request('customer_data');          // 會員資料
        $consumption_info = request('consumption_info');       // 消費資訊
        // $purchase_item    = request('purchase_item');          // 購買項目
        $deduct           = request('deduct_item');            // 折抵項目
        $discount         = request('use_discount_info');      // 使用優惠
        $top_up_info      = [];                                // 儲值紀錄
        $note             = request('note');                   // 備註
        $shop_staff_id    = request('checkout_shop_staff_id'); // 結帳人

        // 購買項目處理
        $purchase_item = [];
        foreach (request('purchase_item') as $key => $item) {
            $check = false;
            foreach ($purchase_item as $k => $pitem) {
                if (!in_array($item['type'],['定金','自訂'])) {
                    if ($pitem['type'] == $item['type'] && $pitem['item']['id'] == $item['item']['id']) {
                        // 累加項目
                        $purchase_item[$k]['count'] += $item['count'];
                        $check = true;
                        break;
                    }
                }
            }
            if ($check == false) {
                if (in_array($item['type'], ['定金', '自訂'])) {
                    $item['item']['name']  = $item['type'] == '定金' ? '定金' : $item['item_name'];
                    $item['item']['price'] = $item['price'];
                }
                $purchase_item[] = $item;
            }
        }

        // 儲存自定付款方式
        if (request('self_pay_type') != '' && request('pay_type')['name'] == '自訂') {
            $model = new ShopPayType;
            $model->shop_id = $shop_info->id;
            $model->name    = request('self_pay_type');
            $model->save();

            $pay_type = [
                'id'   => $model->id,
                'name' => $model->name
            ];
        } else {
            $pay_type = request('pay_type');
        }

        // 處理消費資訊
        // 儲值金額
        $use_top_up = 0;
        if ($consumption_info['top_up_info']['use']) {
            $use_top_up = $consumption_info['top_up_info']['max'];
        }
        // 小計
        $sum = $consumption_info['total'] + $consumption_info['discount'] - $use_top_up;
        // 自訂優惠
        $self_definition_discount = 0;
        if ($consumption_info['checkout_total']['self_definition']!=''){
            $self_definition_discount = $consumption_info['checkout_total']['self_definition'] - $sum;
        }

        $consumption_info['final_total'] = [
            'sum'                      => $sum,
            'self_definition_discount' => $self_definition_discount,
            'pay_type'                 => $pay_type,
            'total'                    => $sum + $self_definition_discount,
        ];

        // 處理儲值
        $customer_top_up = CustomerTopUpLog::where('shop_id', $shop_info->id)
                                            ->where('customer_id', $shop_customer->customer_id)
                                            ->get();
        // 確認是否有新增的儲值
        $add = 0;
        foreach ($purchase_item as $item) {
            if ($item['type'] == '儲值') {
                $top_up = ShopTopUp::find($item['item']['id']);
                $add += $top_up->price;
                foreach ($top_up->roles as $role) {
                    if ($role->type ==1) $add += $role->price;
                }
            }
        }
        $top_up_info = [
            'origin'   => $customer_top_up->sum('price'),
            'add'      => $add,
            'discount' => -1 * $use_top_up,
            'sum'      => $customer_top_up->sum('price') + $add - $use_top_up,
        ];

        // 處理抵扣明細
        $deduct_items = [];
        $search_count = [];
        foreach ($deduct as $key => $item) {
            $in = false;
            foreach ($deduct_items as $k => $di) {
                if ($di['search_id'] == $item['search_id']) {
                    $deduct[$key]['count'] = $deduct_items[$k]['last_count'];
                }
            } 
            
            if (!$in) {
                $shop_staff = ShopStaff::find($deduct[$key]['shop_staff_id']);
                $deduct[$key]['use_count'] = 1;
                $deduct[$key]['last_count'] = $deduct[$key]['count'] - $deduct[$key]['use_count'];
                $deduct[$key]['shop_staff'] = [
                    'id'   => $deduct[$key]['shop_staff_id'],
                    'name' => $shop_staff->company_staff_info->name,
                ];
                $deduct_items[] = $deduct[$key]; 
            }
        }

        // 建立帳單
        if (empty(request('bill_info'))){
            $bill = new Bill;
            $bill->customer_id = $shop_customer->customer_id;
            $bill->company_id  = $shop_info->company_info->id;
            $bill->shop_id     = $shop_info->id;
            $bill->oid         = date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } else {
            $bill = Bill::find(request('bill_info')['id']);
        }                           
        
        $bill->customer_reservation_id = request('customer_reservation') ? request('customer_reservation')['id'] : NULL;
        $bill->pay_type                = json_encode($pay_type,JSON_UNESCAPED_UNICODE);
        $bill->consumption             = json_encode($consumption_info,JSON_UNESCAPED_UNICODE);
        $bill->purchase_item           = json_encode($purchase_item,JSON_UNESCAPED_UNICODE);
        $bill->deduct                  = json_encode($deduct_items,JSON_UNESCAPED_UNICODE);
        $bill->discount                = json_encode($discount,JSON_UNESCAPED_UNICODE);
        $bill->top_up                  = json_encode($top_up_info,JSON_UNESCAPED_UNICODE);
        $bill->note                    = $note;
        $bill->shop_staff_id           = $shop_staff_id;
        $bill->save();

        return response()->json(['status' => true, 'bill' => $bill]);
    }

    // 確認帳單內容
    public function check_bill($shop_id,$oid)
    {
        $shop_info     = Shop::find($shop_id);
        $bill          = Bill::where('oid',$oid)->first();
        if (!$bill) return response()->json(['status' => false, 'errors' => ['message' => ['找不到帳單資料']]]);

        $shop_customer = ShopCustomer::where('shop_id', $shop_info->id)->where('customer_id', $bill->customer_id)->first();
        $shop_staff    = ShopStaff::find($bill->shop_staff_id);

        // 使用優惠資訊
        $use_discount = json_decode($bill->discount);
        $use_discount_info = [];
        foreach ($use_discount as $type => $item) {
            if ($type == 'price_discount' && !empty($item)) {
                $use_discount_info[] = $item;
            } elseif($type == 'free_discount' && $item != NULL){
                foreach ($item as $it) {
                    if($it->selected) $use_discount_info[] = $it;
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
            'staff_name'        => $shop_staff->company_staff_info->name ,
            'consumption_info'  => json_decode($bill->consumption),
            'purchase_item'     => json_decode($bill->purchase_item),
            'use_discount_info' => $use_discount_info,
            'deduct_item'       => json_decode($bill->deduct),
            'top_up_info'       => json_decode($bill->top_up),
            'note'              => $bill->note,
            'sign_img'          => $bill->sign_img ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $bill->sign_img : '',
            'datetime'          => date('Y-m-d h:i a', strtotime($bill->created_at)),
        ];

        // 美業官網簽名頁面
        $sign_url = env('SHILIPAI_WEB') . '/store/'.$shop_info->alias.'/bill/'.$bill->oid.'/sign';

        // 根據可以集點的集點卡計算集點卡累計點數
        $customer_loyalty_cards = CustomerLoyaltyCard::where('customer_id', $shop_customer->customer_id)
                                                    ->where('shop_id', $shop_info->id)
                                                    ->where('last_point','!=',0)
                                                    ->get();
        $give_point_info = [];
        $card_selected = false;
        foreach ($customer_loyalty_cards as $card) {
            $loyalty_card = $card->loyalty_card_info;

            // 有效期限1無期限2顧客獲得當日3最後一次集點4統一起迄
            // 此張卡獲得的點數
            $points = $card->point_log->sum('point');

            $first_get_point_date = $card->created_at;
            if ($points == 0) {
                $last_get_point_date = $card->created_at;
            } else {
                $last_get_point_date = $card->point_log->last()->created_at;
            }

            // 檢查集點的期限
            $deadline = '';
            if ($loyalty_card->deadline_type == 2) {
                $deadline = date('Y-m-d 23:59:59', strtotime($first_get_point_date . "+" . $loyalty_card->year . " year +" . $loyalty_card->month . " month"));
            } elseif ($loyalty_card->deadline_type == 3) {
                $deadline = date('Y-m-d 23:59:59', strtotime($last_get_point_date . "+" . $loyalty_card->year . " year +" . $loyalty_card->month . " month"));
            } elseif ($loyalty_card->deadline_type == 4) {
                $deadline = $loyalty_card->end_date;
                if ($loyalty_card->start_date > date('Y-m-d H:i:s')) continue;
            }

            // 超過活動集點期限
            if ($loyalty_card->deadline_type != 1 && strtotime($deadline) < time()) continue;

            // 計算點數 1消費金額2服務一次3消費一次
            $name = '';
            if ($loyalty_card->condition_type == 1) {
                $give_point = $bill_info['consumption_info']->final_total->sum / $loyalty_card->condition;
                if (floor($give_point) == 0 ) continue;
                $condition = '滿' . $loyalty_card->condition . '元給1點' ;
                $name      = floor($give_point) . '點 - ' . $loyalty_card->name 
                           . '(' . ($loyalty_card->full_point - $card->last_point) . '/' . $loyalty_card->full_point . ')';
            } elseif ($loyalty_card->condition_type == 2 || $loyalty_card->condition_type == 3) {
                $give_point = 1;

                $condition = $loyalty_card->condition_type == 2 ? '服務乙次給一點' : '消費乙次給一點' ;
                $name      = $give_point . '點 - ' . $loyalty_card->name
                           . '(' . ($loyalty_card->full_point - $card->last_point) . '/' . $loyalty_card->full_point . ')';
            }

            if ($loyalty_card->condition_type)

            $give_point_info[] = [
                'customer_loyalty_card_id' => $card->id,
                'condition'                => $condition,
                'name'                     => $name,
                'point'                    => floor($give_point),
                'selected'                 => $card_selected == false ? true : false,
            ];
            $card_selected = true;
        }

        $data = [
            'status'          => true,
            'bill_info'       => $bill_info,
            'give_point_info' => $give_point_info,
            'qr_code'         => "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $sign_url . "&choe=UTF-8",
        ];

        return response()->json($data);
    }

    // 返回結帳
    public function back_checkout($shop_id,$oid)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => false, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_create_checkout', $user_shop_permission['permission'])) return response()->json(['status' => false, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info      = Shop::find($shop_id);
        $bill           = Bill::where('oid', $oid)->first();
        $bill->status   = 'pending';
        $bill->sign_img = NULL; 
        $bill->save();

        $shop_customer = ShopCustomer::where('shop_id', $shop_info->id)->where('customer_id', $bill->customer_id)->first();
        $shop_staff    = ShopStaff::find($bill->shop_staff_id);

        // 製作會員資料
        $personality = json_decode(json_encode(ShopCustomerController::shop_customer_personality($shop_info->id, $shop_customer->id)));
        $traits      = json_decode(json_encode(ShopCustomerController::shop_customer_traits($shop_info->id, $shop_customer->id)));

        $phone = '';
        if ($shop_customer->customer_info && $shop_customer->customer_info->phone) {
            $phone = substr($shop_customer->customer_info->phone, 0, 4)
            . '-' . substr($shop_customer->customer_info->phone, 4, 3)
            . '-' . substr($shop_customer->customer_info->phone, 7, 3);
        }

        $photo = '';
        if ($shop_customer->customer_info && preg_match('/http/i', $shop_customer->customer_info->photo)) {
            $photo = $shop_customer->customer_info->photo;
        } elseif ($shop_customer->customer_info && preg_match('/http/i', $shop_customer->customer_info->photo)) {
            $photo = env('SHOW_PHOTO') . '/api/get/customer/' . $shop_customer->customer_info->photo;
        }

        $customer_data = [
            'shop_customer_id' => $shop_customer->id,
            'name'             => $shop_customer->customer_info->realname,
            'personality'      => $personality->original->data->top->number,
            'traits'           => $traits->original->data->type,
            'sex'              => $shop_customer->customer_info->sex == 'F' ? '女' : ($shop_customer->customer_info->sex == 'M' ? '男' : '中性'),
            'birthday'         => $shop_customer->customer_info->birthday ? date('m.d', strtotime($shop_customer->customer_info->birthday)) : '',
            'age'              => $shop_customer->customer_info->birthday ? ShopCustomerController::getAge($shop_customer->customer_info->birthday) . '歲' : '',
            'constellation'    => $shop_customer->customer_info->birthday ? ShopCustomerController::constellation($shop_customer->customer_info->birthday) : '',
            'phone'            => $phone,
            'photo'            => $photo,
        ];

        // 拿取結帳內容 (商家資料、商家會員、預約資料、顧客資料、被選擇的服務人員、購買項目)
        $data = Self::get_checkout_info($shop_info, $shop_customer, '', $customer_data, '', json_decode($bill->purchase_item, true));

        // 將帳單對應資料填入對應欄位
        $data['pay_type']               = json_decode($bill->pay_type, true);
        $data['consumption_info']       = json_decode($bill->consumption, true);
        $data['purchase_item']          = json_decode($bill->purchase_item, true);
        $data['use_discount_info']      = json_decode($bill->discount,true);
        $data['deduct_item']            = json_decode($bill->deduct,true);
        $data['checkout_shop_staff_id'] = $bill->shop_staff_id;
        $data['customer_reservation']   = $bill->customer_reservation_id ? CustomerReservation::find($bill->customer_reservation_id) : [];

        // 使用優惠將選取狀態調整
        if (!empty($data['use_discount_info'])) {
            foreach ($data['select_discount']['price_discount'] as $k => $sd) {
                $selected = false;

                $udi = $data['use_discount_info']['price_discount'];
                if (!empty($udi)) {
                    if ($sd['id'] == $udi['id'] && $sd['type'] == $udi['type'] && $sd['discount_type'] == $udi['discount_type']) {
                        $data['select_discount']['price_discount'][$k]['selected'] == true;
                        $selected = true;
                    } else {
                        $data['select_discount']['price_discount'][$k]['selected'] == false;
                    }
                } else {
                    $data['use_discount_info']['price_discount'] = '';
                }
            }

            foreach ($data['select_discount']['free_discount'] as $k => $sd) {
                $selected = false;
                foreach ($data['use_discount_info']['free_discount'] as $udi) {
                    if ($sd['id'] == $udi['id'] && $sd['type'] == $udi['type'] && $sd['discount_type'] == $udi['discount_type']) {
                        $data['select_discount']['free_discount'][$k]['selected'] == true;
                        $selected = true;
                    }
                }
                if (!$selected) {
                    $data['select_discount']['free_discount'][$k]['selected'] == false;
                }
            }
        }
        
        $data['status']                    = true;
        $data['permission']                = true;
        $data['note']                      = $bill->note;
        $data['bill_info']                 = $bill;
        $data['purchase_item_permission']  = in_array('shop_purchase_item', $user_shop_permission['permission']) ? true : false;           // 購買項目權限
        $data['discount_items_permission'] = in_array('shop_discount_items', $user_shop_permission['permission']) ? true : false;          // 抵扣項目權限
        $data['use_discount_permission']   = in_array('shop_use_discount', $user_shop_permission['permission']) ? true : false;            // 使用優惠權限
        $data['self_defintion_permission'] = in_array('shop_self_definition_price', $user_shop_permission['permission']) ? true : false;   // 自訂金額權限
        $data['pay_type_permission']       = in_array('shop_select_pay_type', $user_shop_permission['permission']) ? true : false;         // 付款方式權限

        return response()->json($data);
    }

    // 完成結帳
    public function finish_checkout($shop_id)
    {
        // 檢查會員是否已經簽名了
        $bill = Bill::where('oid',request('bill_info')['oid'])->first();
        if (!$bill) return response()->json(['status' => false, 'errors' => ['message' => ['找不到帳單資料']]]);
        if ($bill->status == 'pending' && !request('check')){
            $data = [
                "bill_info"  => request('bill_info'),
                "give_point" => request('give_point'),
                "check"      => true,
            ];
            return response()->json(['status' => false, 'errors' => ['message' => ['會員帳單尚未簽名，是否繼續完成結帳']], 'data' => $data]);
        } 
        if ($bill->status == 'finish') return response()->json(['status' => false, 'errors' => ['message' => ['帳單無法重複完成結帳']]]);

        $shop_info     = Shop::find($shop_id);
        $shop_customer = ShopCustomer::where('shop_id',$shop_info->id)->where('customer_id',$bill->customer_id)->first();

        // 記錄選擇哪張集點卡
        if (!empty(request('give_point'))) {
            // 使用job建立初始資料
            $job = new LoyaltyCardPointLog($bill,request('give_point'));
            dispatch($job);
        }
        
        // 若是有預約資料則要記錄
        if ($bill->customer_reservation_id != '') {
            CustomerReservation::where('id', $bill->customer_reservation_id)->update(['bill_id'=>$bill->id]);
        }

        // 記錄使用的優惠
        $discount = json_decode($bill->discount,true);
        $discount_item = [];
        foreach ($discount as $k => $item_type) {
            if ($k == 'price_discount' && !empty($item_type)) {
                $discount_item[] = $item_type;
            } else {
                if ($item_type != NULL) {
                    foreach ($item_type as $item) {
                        $discount_item[] = $item;
                    }
                }
            }
        }

        foreach ($discount_item as $k => $item) {
            if( $item['selected'] == false ) continue;
            if ($item['type'] == '優惠券') {
                $customer_coupon = CustomerCoupon::find($item['id']);
                $customer_coupon->status     = 'Y';
                $customer_coupon->using_time = date('Y-m-d H:i:s');
                $customer_coupon->bill_id    = $bill->id;
                $customer_coupon->save();

                // 若是使用贈品（產品），需要寫產品記錄
                if ($customer_coupon->coupon_info->type == 'gift' && $customer_coupon->coupon_info->second_type == 1) {
                    $product_log = new ShopProductLog;
                    $product_log->shop_id         = $shop_info->id;
                    $product_log->bill_id         = $bill->id;
                    $product_log->shop_product_id = $customer_coupon->coupon_info->commodityId;
                    $product_log->category        = 'coupon';
                    $product_log->commodity_id    = $customer_coupon->coupon_info->id;
                    $product_log->count           = -1;
                    $product_log->shop_staff_id   = $bill->shop_staff_id;
                    $product_log->save();
                }

            } elseif ($item['type'] == '集點卡') {
                $customer_loyalty_card = CustomerLoyaltyCard::find($item['id']);
                $customer_loyalty_card->status     = 'Y';
                $customer_loyalty_card->using_time = date('Y-m-d H:i:s');
                $customer_loyalty_card->bill_id    = $bill->id;
                $customer_loyalty_card->save();

                // 若是使用贈品（產品），需要寫產品記錄
                if ($customer_loyalty_card->loyalty_card_info->type == 'gift' && $customer_loyalty_card->loyalty_card_info->second_type == 1) {
                    $product_log = new ShopProductLog;
                    $product_log->shop_id         = $shop_info->id;
                    $product_log->bill_id         = $bill->id;
                    $product_log->shop_product_id = $customer_loyalty_card->loyalty_card_info->commodityId;
                    $product_log->category        = 'loyalty_card';
                    $product_log->commodity_id    = $customer_loyalty_card->loyalty_card_info->id;
                    $product_log->count           = -1;
                    $product_log->shop_staff_id   = $bill->shop_staff_id;
                    $product_log->save();
                }

            } elseif ($item['type'] == '儲值') {
                $customer_topUp = CustomerTopUpLog::find($item['id']);
                $customer_topUp->status      = 'Y';
                $customer_topUp->using_time  = date('Y-m-d H:i:s');
                $customer_topUp->use_bill_id = $bill->id;
                $customer_topUp->save();

                // 若是使用贈品（產品），需要寫產品記錄
                if ($customer_topUp->type == 7 && $customer_topUp->top_up_role->second_type == 1) {
                    $product_log = new ShopProductLog;
                    $product_log->shop_id         = $shop_info->id;
                    $product_log->bill_id         = $bill->id;
                    $product_log->shop_product_id = $customer_topUp->top_up_role->commodity_id;
                    $product_log->category        = 'top_up';
                    $product_log->commodity_id    = $customer_topUp->top_up_role->shop_top_up_id;
                    $product_log->count           = -1;
                    $product_log->shop_staff_id   = $bill->shop_staff_id;
                    $product_log->save();
                }

            } elseif ($item['type'] == '會員卡') {
                $shop_membership_card_role = ShopMembershipCardRole::find($item['id']);
                $shop_membership_card      = ShopMembershipCard::find($shop_membership_card_role->shop_membership_card_id);
                $customer_membership_card  = CustomerMembershipCard::where('customer_id',$shop_customer->customer_id)
                                                                   ->where('shop_id',$shop_info->id)
                                                                   ->where('shop_membership_card_id',$shop_membership_card->id)
                                                                   ->first();

                $log = new CustomerMembershipCardLog;
                $log->customer_membership_card_id = $customer_membership_card->id;
                $log->customer_id                 = $shop_customer->customer_id;
                $log->shop_id                     = $shop_info->id;
                $log->company_id                  = $shop_info->company_info->id;
                $log->bill_id                     = $bill->id;
                $log->discount                    = $item['discount_price'];
                $log->save();
            }
        }

        // 記錄會員方案使用
        $deduct_items = json_decode($bill->deduct,true);
        foreach ($deduct_items as $item) {
            $customer_program_group = CustomerProgramGroup::find($item['customer_program_group_id']);
            $customer_program_group->last_count -= 1;
            $customer_program_group->save();

            if (preg_match('/service/i', $item['service_id']) || preg_match('/product/i', $item['service_id'])){
                $word = explode('-', $item['service_id']);
                $commodity_type = $word[0];
                $commodity_id   = $word[1];
            } else {
                $commodity_type = 'service';
                $commodity_id   = $item['service_id'];
            }
            

            $log = new CustomerProgramLog;
            $log->customer_program_id       = $customer_program_group->customer_program_id;
            $log->customer_program_group_id = $customer_program_group->id;
            $log->bill_id                   = $bill->id;
            $log->count                     = -1;
            $log->type                      = 3;
            $log->commodity_type            = $commodity_type;
            $log->commodity_id              = $commodity_id;
            $log->shop_staff_id             = $item['shop_staff_id'];
            $log->save();

            // 方案使用到產品需記錄
            if ($commodity_type == 'product') {
                $product_log = new ShopProductLog;
                $product_log->shop_id         = $shop_info->id;
                $product_log->bill_id         = $bill->id;
                $product_log->shop_product_id = $word[1];
                $product_log->category        = 'program';
                $product_log->commodity_id    = $customer_program_group->group_info->shop_program_id;
                $product_log->count           = -1;
                $product_log->shop_staff_id   = $bill->shop_staff_id;
                $product_log->save();
            }

        }

        // 記錄會員儲值使用
        $top_up_info = json_decode($bill->top_up,true);
        if ($top_up_info['discount'] != 0) {
            $top_up_log = new CustomerTopUpLog;
            $top_up_log->customer_id   = $shop_customer->customer_id;
            $top_up_log->shop_id       = $shop_info->id;
            $top_up_log->company_id    = $shop_info->company_info->id;
            $top_up_log->use_bill_id   = $bill->id;
            $top_up_log->type          = 3;
            $top_up_log->price         = $top_up_info['discount'];
            $top_up_log->shop_staff_id = $bill->shop_staff_id;
            $top_up_log->save();
        }
        
        // 購買產品、服務、儲值、方案、會員卡、訂金、自訂記錄
        $purchase_item = json_decode($bill->purchase_item,true);

        // 使用job建立購買細項資料
        $job = new PurchaseItemLog($bill, $purchase_item, $shop_customer);
        dispatch($job);

        $bill->point_log = json_encode(request('give_point'), JSON_UNESCAPED_UNICODE);
        $bill->status    = 'finish';
        $bill->save();

        return response()->json(['status'=>true]);
    }
    
    // 拿取結帳內容
    public function get_checkout_info($shop_info, $shop_customer, $customer_reservation, $customer_data, $shop_staff_id, $purchase_item)
    {
        // 消費總額
        $comsumption_total = 0;

        // 當前的商家員工
        if ($shop_staff_id!='') {
            $now_staff = ShopStaff::find($shop_staff_id);
        } else {
            $now_staff = ShopStaff::where('shop_id', $shop_info->id)
                                  ->where('user_id', auth()->getUser()->id)
                                  ->first();
        }
       
        // 製作儲值金資料
        $customer_top_up = CustomerTopUpLog::where('shop_id', $shop_info->id)
                                           ->where('customer_id', $shop_customer->customer_id)
                                           ->get();

        $top_up_info = [
            'price' => $customer_top_up->sum('price'),
        ];

        // 員工選項
        $shop_staffs = ShopStaff::where('shop_id', $shop_info->id)->get();
        $select_staff = [];
        foreach ($shop_staffs as $staff) {
            // 替除被開除的員工
            if ($staff->company_staff_info->fire_time == NULL) {
                $select_staff[] = [
                    'id'   => $staff->id,
                    'name' => $staff->company_staff_info->name,
                ];
            }
        }

        // 產品選項
        $shop_product_category = ShopProductCategory::where('shop_id', $shop_info->id)->get();
        $select_products = [];
        $product_reserve = [];
        foreach ($shop_product_category as $product_category) {

            $products = [];
            foreach ($product_category->shop_products->where('status', 'published') as $product) {
                $products[] = [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'price'          => $product->price,
                    'reserve'        => $product->product_logs->sum('count'),
                    'type'           => '產品',
                    'tag_name'       => '',
                    'deadline'       => '',
                    'discount_price' => [],
                    'count'          => 1,
                    'shop_staff_id'  => $now_staff->id,
                    'discount_info'  => [],
                    'group_info'     => [],
                    'content_info'   => [],
                ];

                $product_reserve[] = [
                    'id'      => $product->id,
                    'reserve' => $product->product_logs->sum('count'),
                ];
            }

            $select_products[] = [
                'category_name' => $product_category->name,
                'products'      => $products,
            ];
        }

        // 服務選項
        $shop_services = ShopService::where('shop_id', $shop_info->id)->where('status', 'published')->get();
        $select_services = [];
        foreach ($shop_services as $service) {
            $select_services[] = [
                'id'             => $service->id,
                'type'           => '服務',
                'name'           => $service->name,
                'tag_name'       => '',
                'deadline'       => '',
                'price'          => $service->price,
                'discount_price' => [],
                'count'          => 1,
                'shop_staff_id'  => $now_staff->id,
                'discount_info'  => [],
                'group_info'     => [],
                'content_info'   => [],
            ];
        }

        // 儲值金選項
        $shop_top_ups = ShopTopUp::where('shop_id', $shop_info->id)->where('status', 'published')->get();
        $select_top_ups = [];
        foreach ($shop_top_ups as $top_up) {
            if ($top_up->during_type == 2) {
                // 自訂期間需檢查是否在販售期限內
                if (date('Y-m-d H:i:s') > $top_up->end_date) continue;
            }
            $discount_info = [];
            foreach ($top_up->roles as $role) {
                // 儲值金類型1贈送儲值3贈品4免費
                if ($role->type == 1) {
                    $discount_info[] = [
                        'name'  => '儲值金' . $role->price . '元',
                        'count' => '',
                    ];
                } elseif ($role->type == 3) {
                    $discount_info[] = [
                        'name'  => $role->self_definition ?: $role->product_info->name,
                        'count' => '',
                    ];
                } elseif ($role->type == 4) {
                    $discount_info[] = [
                        'name'  => $role->self_definition ?: $role->service_info->name . '乙次',
                        'count' => '',
                    ];
                }
            }

            $select_top_ups[] = [
                'id'             => $top_up->id,
                'type'           => '儲值',
                'name'           => $top_up->name,
                'tag_name'       => '',
                'deadline'       => '',
                'price'          => $top_up->price,
                'discount_price' => [],
                'count'          => 1,
                'shop_staff_id'  => $now_staff->id,
                'discount_info'  => $discount_info,
                'group_info'     => [],
                'content_info'   => [],
            ];
        }

        // 方案選項
        $shop_programs = ShopProgram::where('shop_id', $shop_info->id)->where('status', 'published')->get();
        $select_programs = [];
        foreach ($shop_programs as $program) {
            if ($program->during_type == 2) {
                // 自訂期間需檢查是否在販售期限內
                if (date('Y-m-d H:i:s') > $program->end_date) continue;
            }

            // 製作組合資料與優惠金額
            $group_info = [];
            $sum = 0;
            foreach ($program->groups as $group) {
                $group_info[] = [
                    'name'  => $group->name,
                    'count' => $group->count,
                ];

                if ($group->type == 1) {
                    // 單選
                    $content = $group->group_content->first();
                    $sum += $group->count * ($content->commodity_type == 'service' ? $content->service_info->price : $content->product_info->price);
                } else {
                    // 多選
                    $avg = $total = 0;
                    foreach ($group->group_content as $content) {
                        $total += $content->commodity_type == 'service' ? $content->service_info->price : $content->product_info->price;
                    }
                    $avg = $total / $group->group_content->count();
                    $sum += round($avg) * $group->count;
                }
            }
            $select_programs[] = [
                'id'             => $program->id,
                'type'           => '方案',
                'name'           => $program->name,
                'tag_name'       => '',
                'deadline'       => '',
                'price'          => $program->price,
                'discount_price' => [
                    'origin_price'   => $sum,
                    'discount_price' => $program->price - $sum
                ],
                'count'          => 1,
                'shop_staff_id'  => $now_staff->id,
                'discount_info'  => [],
                'group_info'     => $group_info,
                'content_info'   => [],
            ];
        }

        // 會員卡選項
        $shop_membership_cards = ShopMembershipCard::where('shop_id', $shop_info->id)->where('status', 'published')->get();
        $select_membership_card = [];
        foreach ($shop_membership_cards as $card) {
            if ($card->during_type == 2) {
                // 自訂期間需檢查是否在販售期限內
                if (date('Y-m-d H:i:s') > $card->end_date) continue;
            }

            if ($card->count_type == 2) {
                // 需判斷會員卡張數
                $customer_buy_cards = CustomerMembershipCard::where('shop_membership_card_id', $card->id)->get()->count();
                if ($customer_buy_cards > $card->count) continue;
            }
            
            // 製作使用期限 卡片期限類別1無期限2顧客購買3統一起迄
            if ($card->card_during_type == 1) {
                $deadline = '無期限';
            } elseif ($card->card_during_type == 2) {
                $deadline = '顧客購買日起算' . ($card->card_year ? $card->card_year . '年' : '') . ($card->card_month ? $card->card_month . '月' : '');
            } else {
                $dadline = $card->card_end_date . '止';
            }

            $roles = [];
            foreach ($card->roles as $role) {

                $role_limits = $role->limit_commodity;
                // 檢查此限制項目是否有在商家的服務或產品內
                $limit_service = $role_limits->where('type', 'service');
                $limit_product = $role_limits->where('type', 'product');

                // 會員卡類型1現金折價2折扣3專屬優惠
                if ($role->type == 1) {
                    $type = '現金折價 ' . $role->price . ' 元';
                } elseif ($role->type == 2) {
                    $type = '折扣 ' . $role->discount . ' 折';
                } else {
                    $type = '專屬優惠價 ' . $role->price . ' 元';
                }

                $items = [];
                if ($role->limit == 1) {
                    $item_text = '無限制';
                } elseif ($role->limit == 2) {
                    $item_text = '全服務品項';
                } elseif ($role->limit == 3) {
                    $item_text = '全產品品項';
                } elseif ($role->limit == 4) {
                    $item_text = '部分品項';
                    $services = ShopService::whereIn('id', $limit_service->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                    $products = ShopProduct::whereIn('id', $limit_product->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                    $items = array_merge($services, $products);
                } elseif ($role->limit == 5) {
                    $item_text = '適用項目：單一服務品項';
                    $items = ShopService::whereIn('id', $limit_service->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                } else {
                    $item_text = '適用項目：單一產品品項';
                    $items = ShopProduct::whereIn('id', $limit_product->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                }

                $roles[] = [
                    'type'      => $type,
                    'item_text' => $item_text,
                    'items'     => $items
                ];
            }

            $select_membership_card[] = [
                'id'             => $card->id,
                'type'           => '會員卡',
                'name'           => $card->name,
                'tag_name'       => $card->tag_name,
                'deadline'       => $deadline,
                'price'          => $card->price,
                'discount_price' => [],
                'count'          => 1,
                'shop_staff_id'  => $now_staff->id,
                'discount_info'  => [],
                'group_info'     => [],
                'content_info'   => [
                    'name'      => $card->name,
                    'tag_name'  => $card->tag_name,
                    'condition' => [
                        $card->use_coupon ? '優惠券可以抵扣購買' : '優惠券不可以抵扣購買',
                        $card->use_topUp  ? '儲值金可以抵扣購買' : '儲值金不可以抵扣購買'
                    ],
                    'roles'     => $roles,
                ],
            ];
        }

        // 會員可抵扣方案
        $single_programs = $multiple_programs = $deduct_items = [];
        $customer_programs = CustomerProgram::where('shop_id', $shop_info->id)->where('customer_id', $shop_customer->customer_id)->get();
        foreach ($customer_programs as $cp) {
            foreach ($cp->groups as $group) {
                if ($group->group_info->type == 1) {
                    // 單選
                    $uid = uniqid();
                    $count = $group->use_log->sum('count');

                    if ($count <= 0) continue;

                    if ($group->group_info->group_content->first()->commodity_type == 'service') {
                        $commodity_type = 'service';
                        $item = $group->group_info->group_content->first()->service_info;
                    } else {
                        $commodity_type = 'product';
                        $item = $group->group_info->group_content->first()->product_info;
                        // if ($item->product_logs->sum('count') < $count) $count = $item->product_logs->sum('count');
                    }

                    // 判斷是否有跟預約一樣的項目
                    if ($customer_reservation != '' 
                        && $commodity_type == 'service'
                        && $customer_reservation->shop_service_id == $group->group_info->group_content->first()->commodity_id) {
                        $add = true;
                        foreach ($deduct_items as $item) {
                            if ($item['service_id'] == $item->id) {
                                $add = false;
                            }
                        }
                        if ($add) {
                            $deduct_items[] = [
                                'customer_program_group_id' => $group->id,
                                'search_id'                 => $uid,
                                'group_name'                => $cp->program_info->name . ' - ' . $group->group_info->name . '(' . $count . '組)', //. $group->group_info->count . '組',
                                'service_id'                => $commodity_type . '-' . $item->id,
                                'service_name'              => $item->name,
                                'count'                     => $count,
                                'shop_staff_id'             => $now_staff->id,
                                'commodity_type'            => $commodity_type,
                                'shop_product_id'           => $commodity_type == 'product' ? $item->id : '',
                            ];
                            $count--;
                        }
                    }

                    // 單選納入多選方案裡面
                    $check = false;
                    foreach ($multiple_programs as $k => $mp) {
                        if ($commodity_type == 'service' && $mp['id'] == 'service-' . $item->id) {
                            $check = true;
                        } elseif ($commodity_type == 'product' && $mp['id'] == 'product-' . $item->id) {
                            $check = true;
                        }

                        if ($check) {
                            $multiple_programs[$k]['option'][] = [
                                'customer_program_group_id' => $group->id,
                                'search_id'                 => $uid,
                                'group_name'                => $cp->program_info->name . ' - ' . $group->group_info->name . ' ('.$count.'組)', //. $group->group_info->count . '組',
                                'service_id'                => $commodity_type . '-' . $item->id,
                                'service_name'              => $item->name,
                                'count'                     => $count,
                                'shop_staff_id'             => $now_staff->id,
                                'commodity_type'            => $commodity_type,
                                'shop_product_id'           => $commodity_type == 'product' ? $item->id : '',
                            ];
                            break;
                        }
                    }

                    if ($check == false) {
                        $multiple_programs[] = [
                            'id'     => $commodity_type . '-' . $item->id,
                            'name'   => $item->name,
                            'option' => [
                                [
                                    'customer_program_group_id' => $group->id,
                                    'search_id'                 => $uid,
                                    'group_name'                => $cp->program_info->name . ' - ' . $group->group_info->name . ' (' . $count . '組)', //. $group->group_info->count . '組',
                                    'service_id'                => $commodity_type . '-' . $item->id,
                                    'service_name'              => $item->name,
                                    'count'                     => $count,
                                    'shop_staff_id'             => $now_staff->id,
                                    'commodity_type'            => $commodity_type,
                                    'shop_product_id'           => $commodity_type == 'product' ? $item->id : '',
                                ],
                            ]
                        ];
                    }

                    $single_programs[] = [
                        'customer_program_group_id' => $group->id,
                        'search_id'                 => $uid,
                        'group_name'                => $cp->program_info->name . ' - ' . $group->group_info->name . ' (' . $count . '組)', //. $group->group_info->count . '組',
                        'service_id'                => $commodity_type . '-' . $item->id,
                        'service_name'              => $item->name,
                        'count'                     => $count,
                        'shop_staff_id'             => $now_staff->id,
                        'commodity_type'            => $commodity_type,
                        'shop_product_id'           => $commodity_type == 'product' ? $item->id : '',
                    ];
                    
                } else {
                    // 多選
                    $items = [];
                    $uid = uniqid();
                    foreach ($group->group_info->group_content as $content) {
                        
                        $count = $group->use_log->sum('count');
                        if ($count <= 0) continue;

                        // 判斷是否有跟預約一樣的項目
                        if ($customer_reservation != '' 
                            && $content->commodity_type == 'service' 
                            && $customer_reservation->shop_service_id == $content->commodity_id) {
                            $add = true;
                            foreach ($deduct_items as $item) {
                                if ($item['service_id'] == $content->service_info->id) {
                                    $add = false;
                                }
                            }
                            
                            if ($add) {
                                $deduct_items[] = [
                                    'customer_program_group_id' => $group->id,
                                    'search_id'                 => $uid,
                                    'group_name'                => $cp->program_info->name . ' - ' . $group->group_info->name .' (' . $count . '組)',
                                    'service_id'                => 'service-'.$content->service_info->id,
                                    'service_name'              => $content->service_info->name,
                                    'count'                     => $count,
                                    'shop_staff_id'             => $now_staff->id,
                                    'commodity_type'            => $content->commodity_type,
                                    'shop_product_id'           => '',
                                ];
                                // $count--;
                            }
                        }

                        // if ($count == 0) continue;

                        $check = false;
                        foreach ($multiple_programs as $k => $mp) {
                            if ($content->commodity_type == 'service' && $mp['id'] == 'service-' . $content->service_info->id) {
                                $check = true;
                                $item = $content->service_info;
                            } elseif ($content->commodity_type == 'product' && $mp['id'] == 'product-' . $content->product_info->id) {
                                $check = true;
                                $item = $content->product_info;
                                // if ($item->product_logs->sum('count') < $count) $count = $item->product_logs->sum('count');
                            }

                            if ($check) {
                                $multiple_programs[$k]['option'][] = [
                                    'customer_program_group_id' => $group->id,
                                    'search_id'                 => $uid,
                                    'group_name'                => $cp->program_info->name . ' - ' . $group->group_info->name . ' (' . $count . '組)', //.'('.$group->group_info->group_content->count().'選'.$count.')',
                                    'service_id'                => $content->commodity_type . '-' . $item->id,
                                    'service_name'              => $item->name,
                                    'count'                     => $count,
                                    'shop_staff_id'             => $now_staff->id,
                                    'commodity_type'            => $content->commodity_type,
                                    'shop_product_id'           => $content->commodity_type == 'product' ? $item->id : '',
                                ];
                                break;
                            }
                        }
                        if (!$check){
                            $item = $content->commodity_type == 'service' ? $item = $content->service_info : $content->product_info;
                            $multiple_programs[] = [
                                'id'     => $content->commodity_type . '-' . $item->id,
                                'name'   => $item->name,
                                'option' => [
                                    [
                                        'customer_program_group_id' => $group->id,
                                        'search_id'                 => $uid,
                                        'group_name'                => $cp->program_info-> name . ' - ' . $group->group_info->name . ' (' . $count . '組)',// . '(' . $group->group_info->group_content->count() . '選' . $count . ')',
                                        'service_id'                => $content->commodity_type . '-' . $item->id,
                                        'service_name'              => $item->name,
                                        'count'                     => $count,
                                        'shop_staff_id'             => $now_staff->id,
                                        'commodity_type'            => $content->commodity_type,
                                        'shop_product_id'           => $content->commodity_type == 'product' ? $item->id : '',
                                    ],
                                ]
                            ];
                        }
                    }
                }
            }
        }

        // 收款方式
        $shop_pay_types = ShopPayType::where('shop_id', $shop_info->id)->get();
        $select_pay_type = [];
        foreach ($shop_pay_types as $type) {
            $select_pay_type[] = [
                'id'   => (string)$type->id,
                'name' => $type->name,
            ];
        }
        $select_pay_type[] = [
            'id'   => 'self_pay_type',
            'name' => '自訂',
        ];

        // 製作購買項目
        if ($customer_reservation != '') {
            $purchase_item = [];
            // 先判斷預約服務是否已被歸類在使用方案項目中
            $check_service = false;
            foreach ($deduct_items as $item) {
                if ($item['service_id'] == 'service-' . $customer_reservation->shop_service_id) {
                    $check_service = true;
                    break;
                }
            }
            if (!$check_service) {
                $purchase_item[] = [
                    'type'          => '服務',
                    'count'         => 1,
                    'shop_staff_id' => $customer_reservation->staff_info->id,
                    'price'         => 0,  // 訂金
                    'item_name'     => '', // 自訂名稱
                    'top_up'        => 'N',
                    'item'          => [
                        'id'             => $customer_reservation->shop_service_id,
                        'name'           => $customer_reservation->service_info->name,
                        'tag_name'       => '',
                        'deadline'       => '',
                        'price'          => $customer_reservation->service_info->price,
                        'discount_price' => [],
                        'shop_staff_id'  => $customer_reservation->staff_info->id,
                        'discount_info'  => [],
                        'group_info'     => [],
                        'content_info'   => [],
                    ],
                ];
                $comsumption_total += $customer_reservation->service_info->price;
            }

            if ($customer_reservation->advances) {
                foreach ($customer_reservation->advances as $advance) {
                    $purchase_item[] = [
                        'type'          => '加值服務',
                        'count'         => 1,
                        'shop_staff_id' => $customer_reservation->staff_info->id,
                        'price'         => 0,  // 訂金
                        'item_name'     => '', // 自訂名稱
                        'top_up'        => 'N',
                        'item'          => [
                            'id'             => $advance->shop_service_id,
                            'name'           => $advance->advance_info->name,
                            'tag_name'       => '',
                            'deadline'       => '',
                            'price'          => $advance->advance_info->price,
                            'discount_price' => [],
                            'shop_staff_id'  => $customer_reservation->staff_info->id,
                            'discount_info'  => [],
                            'group_info'     => [],
                            'content_info'   => [],
                        ],
                    ];
                    $comsumption_total += $advance->advance_info->price;
                }
            }
        }

        // 可使用優惠
        $select_discount = Self::select_discounts($shop_info, $shop_customer, $purchase_item);

        $max = $top_up_info['price'] > $comsumption_total ? $comsumption_total : $top_up_info['price'];

        // 消費金額資訊
        $comsumption_info = [
            'total'       => $comsumption_total,
            'discount'    => 0,
            'top_up_info' => [
                'last' => $top_up_info['price'],
                'max'  => $max,
                'use'  => true,
            ],
            'checkout_total' => [
                'total'           => $comsumption_total - $max,
                'self_definition' => $comsumption_total - $max,
            ],
        ];

        $data = [
            'checkout_shop_staff_id'  => $now_staff->id, // 結帳商家員工id
            'customer_reservation'    => $customer_reservation ?: [],
            'customer_data'           => $customer_data,
            'purchase_item'           => $purchase_item,
            'deduct_item'             => $deduct_items,  // 底扣項目
            'top_up_info'             => $top_up_info,
            'use_discount_info'       => [
                "price_discount" => "",
                "free_discount"  => $select_discount['free_discount'],
            ], // 使用優惠資料
            'consumption_info'        => $comsumption_info,
            'pay_type'                => [],
            'self_pay_type'           => '',
            'note'                    => '',
            'select_staff'            => $select_staff,
            'select_products'         => $select_products,
            'select_services'         => $select_services,
            'select_top_ups'          => $customer_data['name'] == '單購客' ? [] : $select_top_ups,
            'select_programs'         => $customer_data['name'] == '單購客' ? [] : $select_programs,
            'select_membership_cards' => $customer_data['name'] == '單購客' ? [] : $select_membership_card,
            'select_pay_type'         => $select_pay_type,
            'select_discount'         => $select_discount,
            'single_programs'         => $single_programs,
            'multiple_programs'       => $multiple_programs,
            'product_reserve'         => $product_reserve,
        ];

        return $data;
    }

    // 即時計算可使用優惠與消費資訊
    public function get_consumption($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'customer_data'     => 'required',
            // 'purchase_item'     => 'required',
            // 'use_discount_info' => 'required',
        ];

        $messages = [
            'customer_data.required'     => '缺少客戶資料',
            // 'purchase_item.required'     => '缺少購買項目資料',
            // 'use_discount_info.required' => '缺少使用優惠資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        $shop_info = Shop::find($shop_id);

        $shop_customer = ShopCustomer::find(request('customer_data')['shop_customer_id']);

        // 購買項目處理
        $purchase_item = [];
        foreach (request('purchase_item') as $item) {
            $check = false;
            foreach ($purchase_item as $k => $pitem) {
                if (!in_array($item['type'], ['定金', '自訂'])) {
                    if ($pitem['type'] == $item['type'] && $pitem['item']['id'] == $item['item']['id']) {
                        // 累加項目
                        $purchase_item[$k]['count'] += $item['count'];
                        $check = true;
                        break;
                    }
                } else {
                    $item['item']['name']  = $item['type'] == '定金' ? '定金' : $item['item_name'];
                    $item['item']['price'] = $item['price'];
                }
            }
            if ($check == false) {
                $purchase_item[] = $item;
            }
        }

        // 會員已選擇的使用優惠
        $use_discount_info = request('use_discount_info');

        // 根據購買項目製作可使用優惠
        $get_select_discount = Self::select_discounts($shop_info, $shop_customer, $purchase_item);

        // 購買項目總額計算
        $comsumption_total = 0;
        foreach ($purchase_item as $item) {
            if (in_array($item['type'],['定金','自訂'])) $comsumption_total += $item['price'] * $item['count'];
            else                                        $comsumption_total += $item['item']['price'] * $item['count'];
        }

        // 儲值金
        $customer_top_up = CustomerTopUpLog::where('shop_id', $shop_info->id)
                                           ->where('customer_id', $shop_customer->customer_id)
                                           ->get();

        $top_up_info = [
            'price' => $customer_top_up->sum('price'),
        ];

        // 消費金額資訊(根據抵扣項目、使用優惠變動)
        $use_discount_items   = [
            'price_discount' => "",
            'free_discount'  => [],
        ]; // 存放要使用的優惠
        $discount_total_price = 0;  // 總折扣
        $can_topUp_price      = 0;  // 計算可使用儲值金

        // 計算折價優惠
        $price_discount_check = false;
        if ($use_discount_info['price_discount']!="") {
            $use_discount = $use_discount_info['price_discount'];
            // 有選擇折價優惠
            foreach ($get_select_discount['price_discount'] as $k => $get_sd) {
                // 比對同樣的優惠內容，可以使用才做計算
                if ($get_sd['use_permission'] == true && $get_sd['id'] == $use_discount['id'] && $use_discount['type'] == '優惠券' && $get_sd['type'] == '優惠券') {
                    $price_discount_check = true;

                    $customer_coupons = CustomerCoupon::find($use_discount['id']);
                    $shop_coupon      = $customer_coupons->coupon_info;

                    $discount_total = 0;

                    if (in_array($shop_coupon->type, ['discount', 'full_consumption', 'cash'])) {
                        switch ($shop_coupon->type) {
                            case 'full_consumption': // 滿額打折
                            case 'discount':         // 打折
                                if ($shop_coupon->limit == 1) {
                                    // 全品項
                                    foreach ($purchase_item as $item) {

                                        if ($item['type'] == '定金') continue;

                                        if ($item['type'] == '儲值' || $item['type'] == '方案' || $item['type'] == '會員卡') {
                                            switch ($item['type']) {
                                                case '儲值':
                                                    $discount_obj = ShopTopUp::find($item['item']['id']);
                                                    break;
                                                case '方案':
                                                    $discount_obj = ShopProgram::find($item['item']['id']);
                                                    break;
                                                case '會員卡':
                                                    $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                                    break;
                                            }

                                            if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                                                if ($discount_obj->use_topUp == 1 && $discount_obj->use_coupon == 1) {
                                                    $can_topUp_price += $item['item']['price'] * $item['count'] * $shop_coupon->discount / 10;
                                                } elseif ($discount_obj->use_topUp == 1 && $discount_obj->use_coupon == 0) {
                                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                                }
                                            } else {
                                                // 儲值
                                                if ($discount_obj->use_coupon == 1) {
                                                    $can_topUp_price += $item['item']['price'] * $item['count'] * $shop_coupon->discount / 10;
                                                } else {
                                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                                }
                                            }

                                            if ($discount_obj->use_coupon == 0) continue;
                                        } else {
                                            if ($item['type'] == '自訂') {
                                                $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                            } else {
                                                // 服務、產品、加值服務
                                                $can_topUp_price += $item['item']['price'] * $item['count'] * $shop_coupon->discount / 10;
                                            }
                                        }

                                        $discount_total -= $item['item']['price'] * $item['count'] * (10 - $shop_coupon->discount) / 10;
                                    }
                                } elseif ($shop_coupon->limit == 2) {
                                    // 全服務
                                    foreach ($purchase_item as $item) {
                                        if ($item['type'] == '定金') continue;
                                        if ($item['type'] == '服務') {
                                            $discount_total  -= $item['item']['price'] * $item['count'] * (10 - $shop_coupon->discount) / 10;
                                            $can_topUp_price += $item['item']['price'] * $item['count'] * $shop_coupon->discount / 10;
                                        } else {
                                            if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                                                switch ($item['type']) {
                                                    case '方案':
                                                        $discount_obj = ShopProgram::find($item['item']['id']);
                                                        break;
                                                    case '會員卡':
                                                        $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                                        break;
                                                }

                                                if ($discount_obj->use_topUp == 1) {
                                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                                }
                                            } else {
                                                if ($item['type'] == '自訂') {
                                                    $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                                } else {
                                                    // 產品、儲值、加值服務
                                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                                }
                                            }
                                        }
                                    }
                                } elseif ($shop_coupon->limit == 3) {
                                    //  全產品
                                    foreach ($purchase_item as $item) {
                                        if ($item['type'] == '定金') continue;
                                        if ($item['type'] == '產品') {
                                            $discount_total  -= $item['item']['price'] * $item['count'] * (10 - $shop_coupon->discount) / 10;
                                            $can_topUp_price += $item['item']['price'] * $item['count'] * $shop_coupon->discount / 10;
                                        } else {
                                            if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                                                switch ($item['type']) {
                                                    case '方案':
                                                        $discount_obj = ShopProgram::find($item['item']['id']);
                                                        break;
                                                    case '會員卡':
                                                        $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                                        break;
                                                }

                                                if ($discount_obj->use_topUp == 1) {
                                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                                }
                                            } else {
                                                if ($item['type'] == '自訂') {
                                                    $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                                } else {
                                                    // 服務、儲值、加值服務
                                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // 部分品項
                                    foreach ($shop_coupon->limit_commodity as $shop_service) {
                                        // 比對購買項目裡的服務
                                        foreach ($purchase_item as $item) {
                                            if ($item['type'] == '定金') continue;
                                            if ($item['type'] == '服務') {
                                                if ($shop_service->type == 'service' && $shop_service->commodity_id == $item['item']['id']) {
                                                    $discount_total  -= $item['item']['price'] * $item['count'] * (10 - $shop_coupon->discount) / 10;
                                                    $can_topUp_price += $item['item']['price'] * $item['count'] * $shop_coupon->discount / 10;
                                                }
                                            } elseif ($item['type'] == '產品') {
                                                if ($shop_service->type == 'product' && $shop_service->commodity_id == $item['item']['id']) {
                                                    $discount_total  -= $item['item']['price'] * $item['count'] * (10 - $shop_coupon->discount) / 10;
                                                    $can_topUp_price += $item['item']['price'] * $item['count'] * $shop_coupon->discount / 10;
                                                }
                                            } else {
                                                if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                                                    switch ($item['type']) {
                                                        case '方案':
                                                            $discount_obj = ShopProgram::find($item['item']['id']);
                                                            break;
                                                        case '會員卡':
                                                            $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                                            break;
                                                    }

                                                    if ($discount_obj->use_topUp == 1) {
                                                        $can_topUp_price += $item['item']['price'] * $item['count'];
                                                    }
                                                } else {
                                                    if ($item['type'] == '自訂') {
                                                        $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                                    } else {
                                                        // 儲值、加值服務
                                                        $can_topUp_price += $item['item']['price'] * $item['count'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                break;
                            case 'cash':
                                // 折抵金額
                                $discount_total = -1 * $shop_coupon->price;
                                foreach ($purchase_item as $item) {
                                    if ($item['type'] == '定金') continue;
                                    if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                                        switch ($item['type']) {
                                            case '方案':
                                                $discount_obj = ShopProgram::find($item['item']['id']);
                                                break;
                                            case '會員卡':
                                                $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                                break;
                                        }

                                        if ($discount_obj->use_topUp == 1) {
                                            $can_topUp_price += $item['item']['price'] * $item['count'];
                                        }
                                    } else {
                                        if ($item['type'] == '自訂') {
                                            $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                        } else {
                                            // 儲值、服務、產品、加值服務
                                            $can_topUp_price += $item['item']['price'] * $item['count'];
                                        }
                                    }
                                }
                                break;
                        }
                    } elseif (in_array($shop_coupon->type, ['experience'])) {
                        // 體驗價
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '定金') continue;
                            if ($item['type'] == '服務' && $item['item']['id'] == $shop_coupon->commodityId) {
                                $discount_obj   = ShopService::find($item['item']['id']);
                                $discount_total = $shop_coupon->price - $discount_obj->price;

                                $can_topUp_price += $shop_coupon->price * $item['count'];
                            } else {
                                if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                                    switch ($item['type']) {
                                        case '方案':
                                            $discount_obj = ShopProgram::find($item['item']['id']);
                                            break;
                                        case '會員卡':
                                            $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                            break;
                                    }

                                    if ($discount_obj->use_topUp == 1) {
                                        $can_topUp_price += $item['item']['price'] * $item['count'];
                                    }
                                } else {
                                    if ($item['type'] == '自訂') {
                                        $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                    } else {
                                        // 儲值、產品、加值服務、不是體驗價的服務
                                        $can_topUp_price += $item['item']['price'] * $item['count'];
                                    }
                                }
                            }
                        }
                    }

                    $use_discount['discount_price'] = ceil($discount_total);
                    $use_discount['selected']       = true;

                    $use_discount_items['price_discount'] = $use_discount;
                    $discount_total_price += ceil($discount_total);

                    $get_select_discount['price_discount'][$k]['selected'] = true;
                    break;
                } elseif ($get_sd['use_permission'] == true && $get_sd['id'] == $use_discount['id'] && $use_discount['type'] == '集點卡' && $get_sd['type'] == '集點卡') {
                    // 計算優惠金額項目
                    $customer_loyalty_card = CustomerLoyaltyCard::find($use_discount['id']);
                    $shop_loyalty_card     = $customer_loyalty_card->loyalty_card_info;

                    // 折抵金額
                    $discount_total = -1 * $shop_loyalty_card->price;
                    foreach ($purchase_item as $item) {
                        if ($item['type'] == '定金') continue;
                        if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                            switch ($item['type']) {
                                case '方案':
                                    $discount_obj = ShopProgram::find($item['item']['id']);
                                    break;
                                case '會員卡':
                                    $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                    break;
                            }

                            if ($discount_obj->use_topUp == 1) {
                                $can_topUp_price += $item['item']['price'] * $item['count'];
                            }
                        } else {
                            if ($item['type'] == '自訂') {
                                $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                            } else {
                                // 儲值、服務、產品、加值服務
                                $can_topUp_price += $item['item']['price'] * $item['count'];
                            }
                        }
                    }

                    $use_discount['discount_price'] = ceil($discount_total);
                    $use_discount['selected']       = true;

                    $use_discount_items['price_discount'] = $use_discount;
                    $discount_total_price += ceil($discount_total);

                    $get_select_discount['price_discount'][$k]['selected'] = true;
                } elseif ($get_sd['use_permission'] == true && $get_sd['search_id'] == $use_discount['search_id'] && $use_discount['type'] == '會員卡' && $get_sd['type'] == '會員卡') {
                    $role = ShopMembershipCardRole::find($use_discount['id']);

                    $discount_total = $can_topUp_price = 0;
                    // 折抵金額 會員卡類型1現金折價2折扣3專屬優惠
                    if ($role->type == 1) {
                        // 現金折價
                        $discount_total  = -1 * $role->price;
                        // $can_topUp_price = $comsumption_total - $role->price;
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '定金') continue;
                            if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                                switch ($item['type']) {
                                    case '方案':
                                        $discount_obj = ShopProgram::find($item['item']['id']);
                                        break;
                                    case '會員卡':
                                        $discount_obj = ShopMembershipCard::find($item['item']['id']);
                                        break;
                                }

                                if ($discount_obj->use_topUp == 1) {
                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                }
                            } else {
                                if ($item['type'] == '自訂') {
                                    $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                } else {
                                    // 儲值、服務、產品、加值服務
                                    $can_topUp_price += $item['item']['price'] * $item['count'];
                                }
                            }
                        }
                    } elseif ($role->type == 2) {
                        // 折扣
                        if ($role->limit == 1) {
                            // 全品項
                            foreach ($purchase_item as $item) {
                                if ($item['type'] == '定金') continue;
                                if ($item['type'] == '自訂') {
                                    $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                } else {
                                    // 儲值、服務、產品、加值服務
                                    $discount_total  -= $item['item']['price'] * $item['count'] * (10 - $role->discount) / 10;
                                    $can_topUp_price += $item['item']['price'] * $item['count'] * $role->discount / 10;
                                }
                            }
                        } elseif ($role->limit == 2) {
                            // 全服務
                            foreach ($purchase_item as $item) {
                                if ($item['type'] == '定金') continue;
                                if ($item['type'] == '服務') {
                                    $discount_total -= $item['item']['price'] * $item['count'] * (10 - $role->discount) / 10;
                                    $can_topUp_price += $item['item']['price'] * $item['count'] * $role->discount / 10;
                                } else {
                                    if ($item['type'] == '自訂') {
                                        $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                    } else {
                                        // 儲值、方案、會員卡、加值服務、產品
                                        $can_topUp_price += $item['item']['price'] * $item['count'];
                                    }
                                }
                            }
                        } elseif ($role->limit == 3) {
                            // 全產品
                            foreach ($purchase_item as $item) {
                                if ($item['type'] == '定金') continue;
                                if ($item['type'] == '產品') {
                                    $discount_total -= $item['item']['price'] * $item['count'] * (10 - $role->discount) / 10;
                                    $can_topUp_price += $item['item']['price'] * $item['count'] * $role->discount / 10;
                                } else {
                                    if ($item['type'] == '自訂') {
                                        $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                    } else {
                                        // 儲值、方案、會員卡、加值服務、服務
                                        $can_topUp_price += $item['item']['price'] * $item['count'];
                                    }
                                }
                            }
                        } else {
                            // 部分品項判斷是否有包含目前預約的服務
                            foreach ($role->limit_commodity as $shop_service) {
                                // 比對購買項目裡的服務
                                foreach ($purchase_item as $item) {
                                    if ($item['type'] == '定金') continue;
                                    if ($item['type'] == '服務') {
                                        if ($shop_service->type == 'service' && $shop_service->commodity_id == $item['item']['id']) {
                                            $discount_total  -= $item['item']['price'] * $item['count'] * (10 - $role->discount) / 10;
                                            $can_topUp_price += $item['item']['price'] * $item['count'] * $role->discount / 10;
                                        }
                                    } elseif ($item['type'] == '產品') {
                                        if ($shop_service->type == 'product' && $shop_service->commodity_id == $item['item']['id']) {
                                            $discount_total  -= $item['item']['price'] * $item['count'] * (10 - $role->discount) / 10;
                                            $can_topUp_price += $item['item']['price'] * $item['count'] * $role->discount / 10;
                                        }
                                    } else {
                                        if ($item['type'] == '自訂') {
                                            $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                        } else {
                                            // 儲值、方案、會員卡、加值服務
                                            $can_topUp_price += $item['item']['price'] * $item['count'];
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // 專屬優惠
                        foreach ($role->limit_commodity as $shop_service) {
                            // 比對購買項目裡的服務
                            foreach ($purchase_item as $item) {
                                if ($item['type'] == '定金') continue;
                                if ($item['type'] == ($role->limit == 5 ? '服務' : '產品')) {
                                    if ($shop_service->type == ($role->limit == 5 ? 'service' : 'product') && $shop_service->commodity_id == $item['item']['id']) {
                                        $discount_total = ($role->price - $item['item']['price']) * $item['count'];
                                        $can_topUp_price += $role->price * $item['count'];
                                    } else {
                                        if ($item['type'] == '自訂') {
                                            $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                                        } else {
                                            // 儲值、方案、會員卡、加值服務
                                            $can_topUp_price += $item['item']['price'] * $item['count'];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $use_discount['discount_price'] = ceil($discount_total);
                    $use_discount['selected']       = true;

                    $use_discount_items['price_discount'] = $use_discount;
                    $discount_total_price += ceil($discount_total);

                    $get_select_discount['price_discount'][$k]['selected'] = true;
                }
            }
        }

        // 計算免費兌換
        foreach ($use_discount_info['free_discount'] as $use_discount) {

            if ($use_discount['selected'] != true) {
                $use_discount_items['free_discount'][] = $use_discount;
                continue;
            }

            if (($use_discount['type'] == '優惠券' || $use_discount['type'] == '集點卡')){

                $customer_discount_obj = $use_discount['type'] == '優惠券' ? CustomerCoupon::find($use_discount['id']) : CustomerLoyaltyCard::find($use_discount['id']);
                $discount_obj          = $use_discount['type'] == '優惠券' ? $customer_discount_obj->coupon_info : $customer_discount_obj->loyalty_card_info; ;

                if ($discount_obj->type == 'free'){
                    // 免費體驗，需判斷購買項目是否有一樣的服務，有一樣的就要扣除金額
                    if ($discount_obj->second_type == 3){
                        $check_in = false;
                        foreach ($purchase_item as $item){
                            if ($item['type'] == '服務' && $item['item']['id'] == $discount_obj->commodityId){
                                $obj = ShopService::find($item['item']['id']);
                                $use_discount['discount_price'] = -1 * $obj->price;
                                $check_in = true;

                                // $can_topUp_price -= $obj->price;
                                break;
                            }
                        }
                        if ($check_in == false){
                            $use_discount['discount_price'] = 0;
                        }
                    }else{
                        // 自訂
                        $use_discount['discount_price'] = 0;
                    }
                    $use_discount['selected'] = true;
                } elseif ($discount_obj->type == 'gift'){
                    // 贈品
                    if ($discount_obj->second_type == 1) {
                        $check_in = false;
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '產品' && $item['item']['id'] == $discount_obj->commodityId) {
                                $obj = ShopProduct::find($item['item']['id']);
                                $use_discount['discount_price'] = -1 * $obj['price'];
                                $check_in = true;
                                break;
                            }
                        }
                        if ($check_in == false) {
                            $use_discount['discount_price'] = 0;
                        }
                    } else {
                        // 自訂
                        $use_discount['discount_price'] = 0;
                    }
                    $use_discount['selected'] = true;
                }
                // $use_discount_items[] = $use_discount;
                $use_discount_items['free_discount'][] = $use_discount;
                $discount_total_price += $use_discount['discount_price'];

                foreach ($get_select_discount['free_discount'] as $k => $get_sd) {
                    if ($get_sd['id'] == $use_discount['id'] && $use_discount['type'] == '優惠券') {
                        $get_select_discount['free_discount'][$k]['selected'] = true;
                    }

                    if ($get_sd['id'] == $use_discount['id'] && $use_discount['type'] == '集點卡') {
                        $get_select_discount['free_discount'][$k]['selected'] = true;
                    }
                }

            } elseif ($use_discount['type'] == '儲值') {
                // 儲值
                $discount_obj = CustomerTopUpLog::find($use_discount['id']);
                if ($discount_obj->type == 8) {
                    // 免費體驗，需判斷購買項目是否有一樣的服務
                    if ($discount_obj->top_up_role->second_type == 3) {
                        $check_in = false;
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '服務' && $item['item']['id'] == $discount_obj->top_up_role->commodity_id) {
                                $obj = ShopService::find($item['item']['id']);
                                $use_discount['discount_price'] = -1 * $obj->price;
                                $check_in = true;
                                // $can_topUp_price -= $obj->price;
                                break;
                            }
                        }
                        if ($check_in == false) {
                            $use_discount['discount_price'] = 0;
                        }
                    } else {
                        // 自訂
                        $use_discount['discount_price'] = 0;
                    }
                    $use_discount['selected'] = true;
                } elseif ($discount_obj->type == 7) {
                    // 贈品
                    if ($discount_obj->top_up_role->second_type == 1) {
                        $check_in = false;
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '產品' && $item['item']['id'] == $discount_obj->top_up_role->commodity_id) {
                                $obj = ShopProduct::find($item['id']);
                                $use_discount['discount_price'] = -1 * $obj['price'];
                                $check_in = true;
                                break;
                            }
                        }
                        if ($check_in == false) {
                            $use_discount['discount_price'] = 0;
                        }
                    } else {
                        // 自訂
                        $use_discount['discount_price'] = 0;
                    }
                    $use_discount['selected'] = true;
                }
                // $use_discount_items[] = $use_discount;
                $use_discount_items['free_discount'][] = $use_discount;
                $discount_total_price += $use_discount['discount_price'];

                foreach ($get_select_discount['free_discount'] as $k => $get_sd) {
                    if ($get_sd['id'] == $use_discount['id'] && $use_discount['type'] == '儲值') {
                        $get_select_discount['free_discount'][$k]['selected'] = true;
                    }
                }
            }
        }

        if (empty($use_discount_info['free_discount'])) {
            $use_discount_items['free_discount'] = $get_select_discount['free_discount'];
        }

        // 若都沒有選擇優惠，需直接計算購買項目裡可用的儲值金總和
        if ($price_discount_check == false){
            foreach ($purchase_item as $item) {
                if ($item['type'] == '定金') continue;
                if ($item['type'] == '方案' || $item['type'] == '會員卡') {
                    switch ($item['type']) {
                        case '方案':
                            $discount_obj = ShopProgram::find($item['item']['id']);
                            break;
                        case '會員卡':
                            $discount_obj = ShopMembershipCard::find($item['item']['id']);
                            break;
                    }

                    if ($discount_obj->use_topUp == 1) {
                        $can_topUp_price += $item['item']['price'] * $item['count'];
                    }
                } else {
                    if ($item['type'] == '自訂') {
                        $can_topUp_price += $item['top_up'] == 'Y' ? $item['item']['price'] * $item['count'] : 0;
                    } else {
                        // 產品、儲值、服務、加值服務
                        $can_topUp_price += $item['item']['price'] * $item['count'];
                    }
                }
            }
        } 

        // 儲值最大使用金額
        $max = $top_up_info['price'] > $can_topUp_price ? $can_topUp_price : $top_up_info['price'];
        $max = $max > $comsumption_total + $discount_total_price ? $comsumption_total + $discount_total_price : $max;

        if( $max < 0 ) $max = 0;

        // 消費資訊
        $comsumption_info = [
            'total'       => $comsumption_total,
            'discount'    => $discount_total_price,
            'top_up_info' => [
                'last' => $top_up_info['price'],
                'max'  => $max,
                'use'  => true,
            ],
            'checkout_total' => [
                'total'           => $comsumption_total + $discount_total_price - $max,
                'self_definition' => $comsumption_total + $discount_total_price - $max,
            ],
        ];

        $data = [
            'status'            => true,
            'select_discount'   => $get_select_discount,   // 可使用優惠選項 
            'use_discount_info' => $use_discount_items,    // 使用優惠資料
            'consumption_info'  => $comsumption_info,      // 消費資訊
        ];

        return response()->json($data);
    }

    // 使用儲值金變更消費資訊
    public function change_consumption($shop_id)
    {
        $consumption = request()->all();

        if ($consumption['top_up_info']['use'] == true) {
            $price = $consumption['total'] + $consumption['discount'] - $consumption['top_up_info']['max'];
        } else {
            $price = $consumption['total'] + $consumption['discount'];
        }
        $consumption['checkout_total'] = [
            'total'           => $price,
            'self_definition' => $price,
        ];

        return response()->json(['status' => true, 'consumption_info' => $consumption ]);
    }

    // 根據購買項目製作可使用優惠
    public function select_discounts($shop_info, $shop_customer, $purchase_item)
    {
        // 購買項目總額計算
        $comsumption_total = 0;
        foreach ($purchase_item as $item) {
            if ($item['type'] == '定金' || $item['type'] == '自訂') {
                $comsumption_total += $item['price'] * $item['count'];
            } else {
                $comsumption_total += $item['item']['price'] * $item['count'];
            }
        }

        // 可使用優惠券
        $free_discount = $price_discount = [];
        $customer_coupons = CustomerCoupon::where('shop_id', $shop_info->id)
                                            ->where('customer_id', $shop_customer->customer_id)
                                            ->where('status', 'N')
                                            ->get();

        foreach ($customer_coupons as $cc) {
            //  檢查期限是否可用
            if ($cc->coupon_info->end_date <= date('Y-m-d H:i:s')) continue;

            if ($cc->coupon_info->type == 'discount') {
                $type = '折扣';
                if ($cc->coupon_info->limit == 1) $name = '全品項' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 2) $name = '全服務' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 3) $name = '全產品' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 4) $name = '部分品項' . $cc->coupon_info->discount . '折';
            } elseif ($cc->coupon_info->type == 'full_consumption') {
                $type = '滿額折扣';
                if ($cc->coupon_info->limit == 1) $name = '消費滿' . $cc->coupon_info->consumption . '，全品項' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 2) $name = '消費滿' . $cc->coupon_info->consumption . '，全服務' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 3) $name = '消費滿' . $cc->coupon_info->consumption . '，全產品' . $cc->coupon_info->discount . '折';
                if ($cc->coupon_info->limit == 4) $name = '消費滿' . $cc->coupon_info->consumption . '，部分品項' . $cc->coupon_info->discount . '折';
            } elseif ($cc->coupon_info->type == 'experience') {
                if (!$cc->coupon_info->service_info) continue;
                $name = $cc->coupon_info->service_info->name . " 體驗價" . $cc->coupon_info->price . "元";
                $type = '體驗價';
            } elseif ($cc->coupon_info->type == 'free') {
                if (!$cc->coupon_info->self_definition && !$cc->coupon_info->service_info) continue;
                $name = "免費體驗" . ($cc->coupon_info->self_definition ? $cc->coupon_info->self_definition : $cc->coupon_info->service_info->name);
                $type = '免費體驗';
            } elseif ($cc->coupon_info->type == 'gift') {
                if (!$cc->coupon_info->self_definition && !$cc->coupon_info->produce_info) continue;
                $name = "贈送" . ($cc->coupon_info->self_definition ? $cc->coupon_info->self_definition : $cc->coupon_info->produce_info->name);
                $type = '贈品 ';
            } elseif ($cc->coupon_info->type == 'cash') {
                $type = '現金券';
                if ($cc->coupon_info->second_type == 5) {
                    if ($cc->coupon_info->limit == 1) $name = '全品項抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 2) $name = '全服務抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 3) $name = '全產品抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 4) $name = '部分品項抵扣' . $cc->coupon_info->price . '元';
                } else {
                    if ($cc->coupon_info->limit == 1) $name = '消費滿' . $cc->coupon_info->consumption . '，全品項抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 2) $name = '消費滿' . $cc->coupon_info->consumption . '，全服務抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 3) $name = '消費滿' . $cc->coupon_info->consumption . '，全產品抵扣' . $cc->coupon_info->price . '元';
                    if ($cc->coupon_info->limit == 4) $name = '消費滿' . $cc->coupon_info->consumption . '，部分品項抵扣' . $cc->coupon_info->price . '元';
                }
            }

            // 先判斷是否有符合類別門檻
            $use_permission = false;
            // 判斷滿額折扣是否有到達消費門檻
            if ($cc->coupon_info->type == 'full_consumption' && $cc->coupon_info->consumption <= $comsumption_total) {
                $use_permission = true;
            } elseif ($cc->coupon_info->type == 'cash' && $cc->coupon_info->second_type == 6 && $cc->coupon_info->consumption <= $comsumption_total) {
                // 現金券滿額折
                $use_permission = true;
            } elseif ($cc->coupon_info->type == 'cash' && $cc->coupon_info->second_type == 5 && $cc->coupon_info->price <= $comsumption_total ) {
                // 現金券無門檻
                $use_permission = true;
            } elseif ($cc->coupon_info->type == 'discount') {
                // 折扣
                $use_permission = true;
            }

            if (in_array($cc->coupon_info->type, ['discount', 'full_consumption', 'cash']) && $cc->coupon_info->limit == 4) {
                // 部分品項判斷是否有包含目前預約的服務
                if ($use_permission) {
                    $permission = false;
                    foreach ($cc->coupon_info->limit_commodity as $shop_service) {
                        // 比對購買項目裡的服務
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '服務') {
                                if ($shop_service->type == 'service' && $shop_service->commodity_id == $item['item']['id']) {
                                    $permission = true;
                                    break;
                                }
                            }
                            if ($item['type'] == '產品') {
                                if ($shop_service->type == 'product' && $shop_service->commodity_id == $item['item']['id']) {
                                    $permission = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$permission) $use_permission = false;
                }
            } elseif (in_array($cc->coupon_info->type, ['discount', 'full_consumption', 'cash']) && in_array($cc->coupon_info->limit, [1, 2, 3])) {
                if ($use_permission) {
                    $permission = false;
                    if (in_array($cc->coupon_info->limit, [2])) {
                        // 全服務
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '服務') {
                                $permission = true;
                                break;
                            }
                        }
                    }

                    // 全產品
                    if (in_array($cc->coupon_info->limit, [3])) {
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '產品') {
                                $permission = true;
                                break;
                            }
                        }
                    }

                    // 判別全品項
                    if ($permission == false && in_array($cc->coupon_info->limit, [1])) {
                        foreach ($purchase_item as $item) {
                            // 需檢查購買的項目是否可以使用優惠
                            if ($item['type'] == '服務' || $item['type'] == '加值服務') {
                                $permission = true;
                                break;
                            }

                            if ($item['type'] == '儲值') {
                                $shop_topUp = ShopTopUp::find($item['item']['id']);
                                if ($shop_topUp && $shop_topUp->use_coupon == 1) {
                                    $permission = true;
                                    break;
                                }
                            }

                            if ($item['type'] == '方案') {
                                $shop_program = ShopProgram::find($item['item']['id']);
                                if ($shop_program && $shop_program->use_coupon == 1) {
                                    $permission = true;
                                    break;
                                }
                            }

                            if ($item['type'] == '會員卡') {
                                $shop_membership_card = ShopMembershipCard::find($item['item']['id']);
                                if ($shop_membership_card && $shop_membership_card->use_coupon == 1) {
                                    $permission = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$permission) $use_permission = false;
                }
            } else if (in_array($cc->coupon_info->type, ['experience'])) {
                // 體驗價
                $use_permission = false;
                foreach ($purchase_item as $item) {
                    if ($item['type'] == '服務') {
                        if ($cc->coupon_info->commodityId == $item['item']['id']) {
                            $use_permission = true;
                            break;
                        }
                    }
                }
            }

            $limit_text = '';
            $item_name  = [];
            switch ($cc->coupon_info->limit) {
                case 1:
                    $limit_text = '適用項目：全品項';
                    break;
                case 2:
                    $limit_text = '適用項目：全服務品項適用';
                    break;
                case 3:
                    $limit_text = '適用項目：全產品品項適用';
                    break;
                case 4:
                    $limit_commodity = ShopCouponLimit::where('shop_coupon_id', $cc->shop_coupon_id)->get();
                    $limit_text      = '適用 ' . $limit_commodity->count() . ' 項目，如下：';
                    foreach ($limit_commodity as $lc) {
                        if ($lc->type == 'service') {
                            // 服務
                            if ($lc->service_info) {
                                $item_name[] = $lc->service_info->name;
                            }
                        } else {
                            // 產品
                            if ($lc->product_info) {
                                $item_name[] = $lc->product_info->name;
                            }
                        }
                    }
                    break;
            }

            $info = [
                'category'    => 'coupon',
                'name'        => $cc->coupon_info->title,
                'description' => $name,
                'deadline'    => date('Y.m.d', strtotime($cc->coupon_info->end_date)) . '止',
                'type'        => $type,
                'limit_text'  => $limit_text,
                'item_name'   => $item_name,
                'content'     => $cc->coupon_info->content,
                'point_img'   => '',
                'condition'   => [],
            ];

            if (in_array($cc->coupon_info->type, ['free', 'gift'])) {
                $free_use_permission = true;
                // 若是贈送產品，需判斷產品是否足夠
                if ($cc->coupon_info->type == 'gift' && $cc->coupon_info->second_type == 1) {
                    $product_count = ShopProductLog::where('shop_id', $shop_info->id)
                                                    ->where('shop_product_id', $cc->coupon_info->commodityId)
                                                    ->sum('count');
                    if ($product_count <= 0) $free_use_permission = false;
                }

                $free_discount[] = [
                    'id'              => $cc->id,
                    'search_id'       => $cc->id.'coupon',
                    'type'            => '優惠券',
                    'tag_name'        => $cc->coupon_info->title,
                    'name'            => $name,
                    'deadline'        => date('Y.m.d', strtotime($cc->coupon_info->end_date)),
                    'use_permission'  => $free_use_permission,
                    'info'            => $info,
                    'selected'        => false,
                    'discount_price'  => 0,
                    'discount_type'   => 'free',
                    'shop_product_id' => $cc->coupon_info->type == 'gift' && $cc->coupon_info->second_type == 1 ? $cc->coupon_info->commodityId : '',
                ];
            } else {
                // 需判斷是否有折扣符合條件
                $price_discount[] = [
                    'id'             => $cc->id,
                    'search_id'      => $cc->id.'coupon',
                    'type'           => '優惠券',
                    'tag_name'       => $cc->coupon_info->title,
                    'name'           => $name,
                    'deadline'       => date('Y.m.d', strtotime($cc->coupon_info->end_date)),
                    'use_permission' => $use_permission,
                    'info'           => $info,
                    'selected'       => false,
                    'discount_price' => 0,
                    'discount_type'  => 'price'
                ];
            }
        }

        // 可使用集點卡
        $customer_loyalty_cards = CustomerLoyaltyCard::where('shop_id', $shop_info->id)
                                                        ->where('customer_id', $shop_customer->customer_id)
                                                        ->where('status', 'N')
                                                        ->where('last_point', 0)
                                                        ->get();

        $count = 0;
        foreach ($customer_loyalty_cards as $lc) {
            // 適用項目
            $limit_text  = '';
            $limit_items = [];
            switch ($lc->loyalty_card_info->limit) {
                case 1:
                    $limit_text = '適用項目：全品項適用';
                    break;
                case 2:
                    $limit_text = '適用項目：全服務品項適用';
                    break;
                case 3:
                    $limit_text = '適用項目：全產品品項適用';
                    break;
                case 4:
                    $limit_commodity = CompanyLoyaltyCardLimit::where('company_loyalty_card_id', $lc->loyalty_card_info->company_loyalty_card_id)->get();
                    $limit_text      = '適用 ' . $limit_commodity->count() . ' 項目，如下：';
                    foreach ($limit_commodity as $rd) {
                        if ($rd->type == 'service') {
                            if (!$rd->service_info) continue;
                            // 服務
                            $limit_items[] = $rd->service_info->name;
                        } else {
                            // 產品
                            if (!$rd->product_info) continue;
                            $limit_items[] = $rd->product_info->name;
                        }
                    }
                    break;
            }

            if ($lc->loyalty_card_info->type == 'free') {
                if (!$lc->loyalty_card_info->self_definition && !$lc->loyalty_card_info->service_info) continue;
                $name = "免費體驗" . ($lc->loyalty_card_info->self_definition ? $lc->loyalty_card_info->self_definition : $lc->loyalty_card_info->service_info->name);
                $type = '免費體驗';
            } elseif ($lc->loyalty_card_info->type == 'gift') {
                if (!$lc->loyalty_card_info->self_definition && !$lc->loyalty_card_info->produce_info) continue;
                $name = "贈送" . ($lc->loyalty_card_info->self_definition ? $lc->loyalty_card_info->self_definition : $lc->loyalty_card_info->produce_info->name);
                $type = '贈品';
            } elseif ($lc->loyalty_card_info->type == 'cash') {
                if ($lc->loyalty_card_info->second_type == 5) {
                    if ($lc->loyalty_card_info->limit == 1) $name = '全品項抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 2) $name = '全服務抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 3) $name = '全產品抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 4) $name = '部分品項抵扣' . $lc->loyalty_card_info->price . '元';
                } else {
                    if ($lc->loyalty_card_info->limit == 1) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全品項抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 2) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全服務抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 3) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，全產品抵扣' . $lc->loyalty_card_info->price . '元';
                    if ($lc->loyalty_card_info->limit == 4) $name = '消費滿' . $lc->loyalty_card_info->consumption . '，部分品項抵扣' . $lc->loyalty_card_info->price . '元';
                }
                $type = '現金券';
            }

            // 已集滿點數需判斷使用期限是否過期
            if ($lc->loyalty_card_info->discount_limit_type != 1) {
                $date = date('Y-m-d H:i:s', strtotime($lc->point_log->last()->created_at . ' +' . $lc->loyalty_card_info->discount_limit_month . ' month'));

                if (strtotime(date('Y-m-d H:i:s')) <= strtotime($date)) {
                    // 期限內
                    $status = '可使用';
                    $date   = date('Y.m.d', strtotime($date));
                } else {
                    // 已過期
                    continue;
                }
            } else {
                // 無期限
                $status = '可使用';
                $date   = '無期限';
            }

            // 浮水印
            $point_img = '';
            if ($lc->loyalty_card_info->watermark_img) {
                $point_img = env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $lc->loyalty_card_info->watermark_img;
            } else {
                $point_img =  env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $shop_info->company_info->logo;
            }

            // 先判斷是否有符合類別門檻
            $use_permission = false;
            // 判斷滿額折扣是否有到達消費門檻
            if ($lc->loyalty_card_info->type == 'cash' && $lc->loyalty_card_info->second_type == 6 && $lc->loyalty_card_info->consumption <= $comsumption_total) {
                // 現金券滿額折
                $use_permission = true;
            } elseif ($lc->loyalty_card_info->type == 'cash' && $lc->loyalty_card_info->second_type == 5 && $comsumption_total >= $lc->loyalty_card_info->price ) {
                // 現金券無門檻
                $use_permission = true;
            }

            if (in_array($lc->loyalty_card_info->type, ['cash']) && $lc->loyalty_card_info->limit == 4) {
                // 部分品項判斷是否有包含目前預約的服務
                if ($use_permission) {
                    $permission = false;
                    foreach ($lc->loyalty_card_info->limit_commodity as $shop_service) {
                        // 比對購買項目裡的服務
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '服務') {
                                if ($shop_service->type == 'service' && $shop_service->commodity_id == $item['item']['id']) {
                                    $permission = true;
                                    break;
                                }
                            }
                            if ($item['type'] == '產品') {
                                if ($shop_service->type == 'product' && $shop_service->commodity_id == $item['item']['id']) {
                                    $permission = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (!$permission) $use_permission = false;
                }
            } elseif (in_array($lc->loyalty_card_info->type, ['cash']) && in_array($lc->loyalty_card_info->limit, [1, 2, 3])) {
                // 全品項
                if ($use_permission) {
                    $permission = false;
                    if (in_array($lc->loyalty_card_info->limit, [2])) {
                        // 全服務
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '服務') {
                                $permission = true;
                                break;
                            }
                        }
                    }

                    // 全產品
                    if (in_array($lc->loyalty_card_info->limit, [3])) {
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '產品') {
                                $permission = true;
                                break;
                            }
                        }
                    }

                    // 判別全品項
                    if (in_array($lc->loyalty_card_info->limit, [1])) {
                        $permission = true;
                    }

                    if (!$permission) $use_permission = false;
                }
            }  

            $info = [
                'category'    => 'loyalty_card',
                'name'        => $lc->loyalty_card_info->name,
                'description' => $name,
                'deadline'    => $date,
                'type'        => $type,
                'limit_text'  => $limit_text,
                'item_name'   => $limit_items,
                'content'     => $lc->loyalty_card_info->content,
                'point_img'   => $point_img,
                'condition'   => [],
            ];

            if ($lc->loyalty_card_info->type == 'cash') {
                $price_discount[] = [
                    'id'             => $lc->id,
                    'search_id'      => $lc->id.'loyalty',
                    'type'           => '集點卡',
                    'tag_name'       => $lc->loyalty_card_info->name,
                    'name'           => $name,
                    'deadline'       => $date,
                    'use_permission' => $use_permission,
                    'info'           => $info,
                    'selected'       => false,
                    'discount_price' => 0,
                    'discount_type'  => 'price'
                ];
            } else {
                $free_use_permission = true;
                // 若是贈送產品，需判斷產品是否足夠
                if ($lc->loyalty_card_info->type == 'gift' && $lc->loyalty_card_info->second_type == 1) {
                    $product_count = ShopProductLog::where('shop_id', $shop_info->id)
                                                    ->where('shop_product_id', $lc->loyalty_card_info->commodityId)
                                                    ->sum('count');
                    if ($product_count <= 0) $free_use_permission = false;
                }

                $free_discount[] = [
                    'id'              => $lc->id,
                    'search_id'       => $lc->id.'loyalty',
                    'type'            => '集點卡',
                    'tag_name'        => $lc->loyalty_card_info->name,
                    'name'            => $name,
                    'deadline'        => $date,
                    'use_permission'  => $free_use_permission,
                    'info'            => $info,
                    'selected'        => false,
                    'discount_price'  => 0,
                    'discount_type'   => 'free',
                    'shop_product_id' => $lc->loyalty_card_info->type == 'gift' && $lc->loyalty_card_info->second_type == 1 ? $lc->loyalty_card_info->commodityId : '',
                ];
            }
        }

        // 儲值金
        // 會員儲值資料
        $customer_top_up = CustomerTopUp::where('customer_id', $shop_customer->customer_id)
                                        ->where('shop_id', $shop_info->id)
                                        ->get();
        foreach ($customer_top_up as $ctu) {
            foreach ($ctu->logs as $log) {
                // 儲值規則是免費體驗或是贈品
                if ($log->type == 7 || $log->type == 8) {
                    $role_info = $log->top_up_role;
                    if ($role_info) {

                        if ($log->type == 7) {
                            // 贈品
                            if (!$role_info->self_definition && !$role_info->product_info) continue;
                            $name = "贈送" . ($role_info->self_definition ? $role_info->self_definition : $role_info->product_info->name);
                            $type = '贈品';
                        } else {
                            // 免費體驗
                            if (!$role_info->self_definition && !$role_info->service_info) continue;
                            $name = "免費體驗" . ($role_info->self_definition ? $role_info->self_definition : $role_info->service_info->name);
                            $type = '免費體驗';
                        }

                        if ($log->status == 'Y') {
                            continue;
                        } else {
                            // 檢查期限
                            $date = date('Y-m-d H:i:s', strtotime($log->created_at . ' +' . $role_info->deadline_month . ' month'));

                            if (strtotime(date('Y-m-d H:i:s')) <= strtotime($date)) {
                                $info = [
                                    'category'    => 'top_up',
                                    'name'        => $ctu->top_up_info->name,
                                    'description' => $name,
                                    'deadline'    => $date,
                                    'type'        => $type,
                                    'limit_text'  => '',
                                    'item_name'   => [],
                                    'content'     => '',
                                    'point_img'   => '',
                                    'condition'   => [],
                                ];
                            } else {
                                continue;
                            }
                        }

                        // 若是贈送產品，需判斷產品是否足夠
                        $free_use_permission = true;
                        if ($log->type == 7 && $role_info->product_info) {
                            $product_count = ShopProductLog::where('shop_id', $shop_info->id)
                                                            ->where('shop_product_id', $role_info->product_info->id)
                                                            ->sum('count');
                            if ($product_count <= 0) $free_use_permission = false;
                        }

                        $free_discount[] = [
                            'id'              => $log->id,
                            'search_id'       => $log->id.'topUp',
                            'type'            => '儲值',
                            'tag_name'        => $ctu->top_up_info->name,
                            'name'            => $name,
                            'deadline'        => date('Y.m.d',strtotime($date)),
                            'use_permission'  => $free_use_permission,
                            'info'            => $info,
                            'selected'        => false,
                            'discount_price'  => 0,
                            'discount_type'   => 'free',
                            'shop_product_id' => $log->type == 7 && $role_info->product_info ? $role_info->product_info->id : '',
                        ];
                    }
                }
            }
        }

        // 會員卡
        $customer_membership_card = CustomerMembershipCard::where('customer_id', $shop_customer->customer_id)
                                                          ->where('shop_id', $shop_info->id)
                                                          ->get();
        foreach ($customer_membership_card as $card){

            $deadline = '無期限';
            if ($card->membership_card_info->card_during_type == 2) {
                // 顧客購買起
                $deadline = date('Y-m-d', strtotime($card->created_at . "+" . $card->membership_card_info->card_year . "year +" . $card->membership_card_info->card_month . 'month'));
            } elseif ($card->membership_card_info->card_during_type == 3) {
                // 統一起迄
                if (time() > $card->membership_card_info->card_end_date) continue;
                $deadline = date('Y-m-d', strtotime($card->membership_card_info->card_end_date));
            }

            $roles = [];
            foreach ($card->membership_card_info->roles as $role) {

                $role_limits = $role->limit_commodity;
                // 檢查此限制項目是否有在商家的服務或產品內
                $limit_service = $role_limits->where('type', 'service');
                $limit_product = $role_limits->where('type', 'product');

                // 會員卡類型1現金折價2折扣3專屬優惠
                if ($role->type == 1) {
                    $type = '現金折價 ' . $role->price . ' 元';
                } elseif ($role->type == 2) {
                    $type = '折扣 ' . $role->discount . ' 折';
                } else {
                    $type = '專屬優惠價 ' . $role->price . ' 元';
                }

                $items = [];
                $use_permission = false;
                if ($role->limit == 1) {
                    $item_text = '適用項目：無限制';
                } elseif ($role->limit == 2) {
                    $item_text = '適用項目：全服務品項';
                } elseif ($role->limit == 3) {
                    $item_text = '適用項目：全產品品項';
                } elseif ($role->limit == 4) {
                    $item_text = '適用項目：部分品項';
                    $services = ShopService::whereIn('id', $limit_service->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                    $products = ShopProduct::whereIn('id', $limit_product->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                    $items = array_merge($services, $products);
                } elseif ($role->limit == 5) {
                    $item_text = '適用項目：單一服務品項';
                    $items     = ShopService::whereIn('id', $limit_service->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                } else {
                    $item_text = '適用項目：單一產品品項';
                    $items     = ShopProduct::whereIn('id', $limit_product->pluck('commodity_id')->toArray())->pluck('name')->toArray();
                }

                if ($role->limit == 4) {
                    // 部分品項判斷是否有包含目前預約的服務
                    foreach ($role->limit_commodity as $shop_service) {
                        // 比對購買項目裡的服務
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '服務') {
                                if ($shop_service->type == 'service' && $shop_service->commodity_id == $item['item']['id']) {
                                    $use_permission = true;
                                    break;
                                }
                            }

                            if ($item['type'] == '產品') {
                                if ($shop_service->type == 'product' && $shop_service->commodity_id == $item['item']['id']) {
                                    $permission = true;
                                    break;
                                }
                            }
                        }
                    }
                } elseif (in_array($role->limit, [1, 2, 3])) {
                    // 全品項
                    if (in_array($role->limit, [2])) {
                        // 全服務
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '服務') {
                                $use_permission = true;
                                break;
                            }
                        }
                    }

                    // 全產品
                    if (in_array($role->limit, [3])) {
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == '產品') {
                                $use_permission = true;
                                break;
                            }
                        }
                    }

                    // 判別全品項
                    if (in_array($role->limit, [1])) {
                        $use_permission = true;
                    }
                } elseif (in_array($role->limit, [5,6])) {
                    // 比對購買項目裡的服務或產品
                    foreach ($role->limit_commodity as $shop_service) {
                        foreach ($purchase_item as $item) {
                            if ($item['type'] == ($role->limit == 5 ? '服務' : '產品')) {
                                if ($shop_service->type == ($role->limit == 5 ? 'service' : 'product') && $shop_service->commodity_id == $item['item']['id']) {
                                    $use_permission = true;
                                    break;
                                }
                            }
                        }
                    }
                } 

                $info = [
                    'category'    => 'card',
                    'name'        => $type,
                    'description' => $card->membership_card_info->tag_name,
                    'deadline'    => $deadline,
                    'type'        => $type,
                    'limit_text'  => $item_text,
                    'item_name'   => $items,
                    'content'     => '',
                    'point_img'   => '',
                    'condition'   => [
                        $card->membership_card_info->use_coupon ? '優惠券可以抵扣購買' : '優惠券不可以抵扣購買',
                        $card->membership_card_info->use_topUp  ? '儲值金可以抵扣購買' : '儲值金不可以抵扣購買'
                    ],
                ];

                $price_discount[] = [
                    'id'             => $role->id,
                    'search_id'      => $card->id . '_' . $role->id.'_card',
                    'type'           => '會員卡',
                    'tag_name'       => $card->membership_card_info->tag_name,
                    'name'           => $type,
                    'deadline'       => $deadline,
                    'use_permission' => $use_permission,
                    'info'           => $info,
                    'selected'       => false,
                    'discount_price' => 0,
                    'discount_type'  => 'price'
                ];
            }
        }

        $data = [
            'price_discount' => $price_discount,
            'free_discount'  => $free_discount,
        ];

        return $data;
    }
}
