<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreatePermissionsMoudleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permissions_moudle', function (Blueprint $table) {
            $table->unsignedInteger('id');
            $table->integer('company_id')->nullable()->comment('集團');
            $table->integer('shop_id')->nullable()->comment('商家');
            $table->string('moudle_name', 255)->comment('權限名稱');
            $table->text('permission')->nullable()->comment('對應可使用的權限');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->primary('id');
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
        Schema::dropIfExists('permissions_moudle');
    }
}
