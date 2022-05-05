<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateShopReservationTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_reservation_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id')->nullable()->comment('所屬商家');
            $table->integer('type')->nullable()->comment('1提早2小遲到3大遲到4爽約');
            $table->string('name', 50)->nullable()->comment('標籤名稱');
            $table->integer('times')->nullable()->comment('次數');
            $table->char('blacklist', 1)->nullable()->default('N')->comment('Y列入N不列入');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index('shop_id', 'shop_id');

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_reservation_tags');
    }
}