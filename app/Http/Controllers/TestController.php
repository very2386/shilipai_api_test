<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Image;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\BuyMode;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\CustomerLoyaltyCard;
use App\Models\CustomerLoyaltyCardPoint;
use App\Models\CustomerReservation;
use App\Models\CustomerReservationAdvance;
use App\Models\Company;
use App\Models\CompanyServiceCategory;
use App\Models\CompanyCoupon;
use App\Models\CompanyCustomer;
use App\Models\CompanyCouponLimit;
use App\Models\CompanyService;
use App\Models\CompanyStaff;
use App\Models\CompanyTitle;
use App\Models\CompanyLoyaltyCard;
use App\Models\CompanyLoyaltyCardLimit;
use App\Models\MessageLog;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Permission;
use App\Models\PermissionMenu;
use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopServiceCategory;
use App\Models\ShopSet;
use App\Models\ShopBusinessHour;
use App\Models\ShopClose;
use App\Models\ShopPhoto;
use App\Models\ShopService;
use App\Models\ShopServiceAdvance;
use App\Models\ShopServiceStaff;
use App\Models\ShopStaff;
use App\Models\ShopCoupon;
use App\Models\ShopFestivalNotice;
use App\Models\ShopLoyaltyCard;
use App\Models\ShopReservationMessage;
use App\Models\ShopReservationTag;

