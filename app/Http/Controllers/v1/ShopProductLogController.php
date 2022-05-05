<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\ShopProductCategory;
use App\Models\ShopProductLog;
use App\Models\ShopStaff;
use Illuminate\Http\Request;
use Validator;

class ShopProductLogController extends Controller
{
    // 取得商家單一產品銷售記錄
    public function shop_product_logs($shop_id, $shop_product_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_product_logs', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);
        $shop_product_info = ShopProduct::find($shop_product_id);
        if (!$shop_product_info) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到產品項目資料']]]);
        }

        $product_logs = ShopProductLog::where('shop_product_id', $shop_product_id)->orderBy('shop_product_logs.created_at', 'DESC')->get();
        $logs = [];
        foreach ($product_logs as $log) {
            switch ($log->category) {
                case 'first':
                    $source    = '起始庫存';
                    $link_type = 'psi';
                    break;
                case 'coupon':
                    $source    = '優惠券｜' . $log->shop_coupon->title;
                    $link_type = 'bill'; 
                    break;
                case 'program':
                    $source = '方案｜' . $log->shop_program->name;
                    $link_type = 'bill'; 
                    break;
                case 'top_up':
                    $source = '方案｜' . $log->shop_top_up->name;
                    $link_type = 'bill'; 
                    break;
                case 'loyalty_card':
                    $source = '集點卡｜' . $log->shop_loyalty_card->name;
                    $link_type = 'bill'; 
                    break;
                case 'buy':
                    $source = '購買';
                    $link_type = 'bill'; 
                    break;
                case 'purchase':
                    $source = '進貨';
                    $link_type = 'psi'; 
                    break;
                case 'give_back':
                    $source = '銷貨｜退貨';
                    $link_type = 'psi'; 
                    break;
                case 'scrapped':
                    $source = '銷貨｜報廢';
                    $link_type = 'psi'; 
                    break;
                case 'consumables':
                    $source = '銷貨｜店內耗材';
                    $link_type = 'psi'; 
                    break;
                case 'customize':
                    $source = '銷貨｜自訂';
                    if ($log->category_definition) $source = '銷貨｜' . $log->category_definition;
                    $link_type = 'psi'; 
                    break;
                case 'inventory':
                    $source = '盤點調整';
                    $link_type = 'inventory'; 
                    break;
                // case 'invalid':
                //     $source = '銷貨｜作廢';
                //     $link_type = 'psi'; 
                //     break;
                default:
                    $source = $link_type = '';
                    break;
            }

            $logs[] = [
                'id'        => $log->category == 'inventory' ? $log->commodity_id : $log->id,
                'datetime'  => substr($log->created_at, 0, 16),
                'source'    => $source,
                'staff'     => $log->staff_info->company_staff_info->name,
                'count'     => $log->count,
                'link_type' => $link_type,
            ];
        }

        $purchase = $product_logs->where('count', '>', 0)->sum('count');
        $sale     = $product_logs->where('count', '<', 0)->sum('count');
        $reserve  = $product_logs->sum('count');

        $data = [
            'status'          => true,
            'permission'      => true,
            'look_permission' => in_array('shop_product_psi_look', $user_shop_permission['permission']) ? true : false,
            'statistics'      => [
                'purchase' => $purchase, // 進貨
                'sale'     => $sale,     // 銷售
                'reserve'  => $reserve,  // 庫存  
            ],
            'product_info'    => [
                'name' => $shop_product_info->name,
            ], 
            'product_logs' => $logs,   
        ];

