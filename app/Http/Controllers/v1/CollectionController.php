<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Photo;
use App\Models\Shop;

class CollectionController extends Controller
{
    // 取得商家全部作品集
    public function shop_collection($shop_id)
    {
    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_collections',$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info;

    	$shop_collections = Album::where('shop_id',$shop_id)->where('type','collection')->orderBy('sequence','ASC')->get();
    	foreach( $shop_collections as $collection ){
            if( $collection->cover ) $collection->cover = env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$collection->cover;
    	}

    	$create_permission = true;
    	if( $shop_info->buy_mode_id == 0 && $shop_collections->count() == 1 ){
    		// 基本版只能有一本，需檢查數量
    		$create_permission = false;
    	}elseif( $shop_info->buy_mode_id >= 1 ){
    		$create_permission = in_array('shop_collection_create_btn',$user_shop_permission['permission']) ? true : false;
    	}
    	
    	$data = [
            'status'            => true,
            'permission'        => true,
            'create_permission' => $create_permission,
            'delete_permission' => in_array('shop_collection_delete_btn',$user_shop_permission['permission']) ? true : false,
            'data'              => $shop_collections,
        ];

        return response()->json($data);
    }

    // 新增/編輯商家作品集資料
    public function shop_collection_info($shop_id,$album_id="")
    {
    	if( $album_id ){
            $collection = Album::find($album_id);
            if( !$collection ){
                return response()->json(['status'=>false,'errors'=>['message'=>['找不到作品集資料']]]);
            }
            $type = 'edit';
        }else{
            $collection = new Album;
            $type       = 'create';
        }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

    	// 拿取使用者的商家權限
    	$user_shop_permission = PermissionController::user_shop_permission($shop_id);
    	if( $user_shop_permission['status'] == false ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>[$user_shop_permission['errors']]]]);

    	// 確認頁面瀏覽權限
    	if( !in_array('shop_collection_'.$type,$user_shop_permission['permission']) ) return response()->json(['status'=>true,'permission'=>false,'errors'=>['message'=>['使用者沒有權限']]]);

    	foreach( $collection->photos as $photo){
            $photo->cover = $photo->photo_info->photo == $collection->cover ? 'Y' : 'N';
    		$photo->photo = env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo;
            $photo->date  = date('Y年m月',strtotime($photo->created_at));
    	}

        // 此商家已上傳的照片數量
        $shop_collections = Album::where('shop_id',$shop_info->id)->get();
        $shop_album_photos = AlbumPhoto::whereIn('album_id',$shop_collections->pluck('id')->toArray())->get();

        $update_photo = true;
        if( !in_array('shop_collection_'.$type.'_photo_upload'.$type,$user_shop_permission['permission']) ) $update_photo = false;
        if( $shop_album_photos->groupBy('photo_id')->count() >= $shop_info->photo_limit )                   $update_photo = false;

        // 作品集資料
    	$collection_info = [
    		'id'                      => $collection->id,
    		'name'                    => $collection->name,
    		'name_permission'         => in_array('shop_collection_'.$type.'_name',$user_shop_permission['permission']) ? true : false,
    		'photo_count'             => $collection->photos->count(),
    		'photos'                  => $collection->photos,
            'upload_permission'       => $update_photo,
    		'delete_photo_permission' => in_array('shop_collection_'.$type.'_photo_delete'.$type,$user_shop_permission['permission']) ? true : false,
    	];

        // 依月份分相簿
        $shop_albums   = Album::where('shop_id',$shop_id)->pluck('id')->toArray();
        $shop_photos   = AlbumPhoto::whereIn('album_id',$shop_albums)
                                     ->orderBy('album_photos.created_at','DESC')
                                     ->get();
        $select_albums = []; 
        foreach( $shop_photos as $photo ){
            // 顯示是否被選取過
            $selected = false;
            foreach( $collection->photos as $cphoto ){
                if( $cphoto->id == $photo->id ){
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
                            'album_id' => $selected ? $photo->album_id : '',
                            'photo_id' => $photo->photo_id,
                            'cover'    => 'N',
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
                        'album_id' => $selected ? $photo->album_id : '',
                        'photo_id' => $photo->photo_id,
                        'cover'    => 'N',
                        'photo'    => env('SHOW_PHOTO').'/api/show/'.$shop_info->alias.'/'.$photo->photo_info->photo,
                        'selected' => $selected,
                        'date'     => date('Y年m月',strtotime($photo->created_at)),
                    ],]
                ];
            }
        }

        // 判斷是否還可以上傳照片
        $photo_upload = true;
        if( $shop_info->buy_mode_id == 0 && $collection->photos->count() >= 3 ){
            // 判斷是否已經超過三張
            $photo_upload = false;
        }elseif( ($shop_info->buy_mode_id == 1 || $shop_info->buy_mode_id == 2) && $shop_photos->count() >= 5000 ){
            // 判斷照片張數總量
            $photo_upload = false;
        }else if( $shop_info->buy_mode_id > 2 && $shop_photos->count() >= 8000 ){
            $photo_upload = false;
        }

        $data = [
            'status'        => true,
            'permission'    => true,
            'photo_upload'  => $photo_upload,
            'select_albums' => $select_albums,
            'data'          => $collection_info,
        ];

		return response()->json($data);
    }

    // 儲存商家作品集照片
    public function shop_collection_save($shop_id)
    {
    	$user_info    = auth()->User();
    	$shop_info    = Shop::find($shop_id);
    	$company_info = $shop_info->company_info; 

    	if( request('id') ){
    	    // 編輯
    	    $shop_collection = Album::find(request('id'));
    	    if( !$shop_collection ){
    	        return response()->json(['status'=>false,'errors'=>['message'=>['找不到作品集資料']]]);
    	    }
    	}else{
    	    // 新增
    	    $shop_collection = new Album;
    	    $shop_collection->shop_id = $shop_id;
    	    $shop_collection->type    = 'collection';
    	}

    	$shop_collection->name = request('name') ?:'暫存';
    	$shop_collection->save();

        $old_photo_id  = [];
        $insert        = [];
        $select_photos = [];
        foreach( request('photos') as $photo ){
            if( $photo['id'] ){
                $old_photo_id[] = $photo['photo_id'];
                // 先將舊有照片裡，有被設成cover的先記錄起來
                if( $photo['cover'] == 'Y' ){
                    $shop_collection->cover = Photo::find($photo['photo_id'])->photo;
                    $shop_collection->save(); 

                    AlbumPhoto::where('id',$photo['id'])->update(['cover'=>'Y']);   
                    AlbumPhoto::where('album_id',$shop_collection->id)->where('photo_id','!=',$photo['photo_id'])->update(['cover'=>'N']);
                }

            } else {
                if( $photo['photo_id'] != "" || $photo['photo_id'] != NULL ){
                    // 既有的照片加入
                    $select_photos[] = [
                        'album_id' => $shop_collection->id,
                        'photo_id' => $photo['photo_id'],
                        'cover'    => $photo['cover'],
                    ];
                }else{
                    // 新上傳的照片
                    $insert[] = [
                        'photo'    => $photo['photo'],
                        'cover'    => $photo['cover'],
                    ];
                }
            }               
        }

        // 先刪除照片
        $delete_photos = AlbumPhoto::where('album_id',$shop_collection->id)->whereNotIn('photo_id',$old_photo_id)->join('photos', 'album_photos.photo_id', '=', 'photos.id')->get();
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

            // 若此張照片是封面照片，需更換成別的照片
            if( $dp->cover == 'Y' ){
                // 先檢查除了自己本身外，是否有被設為封面的圖片
                $other_photo = AlbumPhoto::where('album_id',$shop_collection->id)->where('photo_id','!=',$dp->photo_id)->where('cover','Y')->orderBy('id','DESC')->first();
                // 若沒有被設為封面的圖片，則繼續檢查相簿裡的照片張數
                if( !$other_photo ){
                    if( AlbumPhoto::where('album_id',$shop_collection->id)->where('photo_id','!=',$dp->photo_id)->count() == 0 ){
                        $shop_collection->cover = NULL;
                    }else{
                        $other_photo = AlbumPhoto::where('album_id',$shop_collection->id)->where('photo_id','!=',$dp->photo_id)->orderBy('id','DESC')->first();

                        $photo_info             = $other_photo->photo_info;
                        $shop_collection->cover = $photo_info->photo;

                        $other_photo->cover = 'Y';
                        $other_photo->save();
                    }
                    $shop_collection->save();
                }
            }
        }
        // 刪除相簿與照片的關連
        AlbumPhoto::where('album_id',$shop_collection->id)->whereNotIn('photo_id',$old_photo_id)->delete();

        // 儲存既有相片
        foreach( $select_photos as $k => $data ){
            $new_album_photo = new AlbumPhoto;
            $new_album_photo->album_id = $shop_collection->id;
            $new_album_photo->photo_id = $data['photo_id'];
            $new_album_photo->cover    = $data['cover'];
            $new_album_photo->save();

            if( $data['cover'] == 'Y' ){
                $shop_collection->cover = Photo::find($data['photo_id'])->photo;
                $shop_collection->save();

                // 其他照片cover設成 N
                AlbumPhoto::where('album_id',$shop_collection->id)->where('photo_id','!=',$new_album_photo->id)->update(['cover'=>'N']);
            }
        }

        // 儲存新照片
        foreach( $insert as $k => $data ){
            $picName = PhotoController::save_base64_photo($shop_info->alias,$data['photo']);

            $new_photo = new Photo;
            $new_photo->user_id = $user_info->id;
            $new_photo->photo   = $picName;
            $new_photo->save();

            $new_album_photo = new AlbumPhoto;
            $new_album_photo->album_id = $shop_collection->id;
            $new_album_photo->photo_id = $new_photo->id;
            $new_album_photo->cover    = $data['cover'];
            $new_album_photo->save();

            if( $data['cover'] == 'Y' ){
                $shop_collection->cover = $picName;
                $shop_collection->save();

                // 其他照片cover設成 N
                AlbumPhoto::where('album_id',$shop_collection->id)->where('photo_id','!=',$new_photo->id)->update(['cover'=>'N']);
            }
        }

	    return response()->json(['status'=>true]);
    }

    // 刪除商家作品集
    public function shop_collection_delete($shop_id,$album_id)
    {
    	$shop_collection = Album::find($album_id);
	    if( !$shop_collection ){
	        return response()->json(['status'=>false,'errors'=>['message'=>['找不到作品集資料']]]);
	    }
        
        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        $shop_collection->delete();

        // 刪除關連資料
        $album_photos = AlbumPhoto::where('album_id',$album_id)->get();
        foreach( $album_photos as $ap ){
        	// 檢查照片是否還有在其他相簿裡，若沒有才刪除是自己上傳的照片
        	$photo_count = AlbumPhoto::where('photo_id',$ap->photo_id)->get()->count();
        	if( $photo_count == 1 ){
        		$photo = Photo::where('id',$ap->photo_id)->first();
                if( $photo ){
                    // 刪除檔案
                    $filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$photo->photo;
                    if(file_exists($filePath)){
                        unlink($filePath);
                    }
                    $photo->delete();
                } 
        	} 
        }
        AlbumPhoto::where('album_id',$album_id)->delete();

        return response()->json(['status'=>true]);
    }

    // 刪除商家作品集單張照片
    public function shop_collection_photo_delete($shop_id,$album_id,$photo_id)
    {
    	$photo = Photo::find($photo_id);
	    if( !$photo ){
	        return response()->json(['status'=>false,'errors'=>['message'=>['找不到照片資料']]]);
	    }

        $shop_info    = Shop::find($shop_id);
        $company_info = $shop_info->company_info;

        // 先檢查此張照片是否有被其他相簿存放，若有就只刪除相簿關連，若都沒有其他相簿存放，就直接刪除照片
        $photo_count = AlbumPhoto::where('photo_id',$photo_id)->get()->count();
        if( $photo_count == 1 ){
        	// 刪除檔案
    		$filePath = env('OLD_OTHER').'/'.$shop_info->alias.'/'.$photo->photo;
            if(file_exists($filePath)){
                unlink($filePath);
            }
            $photo->delete();
            // 刪除相簿與照片的關連
            AlbumPhoto::where('album_id',$album_id)->where('photo_id',$photo_id)->delete();
        }else{
        	// 刪除此作品集的關聯
        	AlbumPhoto::where('album_id',$album_id)->where('photo_id',$photo_id)->delete();
        }

        // 若此張照片是封面照片，需更換成別的照片
        $shop_collection = Album::find($album_id);
        if( $shop_collection->cover == $photo->photo ){
        	$other_photo = AlbumPhoto::where('album_id',$album_id)->orderBy('id','DESC')->first();
        	if( !$other_photo ){
        		$shop_collection->cover = NULL;
        	}else{
        		$photo_info = $other_photo->photo_info;
        		$shop_collection->cover = $photo_info->photo;
        	}
        	
        	$shop_collection->save();
        }

        return response()->json(['status'=>true]);
    }

}