class TestController extends Controller
{
    //
    public function update_database()
    {
    	// 拿取舊實力派資料
        User::truncate();
    	$insert_users = DB::connection('mysql2')->table('users')->whereIn('companyId',['24571275'])->get();
        
        // 新增
        $user_insert = [];
        foreach( $insert_users as $user ){
        	$user_insert[] = [
        		'name'       => $user->real_name,
        		'phone'      => $user->phone,
        		'password'   => $user->password,
        		'code'       => $user->code,
        		'created_at' => $user->created_at,
        		'updated_at' => $user->updated_at,
        		'deleted_at' => $user->deleted_at,
        	];
        }
        User::insert($user_insert);

        // 更新code資料
        $users = User::withTrashed()->where('code','!=',NULL)->get();
        foreach( $users as $user ){
            $user->code = User::withTrashed()->where('phone',$user->code)->value('id');
            $user->save();
        }

        // Company===================================================================
        Company::truncate();
        Shop::truncate();
        $insert_companys = DB::connection('mysql2')->table('tb_company')->whereIn('id',['24571275'])->get();
        // $update_companys = DB::connection('mysql2')->table('tb_company')->whereIn('phone',$user_phone)->get();

        // 新增
        $insert = [];
        ShopSet::truncate();
        foreach( $insert_companys as $company ){

            if( strlen($company->id) > 8 ) continue;

            $company_info = DB::connection('mysql2')->table('company_infos')->where('companyId',$company->id )->first();

            $new_company = new Company;
            $new_company->companyId = $company_info->companyId;
            switch ($company_info->mode) {
                case 3: // 原實力派管家三年份
                case 1: // 原實力派管家一年份
                    $new_company->buy_mode_id = 2;
                    break;
                case 7: // 原美業官網進階方案（年繳）
                    $new_company->buy_mode_id = 1;
                    break;
                case 8: // 原美業官網進階方案（月繳）
                case 9: // 原美業官網基本方案
                    $new_company->buy_mode_id = 0;
                    break;
            }
            
            $new_company->name        = $company->name;
            $new_company->deadline    = $company_info->end_date;
            $new_company->gift_sms    = $company_info->gift_letter;
            $new_company->buy_sms     = $company_info->buy_letter;
            $new_company->terms       = $company_info->terms;
            $new_company->logo        = $company_info->store_logo ? str_replace('/upload/images/', '', $company_info->store_logo) : NULL;
            $new_company->banner      = $company_info->store_pic ? str_replace('/upload/images/', '', $company_info->store_pic) : NULL;
            $new_company->created_at  = $company_info->created_at;
            $new_company->updated_at  = $company_info->updated_at;
            $new_company->save();

            $shop = new Shop;
            $shop->company_id          = $new_company->id;
            $shop->alias               = $company_info->companyId;
            $shop->name                = $company->name;
            $shop->phone               = $company->phone;
            $shop->address             = $company_info->store_addr;
            $shop->logo                = $company_info->store_logo ? str_replace('/upload/images/', '', $company_info->store_logo) : NULL;
            $shop->banner              = $company_info->store_pic ? str_replace('/upload/images/', '', $company_info->store_pic) : NULL;
            $shop->info                = $company_info->content;
            $shop->line                = $company_info->line_id;
            $shop->facebook_name       = $company_info->facebook_name;
            $shop->facebook_url        = $company_info->facebook_href;
            $shop->ig                  = $company_info->ig_name;
            $shop->web_name            = $company_info->web_name;
            $shop->web_url             = $company_info->web_href;
            $shop->operating_status_id = $company_info->status;
            $shop->created_at          = $company_info->created_at;
            $shop->updated_at          = $company_info->updated_at;
            $shop->save();

            // 新增設定檔
            $new_set = new ShopSet;
            $new_set->shop_id           = $shop->id;
            $new_set->reservation_check = $company_info->reservation_check;
            $new_set->color_select      = 1;
            $new_set->color             = '#9440a1';
            $new_set->show_phone        = $company_info->show_phone;
            $new_set->save();
        }

        // 建立預設預約發送訊息===================================================================
        ShopReservationMessage::truncate();
        // wait待審核check確認/通過預約shop_cancel商家取消/不通過customer_cancel客戶取消change變更預約
        $type = ['wait','check','shop_cancel','customer_cancel','change'];
        $shops = Shop::get();
        foreach( $shops as $shop ){
            foreach( $type as $t ){
                switch($t){
                    case 'wait':
                        $msg = '「"會員名稱"」您好，您預約「"商家名稱"」的「"服務名稱"」已送出等待商家確認中，訂單細節：「"訂單連結"」';
                        break;
                    case 'check':
                        $msg = '您在「"商家名稱"」預約的服務已確認，「"預約日期時間"」期待您的到來，詳情：「"訂單連結"」';
                        break;
                    case 'shop_cancel':
                        $msg = '「"會員名稱"」您好，「"商家名稱"」在您預約的時段無法為您服務，可選擇其他時段，再次預約：「"再次預約連結"」';
                        break;
                    case 'customer_cancel':
                        $msg = '「"會員名稱"」您好，您已取消「"商家名稱"」預約「"預約日期時間"」「"服務名稱"」；再次預約：「"再次預約連結"」';
                        break;
                    case 'change':
                        $msg = '「"會員名稱"」您好，已將「"服務名稱"」的時間變更為「"預約日期時間"」；「"商家名稱"」期待您的到來，訂單細節：「"訂單連結"」';
                        break;
                }

                $message = new ShopReservationMessage;
                $message->shop_id = $shop->id;
                $message->type    = $t;
                $message->content = $msg;
                $message->status  = 'N';
                $message->save();
            }
        }

        // 建立預設預約標籤===================================================================
        ShopReservationTag::truncate();
        $type = [1,2,3,4];
        $shops = Shop::get();
        foreach( $shops as $shop ){
            foreach( $type as $t ){
                $data = new ShopReservationTag;
                $data->shop_id = $shop->id;
                $data->type    = $t;
                $data->save();
            }
        }

        // 建立permission===================================================================
        // $users = User::get();
        Permission::truncate();
        foreach( $insert_users as $user ){
            $company = Company::where('companyId',$user->companyId)->first();
            if( !$company ) continue;
            $shop = Shop::where('company_id',$company->id)->first();
            $user_id = User::where('password',$user->password)->value('id');
            if( !$user_id ){
                // 刪除公司與商家資料
                $company->delete();
                $shop->delete();
                continue;
            }

            switch ($company->buy_mode_id) {
                case 0:
                    $permission = implode(',',PermissionMenu::where('basic',1)->pluck('value')->toArray());
                    break;
                case 1:
                    $permission = implode(',',PermissionMenu::where('plus',1)->pluck('value')->toArray());
                    break;
                case 2:
                    $permission = implode(',',PermissionMenu::where('pro_cs',1)->pluck('value')->toArray());
                    break;
            }

            // 建立公司權限
            $company_permission = Permission::where('user_id',$user_id)->where('company_id',$company->id)->first();
            if(!$company_permission) $company_permission = new Permission;
            $company_permission->user_id     = $user_id;
            $company_permission->company_id  = $company->id;
            $company_permission->buy_mode_id = $company->buy_mode_id;
            $company_permission->permission  = $permission;
            $company_permission->save();

            // 建立分店權限
            $shop_permission = Permission::where('user_id',$user_id)->where('shop_id',$shop->id)->first();
            if(!$shop_permission) $shop_permission = new Permission;
            $shop_permission->user_id     = $user_id;
            $shop_permission->company_id  = $company->id;
            $shop_permission->shop_id     = $shop->id;
            $shop_permission->buy_mode_id = $company->buy_mode_id;
            $shop_permission->permission  = $permission;
            $shop_permission->save();
        }

        // 簡訊記錄===================================================================
        $shops = Shop::get();
        MessageLog::truncate();
        foreach( $shops as $shop ){
            $old_log = DB::connection('mysql2')->table('message_logs')->where('companyId',$shop->alias)->get();
            foreach( $old_log as $o_log ){
                $new_log = new MessageLog;
                $new_log->company_id              = $shop->company_info->id;
                $new_log->shop_id                 = $shop->id;
                $new_log->phone                   = $o_log->phone;
                $new_log->content                 = $o_log->content;
                $new_log->use                     = $o_log->use;
                $new_log->reservation             = $o_log->reservation;
                $new_log->customer_reservation_id = $o_log->event_id ? CustomerReservation::where('old_event_id',$o_log->event_id)->value('id') : NULL;
                $new_log->created_at              = $o_log->created_at;
                $new_log->updated_at              = $o_log->updated_at;
                $new_log->save();
            }
        }
        
        // 營業資料===================================================================
        $weeks = [ '星期一' , '星期二' , '星期三' , '星期四' , '星期五' , '星期六' , '星期日' ];
        $shops = Shop::get();
        ShopBusinessHour::truncate();
        foreach( $shops as $shop ){
            $old_business = DB::connection('mysql2')->table('tb_businesshours')->where('companyId',$shop->alias)->where('deleteTime',NULL)->orderBy('startTime')->get();
            if( !$old_business ){
                foreach( $weeks as $k => $week ){
                    if( $week != '星期日' ){
                        $new_business = new ShopBusinessHour;
                        $new_business->shop_id = $shop->id;
                        $new_business->type    = false;
                        $new_business->week    = $k+1;
                        $new_business->start   = NULL;
                        $new_business->end     = NULL;
                        $new_business->save();
                    }else{
                        $new_business = new ShopBusinessHour;
                        $new_business->shop_id = $shop->id;
                        $new_business->type    = false;
                        $new_business->start   = NULL;
                        $new_business->end     = NULL;
                        $new_business->week    = $k+1;
                        $new_business->save();
                    }
                }
            }else{
                foreach( $weeks as $k => $week ){
                    $check_week = 0;
                    foreach( $old_business as $old ){
                        if( $week == $old->selectedDay ){
                            $new_business = new ShopBusinessHour;
                            $new_business->shop_id = $shop->id;
                            $new_business->type    = true;
                            $new_business->week    = $k+1;
                            $new_business->start   = $old->startTime;
                            $new_business->end     = $old->endTime;
                            $new_business->save();
                            $check_week = 1;
                        }
                    }
                    if( $check_week == 0 ){
                        $new_business = new ShopBusinessHour;
                        $new_business->shop_id = $shop->id;
                        $new_business->type    = false;
                        $new_business->start   = NULL;
                        $new_business->end     = NULL;
                        $new_business->week    = $k+1;
                        $new_business->save();
                    }
                } 
            }
        } 

        ShopClose::truncate();
        foreach( $shops as $shop ){
            $old_close = DB::connection('mysql2')->table('tb_closed')->where('companyId',$shop->alias)->where('deleteTime',NULL)->first();
            if( $old_close ){
                if( $old_close->weekType != '' && $old_close->monthType != '每週' ){
                    $weekType = str_replace('星期一', 'Mon', $old_close->weekType);
                    $weekType = str_replace('星期二', 'Tue', $weekType);
                    $weekType = str_replace('星期三', 'Web', $weekType);
                    $weekType = str_replace('星期四', 'Thu', $weekType);
                    $weekType = str_replace('星期五', 'Fri', $weekType);
                    $weekType = str_replace('星期六', 'Sat', $weekType);
                    $weekType = str_replace('星期日', 'Sun', $weekType);


                    $type = str_replace('每個月第1週', 1 , $old_close->monthType);
                    $type = str_replace('每個月第2週', 2 , $type);
                    $type = str_replace('每個月第3週', 3 , $type);
                    $type = str_replace('每個月第4週', 4 , $type);

                    $new_close = new ShopClose;
                    $new_close->shop_id = $shop->id;
                    $new_close->type    = (int)$type;
                    $new_close->week    = $weekType;
                    $new_close->save();
                }else{
                    $new_close = new ShopClose;
                    $new_close->shop_id = $shop->id;
                    $new_close->type    = 6;
                    $new_close->week    = NULL;
                    $new_close->save();
                }
            }
        }

        // 環境照片===================================================================
        $shops = Shop::get();
        ShopPhoto::truncate();
        foreach( $shops as $shop ){
            $old_photos = DB::connection('mysql2')->table('company_photos')->where('companyId',$shop->alias)->get();
            foreach( $old_photos as $photo ){
                $new_photo = new ShopPhoto;
                $new_photo->shop_id = $shop->id;
                $new_photo->photo   = $photo->path ? $photo->path.'.jpg' : NULL;
                $new_photo->save();
            }
        }

        // 服務項目/加值項目===========================================================================================
        $companies = Company::get();
        CompanyServiceCategory::truncate();
        CompanyService::truncate();
        ShopService::truncate();
        ShopServiceCategory::truncate();
        foreach( $companies as $company){
            $shop = Shop::where('company_id',$company->id)->first();
            $old_set = DB::connection('mysql2')
                                            ->table('company_infos')
                                            ->where('companyId',$company->companyId)->first();

            // 分類
            $old_service_category = DB::connection('mysql2')
                                            ->table('tb_productcategories')
                                            ->where('companyId',$company->companyId)
                                            ->where('classification',2)
                                            ->where('deleteTime',NULL)
                                            ->get();
            foreach( $old_service_category as $old_category ){
                $new_category = new CompanyServiceCategory;
                $new_category->company_id = $company->id;
                $new_category->type       = 'service';
                $new_category->name       = $old_category->name;
                $new_category->info       = $old_category->info;
                $new_category->photo      = $old_category->imageUrl ? $old_category->imageUrl.'.jpg' : NULL;
                $new_category->sequence   = $old_category->sequence;
                $new_category->save();

                $new_shop_category = new ShopServiceCategory;
                $new_shop_category->company_category_id = $new_category->id;
                $new_shop_category->shop_id             = $shop->id;
                $new_shop_category->type                = 'service';
                $new_shop_category->name                = $old_category->name;
                $new_shop_category->info                = $old_category->info;
                $new_shop_category->photo               = $old_category->imageUrl ? $old_category->imageUrl.'.jpg' : NULL;
                $new_shop_category->sequence            = $old_category->sequence;
                $new_shop_category->save();

                // 此分類下的服務
                $old_services = DB::connection('mysql2')
                                            ->table('tb_commodity')
                                            ->where('companyId',$company->companyId)
                                            ->where('pcId',$old_category->id)
                                            ->where('deleteTime',NULL)
                                            ->get();

                foreach( $old_services as $o_service ){
                    $new_service = new CompanyService;
                    $new_service->company_id          = $company->id;
                    $new_service->company_category_id = $new_category->id;
                    $new_service->type                = 'service';
                    $new_service->name                = $o_service->name;
                    $new_service->info                = $o_service->info;
                    $new_service->photo               = $o_service->imageUrl ? $o_service->imageUrl.'.jpg' : NULL;
                    $new_service->sequence            = $o_service->sequence;
                    $new_service->price               = $o_service->price;
                    $new_service->basic_price         = $o_service->lprice;
                    
                    if( $o_service->up == 'Y' ){
                        $new_service->show_type = 3;
                        $new_service->show_text = $o_service->price;
                    }elseif( $o_service->text_price ){
                        $new_service->show_type = 4;
                        $new_service->show_text = $o_service->text_price;
                    }elseif( $old_set->show_price == 1 ){
                        $new_service->show_type = 1;
                    }else{
                        $new_service->show_type = 2;
                    }

                    $new_service->show_time    = $old_set->show_time;
                    $new_service->service_time = $o_service->needTime;
                    $new_service->status       = $o_service->launched == 1 ? 'published' : 'pending';
                    $new_service->save();

                    $new_shop_service = new ShopService;
                    $new_shop_service->shop_id                  = $shop->id;
                    $new_shop_service->shop_service_category_id = $new_shop_category->id;
                    $new_shop_service->company_service_id       = $new_service->id;
                    $new_shop_service->type                     = 'service';
                    $new_shop_service->name                     = $o_service->name;
                    $new_shop_service->info                     = $o_service->info;
                    $new_shop_service->photo                    = $o_service->imageUrl ? $o_service->imageUrl.'.jpg' : NULL;
                    $new_shop_service->sequence                 = $o_service->sequence;
                    $new_shop_service->price                    = $o_service->price;
                    $new_shop_service->basic_price              = $o_service->lprice;
                    
                    if( $o_service->up == 'Y' ){
                        $new_shop_service->show_type = 3;
                        $new_shop_service->show_text = $o_service->price;
                    }elseif( $o_service->text_price ){
                        $new_shop_service->show_type = 4;
                        $new_shop_service->show_text = $o_service->text_price;
                    }elseif( $old_set->show_price == 1 ){
                        $new_shop_service->show_type = 1;
                    }else{
                        $new_shop_service->show_type = 2;
                    }

                    $new_shop_service->show_time    = $old_set->show_time;
                    $new_shop_service->service_time = $o_service->needTime;
                    $new_shop_service->status       = $o_service->launched == 1 ? 'published' : 'pending';
                    // $new_shop_service->edit         = 1;
                    $new_shop_service->old_id       = $o_service->id;
                    $new_shop_service->save();

                }
            }

            // 加值項目
            $old_add_items = DB::connection('mysql2')
                                            ->table('tb_commodity')
                                            ->where('companyId',$company->companyId)
                                            ->where('classId',5)
                                            ->where('deleteTime',NULL)
                                            ->get();
            foreach( $old_add_items as $item ){
                $new_advance = new CompanyService;
                $new_advance->company_id  = $company->id;
                $new_advance->type        = 'advance';
                $new_advance->name        = $item->name;
                $new_advance->info        = $item->info;
                $new_advance->photo       = $item->imageUrl ? $item->imageUrl.'.jpg' : NULL;
                $new_advance->sequence    = $item->sequence;
                $new_advance->price       = $item->price;
                $new_advance->basic_price = $item->lprice;
                
                if( $item->up == 'Y' ){
                    $new_advance->show_type = 3;
                    $new_advance->show_text = $item->price;
                }elseif( $item->text_price ){
                    $new_advance->show_type = 4;
                    $new_advance->show_text = $item->text_price;
                }elseif( $old_set->show_price == 1 ){
                    $new_advance->show_type = 1;
                }else{
                    $new_advance->show_type = 2;
                }

                $new_advance->show_time    = $old_set->show_time;
                $new_advance->service_time = $item->needTime;
                $new_advance->status       = $item->launched == 1 ? 'published' : 'pending';
                $new_advance->save();

                $new_shop_advance = new ShopService;
                $new_shop_advance->shop_id            = $shop->id;
                $new_shop_advance->company_service_id = $new_advance->id;
                $new_shop_advance->type               = 'advance';
                $new_shop_advance->name               = $item->name;
                $new_shop_advance->info               = $item->info;
                $new_shop_advance->photo              = $item->imageUrl ? $item->imageUrl.'.jpg' : NULL;
                $new_shop_advance->sequence           = $item->sequence;
                $new_shop_advance->price              = $item->price;
                $new_shop_advance->basic_price        = $item->lprice;
                $new_shop_advance->old_id             = $item->id;

                if( $item->up == 'Y' ){
                    $new_shop_advance->show_type = 3;
                    $new_shop_advance->show_text = $item->price;
                }elseif( $item->text_price ){
                    $new_shop_advance->show_type = 4;
                    $new_shop_advance->show_text = $item->text_price;
                }elseif( $old_set->show_price == 1 ){
                    $new_shop_advance->show_type = 1;
                }else{
                    $new_shop_advance->show_type = 2;
                }

                $new_shop_advance->show_time    = $old_set->show_time;
                $new_shop_advance->service_time = $item->needTime;
                $new_shop_advance->status       = $item->launched == 1 ? 'published' : 'pending';
                // $new_shop_advance->edit         = 1;
                $new_shop_advance->save();
            }
        }

        // 服務與加值項目關連
        ShopServiceAdvance::truncate();
        $old_datas = DB::connection('mysql2')->table('match_services')->get();
        foreach( $old_datas as $o_data ){
            if( ShopService::where('old_id',$o_data->serviceId)->value('id') ) {
                $new_shop_service_advance = new ShopServiceAdvance;
                $new_shop_service_advance->shop_service_id = ShopService::where('old_id',$o_data->serviceId)->value('id');
                $new_shop_service_advance->shop_advance_id = ShopService::where('old_id',$o_data->addId)->value('id');
                $new_shop_service_advance->created_at      = $o_data->created_at;
                $new_shop_service_advance->updated_at      = $o_data->updated_at;
                $new_shop_service_advance->save();
            }
        }
                                            
        // 員工資料===================================================================
        $companies = Company::get();
        CompanyStaff::truncate();
        ShopStaff::truncate();
        CompanyTitle::truncate();
        foreach( $companies as $company ){
            $old_staffs = DB::connection('mysql2')
                                            ->table('tb_staff')
                                            ->where('companyId',$company->companyId)
                                            ->where('deleteTime',NULL)
                                            ->get();
            foreach( $old_staffs as $o_staff ){
                $new_company_staff = new CompanyStaff;
                $new_company_staff->company_id          = $company->id;
                $new_company_staff->name                = $o_staff->name;
                $new_company_staff->info                = $o_staff->info;
                $new_company_staff->photo               = $o_staff->photoUrl ? $o_staff->photoUrl.'.jpg' : NULL;
                $new_company_staff->line_id             = $o_staff->lineId;
                $new_company_staff->calendar_token      = $o_staff->calendar_token;
                $new_company_staff->phone               = $o_staff->phone;
                $new_company_staff->email               = $o_staff->email;
                $new_company_staff->calendar_color      = $o_staff->color ?: '#AC8CD5';
                $new_company_staff->calendar_color_type = $o_staff->color ? 2 : 1;

                // 職稱
                if( $o_staff->position ){
                    // 建立集團用的職稱
                    $title = CompanyTitle::where('company_id',$company->id)->where('name',$o_staff->position)->first();
                    if( !$title ){
                        $title = new CompanyTitle;
                        $title->company_id = $company->id;
                        $title->name       = $o_staff->position;
                        $title->save();
                    }
                    $new_company_staff->company_title_id_a = $title->id;
                }

                // 利用電話找出user
                if( $o_staff->phone && $o_staff->master == 0 ){
                    // 此員工身分是老闆，判斷是否需建立帳號
                    $user = User::where('phone',$o_staff->phone)->first();
                    if( $user ){
                        $new_company_staff->user_id = $user->id;

                        $user->photo = $o_staff->photoUrl ? $o_staff->photoUrl.'.jpg' : NULL;
                        $user->save();
                    }else{
                        $user = new User;
                        $user->name     = $o_staff->name;
                        $user->phone    = $o_staff->phone;
                        $user->photo    = $o_staff->photoUrl ? $o_staff->photoUrl.'.jpg' : NULL;
                        $user->password = password_hash('123456', PASSWORD_DEFAULT);
                        $user->save();

                        // company_staff資料加入user_id
                        $new_company_staff->user_id = $user->id;
                        $new_company_staff->save();

                        switch ($company->buy_mode_id) {
                            case 0:
                                $permission = implode(',',PermissionMenu::where('basic',1)->pluck('value')->toArray());
                                break;
                            case 1:
                                $permission = implode(',',PermissionMenu::where('plus',1)->pluck('value')->toArray());
                                break;
                            case 2:
                                $permission = implode(',',PermissionMenu::where('pro_cs',1)->pluck('value')->toArray());
                                break;
                        }

                        // 建立公司權限
                        $company_permission = new Permission;
                        $company_permission->user_id     = $user->id;
                        $company_permission->company_id  = $company->id;
                        $company_permission->buy_mode_id = $company->buy_mode_id;
                        $company_permission->permission  = $permission;
                        $company_permission->save();

                        // 建立分店權限
                        $shop_permission = Permission::where('user_id',$user_id)->where('shop_id',$shop->id)->first();
                        if(!$shop_permission) $shop_permission = new Permission;
                        $shop_permission->user_id     = $user_id;
                        $shop_permission->company_id  = $company->id;
                        $shop_permission->shop_id     = $shop->id;
                        $shop_permission->buy_mode_id = $company->buy_mode_id;
                        $shop_permission->permission  = $permission;
                        $shop_permission->save();
                    } 

                    $new_company_staff->save();
                    $new_shop_staff = new ShopStaff;
                    $new_shop_staff->shop_id            = $company->shop_infos->where('alias',$company->companyId)->first()->id;
                    $new_shop_staff->company_staff_id   = $new_company_staff->id;
                    $new_shop_staff->company_title_id_a = $new_company_staff->company_title_id_a;
                    $new_shop_staff->old_id             = $o_staff->id;
                    $new_shop_staff->master             = Permission::where('user_id',$user->id)->where('company_id',$company->id)->where('shop_id',NULL)->first() ? 0 : 1;
                    $new_shop_staff->save();

                    // 建立員工權限
                    $permission = new Permission;
                    $permission->user_id       = $user->id;
                    $permission->company_id    = $company->id;
                    $permission->shop_id       = $company->shop_infos->where('alias',$company->companyId)->first()->id;
                    $permission->shop_staff_id = $new_shop_staff->id;
                    $permission->buy_mode_id   = $company->buy_mode_id;
                    $permission->permission    = implode(',',PermissionMenu::where('value','like','staff_%')->pluck('value')->toArray());
                    $permission->save();  
                }else{
                    // 員工建立帳號
                    if( $o_staff->phone ){
                        $user = new User;
                        $user->name     = $o_staff->name;
                        $user->phone    = $o_staff->phone;
                        $user->photo    = $o_staff->photoUrl ? $o_staff->photoUrl.'.jpg' : NULL;
                        $user->password = password_hash('123456', PASSWORD_DEFAULT);
                        $user->save();

                        // company_staff資料加入user_id
                        $new_company_staff->user_id = $user->id;
                        $new_company_staff->save();

                        $new_shop_staff = new ShopStaff;
                        $new_shop_staff->shop_id            = $company->shop_infos->where('alias',$company->companyId)->first()->id;
                        $new_shop_staff->company_staff_id   = $new_company_staff->id;
                        $new_shop_staff->company_title_id_a = $new_company_staff->company_title_id_a;
                        $new_shop_staff->old_id             = $o_staff->id;
                        $new_shop_staff->save();

                        // 建立員工權限
                        $permission = new Permission;
                        $permission->user_id       = $user->id;
                        $permission->company_id    = $company->id;
                        $permission->shop_id       = $company->shop_infos->where('alias',$company->companyId)->first()->id;
                        $permission->shop_staff_id = $new_shop_staff->id;
                        $permission->buy_mode_id   = $company->buy_mode_id;
                        $permission->permission    = implode(',',PermissionMenu::where('value','like','staff_%')->pluck('value')->toArray());
                        $permission->save();  
                    }
                }

            }
        }

        // 服務與員工關連
        ShopServiceStaff::truncate();
        $old_datas = DB::connection('mysql2')->table('staff_services')->get();
        foreach( $old_datas as $o_data ){
            if( ShopService::where('old_id',$o_data->commodity_id)->value('id') && ShopStaff::where('old_id',$o_data->staff_id)->value('id') ) {
                $id = ShopStaff::where('old_id',$o_data->staff_id)->value('id');
                $new_shop_service_advance = new ShopServiceStaff;
                $new_shop_service_advance->shop_service_id = ShopService::where('old_id',$o_data->commodity_id)->value('id');
                $new_shop_service_advance->shop_staff_id   = ShopStaff::where('old_id',$o_data->staff_id)->value('id');
                $new_shop_service_advance->created_at      = $o_data->created_at;
                $new_shop_service_advance->updated_at      = $o_data->updated_at;
                $new_shop_service_advance->save();
            }
        }

        // 新增員工營業時間
        $shops = Shop::get();
        $insert_open  = [];
        $insert_close = [];
        foreach( $shops as $shop ){
            foreach( $shop->shop_staffs as $staff ){
                foreach( $shop->shop_business_hours as $open ){
                    $insert_open[] = [
                        'shop_id'       => $shop->id,
                        'shop_staff_id' => $staff->id,
                        'type'          => $open->type,
                        'week'          => $open->week,
                        'start'         => $open->start,
                        'end'           => $open->end,
                    ];
                }

                if( $shop->shop_close ){
                    $insert_close[] = [
                        'shop_id'       => $shop->id,
                        'shop_staff_id' => $staff->id,
                        'type'          => $shop->shop_close->type,
                        'week'          => $shop->shop_close->week,
                    ]; 
                }
            }
        }
        ShopBusinessHour::insert($insert_open);
        ShopClose::insert($insert_close);

        // 付款記錄===================================================================
        $companies = Company::get();
        Order::truncate();
        foreach( $companies as $company ){
            $old_orders = DB::connection('mysql2')
                                            ->table('orders')
                                            ->where('store_id',$company->companyId)
                                            ->get();
            foreach( $old_orders as $old ){
                $new_order = new Order;
                $new_order->oid         = $old->oid;
                $new_order->user_id     = Permission::where('company_id',$company->id)->value('user_id');
                $new_order->company_id  = $company->id;
                switch ($old->buy_mode_id) {
                    case 3: // 原實力派管家三年份
                    case 1: // 原實力派管家一年份
                        $new_order->buy_mode_id = 2;
                        $new_order->note = BuyMode::where('id',$new_order->buy_mode_id)->value('title');
                        break;
                    case 7: // 原美業官網進階方案（年繳）
                        $new_order->buy_mode_id = 1;
                        $new_order->note = BuyMode::where('id',$new_order->buy_mode_id)->value('title');
                        break;
                    case 8: // 原美業官網進階方案（月繳）
                    case 9: // 原美業官網基本方案
                        $new_order->buy_mode_id = 0;
                        $new_order->note = BuyMode::where('id',$new_order->buy_mode_id)->value('title');
                        break;
                    default:
                        if( $old->buy_mode_id == '' ) $new_order->buy_mode_id = 50;
                        $new_order->note = $old->note;
                        break;
                }

                $new_order->member_addresses_id = $old->member_addresses_id;
                $new_order->code                = User::withTrashed()->where('phone',$old->recommend)->value('id');
                $new_order->discount_id         = $old->discount_id;
                $new_order->price               = $old->price;
                $new_order->order_note          = $old->order_note;
                $new_order->pay_return          = $old->pay_return;
                $new_order->message             = $old->message;
                $new_order->pay_status          = $old->pay_status;
                $new_order->pay_date            = $old->pay_date;
                $new_order->pay_type            = $old->pay_type;
                $new_order->created_at          = $old->created_at;
                $new_order->updated_at          = $old->updated_at;
                $new_order->save();
            }

            $delete_order = DB::connection('mysql2')
                                            ->table('orders')
                                            ->where('store_id',$company->companyId)
                                            ->where('deleted_at','!=',NULL)
                                            ->get();
            foreach( $delete_order as $old ){
                $new_order = new Order;
                $new_order->oid         = $old->oid;
                $new_order->user_id     = Permission::where('company_id',$company->id)->value('user_id');
                $new_order->company_id  = $company->id;
                switch ($old->buy_mode_id) {
                    case 3: // 原實力派管家三年份
                    case 1: // 原實力派管家一年份
                        $new_order->buy_mode_id = 2;
                        $new_order->note = BuyMode::where('id',$new_order->buy_mode_id)->value('title');
                        break;
                    case 7: // 原美業官網進階方案（年繳）
                        $new_order->buy_mode_id = 1;
                        $new_order->note = BuyMode::where('id',$new_order->buy_mode_id)->value('title');
                        break;
                    case 8: // 原美業官網進階方案（月繳）
                    case 9: // 原美業官網基本方案
                        $new_order->buy_mode_id = 0;
                        $new_order->note = BuyMode::where('id',$new_order->buy_mode_id)->value('title');
                        break;
                    default:
                        $new_order->note = $old->note;
                        break;
                }

                $new_order->member_addresses_id = $old->member_addresses_id;
                $new_order->code                = User::withTrashed()->where('phone',$old->recommend)->value('id');
                $new_order->discount_id         = $old->discount_id;
                $new_order->price               = $old->price;
                
                $new_order->order_note          = $old->order_note;
                $new_order->pay_return          = $old->pay_return;
                $new_order->message             = $old->message;
                $new_order->pay_status          = $old->pay_status;
                $new_order->pay_date            = $old->pay_date;
                $new_order->pay_type            = $old->pay_type;
                $new_order->created_at          = $old->created_at;
                $new_order->updated_at          = $old->updated_at;
                $new_order->save();
            }

        }

        // 優惠券=======================================================================================
        CompanyCoupon::truncate();
        CompanyCouponLimit::truncate();
        ShopCoupon::truncate();
        $companies = Company::get();
        foreach( $companies as $company ){
            $old_coupons = DB::connection('mysql2')
                                            ->table('coupons')
                                            ->where('companyId',$company->companyId)
                                            ->get();
            $shop = $company->shop_infos->first();
            foreach( $old_coupons as $o_coupon ){
                $new_company_coupon = new CompanyCoupon;
                $new_company_coupon->company_id      = $company->id;
                $new_company_coupon->type            = $o_coupon->type;
                $new_company_coupon->title           = $o_coupon->title;
                $new_company_coupon->description     = $o_coupon->description;
                $new_company_coupon->start_date      = $o_coupon->start_date;
                $new_company_coupon->end_date        = $o_coupon->end_date;
                $new_company_coupon->consumption     = $o_coupon->consumption;
                $new_company_coupon->discount        = $o_coupon->discount;
                $new_company_coupon->price           = $o_coupon->price;
                $new_company_coupon->count_type      = $o_coupon->count_type;
                $new_company_coupon->count           = $o_coupon->count;

                if( $o_coupon->type == 'gift' ){
                    $new_company_coupon->second_type = $o_coupon->second_type;
                }elseif( $o_coupon->type == 'free' ){
                    $new_company_coupon->second_type = $o_coupon->second_type == 1 ? 3 : 4;
                }elseif( $o_coupon->type == 'cash' ){
                    $new_company_coupon->second_type = $o_coupon->second_type == 1 ? 5 : 6;
                }

                if( $o_coupon->type == 'gift' ){
                    $new_company_coupon->commodityId = $o_coupon->commodityId ? '' : NULL;
                }else{
                    $new_company_coupon->commodityId = $o_coupon->commodityId ? ShopService::where('old_id',$o_coupon->commodityId)->value('id') : NULL;
                }
                $new_company_coupon->self_definition = $o_coupon->self_definition;
                $new_company_coupon->photo_type      = $o_coupon->photo_type;
                $new_company_coupon->photo           = $o_coupon->photo ? $o_coupon->photo.'.jpg' : NULL;
                $new_company_coupon->use_type        = $o_coupon->use_type;
                $new_company_coupon->get_level       = $o_coupon->get_level;
                $new_company_coupon->customer_level  = $o_coupon->customer_level;
                $new_company_coupon->show_type       = $o_coupon->show_type;
                $new_company_coupon->limit           = $o_coupon->limit;
                $new_company_coupon->content         = $o_coupon->content;
                $new_company_coupon->status          = $o_coupon->status;
                $new_company_coupon->view            = $o_coupon->view;
                $new_company_coupon->created_at      = $o_coupon->created_at;
                $new_company_coupon->updated_at      = $o_coupon->updated_at;
                $new_company_coupon->deleted_at      = $o_coupon->deleted_at;
                $new_company_coupon->save();


                $new_coupon = new ShopCoupon;
                $new_coupon->shop_id           = $shop->id;
                $new_coupon->company_coupon_id = $new_company_coupon->id;
                $new_coupon->view              = $o_coupon->view;
                $new_coupon->status            = $o_coupon->status;
                $new_coupon->created_at        = $o_coupon->created_at;
                $new_coupon->updated_at        = $o_coupon->updated_at;
                $new_coupon->deleted_at        = $o_coupon->deleted_at;
                $new_coupon->old_id            = $o_coupon->id;
                $new_coupon->save();

                $old_limits = DB::connection('mysql2')
                                            ->table('coupon_limits')
                                            ->where('coupon_id',$o_coupon->id)
                                            ->get();
                foreach( $old_limits as $o_limit ){
                    $new_company_limit = new CompanyCouponLimit;
                    $new_company_limit->company_id        = $company->id;
                    $new_company_limit->company_coupon_id = $new_company_coupon->id;
                    

                    $old_commodity = DB::connection('mysql2')
                                            ->table('tb_commodity')
                                            ->where('id',$o_limit->commodityId)
                                            ->first();
                    $new_company_limit->type = $old_commodity->classId == 1 ? 'product' : 'service'; 
                    if( $old_commodity->classId == 1 ){
                        // 產品
                        $new_company_limit->commodity_id = NULL; 
                    }else{
                        // 服務
                        $new_company_limit->commodity_id = ShopService::where('old_id',$old_commodity->id)->value('id');
                    }
                    $new_company_limit->created_at = $new_coupon->created_at;
                    $new_company_limit->updated_at = $new_coupon->updated_at;
                    $new_company_limit->save();

                }
            }
        }

        // 集點卡=======================================================================================
        CompanyLoyaltyCard::truncate();
        CompanyLoyaltyCardLimit::truncate();
        ShopLoyaltyCard::truncate();
        $companies = Company::get();
        foreach( $companies as $company ){
            $old_cards = DB::connection('mysql2')
                                            ->table('reward_cards')
                                            ->where('companyId',$company->companyId)
                                            ->get();

            $shop = $company->shop_infos->first();
            foreach( $old_cards as $o_card ){

                $new_company_card = new CompanyLoyaltyCard;
                $new_company_card->company_id           = $company->id;
                $new_company_card->name                 = $o_card->name;
                $new_company_card->condition_type       = $o_card->condition_type;
                $new_company_card->condition            = $o_card->condition;
                $new_company_card->full_point           = $o_card->full_point;
                $new_company_card->first_point          = $o_card->first_point;
                $new_company_card->deadline_type        = $o_card->deadline_type;
                $new_company_card->year                 = $o_card->year;
                $new_company_card->month                = $o_card->month;
                $new_company_card->start_date           = $o_card->start_date;
                $new_company_card->end_date             = $o_card->end_date;
                $new_company_card->content              = $o_card->content;
                $new_company_card->background_type      = $o_card->background_type;
                $new_company_card->background_color     = $o_card->background_color;
                $new_company_card->background_img       = $o_card->background_img ? $o_card->background_img.'.jpg' : NULL;
                $new_company_card->watermark_type       = $o_card->watermark_type;
                $new_company_card->watermark_img        = $o_card->watermark_img ? $o_card->watermark_img.'.jpg' : NULL;
                $new_company_card->type                 = $o_card->type;
                // $new_company_card->second_type          = $o_card->second_type;

                if( $o_card->type == 'gift' ){
                    $new_company_card->second_type = $o_card->second_type;
                }elseif( $o_card->type == 'free' ){
                    $new_company_card->second_type = $o_card->second_type == 1 ? 3 : 4;
                }else{
                    $new_company_card->second_type = $o_card->second_type == 3 ? 5 : 6;
                }

                if( $o_card->type == 'gift' ){
                    $new_company_card->commodityId = $o_card->commodityId ? '' : NULL;
                }else{
                    $new_company_card->commodityId = $o_card->commodityId ? ShopService::where('old_id',$o_card->commodityId)->value('id') : NULL;
                }
                $new_company_card->self_definition      = $o_card->self_definition;
                $new_company_card->price                = $o_card->price;
                $new_company_card->consumption          = $o_card->consumption;
                $new_company_card->limit                = $o_card->limit;
                $new_company_card->get_limit            = $o_card->get_limit;
                $new_company_card->get_limit_minute     = $o_card->get_limit_minute;
                $new_company_card->discount_limit_type  = $o_card->discount_limit_type;
                $new_company_card->discount_limit_month = $o_card->discount_limit_month;
                $new_company_card->notice_day           = $o_card->notice_day;
                $new_company_card->status               = $o_card->status;
                $new_company_card->view                 = $o_card->view;
                $new_company_card->created_at           = $o_card->created_at;
                $new_company_card->updated_at           = $o_card->updated_at;
                $new_company_card->deleted_at           = $o_card->deleted_at;
                $new_company_card->save();

                $new_card = new ShopLoyaltyCard;
                $new_card->shop_id                 = $shop->id;
                $new_card->company_loyalty_card_id = $new_company_card->id;
                $new_card->view                    = $o_card->view;
                $new_card->status                  = $o_card->status;
                $new_card->created_at              = $o_card->created_at;
                $new_card->updated_at              = $o_card->updated_at;
                $new_card->deleted_at              = $o_card->deleted_at;
                $new_card->old_id                  = $o_card->id;
                $new_card->save();

                $old_limits = DB::connection('mysql2')
                                            ->table('reward_limits')
                                            ->where('reward_id',$o_card->id)
                                            ->get();
                foreach( $old_limits as $o_limit ){
                    $new_company_limit = new CompanyLoyaltyCardLimit;
                    $new_company_limit->company_id              = $company->id;
                    $new_company_limit->company_loyalty_card_id = $new_company_card->id;
                    

                    $old_commodity = DB::connection('mysql2')
                                            ->table('tb_commodity')
                                            ->where('id',$o_limit->commodityId)
                                            ->first();
                    $new_company_limit->type = $old_commodity->classId == 1 ? 'product' : 'service'; 
                    if( $old_commodity->classId == 1 ){
                        // 產品
                        $new_company_limit->commodity_id = NULL;
                    }else{
                        // 服務
                        $new_company_limit->commodity_id = ShopService::where('old_id',$old_commodity->id)->value('id');
                    }
                    $new_company_limit->created_at = $new_company_card->created_at;
                    $new_company_limit->updated_at = $new_company_card->updated_at;
                    $new_company_limit->save();
                }

            }
        }

        // 會員資料========================================================================================================================
        Customer::truncate();
        CompanyCustomer::truncate();
        ShopCustomer::truncate();

        $old_customers = DB::connection('mysql2')->table('shilipai_customers')->where('deleted_at',NULL)->get();
        foreach( $old_customers as $o_customer ){
            $new_customer = new Customer;
            $new_customer->realname      = $o_customer->name;
            $new_customer->phone         = $o_customer->phone;
            $new_customer->email         = $o_customer->email;
            $new_customer->sex           = $o_customer->sex == 0 ? 'M' : ($o_customer->sex == 1 ? 'F' : 'C') ;
            $new_customer->birthday      = $o_customer->birthday;
            $new_customer->facebook_id   = $o_customer->facebook_id;
            $new_customer->facebook_name = $o_customer->facebook_name;
            $new_customer->google_id     = $o_customer->google_id;
            $new_customer->google_name   = $o_customer->google_name;
            $new_customer->line_id       = $o_customer->line_id;
            $new_customer->line_name     = $o_customer->line_name;
            $new_customer->login_date    = $o_customer->login_date;
            $new_customer->photo         = $o_customer->photo ? (preg_match('/http/i', $o_customer->photo ) ? $o_customer->photo : str_replace('/upload/images/','',$o_customer->photo) ) : NULL;
            $new_customer->banner        = $o_customer->banner ? (preg_match('/http/i', $o_customer->banner ) ? $o_customer->banner : str_replace('/upload/images/','',$o_customer->banner) ) : NULL;
            $new_customer->created_at    = $o_customer->created_at;
            $new_customer->updated_at    = $o_customer->updated_at;
            $new_customer->old_id        = $o_customer->id;
            $new_customer->save();

            // 找出在舊有company下的顧客
            $old_company_customers = DB::connection('mysql2')->table('tb_customer')->where('shilipai_customer_id',$o_customer->id)->where('deleteTime',NULL)->get();
            foreach( $old_company_customers as $oc_customer ){
                if( Company::where('companyId',$oc_customer->companyId)->value('id') == '' ) continue;

                $new_company_customer = new CompanyCustomer;
                $new_company_customer->customer_id = $new_customer->id;
                $new_company_customer->company_id  = Company::where('companyId',$oc_customer->companyId)->value('id');
                $new_company_customer->created_at  = $oc_customer->joinTime;
                $new_company_customer->updated_at  = $oc_customer->lastUpdate;
                $new_company_customer->save();

                $new_shop_customer = new ShopCustomer;
                $new_shop_customer->customer_id = $new_customer->id;
                $new_shop_customer->company_id  = $new_company_customer->company_id;
                $new_shop_customer->shop_id     = Shop::where('company_id',$new_company_customer->company_id)->value('id');
                $new_shop_customer->created_at  = $oc_customer->joinTime;
                $new_shop_customer->updated_at  = $oc_customer->lastUpdate;
                $new_shop_customer->save();
            }
        }

        $old_company_customers = DB::connection('mysql2')->table('tb_customer')->where('shilipai_customer_id',NULL)->where('deleteTime',NULL)->get();
        foreach( $old_company_customers as $o_customer ){
            if( $o_customer->email == '' && $o_customer->name == '' && $o_customer->phone == '' ) continue;

            if( !Company::where('companyId',$o_customer->companyId)->value('id') ) continue;

            $new_customer = new Customer;
            $new_customer->login_date = $o_customer->joinTime;
            $new_customer->sex        = $o_customer->sex == 0 ? 'M' : ($o_customer->sex == 1 ? 'F' : 'C') ;
            $new_customer->realname   = preg_match('/@/i', $o_customer->name) ? $o_customer->email : $o_customer->name;
            $new_customer->email      = preg_match('/@/i', $o_customer->name) ? $o_customer->name : $o_customer->email;
            $new_customer->birthday   = substr($o_customer->birthDay,0,10);
            $new_customer->created_at = $o_customer->joinTime;
            $new_customer->updated_at = $o_customer->lastUpdate;
            $new_customer->old_id     = $o_customer->id;
            $new_customer->save();

            $new_company_customer = new CompanyCustomer;
            $new_company_customer->customer_id = $new_customer->id;
            $new_company_customer->company_id  = Company::where('companyId',$o_customer->companyId)->value('id');
            $new_company_customer->created_at  = $o_customer->joinTime;
            $new_company_customer->updated_at  = $o_customer->lastUpdate;
            $new_company_customer->save();

            $new_shop_customer = new ShopCustomer;
            $new_shop_customer->customer_id = $new_customer->id;
            $new_shop_customer->company_id  = $new_company_customer->company_id;
            $new_shop_customer->shop_id     = Shop::where('company_id',$new_company_customer->company_id)->value('id');
            $new_shop_customer->created_at  = $o_customer->joinTime;
            $new_shop_customer->updated_at  = $o_customer->lastUpdate;
            $new_shop_customer->save();

        }

        // 會員優惠券========================================================================================================================
        CustomerCoupon::truncate();
        $old_data = DB::connection('mysql2')->table('customer_coupons')->get();
        foreach( $old_data as $o_data ){
            if( Customer::where('old_id',$o_data->shilipai_customer_id)->value('id') ){
                $new_customer_coupon = new CustomerCoupon;
                $new_customer_coupon->shop_id        = Shop::where('alias',$o_data->companyId)->value('id');
                $new_customer_coupon->company_id     = Company::where('companyId',$o_data->companyId)->value('id');
                $new_customer_coupon->customer_id    = Customer::where('old_id',$o_data->shilipai_customer_id)->value('id');
                $new_customer_coupon->shop_coupon_id = ShopCoupon::where('old_id',$o_data->coupon_id)->value('id');
                $new_customer_coupon->status         = $o_data->status;
                $new_customer_coupon->using_time     = $o_data->using_time;
                $new_customer_coupon->created_at     = $o_data->created_at;
                $new_customer_coupon->updated_at     = $o_data->updated_at;
                $new_customer_coupon->save();
            }
        }

        // 會員集點卡========================================================================================================================
        CustomerLoyaltyCard::truncate();
        CustomerLoyaltyCardPoint::truncate();
        $old_data = DB::connection('mysql2')->table('customer_rewards')->get();
        foreach( $old_data as $o_data ){
            if( Customer::where('old_id',$o_data->shilipai_customer_id)->value('id') ){
                $shopLoyaltyCard = ShopLoyaltyCard::where('old_id',$o_data->reward_card_id)->first();

                if( !$shopLoyaltyCard ) continue; 

                // 建立會員集點卡資料
                $new_customer_card = new CustomerLoyaltyCard;
                $new_customer_card->shop_id              = Shop::where('alias',$o_data->companyId)->value('id');
                $new_customer_card->company_id           = Company::where('companyId',$o_data->companyId)->value('id');
                $new_customer_card->customer_id          = Customer::where('old_id',$o_data->shilipai_customer_id)->value('id');
                $new_customer_card->shop_loyalty_card_id = $shopLoyaltyCard->id;
                $new_customer_card->card_no              = $o_data->card_no;
                $new_customer_card->full_point           = CompanyLoyaltyCard::where('id',$shopLoyaltyCard->company_loyalty_card_id)->value('full_point');
                $new_customer_card->last_point           = $new_customer_card->full_point;
                $new_customer_card->status               = $o_data->status;
                $new_customer_card->using_time           = $o_data->using_time;
                $new_customer_card->created_at           = $o_data->created_at;
                $new_customer_card->updated_at           = $o_data->updated_at;
                $new_customer_card->save();

                // 集點記錄
                $old_points = DB::connection('mysql2')->table('customer_reward_points')->where('customer_reward_id',$o_data->id)->get();
                $point = 0;
                foreach( $old_points as $o_point ){
                    $new_point = new CustomerLoyaltyCardPoint;
                    $new_point->customer_loyalty_card_id = $new_customer_card->id;
                    $new_point->point                    = $o_point->point;
                    $new_point->created_at               = $o_point->created_at;
                    $new_point->updated_at               = $o_point->updated_at;
                    $new_point->save();

                    $point += $o_point->point;
                    $new_customer_card->last_point = $new_customer_card->full_point - $point;
                    $new_customer_card->save();
                }
            }
        }

        // 作品集資料 ==================================================================================================================================
        Album::truncate();
        AlbumPhoto::truncate();
        Photo::truncate();
        $companies = Company::get();
        $old_collection_name = [];
        foreach( $companies as $company ){
            $old_collections = DB::connection('mysql2')->table('photo_tags')->where('companyId',$company->companyId)->orderBy('sequence','ASC')->get();
            $sequence = 1;
            foreach( $old_collections->groupBy('name') as $name => $photo_data ){
                $shop_id = Permission::where('company_id',$company->id)->where('shop_id','!=',NULL)->value('shop_id');
                if( !$shop_id ) continue;

                // 先建立相本
                $new_album = new Album;
                $new_album->shop_id  = $shop_id;
                $new_album->name     = $name;
                $new_album->type     = 'collection';
                $new_album->sequence = $sequence++;
                $new_album->save();

                $old_photo_ids = $photo_data->pluck('albumDetail_id')->toArray();
                $old_photos = DB::connection('mysql2')->table('tb_albumDetails')->whereIn('id',$old_photo_ids)->get();
                $photo_insert = [];
                foreach( $old_photos as $k => $o_photo ){
                    $new_photo = new Photo;
                    $new_photo->user_id = Permission::where('company_id',$company->id)->value('user_id');
                    $new_photo->photo   = $o_photo->path.'.jpg';
                    $new_photo->save();

                    $new_album_photo = new AlbumPhoto;
                    $new_album_photo->album_id   = $new_album->id;
                    $new_album_photo->photo_id   = $new_photo->id;
                    $new_album_photo->cover      = $k == 0 ? 'Y' : 'N';
                    $new_album_photo->created_at = $o_photo->lastUpdate;
                    $new_album_photo->updated_at = $o_photo->lastUpdate;
                    $new_album_photo->save();

                    if( $k == 0 ){
                        $new_album->cover = $o_photo->path.'.jpg';
                        $new_album->save();
                    }
                }

            }
        }

        // 預約資料
        CustomerReservation::truncate();
        CustomerReservationAdvance::truncate();
        $shop_services = ShopService::pluck('id','old_id')->toArray();
        $shop_staffs   = ShopStaff::pluck('id','old_id')->toArray();

        $companies = Company::get();
        foreach( $companies as $company ){
            $old_events = DB::connection('mysql2')->table('tb_events')->where('companyId',$company->companyId)->where('deleteTime',NULL)->get();
            foreach( $old_events as $o_event ){

                $old_reservation = DB::connection('mysql2')->table('reservations')->where('event_id',$o_event->id)->first();
                $old_customer    = DB::connection('mysql2')->table('tb_customer')->where('id',$o_event->customerId)->first();

                if( !$old_customer || !isset($shop_services[$o_event->serviceId]) || !isset($shop_staffs[$o_event->staffId]) ) continue;

                $customer = '';
                if( $old_customer->shilipai_customer_id != '' ){
                    $customer = Customer::where('old_id',$old_customer->shilipai_customer_id)->value('id');
                }else{
                    $customer = Customer::where('old_id',$old_customer->id)->value('id');
                }

                if( $customer == '' ) continue;

                $new_reservation = new CustomerReservation;
                $new_reservation->customer_id        = $customer;
                $new_reservation->company_id         = $company->id;
                $new_reservation->shop_id            = Shop::where('alias',$o_event->companyId)->value('id');
                $new_reservation->shop_service_id    = $shop_services[$o_event->serviceId];
                $new_reservation->shop_staff_id      = $shop_staffs[$o_event->staffId];
                $new_reservation->start              = $o_event->start;
                $new_reservation->end                = $o_event->end;
                $new_reservation->need_time          = (strtotime($o_event->end)-strtotime($o_event->start))/60;
                $new_reservation->google_calendar_id = $o_event->google_calendar_id ?: NULL;
                $new_reservation->status             = $old_reservation && in_array( $old_reservation->status, ['Y','N']) ? $old_reservation->status : 'Y';
                $new_reservation->cancel_status      = $old_reservation && in_array( $old_reservation->status, ['C','M']) ? $old_reservation->status : NULL;
                $new_reservation->tag                = $o_event && $o_event->status && $o_event->status != 'N' ? $o_event->status : NULL;
                $new_reservation->created_at         = $o_event->lastUpdate;
                $new_reservation->updated_at         = $o_event->lastUpdate;
                $new_reservation->old_event_id       = $o_event->id;
                $new_reservation->save();

                // 加值項目
                $old_add_items = DB::connection('mysql2')->table('event_add_items')->where('event_id',$o_event->id)->get();
                foreach( $old_add_items as $old_add_item ){
                    $new_reservation_add = new CustomerReservationAdvance;
                    $new_reservation_add->customer_reservation_id = $new_reservation->id;
                    $new_reservation_add->shop_service_id         = $shop_services[$old_add_item->commodity_id];
                    $new_reservation_add->created_at              = $old_add_item->created_at;
                    $new_reservation_add->updated_at              = $old_add_item->updated_at;
                    $new_reservation_add->save();
                }
            }
        }

        foreach( $companies as $company ){
            $old_reservations = DB::connection('mysql2')->table('reservations')->where('companyId',$company->companyId)->where('deleted_at',NULL)->get();
            foreach( $old_reservations as $o_reservation ){
                $customer_reservation = CustomerReservation::where('old_event_id',$o_reservation->event_id)->first();
                if( !$customer_reservation ){

                    $old_customer = DB::connection('mysql2')->table('tb_customer')->where('id',$o_reservation->customer_id)->first();
                    $old_event    = DB::connection('mysql2')->table('tb_events')->where('id',$o_reservation->event_id)->first();

                    if( !$old_customer || !isset($shop_services[$o_reservation->product_item]) || !isset($shop_staffs[$o_reservation->service_personnel]) ) continue;

                    $customer = '';
                    if( $old_customer->shilipai_customer_id != '' ){
                        $customer = Customer::where('old_id',$old_customer->shilipai_customer_id)->value('id');
                    }else{
                        $customer = Customer::where('old_id',$old_customer->id)->value('id');
                    }

                    if( $customer == '' ) continue;

                    $new_reservation = new CustomerReservation;
                    $new_reservation->customer_id        = $customer;
                    $new_reservation->company_id         = $company->id;
                    $new_reservation->shop_id            = Shop::where('alias',$o_reservation->companyId)->value('id');
                    $new_reservation->shop_service_id    = $shop_services[$o_reservation->product_item];
                    $new_reservation->shop_staff_id      = $shop_staffs[$o_reservation->service_personnel];
                    $new_reservation->start              = $o_reservation->date;
                    $new_reservation->end                = date('Y-m-d H:i:s',strtotime($o_reservation->date."+".$o_reservation->service_time." minute"));
                    $new_reservation->need_time          = $o_reservation->service_time;
                    $new_reservation->google_calendar_id = $old_event ? $old_event->google_calendar_id : NULL;
                    $new_reservation->status             = $o_reservation && in_array( $o_reservation->status, ['Y','N']) ? $o_reservation->status : 'Y';
                    $new_reservation->cancel_status      = $o_reservation && in_array( $o_reservation->status, ['C','M']) ? $o_reservation->status : NULL;
                    $new_reservation->tag                = $old_event && $old_event->status && $old_event->status != 'N' ? $old_event->status : NULL;
                    $new_reservation->created_at         = $o_reservation->created_at;
                    $new_reservation->updated_at         = $o_reservation->updated_at;
                    $new_reservation->old_event_id       = $old_event ? $old_event->id :NULL;
                    $new_reservation->save();


                    // 加值項目
                    $old_add_items = explode(',',$o_reservation->add_items);
                    foreach( $old_add_items as $old_add_item ){
                        if( !isset($shop_services[$old_add_item]) ) continue;
                        $new_reservation_add = new CustomerReservationAdvance;
                        $new_reservation_add->customer_reservation_id = $new_reservation->id;
                        $new_reservation_add->shop_service_id         = $shop_services[$old_add_item];
                        $new_reservation_add->created_at              = $o_reservation->created_at;
                        $new_reservation_add->updated_at              = $o_reservation->updated_at;
                        $new_reservation_add->save();
                    }

                }
            }
        }
        return response()->json(['status'=>true]);
    }