        return response()->json($data);
    }

    // 取得商家產品進銷存
    public function shop_product_psi($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_product_psi', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        $product_psi_logs = ShopProductLog::where('shop_id', $shop_info->id)->where('bill_id', NULL)->orderBy('shop_product_logs.created_at', 'DESC')->get();
        $psi_logs = [];

        foreach ($product_psi_logs as $log) {
            $item = $item_color = '';
            switch ($log->category) {
                case 'purchase':
                    $item = '進貨';
                    $item_color = 'black';
                    break;
                case 'give_back':
                    $item = '退貨';
                    $item_color = 'red';
                    break;
                case 'scrapped':
                    $item = '報廢';
                    $item_color = 'red';
                    break;
                case 'consumables':
                    $item = '店內耗材';
                    $item_color = 'red';
                    break;
                case 'customize':
                    $item = '自訂';
                    if ($log->category_definition) $item = $log->category_definition;
                    $item_color = 'red';
                    break;
                case 'inventory':
                    $item = '盤點異動';
                    $item_color = 'red';
                    break;
                default:
                    $item = $item_color = '';
                    break;
            }

            $psi_logs[] = [
                'id'           => $log->id,
                'datetime'     => substr($log->created_at, 0, 16),
                'item'         => $item . ($log->cancel == 'Y' ? '(已作廢)' : ''),
                'item_color'   => $log->cancel == 'Y' ? 'gray' : $item_color,
                'product_name' => $log->product_info->name,
                'count'        => $log->count,
                'cancel'       => $log->cancel,
            ];
        }

        $data = [
            'status'              => true,
            'permission'          => true,
            'purchase_permission' => in_array('shop_product_psi_purchase', $user_shop_permission['permission']) ? true : false,
            'edit_permission'     => in_array('shop_product_psi_edit', $user_shop_permission['permission']) ? true : false,
            'look_permission'     => in_array('shop_product_psi_look', $user_shop_permission['permission']) ? true : false,
            'shop_products'       => ShopProductController::shop_product_select($shop_info->id), 
            'data'                => $psi_logs,
        ];

        return response()->json($data);
    }

    // 商家進貨
    public function shop_product_purchase($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        $shop_info = Shop::find($shop_id);

        // 取得商家產品
        $category_infos = ShopProductCategory::where('shop_id', $shop_info->id)->orderBy('sequence', 'ASC')->get();
        // 在拿取shop裡有的服務
        $shop_products = [];
        foreach ($category_infos as $k => $info) {
            $products = [];
            foreach ($info->shop_products as $product) {
                $products[] = [
                    'id'      => $product->id,
                    'name'    => $product->name,
                    // 'price'   => '',
                    // 'count'   => '',
                ];
            }

            $info->match_products = $products;
            unset($info->shop_products);

            $shop_products[] = [
                'category_name' => $info->name,
                'products'      => $products,
            ];
        }

        $data = [
            'status'            => true,
            'permission'        => true,
            'data'              => $shop_products,
        ];

        return response()->json($data);
    }

    // 儲存商家進貨資料
    public function shop_product_purchase_save($shop_id)
    {
        // 驗證欄位資料
        $rules     = ['shop_products' => 'required'];
        $messages = [
            'shop_products.required' => '缺少進貨產品資料',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        $shop_info  = Shop::find($shop_id);
        $user       = auth()->getUser();
        $shop_staff = ShopStaff::where('user_id', $user->id)->where('shop_id', $shop_info->id)->first();

        foreach (request('shop_products') as $product ) {
            $log = new ShopProductLog;
            $log->shop_id = $shop_info->id;
            $log->shop_product_id = $product['id'];
            $log->category        = 'purchase';
            $log->count           = $product['count'];
            $log->price           = $product['price'];
            $log->shop_staff_id   = $shop_staff->id;
            $log->note            = request('note') ?: NULL;
            $log->save();
        }

        return response()->json(['status' => true]);
    }

    // 儲存商家銷貨資料
    public function shop_product_sale_save($shop_id)
    {
        // 驗證欄位資料
        $rules = [
            'shop_product_id' => 'required',
            'category'        => 'required',
        ];
        $messages = [
            'shop_product_id.required' => '缺少進貨產品資料',
            'category.required'        => '請選擇異動項目資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        $shop_info  = Shop::find($shop_id);
        $user       = auth()->getUser();
        $shop_staff = ShopStaff::where('user_id', $user->id)->where('shop_id', $shop_info->id)->first();

        $log = new ShopProductLog;
        $log->shop_id             = $shop_info->id;
        $log->shop_product_id     = request('shop_product_id');
        $log->shop_product_log_id = request('shop_product_log_id')?:NULL;
        $log->category            = request('category');
        $log->category_definition = request('category_definition');
        $log->count               = -1 * request('count');
        $log->price               = request('price');
        $log->shop_staff_id       = $shop_staff->id;
        $log->note                = request('note') ?: NULL;
        $log->save();

        return response()->json(['status' => true]);
    }

    // 商家產品進銷存查看
    public function shop_psi_look($shop_id, $shop_product_log_id)
    {
        $shop_info  = Shop::find($shop_id);
        $user       = auth()->getUser();
        $shop_staff = ShopStaff::where('user_id', $user->id)->where('shop_id', $shop_info->id)->first();

        $product_log = ShopProductLog::find($shop_product_log_id);
        if (!$product_log) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到項目資料']]]);
        }

        switch ($product_log->category) {
            case 'first':
                $type = '起始庫存';
                break;
            case 'purchase':
                $type = '進貨';
                break;
            case 'give_back':
                $type = '退貨';
                break;
            case 'scrapped':
                $type = '報廢';
                break;
            case 'consumables':
                $type = '店內耗材';
                break;
            case 'customize':
                $type = '自訂';
                if ($product_log->category_definition) $type = $product_log->category_definition;
                break;
            case 'inventory':
                $type = '盤點異動';
                break;
            default:
                $type = $item_color = '';
                break;
        }

        $change_logs = [];
        if ($type == '進貨') {
            foreach ($product_log->change_logs as $change_log) {
                switch ($change_log->category) {
                    case 'purchase':
                        $change_type = '進貨';
                        break;
                    case 'give_back':
                        $change_type = '退貨';
                        break;
                    case 'scrapped':
                        $change_type = '報廢';
                        break;
                    case 'consumables':
                        $change_type = '店內耗材';
                        break;
                    case 'customize':
                        $change_type = '自訂';
                        if ($product_log->category_definition) $change_type = $product_log->category_definition;
                        break;
                    case 'inventory':
                        $change_type = '盤點異動';
                        break;
                    default:
                        $change_type = $item_color = '';
                        break;
                }

                $change_logs[] = [
                    'type'     => $change_type,
                    'count'    => $change_log->count,
                    'note'     => $change_log->note,
                    'staff'    => $change_log->staff_info->company_staff_info->name,
                    'datetime' => substr($change_log->created_at, 0, 16),

                ];
            }
        }

        $log_info = [
            'type'         => $type,
            'product_id'   => $product_log->product_info->id,
            'product_name' => $product_log->product_info->name,
            'datetime'     => substr($product_log->created_at, 0, 16),
            'staff_name'   => $product_log->staff_info->company_staff_info->name,
            'price'        => $product_log->price,
            'count'        => $product_log->count,
            'sum'          => number_format($product_log->price * $product_log->count),
            'note'         => $product_log->note,
            'change_logs'  => $change_logs,
            'relation_id'  => $product_log->shop_product_log_id ?: "",
        ];

        $data = [
            'status'     => true,
            'data'       => $log_info,
        ];

        return response()->json($data);
    }

    // 商家進銷存記錄作廢
    public function shop_psi_cancel($shop_id, $shop_product_log_id)
    {
        $shop_product_log_info = ShopProductLog::find($shop_product_log_id);
        if (!$shop_product_log_info) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到項目資料']]]);
        }

        // 作廢進銷存記錄
        $shop_product_log_info->cancel = 'Y';
        $shop_product_log_info->save();

        return response()->json(['status' => true]);
    }

}
