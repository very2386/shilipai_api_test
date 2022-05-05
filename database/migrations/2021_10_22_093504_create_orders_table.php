<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('oid', 20)->default('')->comment('訂單編號');
            $table->integer('user_id')->nullable()->comment('購買者');
            $table->integer('company_id')->comment('購買company');
            $table->integer('buy_mode_id')->nullable()->comment('購買方案id');
            $table->integer('member_addresses_id')->nullable()->comment('送貨地址');
            $table->integer('code')->nullable()->comment('推薦人user id');
            $table->integer('discount_id')->nullable()->comment('優惠id');
            $table->integer('price')->nullable()->comment('總金額');
            $table->string('order_note', 255)->nullable()->comment('訂單備註');
            $table->text('pay_return')->nullable()->comment('金流回傳');
            $table->char('pay_status', 1)->default('N')->comment('付款狀態');
            $table->dateTime('pay_date')->nullable()->comment('付款日期');
            $table->string('pay_type', 10)->nullable()->default('')->comment('付款方式');
            $table->string('message', 50)->nullable()->comment('回傳訊息');
            $table->string('note', 255)->nullable()->comment('備註');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable();

            $table->index('oid', 'oid');
            $table->index('company_id', 'store_id');
            $table->index('buy_mode_id', 'buy_mode_id');
            $table->index('discount_id', 'dicount_id');

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