    // 照片搬移
    public function sort_out_photo()
    {
        // 集團/分店照片搬移
        $company_infos = DB::connection('mysql2')->table('company_infos')->get();   
        foreach( $company_infos as $info ){
            $company = Company::where('companyId',$info->companyId)->first();
            if( !$company ) continue;
            $shop    = Shop::where('company_id',$company->id)->first();
            if( $info->store_pic ){
                $filePath = env('OLD_LOCAL').'/public/'.$info->store_pic;
                $new_path = env('OLD_OTHER').'/'.$info->companyId.'/'.str_replace('/upload/images/', '', $info->store_pic);
                if( file_exists($filePath) ){

                    // 先判斷資料夾是否存在
                    $file_path = env('OLD_OTHER').'/'.$info->companyId;
                    if(!file_exists($file_path)){
                        $old = umask(0);
                        mkdir($file_path,0775, true);
                        umask($old);
                    }

                    $line = shell_exec("cp ".$filePath." ".$new_path);
                    // 搬完圖片後，順便更新資料表
                    $company->banner = str_replace('/upload/images/', '', $info->store_pic);
                    $shop->banner    = str_replace('/upload/images/', '', $info->store_pic);
                }
            }

            if( $info->store_logo ){
                $filePath = env('OLD_LOCAL').'/public/'.$info->store_logo;
                $new_path = env('OLD_OTHER').'/'.$info->companyId.'/'.str_replace('/upload/images/', '', $info->store_logo);
                if( file_exists($filePath) ){

                    // 先判斷資料夾是否存在
                    $file_path = env('OLD_OTHER').'/'.$info->companyId;
                    if(!file_exists($file_path)){
                        $old = umask(0);
                        mkdir($file_path,0775, true);
                        umask($old);
                    }

                    $line = shell_exec("cp ".$filePath." ".$new_path);
                    // 搬完圖片後，順便更新資料表
                    $company->logo = str_replace('/upload/images/', '', $info->store_logo);
                    $shop->logo    = str_replace('/upload/images/', '', $info->store_logo);
                }
            }

            $company->save();
            $shop->save();
        }

        // 顧客大頭照與背景圖片搬移
        $shilipai_customers = DB::connection('mysql2')->table('shilipai_customers')->get();
        foreach( $shilipai_customers as $customer ){

            // 新實力派顧客資料
            if( $customer->photo && preg_match('/upload/i',$customer->photo) ){
                $filePath = env('OLD_LOCAL').'/public'.$customer->photo;
                $new_path = env('OLD_OTHER').'/shilipai_customer/'.str_replace('/upload/images/', '', $customer->photo);

                if( file_exists($filePath) ){

                    // 先判斷資料夾是否存在
                    $file_path = env('OLD_OTHER').'/shilipai_customer';
                    if(!file_exists($file_path)){
                        $old = umask(0);
                        mkdir($file_path,0775, true);
                        umask($old);
                    }

                    $line = shell_exec("cp ".$filePath." ".$new_path);
                    // 搬完圖片後，順便更新資料表
                }
            }

            if( $customer->banner && preg_match('/upload/i',$customer->banner) ){
                $filePath = env('OLD_LOCAL').'/public/'.$customer->banner;
                $new_path = env('OLD_OTHER').'/shilipai_customer/'.str_replace('/upload/images/', '', $customer->banner);
                if( file_exists($filePath) ){

                    // 先判斷資料夾是否存在
                    $file_path = env('OLD_OTHER').'/shilipai_customer';
                    if(!file_exists($file_path)){
                        $old = umask(0);
                        mkdir($file_path,0775, true);
                        umask($old);
                    }

                    $line = shell_exec("cp ".$filePath." ".$new_path);
                    // 搬完圖片後，順便更新資料表
                }
            }
        }

        return true;   
        
    }

