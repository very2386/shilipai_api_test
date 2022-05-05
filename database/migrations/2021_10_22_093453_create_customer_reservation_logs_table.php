<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateCustomerReservationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_reservation_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id')->nullable()->comment('所屬顧客');
            $table->integer('customer_reservation_id')->nullable()->comment('所屬預約');
            $table->char('type', 2)->nullable()->comment('shop_cancel商家取消customer_cancel會員取消customer_change客戶變更shop_change商家修改');
            $table->text('before')->nullable()->comment('修改前預約內容');
            $table->text('after')->nullable()->comment('修改後預約內容');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index('customer_id', 'customer_id');
            $table->index('customer_reservation_id', 'customer_reservation_id');

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_reservation_logs');
    }
}
