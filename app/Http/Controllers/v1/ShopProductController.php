<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\CompanyCoupon;
use App\Models\CompanyCouponLimit;
use App\Models\CompanyLoyaltyCard;
use App\Models\CompanyLoyaltyCardLimit;
use App\Models\CompanyProduct;
use App\Models\CompanyProductCategory;
use App\Models\Shop;
use App\Models\ShopMembershipCard;
use App\Models\ShopProduct;
use App\Models\ShopProductCategory;
use App\Models\ShopProductLog;
use App\Models\ShopProgram;
use App\Models\ShopStaff;
use App\Models\ShopTopUp;
use Validator;
use Illuminate\Http\Request;

class ShopProductController extends Controller
{
    // 商家產品列表
    public function shop_products($shop_id)
    {
        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // // 確認頁面瀏覽權限
        if (!in_array('shop_products', $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        $shop_info = Shop::find($shop_id);

        // 先拿出分類
        $shop_product_categories = ShopProductCategory::where('shop_id', $shop_info->id)->orderBy('sequence','ASC')->get();

        // 在處理分類裡有的產品
        $shop_products = [];
        foreach ($shop_product_categories as $category) {

            $product_info = [];
            foreach ($category->shop_products as $shop_product ) {
                $product_info[] = [
                    'id'     => $shop_product->id,
                    'name'   => $shop_product->name,
                    'price'  => $shop_product->price,
                    'stock'  => $shop_product->product_logs->sum('count'),
                    'status' => $shop_product->status,
                    'photo'  => $shop_product->photo ? env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'. $shop_product->photo : NULL,
                ];
            }

            $shop_products[] = [
                'category_id'   => $category->id,
                'category_name' => $category->name,
                'product_count' => $category->shop_products->count(),
                'products'      => $product_info,
            ];
        }

        $data = [
            'status'     => true,
            'permission' => true,
            'category_sort_permission'   => in_array('shop_product_category_sort_btn', $user_shop_permission['permission']) ? true : false,    // 分類排序
            'category_add_permission'    => in_array('shop_product_category_create_btn', $user_shop_permission['permission']) ? true : false,  // 分類新增
            'category_edit_permission'   => in_array('shop_product_category_edit_btn', $user_shop_permission['permission']) ? true : false,    // 分類編輯
            'category_delete_permission' => in_array('shop_product_category_delete_btn', $user_shop_permission['permission']) ? true : false,  // 分類刪除
            'product_add_permission'     => in_array('shop_product_create_btn', $user_shop_permission['permission']) ? true : false,           // 產品新增
            'product_edit_permission'    => in_array('shop_product_edit_btn', $user_shop_permission['permission']) ? true : false,             // 產品編輯
            'product_delete_permission'  => in_array('shop_product_delete_btn', $user_shop_permission['permission']) ? true : false,           // 產品刪除
            'product_status_permission'  => in_array('shop_product_status_btn', $user_shop_permission['permission']) ? true : false,           // 產品上下架
            'product_sort_permission'    => in_array('shop_product_sort_btn', $user_shop_permission['permission']) ? true : false,             // 產品排序
            'product_log_permission'     => in_array('shop_product_logs_btn', $user_shop_permission['permission']) ? true : false,             // 產品銷售記錄
            'data'       => $shop_products,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家產品資料
    public function shop_product_info($shop_id, $shop_product_id = "", $mode = "")
    {
        if ($shop_product_id) {
            $shop_product_info = ShopProduct::find($shop_product_id);
            if (!$shop_product_info) {
                return response()->json(['status' => false, 'errors' => ['message' => ['找不到產品項目資料']]]);
            }
            $type = $mode == "" ? 'edit' : 'create';
        } else {
            $shop_product_info = new ShopProduct;
            $type              = 'create';
        }

        $shop_info    = Shop::find($shop_id);

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if ($user_shop_permission['status'] == false) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => [$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        if (!in_array('shop_product_' . $type, $user_shop_permission['permission'])) return response()->json(['status' => true, 'permission' => false, 'errors' => ['message' => ['使用者沒有權限']]]);

        if ($type == 'edit') $count_permission = false;
        else {
            $count_permission = in_array('shop_product_' . $type . '_count', $user_shop_permission['permission']) ? true : false;
        }

        $product_info = [
            'id'                                  => $shop_product_info->id,
            'shop_product_category_id'            => $shop_product_info->shop_product_category_id,
            'shop_product_category_id_permission' => in_array('shop_product_' . $type . '_category', $user_shop_permission['permission']) ? true : false,
            'name'                                => $shop_product_info->name,
            'name_permission'                     => in_array('shop_product_' . $type . '_name', $user_shop_permission['permission']) ? true : false,
            'price'                               => $shop_product_info->price || $shop_product_info->price == 0 ? (string)$shop_product_info->price : '',
            'price_permission'                    => in_array('shop_product_' . $type . '_price', $user_shop_permission['permission']) ? true : false,
            'basic_price'                         => $shop_product_info->basic_price || $shop_product_info->basic_price == 0  ? (string)$shop_product_info->basic_price : '',
            'basic_price_permission'              => in_array('shop_product_' . $type . '_basic_price', $user_shop_permission['permission']) ? true : false,
            'info'                                => $shop_product_info->info,
            'info_permission'                     => in_array('shop_product_' . $type . '_info', $user_shop_permission['permission']) ? true : false,
            'photo'                               => $shop_product_info->photo ? env('SHOW_PHOTO') . '/api/show/' . $shop_info->alias . '/' . $shop_product_info->photo : NULL,
            'photo_permission'                    => in_array('shop_product_' . $type . '_photo', $user_shop_permission['permission']) ? true : false,
            'count'                               => $shop_product_info->count,
            'count_permission'                    => $count_permission,
            'notice'                              => $shop_product_info->notice,
            'notice_permission'                   => in_array('shop_product_' . $type . '_notice', $user_shop_permission['permission']) ? true : false,
            'notice_count'                        => $shop_product_info->notice_count,
            'notice_count_permission'             => in_array('shop_product_' . $type . '_notice_count', $user_shop_permission['permission']) ? true : false,
            'barcode'                             => $shop_product_info->barcode,
            'barcode_permission'                  => in_array('shop_product_' . $type . '_barcode', $user_shop_permission['permission']) ? true : false,
            'status'                              => $shop_product_info->status ?: 'published',
            'status_permission'                   => in_array('shop_product_' . $type . '_status', $user_shop_permission['permission']) ? true : false,
        ];

        $shop_categories = ShopProductCategory::select('id','name')->where('shop_id', $shop_info->id)->sort()->get();

        $data = [
            'status'                  => true,
            'permission'              => true,
            'shop_service_categories' => $shop_categories,
            'data'                    => $product_info,
        ];

        return response()->json($data);
    }

    // 儲存商家產品資料
    public function shop_product_save($shop_id, $shop_product_id = "")
    {
        // 驗證欄位資料
        $rules = [
            'name'                     => 'required',
            'shop_product_category_id' => 'required',
            'price'                    => 'required',
        ];

        if (!$shop_product_id) {
            // 新增
            $rules['status'] = 'required';
            $rules['count']  = 'required';
        } 

        $messages = [
            'name.required'                     => '請填寫產品名稱',
            'shop_product_category_id.required' => '請選擇產品類別',
            'price.required'                    => '請填寫價格',
            'status.required'                   => '缺少是否暫存資料',
            'count.required'                    => '請填寫起始庫存',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        if ($shop_product_id) {
            // 編輯
            $shop_product_info = ShopProduct::find($shop_product_id);
            if (!$shop_product_info) {
                return response()->json(['status' => false, 'errors' => ['message' => ['找不到產品項目資料']]]);
            }
        } else {
            // 新增
            $shop_product_info = new ShopProduct;
            $shop_product_info->shop_id = $shop_id;
            $shop_product_info->count   = request('count');
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 儲存商家產品資料
        $shop_product_info->shop_product_category_id = request('shop_product_category_id');
        $shop_product_info->name                     = request('name');
        $shop_product_info->price                    = request('price');
        $shop_product_info->basic_price              = request('basic_price');
        $shop_product_info->info                     = request('info');
        $shop_product_info->barcode                  = request('barcode');
        $shop_product_info->notice                   = request('notice');
        $shop_product_info->notice_count             = request('notice_count');
        $shop_product_info->status                   = request('status');

        if (request('photo') && (!$shop_product_info->photo || !preg_match('/' . $shop_product_info->photo . '/i', request('photo')))) {
            $picName = PhotoController::save_base64_photo($shop_info->alias, request('photo'), $shop_product_info->photo);
            $shop_product_info->photo = $picName;
        }

        $shop_product_info->save();

        // 起始庫存
        if (request('count')) {
            $user       = auth()->getUser();
            $shop_staff = ShopStaff::where('user_id', $user->id)->where('shop_id', $shop_info->id)->first();

            $product_log = new ShopProductLog;
            $product_log->shop_id         = $shop_info->id;
            $product_log->shop_product_id = $shop_product_info->id;
            $product_log->category        = 'first';
            $product_log->count           = request('count');
            $product_log->shop_staff_id   = $shop_staff->id;
            $product_log->save(); 
        }

        // 需判斷購買方案，若是基本和進階，基本上就是直接一起新增/編輯集團產品，多分店則只更新商家的產品資料
        if (in_array($shop_info->buy_mode_id, [0, 1, 2, 5, 6])) {
            if ($shop_product_id) {
                $company_product_info = $shop_product_info->company_product_info;
                if (!$company_product_info) {
                    $shop_product_category    = $shop_product_info->category_info;
                    $company_product_category = CompanyProductCategory::where('id', $shop_product_category->company_product_category_id)->first();

                    $company_product_info = new CompanyProduct;
                    $company_product_info->company_id                  = $company_info->id;
                    $company_product_info->company_product_category_id = $company_product_category->id;
                    $company_product_info->count                       = request('count');
                }
            } else {
                $shop_product_category    = $shop_product_info->category_info;
                $company_product_category = CompanyProductCategory::where('id', $shop_product_category->company_product_category_id)->first();

                $company_product_info = new CompanyProduct;
                $company_product_info->company_id                  = $company_info->id;
                $company_product_info->company_product_category_id = $company_product_category->id;
                $company_product_info->count                       = request('count');
            }

            // 一併更新集團的產品資料
            $company_product_info->name         = request('name');
            $company_product_info->price        = request('price');
            $company_product_info->basic_price  = request('basic_price');
            $company_product_info->info         = request('info');
            $company_product_info->barcode      = request('barcode');
            $company_product_info->status       = request('status');
            $company_product_info->notice       = request('notice');
            $company_product_info->notice_count = request('notice_count');
            $company_product_info->photo        = $shop_product_info->photo;
            $company_product_info->save();

            if (!$shop_product_id) {
                $shop_product_info->company_product_id = $company_product_info->id;
                $shop_product_info->save();
            }
        }

        return response()->json(['status' => true, 'data' => $shop_product_info]);
    }

    // 刪除商家產品資料
    public function shop_product_delete($shop_id, $shop_product_id)
    {
        $shop_product_info = ShopProduct::find($shop_product_id);
        if (!$shop_product_info) {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到產品項目資料']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 判斷是否已被優惠券、集點卡、儲值、方案、服務通知、條件通知模組使用
        $check_data = CompanyCoupon::where('company_id', $company_info->id)->where('status', 'published')->get();
        foreach ($check_data as $data) {
            if ($data->secound_type == 1 && $data->commodityId == $shop_product_id) {
                return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->title . '(優惠券)使用。無法刪除產品']]]);
            } else {
                // 檢查使用限制裡得
                $check_coupon_limits = CompanyCouponLimit::where('company_coupon_id', $data->id)->where('commodity_id', $shop_product_id)->where('type', 'product')->first();
                if ($check_coupon_limits) return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->title . '(優惠券)使用。無法刪除產品']]]);
            }
        }
        $check_data = CompanyLoyaltyCard::where('company_id', $company_info->id)->where('status', 'published')->get();
        foreach ($check_data as $data) {
            if ($data->secound_type == 1 && $data->commodityId == $shop_product_id) {
                return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->name . '(集點卡)使用。無法刪除產品']]]);
            } else {
                // 檢查使用限制裡得
                $check_data_limits = CompanyLoyaltyCardLimit::where('company_loyalty_card_id', $data->id)->where('commodity_id', $shop_product_id)->where('type', 'product')->first();
                if ($check_data_limits) return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->name . '(集點卡)使用。無法刪除產品']]]);
            }
        }
        $check_data = ShopTopUp::where('shop_id', $shop_info->id)->where('status', 'published')->get();
        foreach ($check_data as $data) {
            if ($data->roles) {
                $check_limit = $data->roles->where('commodity_id', $shop_product_id)->where('second_type', 1);
                if ($check_limit->count()) return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->name . '(儲值)使用。無法刪除產品']]]);

                foreach ($data->roles as $role) {
                    if ($role->limit_commodity) {
                        $check_limit_commodity = $role->limit_commodity->where('commodity_id', $shop_product_id)->where('type', 'product');
                        if ($check_limit_commodity->count()) return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->name . '(儲值)使用。無法刪除產品']]]);
                    }
                }
            }
        }
        $check_data = ShopProgram::where('shop_id', $shop_info->id)->where('status', 'published')->get();
        foreach ($check_data as $data) {
            foreach ($data->groups as $group) {
                if ($group->group_content) {
                    $content_commoditys = $group->group_content->where('commodity_type', 'product')->pluck('commodity_id')->toArray();
                    if (in_array($shop_product_id, $content_commoditys)) return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->name . '(方案)使用。無法刪除產品']]]);
                }
            }
        }
        $check_data = ShopMembershipCard::where('shop_id', $shop_info->id)->where('status', 'published')->get();
        foreach ($check_data as $data) {
            if ($data->roles) {
                foreach ($data->roles as $role) {
                    if ($role->limit_commodity) {
                        $check_limit_commodity = $role->limit_commodity->where('commodity_id', $shop_product_id)->where('type', 'product');
                        if ($check_limit_commodity->count()) return response()->json(['status' => false, 'errors' => ['message' => ['此產品已被' . $data->name . '(會員卡)使用。無法刪除產品']]]);
                    }
                }
            }
        }

        // 刪除商家產品資料
        $shop_product_info->delete();

        if (in_array($shop_info->buy_mode_id, [0, 1, 2, 5, 6])) {
            // 基本版與進階版則一併刪除集團產品
            CompanyProduct::where('id', $shop_product_info->company_product_id)->delete();

            // 刪除產品的圖片
            if ($shop_product_info->photo) {
                $filePath = env('OLD_OTHER') . '/' . $shop_info->alias . '/' . $shop_product_info->photo;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $shop_product_info->photo = NULL;
            $shop_product_info->save();
        }

        return response()->json(['status' => true]);
    }

    // 更改商家產品上下架狀態
    public function shop_product_status($shop_id, $shop_product_id)
    {
        // 驗證欄位資料
        $rules     = ['status' => 'required'];
        $messages = [
            'status.required' => '缺少上下架資料',
        ];
        $validator = Validator::make(request()->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->getMessageBag()->toArray()]);
        }

        $shop_product = ShopProduct::find($shop_product_id);
        if ($shop_product) {
            $shop_product->status = request('status');
            $shop_product->save();
        } else {
            return response()->json(['status' => false, 'errors' => ['message' => ['找不到產品項目資料']]]);
        }

        return response()->json(['status' => true]);
    }

    // 拿取商家有的可選的產品
    static public function shop_product_select($shop_id)
    {
        // 取得商家產品
        $category_infos = ShopProductCategory::where('shop_id', $shop_id)->orderBy('sequence', 'ASC')->get();
        // 在拿取shop裡有的服務
        $categories = [];
        foreach ($category_infos as $k => $info) {
            $products = [];
            foreach ($info->shop_products->where('status', 'published') as $product) {
                $products[] = [
                    'id'      => $product->id,
                    'name'    => $product->name,
                    'price'   => $product->price,
                ];
            }

            $info->match_products = $products;
            unset($info->shop_products);

            $categories[] = [
                'category_name' => $info->name,
                'products'      => $products,
            ];
        }

        return $categories;
    }
}