    public function imgto64()
    {
        $path = '/Library/WebServer/renmo/shilipai_upload_img/24571275/5e846477f4ca19465d11d3dd1465c77f23b2fa78.jpg';
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $base64 = base64_encode($data);

        echo 'data:image/jpeg;base64,'.$base64;
    }

    public function point_in()
    {
        $start   = microtime(date('Y-m-d H:i:s'));
        $point   = [ 'x' => 0 , 'y' => 3 ];
        $polygon = array("0 0","5 0","5 5","0 5","0 0");
         
        // Transform string coordinates into arrays with x and y values
        $vertices = array(); 
        foreach ($polygon as $vertex) {
            $coordinates = explode(" ", $vertex);
            $vertices[] = array("x" => $coordinates[0], "y" => $coordinates[1]);
        }
 
        // Check if the point sits exactly on a vertex
        foreach($vertices as $vertex) {
            if ($point == $vertex) {
                $res = "vertex";
                $end = microtime(date('Y-m-d H:i:s'));
                $time = $end - $start;
                return response()->json(['status'=>true,'res'=>$res,'time'=>$time ]);
            }
        }
 
        // Check if the point is inside the polygon or on the boundary
        $intersections = 0; 
        $vertices_count = count($vertices);
 
        for ($i=1; $i < $vertices_count; $i++) {
            $vertex1 = $vertices[$i-1]; 
            $vertex2 = $vertices[$i];

            if ($vertex1['y'] == $vertex2['y'] and $vertex1['y'] == $point['y'] and $point['x'] > min($vertex1['x'], $vertex2['x']) and $point['x'] < max($vertex1['x'], $vertex2['x'])) { // Check if point is on an horizontal polygon boundary
                $res = "boundary";
                $end = microtime(date('Y-m-d H:i:s'));
                $time = $end - $start;
                return response()->json(['status'=>true,'res'=>$res,'time'=>$time]);
            }
            if ($point['y'] > min($vertex1['y'], $vertex2['y']) and $point['y'] <= max($vertex1['y'], $vertex2['y']) and $point['x'] <= max($vertex1['x'], $vertex2['x']) and $vertex1['y'] != $vertex2['y']) { 
                $xinters = ($point['y'] - $vertex1['y']) * ($vertex2['x'] - $vertex1['x']) / ($vertex2['y'] - $vertex1['y']) + $vertex1['x']; 
                if ($xinters == $point['x']) { // Check if point is on the polygon boundary (other than horizontal)
                    $res = "boundary";
                    $end = microtime(date('Y-m-d H:i:s'));
                    $time = $end - $start;
                    return response()->json(['status'=>true,'res'=>$res,'time'=>$time ]);
                }
                if ($vertex1['x'] == $vertex2['x'] || $point['x'] <= $xinters) {
                    $intersections++; 
                }
            } 
        } 
        // If the number of edges we passed through is odd, then it's in the polygon. 
        if ($intersections % 2 != 0) {
            $res = "inside";
            $end = microtime(date('Y-m-d H:i:s'));
            $time = $end - $start;
            return response()->json(['status'=>true,'res'=>$res,'time'=>$time ]);
        } else {
            $res = "outside";
            $end = microtime(date('Y-m-d H:i:s'));
            $time = $end - $start;
            return response()->json(['status'=>true,'res'=>$res,'time'=>$time ]);
        }
    }

