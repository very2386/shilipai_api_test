<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\ToolController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// 舊資料搬移至新資料庫
Route::get('/test/update/database',[TestController::class, 'update_database']);
// 圖片整理
Route::get('/test/sort/out/photo',[TestController::class, 'sort_out_photo']);
// img to base64
Route::get('/test/imgto64',[TestController::class, 'imgto64']);
// 檢查tag是否在指定區域內
Route::get('/test/point_in',[TestController::class, 'point_in']);
// 測試簡訊api
Route::get('/test/send/message',[TestController::class, 'send_message']);
// 測試導頁
Route::get('/test/redirect',function(){
	return redirect('https://www.google.com');
});
// 測試金流
Route::get('/test/pay',[TestController::class, 'test_pay']);

// 上傳檔案建立會員資料頁面
Route::get('/customer/file/upload', [ToolController::class , 'customer_file_upload']);
// 上傳檔案建立會員資料儲存
Route::post('/customer/file/upload/save', [ToolController::class, 'customer_file_upload_save']);
