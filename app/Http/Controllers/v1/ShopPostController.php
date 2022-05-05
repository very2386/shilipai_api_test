<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Shop;
use App\Models\ShopPost;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Photo;

class ShopPostController extends Controller
{
    // 取得商家全部貼文資料
    public function shop_posts($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_posts',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

        $posts = ShopPost::where('shop_id',$shop_id)->orderBy('id','DESC')->get();

    	$data = [
            'status'                       => true,
            'permission'                   => true,
            'shop_post_create_permission'  => in_array('shop_post_create_btn',$user_shop_permission['permission']) ? true : false, 
            'shop_post_edit_permission'    => in_array('shop_post_edit_btn',$user_shop_permission['permission']) ? true : false, 
            'shop_post_delete_permission'  => in_array('shop_post_delete_btn',$user_shop_permission['permission']) ? true : false, 
            // 'shop_post_set_top_permission' => in_array('shop_post_set_top',$user_shop_permission['permission']) ? true : false, 
            'data'                         => $posts,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家貼文資料
    public function shop_post_info($shop_id,$shop_post_id="")
    {
        if( $shop_post_id ){
            $shop_post = ShopPost::find($shop_post_id);
            if( !$shop_post ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到貼文資料']]]);
            }
            $type = 'edit';
        }else{
            $shop_post = new ShopPost;
            $type      = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_post_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        // 貼文裡的照片
        $post_album = Album::where('type','post')->where('shop_id',$shop_id)->where('post_id',$shop_post_id)->first();
        $photos = [];
        if( $post_album ){
            foreach( $post_album->photos as $photo ){
                $photos[] = [
                    'id'       => $photo->id,
                    'album_id' => $photo->album_id,
                    'photo_id' => $photo->photo_id,
                    'photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo,
                    'date'     => date('Y年m月',strtotime($photo->created_at)),
                ];
            }
        }

        if( !$post_album || $post_album->photos->count() == 0 ){
            $photos = [
                // 'id'       => null,
                // 'album_id' => null,
                // 'photo_id' => null,
                // 'photo'    => null,
                // 'date'     => null,
            ];
        }

        // 既有相簿選項
        // 依月份分相簿
        $shop_albums   = Album::where('shop_id',$shop_id)->pluck('id')->toArray();
        $shop_photos   = AlbumPhoto::whereIn('album_id',$shop_albums)
                                     ->orderBy('album_photos.created_at','DESC')
                                     ->get();
        // return $shop_photos;
        $select_albums = []; 
        foreach( $shop_photos as $photo ){
            // 顯示是否被選取過
            $selected = false;
            foreach( $photos as $po ){
                if( $po['id'] == $photo->id ){
                    $selected = true;
                    break;
                }
            }

            $check_month = false;
            foreach( $select_albums as $k => $album ){
                if( $album['date'] == date('Y年m月',strtotime($photo->created_at)) ){
                    $check_month = true;

                    $same_photo = false;
                    foreach( $select_albums[$k]['photos'] as $sa ){
                        if( $sa['photo_id'] == $photo->photo_id ){
                            $same_photo = true;
                            break;
                        }
                    }

                    if( $same_photo == false ){
                        $select_albums[$k]['photos'][] = [
                            'id'       => $selected ? $photo->id : '',
                            'album_id' => $photo->album_id,
                            'photo_id' => $photo->photo_id,
                            'photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo,
                            'selected' => $selected,
                            'date'     => date('Y年m月',strtotime($photo->created_at)),
                        ];
                    }
                    break;
                }
            }

            if( $check_month == false ){
                $select_albums[] = [
                    'date'   => date('Y年m月',strtotime($photo->created_at)),
                    'photos' => [[
                        'id'       => $selected ? $photo->id : '',
                        'album_id' => $photo->album_id,
                        'photo_id' => $photo->photo_id,
                        'photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo,
                        'selected' => $selected,
                        'date'     => date('Y年m月',strtotime($photo->created_at)),
                    ],]
                ];
            }
        }

        $shop_post_data = [
            'id'                 => $shop_post->id,
            'content'            => $shop_post->content,
            'content_permission' => in_array('shop_post_'.$type.'_content',$user_shop_permission['permission']) ? true : false,
            'photos'             => $photos,
            'photo_permission'   => in_array('shop_post_'.$type.'_photo',$user_shop_permission['permission']) ? true : false,
            'top'                => $shop_post->top ? $shop_post->top : 'N',
            'top_permission'     => in_array('shop_post_'.$type.'_top',$user_shop_permission['permission']) ? true : false,
        ];

        $data = [
            'status'        => true,
            'permission'    => true,
            'select_albums' => $select_albums,
            'data'          => $shop_post_data
        ];

        return response()->json($data);
    }

    // 儲存/更新商家貼文資料
    public function shop_post_save($shop_id,$shop_post_id="")
    {
        // 驗證欄位資料
        $rules = [
            'content' => 'required', 
            'top'     => 'required',
        ];

        $messages = [
            'content.required' => '請填寫貼文內容',
            'top.required'     => '缺少置頂資料',
        ];

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()){
            return response()->json(['status' => false,'errors' => $validator->getMessageBag()->toArray()]); 
        }

        if( $shop_post_id ){
            // 編輯
            $shop_post = ShopPost::find($shop_post_id);
            if( !$shop_post ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到貼文資料']]]);
            }
        }else{
            // 新增
            $shop_post = new ShopPost;
            $shop_post->shop_id = $shop_id;
        }

        $user_info    = auth()->User();
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 儲存貼文資料
        $shop_post->content = request('content');
        $shop_post->top     = request('top');
        $shop_post->status  = 'published';
        $shop_post->save();

        if( !$shop_post->post_album ){
            $post_album = new Album;
            $post_album->name     = '貼文';
            $post_album->type     = 'post';
            $post_album->shop_id  = $shop_id;
            $post_album->post_id  = $shop_post->id;
            $post_album->sequence = 1;
            $post_album->save();
        }else{
            $post_album = $shop_post->post_album;
        }

        // 處理貼文裡的照片
        $old_photo_id  = [];
        $insert        = [];
        $select_photos = [];
        foreach( request('photos') as $photo ){
            if( $photo['id'] ){
                $old_photo_id[] = $photo['photo_id'];
            } else {
                if( $photo['photo_id'] != "" || $photo['photo_id'] != NULL ){
                    // 既有的照片加入
                    $select_photos[] = [
                        'album_id' => $post_album->id,
                        'photo_id' => $photo['photo_id'],
                    ];
                }else{
                    // 新上傳的照片
                    $insert[] = [
                        'photo' => $photo['photo'],
                    ];
                }
            }               
        }

        $shop_post = ShopPost::find($shop_post->id);

        // 先刪除照片
        $delete_photos = AlbumPhoto::where('album_id',$shop_post->post_album->id)->whereNotIn('album_photos.photo_id',$old_photo_id)->join('photos', 'album_photos.photo_id', '=', 'photos.id')->get();

        foreach( $delete_photos as $dp ){
            // 先檢查此張照片是否有被其他相簿存放，若有就只刪除相簿關連，若都沒有其他相簿存放，就直接刪除照片
            $photo_count = AlbumPhoto::where('photo_id',$dp->photo_id)->get()->count();
            if( $photo_count == 1 ){
                // 刪除檔案
                $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$dp->photo;
                if(file_exists($filePath)){
                    unlink($filePath);
                }
                Photo::find($dp->photo_id)->delete();
            }
        }
        // 刪除相簿與照片的關連
        AlbumPhoto::where('album_id',$shop_post->post_album->id)->whereNotIn('photo_id',$old_photo_id)->delete();

        // 儲存既有相片
        foreach( $select_photos as $k => $data ){
            $new_album_photo = new AlbumPhoto;
            $new_album_photo->album_id = $post_album->id;
            $new_album_photo->photo_id = $data['photo_id'];
            $new_album_photo->save();
        }

        // 儲存新照片
        $inser_photo = [];
        foreach( $insert as $k => $data ){
            $picName = PhotoController::save_base64_photo($shop_info->alias,$data['photo']);

            $new_photo = new Photo;
            $new_photo->user_id = $user_info->id;
            $new_photo->photo   = $picName;
            $new_photo->save();

            $new_album_photo = new AlbumPhoto;
            $new_album_photo->photo_id = $new_photo->id;
            $new_album_photo->album_id = $shop_post->post_album->id;
            $new_album_photo->cover    = 'N';
            $new_album_photo->save();
        }

        return response()->json(['status'=>true]);
    }

    // 刪除商家貼文資料
    public function shop_post_delete($shop_id,$shop_post_id)
    {
        $shop_post = ShopPost::find($shop_post_id);
        if( !$shop_post ){
            return response()->json(['status'=>false,'errors'=>['message'=>['找不到貼文資料']]]);
        }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 刪除貼文相簿與照片
        $delete_photos = AlbumPhoto::where('album_id',$shop_post->post_album->id)->join('photos', 'album_photos.photo_id', '=', 'photos.id')->get();
        foreach( $delete_photos as $photo ){
            // 先檢查此張照片是否有被其他相簿存放，若有就只刪除相簿關連，若都沒有其他相簿存放，就直接刪除照片
            $photo_count = AlbumPhoto::where('photo_id',$photo->photo_id)->get()->count();
            if( $photo_count == 1 ){
                // 刪除檔案
                $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$photo->photo;
                if(file_exists($filePath)){
                    unlink($filePath);
                }
                Photo::find($photo->photo_id)->delete();
                $photo->delete();
            }
        }

        // 刪除貼文相簿
        $shop_post->post_album->delete();

        // 刪除商家貼文資料
        $shop_post->delete();

        return response()->json(['status'=>true]);
    }

    // 更新商家貼文置頂狀態
    public function shop_post_top_save($shop_id,$shop_post_id)
    {
        if( $shop_post_id ){
            $shop_post = ShopPost::find($shop_post_id);
            if( !$shop_post ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到貼文資料']]]);
            }
            $type = 'edit';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 拿取使用者的商家權限
        $user_shop_permission = PermissionController::user_shop_permission($shop_id);
        if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

        // 確認頁面瀏覽權限
        // if( !in_array('shop_post_top'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

        $shop_post->top = request('top');
        $shop_post->save();

        return response()->json(['status'=>true]);

    }

}
