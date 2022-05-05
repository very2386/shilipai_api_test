<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Image;

class PhotoController extends Controller
{
    // 顯示商家所有圖片
    public function show_photo($id,$photo)
    {
    	$file = env('UPLOAD_IMG') . '/'.$id.'/'.$photo;
        if(file_exists($file)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            header("Content-Type: $mime");
            $domains = ['api.shilip.ai'];
            $origin = isset(request()->server()['HTTP_HOST']) ? request()->server()['HTTP_HOST'] : '';
            if( in_array($origin,$domains) ){
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Headers: Authorization,authenticated");
                header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, OPTIONS");
            }
            readfile($file);
        }
    }

    // 顯示商家會員圖片
    public function show_customer_photo($photo)
    {
        $file = env('UPLOAD_IMG') . '/shilipai_customer/'.$photo;
        if(file_exists($file)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            header("Content-Type: $mime");
            $domains = ['api.shilip.ai'];
            $origin = isset(request()->server()['HTTP_HOST']) ? request()->server()['HTTP_HOST'] : '';
            if( in_array($origin,$domains) ){
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Headers: Authorization,authenticated");
                header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, OPTIONS");
            }
            readfile($file);
        }
    }

    // 顯示會員人格圖片
    public function show_customer_personality_photo($number)
    {
        $file = env('UPLOAD_IMG') . '/customer_personality/'.$number.'.png';
        if(file_exists($file)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            header("Content-Type: $mime");
            $domains = ['api.shilip.ai'];
            $origin = isset(request()->server()['HTTP_HOST']) ? request()->server()['HTTP_HOST'] : '';
            if( in_array($origin,$domains) ){
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Headers: Authorization,authenticated");
                header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, OPTIONS");
            }
            readfile($file);
        }
    }

    // 顯示會員五行圖片
    public function show_customer_traits_photo($type,$word)
    {
        // if( $type == 'icon' ){
        //     $file = env('UPLOAD_IMG') . '/customer_traits/'.$type.'/'.$word;
        // }else{
        //     $file = env('UPLOAD_IMG') . '/customer_traits/'.$type.'/'.$word;
        // }

        $file = env('UPLOAD_IMG') . '/customer_traits/'.$type.'/'.$word;

        if(file_exists($file)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            header("Content-Type: $mime");
            $domains = ['api.shilip.ai'];
            $origin = isset(request()->server()['HTTP_HOST']) ? request()->server()['HTTP_HOST'] : '';
            if( in_array($origin,$domains) ){
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Headers: Authorization,authenticated");
                header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, OPTIONS");
            }
            readfile($file);
        }
    }

    // 照片儲存
    static public function save_base64_photo($companyId,$photo_file,$delete_photo="")
    {
        // 先判斷資料夾是否存在
        $file_path = env('OLD_OTHER').'/'.$companyId;
        if(!file_exists($file_path)){
            $old = umask(0);
            mkdir($file_path,0775, true);
            umask($old);
        }

        // 刪除舊的照片
        if( $delete_photo ){
            $filePath = env('OLD_OTHER').'/'.$companyId.'/'.$delete_photo;
            if(file_exists($filePath)){
                unlink($filePath);
            }
        }

        if( preg_match('/base64/i',$photo_file) ){
            $data = $photo_file;
            list($type, $data) = explode(';', $data);
            list(, $data)      = explode(',', $data);
            $data      = base64_decode($data);
            $file_name = sha1(uniqid('', true));
            $picName   = $file_name.'.jpg';
            file_put_contents( env('OLD_OTHER').'/'.$companyId.'/'.$picName, $data );

            $rotate = self::image_rotate(env('OLD_OTHER').'/'.$companyId.'/'.$picName);

            // 壓縮圖片
            Image::make(env('OLD_OTHER').'/'.$companyId.'/'.$picName)->resize(1000, null, function ($constraint) {
                $constraint->aspectRatio();
            })->rotate($rotate)->save(env('OLD_OTHER').'/'.$companyId.'/'.$picName,80);

        }else{
            $picName = sha1(uniqid('', true));
            $img = Image::make($photo_file)->resize(1000, null, function ($constraint) {
                                $constraint->aspectRatio();
                            })->save(env('OtherFolderImg').'/'.request('company_id').'/'.$picName.'.jpg',80);
        }

        $rotate = self::image_rotate(env('OLD_OTHER').'/'.$companyId.'/'.$picName);

        // 壓縮圖片
        Image::make(env('OLD_OTHER').'/'.$companyId.'/'.$picName)->resize(1000, null, function ($constraint) {
            $constraint->aspectRatio();
        })->rotate($rotate)->save(env('OLD_OTHER').'/'.$companyId.'/'.$picName,80);

        return $picName;
    }

    static public function image_rotate($path){
        $rotate = 0;
        if( exif_imagetype($path) === IMAGETYPE_JPEG ){
            $image = imagecreatefromjpeg($path);
            
            @$exif = exif_read_data($path);
            if(!empty($exif) && isset($exif['Orientation'])) {
                switch($exif['Orientation']) {
                    case 8:
                        $rotate = 90;
                        break;
                    case 3:
                        $rotate = 180;
                        break;
                    case 6:
                        $rotate = -90;
                        break;
                    default:
                        $rotate = 0;
                        break;
                }
            }
        }
        
        return $rotate;
    }
}
