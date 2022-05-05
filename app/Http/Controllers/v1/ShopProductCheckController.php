<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\ShopProductCategory;
use App\Models\ShopProductCheck;
use App\Models\ShopProductLog;
use App\Models\ShopStaff;
use Illuminate\Http\Request;
use Validator;

class ShopProductCheckController extends Controller
{
    // 商家產品盤點列表
    public function shop_product_check_list($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_product_check_list', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        // 拿取產品依照分類
        $product_categories = ShopProductCategory::where('shop_id', $shop_info->id)->orderBy('sequence','ASC')->get();
        $product_info = [];
        foreach ($product_categories as $category) {
            $products = [];
            foreach ($category->shop_products as $product) {
                $last_product_check = $product->check_info->last();
                if ($last_product_check && $last_product_check->reserve != $last_product_check->check && $last_product_check->revision_datetime == NULL) {
                    $status = 'error';
                    $error_info = [
                        'error_id'    => $last_product_check->id,
                        'produc_name' => $product->name,
                        'reserve'     => $last_product_check->reserve,                              // 當時庫存數
                        'check_time'  => substr($last_product_check->created_at, 0, 16),            // 盤點時間
                        'check'       => $last_product_check->check,                                // 盤點數
                        'error_count' => $last_product_check->check - $last_product_check->reserve, // 異常數量
                    ];
                    $datetime = substr($last_product_check->created_at, 0, 16);
                } else {
                    $status = 'check';
                    $datetime = '-';
                    if ($last_product_check && $last_product_check->revision_datetime) {
                        $datetime = substr($last_product_check->revision_datetime, 0, 16);
                    } elseif ($last_product_check && !$last_product_check->revision_datetime) {
                        $datetime = substr($last_product_check->created_at, 0, 16);
                    }
                }

                $products[] = [
                    'id'         => $product->id,
                    'name'       => $product->name,
                    'datetime'   => $datetime,
                    'reserve'    => $product->product_logs->sum('count'), // 庫存數
                    'status'     => $status,
                    'error_info' => $status == 'check' ? [] : $error_info,
                ];
            }

            $product_info[] = [
                'category_name' => $category->name,
                'products'      => $products,
                'product_count' => count($products),
            ];
        }

        $data = [
            'status'           => true,
            'permission'       => true,
            'check_permission' => in_array('shop_product_check_btn', $user_shop_permission['permission']) ? true : false,      // 盤點權限
            'edit_permission'  => in_array('shop_product_check_edit_btn', $user_shop_permission['permission']) ? true : false, // 異動權限
            'log_permission'   => in_array('shop_product_check_log_btn', $user_shop_permission['permission']) ? true : false,  // 記錄權限
            'data'             => $product_info,
        ];

        return response()->json($data);
    }

    // 商家產品盤點資料儲存
    public function shop_product_check_save($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'shop_product_id' => 'required',
            'check'           => 'required',
        ];

        $messages = [
            'shop_product_id.required' => '缺少產品id',
            'check.required'           => '請填寫盤點數量',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        $shop_info = Shop::find($shop_id);
        $user       = auth()->getUser();
        $shop_staff = ShopStaff::where('user_id', $user->id)->where('shop_id', $shop_info->id)->first();

        $product_info = ShopProduct::find(request('shop_product_id'));
        if (!$product_info) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到產品項目資料']]]);
        }

        $check_info = new ShopProductCheck;
        $check_info->shop_id         = $shop_info->id;
        $check_info->shop_product_id = request('shop_product_id'); 
        $check_info->reserve         = $product_info->product_logs->sum('count');
        $check_info->check           = request('check');
        $check_info->shop_staff_id   = $shop_staff->id;
        $check_info->save();

        return response()->json(['status' => true]);
    }

    // 商家產品盤點異常校正資料儲存
    public function shop_product_revision_save($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'error_id' => 'required',
            'recheck'  => 'required',
        ];

        $messages = [
            'error_id.required' => '缺少異常資料id',
            'recheck.required'  => '請填寫重盤點數量',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        $shop_info = Shop::find($shop_id);
        $user       = auth()->getUser();
        $shop_staff = ShopStaff::where('user_id', $user->id)->where('shop_id', $shop_info->id)->first();

        $check_info = ShopProductCheck::find(request('error_id'));
        $check_info->revision          = request('recheck');
        $check_info->revision_datetime = date('Y-m-d H:i:s');
        $check_info->note              = request('note');
        $check_info->save();

        // 寫入產品記錄
        $log = new ShopProductLog;
        $log->shop_id = $shop_info->id;
        $log->shop_product_id = $check_info->shop_product_id;
        $log->category        = 'inventory';
        $log->commodity_id    = $check_info->id;
        $log->count           = $check_info->revision - $check_info->reserve;
        $log->shop_staff_id   = $shop_staff->id;
        $log->save();

        return response()->json(['status' => true]);
    }

    // 商家產品盤點歷史記錄
    public function shop_product_check_logs($shop_id, $shop_product_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);
        
        $shop_info = Shop::find($shop_id);

        $check_logs = [];
        $product_check_logs = ShopProductCheck::where('shop_id', $shop_info->id)
                                              ->where('shop_product_id', $shop_product_id)
                                              ->orderBy('updated_at','DESC')
                                              ->get();
        foreach ($product_check_logs as $log) {

            $status = '';
            if ($log->reserve != $log->check && $log->revision_datetime == NULL) {
                $status = '異常';
            } elseif ($log->reserve != $log->check && $log->revision_datetime != NULL) {
                $status = '異常記錄';
            }

            $check_logs[] = [
                'id'       => $log->id,
                'datetime' => substr($log->updated_at, 0, 16),
                'staff'    => $log->staff_info->company_staff_info->name,
                'count'    => $log->reserve,                   // 當時庫存
                'revision' => $status == '異常' || $status == '' ? '-' : $log->revision - $log->reserve,  // 校正數
                'status'   => $status,
            ];
        }

        $data = [
            'status'    => true,
            'permsiion' => true,
            'data'      => $check_logs,
        ];

        return response()->json($data);
    }

    // 商家產品盤點異常記錄資料 
    public function shop_product_check_error_info($shop_id, $shop_product_check_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        $shop_info = Shop::find($shop_id);

        $product_check_logs = ShopProductCheck::find($shop_product_check_id);

        $data = [
            'status'    => true,
            'permsiion' => true,
            'data'      => [
                'type'              => '異常記錄',
                'product_id'        => $product_check_logs->product_info->id,
                'product_name'      => $product_check_logs->product_info->name,
                'datetime'          => substr($product_check_logs->created_at, 0, 16), // 盤點時間
                'reserve'           => $product_check_logs->reserve, // 當時庫存
                'check'             => $product_check_logs->check,   // 盤點數量
                'error_count'       => $product_check_logs->check - $product_check_logs->reserve,
                'revision'          => $product_check_logs->revision,// 重盤數
                'revision_count'    => $product_check_logs->revision - $product_check_logs->reserve, // 校正數量
                'note'              => $product_check_logs->note,
                'revision_datetime' => substr($product_check_logs->revision_datetime, 0, 16),
            ],
        ];

        return response()->json($data);
    }
}
