<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\AlbumPhoto;
use App\Models\Photo;
use App\Models\Shop;
use App\Models\ShopPost;

class ClearPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear_posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每天清除已過期的貼文資料';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $start = microtime(date('Y-m-d H:i:s'));
        
        $shop_posts = ShopPost::get();

        foreach( $shop_posts as $shop_post ){

            $shop_info    = Shop::find($shop_post->shop_id);
            $company_info = $shop_info->company_info;

            if( $shop_info->buy_mode_id == 0 ){
                // 基本版
                $day = 30;
            }else{
                // plus、pro版
                $day = 365;
            }

            if( time() - strtotime($shop_post->updated_at) < $day * 86400 ){
                continue;
            }

            // 刪除貼文相簿與照片
            if( $shop_post->post_album ){
                $delete_photos = AlbumPhoto::where('album_id',$shop_post->post_album->id)->join('photos', 'album_photos.photo_id', '=', 'photos.id')->get();
                foreach( $delete_photos as $photo ){
                    // 先檢查此張照片是否有被其他相簿存放，若有就只刪除相簿關連，若都沒有其他相簿存放，就直接刪除照片
                    $photo_count = AlbumPhoto::where('photo_id',$photo->photo_id)->get()->count();
                    if( $photo_count == 1 ){
                        // 刪除檔案
                        $filePath = env('OLD_OTHER').'/'. $shop_info->alias .'/'.$photo->photo;
                        if(file_exists($filePath)){
                            unlink($filePath);
                        }
                        Photo::find($photo->photo_id)->delete();
                        $photo->delete();
                    }
                }
            
                // 刪除貼文相簿
                $shop_post->post_album->delete();
            }

            // 刪除商家貼文資料
            $shop_post->delete();
        }

        $end = microtime(date('Y-m-d H:i:s'));
        $this->info('每天清除已過期的貼文資料完成'.( $end - $start ));
    }
}
