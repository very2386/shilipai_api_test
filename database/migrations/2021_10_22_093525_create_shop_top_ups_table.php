<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateShopTopUpsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_top_ups', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id')->nullable()->comment('所屬商家');
            $table->integer('company_top_up_id')->nullable()->comment('所屬集團方案id');
            $table->enum('status', ['pending', 'published'])->nullable()->default('pending')->comment('上下架');
            $table->integer('view')->nullable()->default(0)->comment('觀看次數');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index('shop_id', 'shop_id');
            $table->index('company_top_up_id', 'company_top_up_id');

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_top_ups');
    }
}