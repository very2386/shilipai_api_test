<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreatePermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->integer('company_id')->nullable()->comment('集團');
            $table->integer('shop_id')->nullable()->comment('商家');
            $table->integer('shop_staff_id')->nullable()->comment('員工');
            $table->integer('buy_mode_id')->nullable()->comment('購買方案');
            $table->text('permission')->nullable()->comment('對應可使用的權限');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index('user_id', 'user_id');
            $table->index('company_id', 'company_id');
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
        Schema::dropIfExists('permissions');
    }
}
