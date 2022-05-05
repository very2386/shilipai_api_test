<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateShopClosesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_closes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id')->comment('所屬店家');
            $table->integer('shop_staff_id')->nullable()->comment('所屬商家員工');
            $table->integer('type')->comment('1第一週2第二週3第三週4第四週5每週6不指定');
            $table->string('week', 50)->nullable()->default('')->comment('當週星期幾公休');
            $table->dateTime('created_at')->nullable()->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('updated_at')->nullable()->default(\DB::raw('CURRENT_TIMESTAMP'));

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
        Schema::dropIfExists('shop_closes');
    }
}
