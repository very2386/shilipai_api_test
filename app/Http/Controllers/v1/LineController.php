<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Permission;
use DB;

class LineController extends Controller
{
    // 取得line@全部三格圖片
	public function get_line_pic($shop_id)
	{
		$shop_info = Shop::find($shop_id);

		$categories = DB::table('line_pics')->groupBy('category')->pluck('category')->toArray();
		$pics       = DB::table('line_pics')->get();

		$data               = [];
		$selected_photo_url = '';
		$selected_name      = '';
		foreach( $categories as $k => $cate ){
			$pic_data = [];
			foreach( $pics as $pic ){
				if( $pic->category == $cate ){
					$pic_data[] = [
						'name' => $pic->name,
						'url'  => env('SHOW_PHOTO').'/api/show/linePic/'.$pic->category.'/'.$pic->name,
					];

					if( $pic->name == $shop_info->shop_set->line_photo ){
						$selected_photo_url = env('SHOW_PHOTO').'/api/show/linePic/'.$pic->category.'/'.$pic->name;
						$selected_name      = $pic->name;
					}
				}
			}
			$data[$k] = [
				'category' => $cate,
				'pics'     => $pic_data,
			];
		}

		// 拿取使用者的商家權限
        $shop_permission = Permission::where('shop_id',$shop_id)->where('shop_staff_id',NULL)->first();
		$permission = explode(',',$shop_permission->permission);

		$data = [
			'status'         => true,
			'permission'     => !in_array('line_button',$permission) ? false : true, 
			'selected_photo' => $selected_photo_url,
			'selected_name'  => $selected_name,
			'data'           => $data
		];

		return response()->json($data);
	}


	// 顯示line@三格圖片
	public function show_line_pic($category,$photo_name)
	{
		$file = env('UPLOAD_IMG') . '/line_pic/'.$category.'/'.$photo_name;
        if(file_exists($file)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            header("Content-Type: $mime");
            readfile($file);
        }
	} 

}
