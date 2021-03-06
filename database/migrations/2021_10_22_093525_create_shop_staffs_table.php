<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateShopStaffsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_staffs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id')->comment('所屬店家');
            $table->integer('company_staff_id')->comment('納入店家員工');
            $table->integer('master')->nullable()->default(1)->comment('0老闆1員工');
            $table->string('nickname', 20)->nullable()->comment('暱稱');
            $table->integer('company_title_id_a')->nullable()->comment('職稱a');
            $table->integer('company_title_id_b')->nullable()->comment('職稱b');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->string('old_id', 20)->nullable()->comment('舊資料id');

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
        Schema::dropIfExists('shop_staffs');
    }
}
