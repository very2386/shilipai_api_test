<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateShopsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->comment('所屬公司');
            $table->string('alias', 20)->nullable()->comment('別名');
            $table->string('name', 50)->nullable()->comment('預設同conpany name');
            $table->string('phone', 50)->nullable()->comment('預約電話');
            $table->string('address', 100)->nullable()->comment('店家地址');
            $table->string('logo', 255)->nullable()->comment('店家logo');
            $table->string('banner', 255)->nullable()->comment('店家背景圖');
            $table->text('info')->nullable()->comment('店家介紹');
            $table->string('line', 100)->nullable()->comment('line id');
            $table->text('line_url')->nullable()->comment('line連結');
            $table->string('facebook_name', 100)->nullable()->comment('facebook粉絲團名稱');
            $table->text('facebook_url')->nullable()->comment('facebook粉絲團網址');
            $table->string('ig', 100)->nullable()->comment('ig id');
            $table->text('ig_url')->nullable()->comment('ig 連結');
            $table->string('web_name', 100)->nullable()->comment('官方網站名稱');
            $table->text('web_url')->nullable()->comment('官方網站網址');
            $table->enum('status', ['pending', 'published'])->default('published')->comment('狀態 pending:下架 published:上架');
            $table->integer('operating_status_id')->nullable()->comment('方案操作狀態');
            $table->integer('photo_limit')->nullable()->default(5000)->comment('照片上傳最大數量');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index('company_id', 'company_id');

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shops');
    }
}