    // 測試發送簡訊api
    public function send_message()
    {
        // 先取得剩餘簡訊數
        $url   = 'http://smsapi.mitake.com.tw/api/mtk/SmQuery'; 
        $data  = 'username='.config('services.phone.username');
        $data .= '&password='.config('services.phone.password');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);
        $before = (int)explode("=",$output)[1];

        // 預設簡訊內容
        $random       = rand(1, 9) . rand(1, 9) .  rand(1, 9) .  rand(1, 9) .  rand(1, 9) .  rand(1, 9);
        $phone_number = '0910351897';
        $sendword     = "「實力派管理平台」您帳號已開通\n連結：lihi1.cc/xObSe\n帳號：手機號碼\n" ."密碼：". $random."\n"."請在30分鐘內完成驗證並修改密碼。12";
        
        // url
        $url  = 'http://smsapi.mitake.com.tw/api/mtk/SmSend'; 
        $url .= '?CharsetURL=UTF-8';
        
        // parameters
        $data  = 'username='.config('services.phone.username'); 
        $data .= '&password='.config('services.phone.password');
        $data .= '&dstaddr='.$phone_number; 
        $data .= '&smbody='.$sendword;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);

        // 先取得剩餘簡訊數
        $url  = 'http://smsapi.mitake.com.tw/api/mtk/SmQuery'; 
        $data = 'username='.config('services.phone.username');//'; 
        $data .= '&password='.config('services.phone.password');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec ($ch);

        $after = (int)explode("=",$output)[1];

        $use   = $before - $after;

        return response()->json(['use'=>$use,'len'=> mb_strlen($sendword,'utf-8'),'content'=>$sendword ]);

    }

    public function test_pay()
    {
        $HashKey = config('services.newebpay.HashKey');
        $HashIV  = config('services.newebpay.HashIV');

        // 定期定額
        $mdata = [
            'MerchantID'      => config('services.newebpay.MerchantID'),         // 商店代號   
            'RespondType'     => 'JSON',                                         // 回傳格式
            'TimeStamp'       => time(),                                         // 時間戳記
            'Version'         => "1.1",                                          // 串接程式版本
            'MerOrderNo'      => time(),                                         // 商店訂單編號
            'PeriodAmt'       => 1000,                                           // 訂單金額
            'ProdDesc'        => '測試定期定額項目',                               // 商品資訊
            'PeriodType'      => 'M',                                            // 週期類別
            'PeriodPoint'     => date('d'),                                      // 交易週期授權時間
            'PeriodStartType' => 3,                                              // 檢查卡號模式
            'PeriodTimes'     => '12',                                           // 授權期數
            // 'PayerEmail'      => 'aarondu@renmo.cc',                             // 付款人電子信箱
            'ReturnURL'       => env('SHOW_PHOTO').'/api/test/pay/return',
            'NotifyURL'       => env('SHOW_PHOTO').'/api/test/pay/notify/return',
        ];

        // return $mdata;

        $TradeInfo = $PostData = Self::create_mpg_aes_encrypt($mdata, $HashKey, $HashIV); 
        $TradeSha  = strtoupper(hash("sha256","HashKey=".$HashKey."&".$TradeInfo."&HashIV=".$HashIV));

        $action = env('NEWBPAYPERIOD');

        return view('pay_test',compact('action','TradeInfo','TradeSha','PostData'));

    } 

    public function test_pay_return()
    {
        $Period = request('Period'); // '30e944d284496462074b14441071b1631a38f4bf19214496f2fc65f5ca453ef3e40d82247a3208ce724c99f328af0cda9a645ddb3e558b844ffac04a9d2a17b5cab6e031ded989fad81d392962bc42e59520b836a865cea7051bff76b286734b6ebafcea62b35ff1744753b96f2f3321de322cee55bb9b054931bbd9d7b45f2ee40448c4c127de91f6c42e9f93457e74dc4f61c3962bd495b8d4c1083c27c15dd01f5340405070abb5d33f07095b93e7d6090c5002bbd034b282eb1494bfe1568df240799d53aae44562083af61a3dcb20704672ddb570cc6b5e061d6b27ed15a6f29be4d6dcf5daca3cc23c4511f412837eed05d3122fa0c92d0a97a93ee63c259d0d0311ed27f80e2d7e5139be88e7d1797ddb80c4a6540fff85233db63b64c2b25fa424873437debfa2d20a744ccd69da0224c68807a0a60df4002d1c2550ceb4193bbef71fab98fe9b3f2774d6aea5dffa2406d83af67f2d09f06d77c7da1c255807617a482aa7ece76fb3fe73656e69ccb8046eaf82aa8008ee262f4de04b0b34f851ba88c9c266fed9a311046b1950555163673e500f4a4e77fe81b3b8'

        // 信用卡定期定額
        $HashKey = config('services.newebpay.HashKey');
        $HashIV  = config('services.newebpay.HashIV');

        $data    = json_decode(Self::create_aes_decrypt($Period, $HashKey , $HashIV),JSON_UNESCAPED_UNICODE) ;
        
        return $data;
    }

    // 交易資料 AES 加密
    public static function create_mpg_aes_encrypt ($parameter = "" , $key = "", $iv = "") 
    {
        $return_str = '';
        if (!empty($parameter)) {
            //將參數經過 URL ENCODED QUERY STRING
            $return_str = http_build_query($parameter);
        }
        $len = strlen($return_str);
        $pad = 32 - ($len % 32);
        $return_str .= str_repeat(chr($pad), $pad);
        return trim(bin2hex(openssl_encrypt($return_str, 'aes-256-cbc', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv)));
    }

    // 交易資料 AES 解密
    public static function create_aes_decrypt($parameter = "", $key = "", $iv = "")
    {
        $string = openssl_decrypt(hex2bin($parameter),'AES-256-CBC', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv) ; 
        $slast = ord(substr($string, -1));
        $slastc = chr($slast);
        $pcheck = substr($string, -$slast);
        if (preg_match("/$slastc{" . $slast . "}/", $string)) {
            $string = substr($string, 0, strlen($string) - $slast);
            return $string;
        } else {
            return false;
        }
    }



}
