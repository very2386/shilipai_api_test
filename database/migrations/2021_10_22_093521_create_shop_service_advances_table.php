<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateShopServiceAdvancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_service_advances', function (Blueprint $table) {
            $table->increments('id');
            $table->string('shop_service_id', 20)->nullable()->comment('分店員工id');
            $table->string('shop_advance_id', 20)->nullable()->comment('分店服務品項id');
            $table->dateTime('created_at')->nullable()->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('updated_at')->nullable()->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('deleted_at')->nullable();

            $table->index('shop_service_id', 'shop_service_id');
            $table->index('shop_advance_id', 'shop_advance_id');

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_service_advances');
    }
}
