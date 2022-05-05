<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreateCustomerProgramLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_program_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_program_id')->nullable()->comment('顧客購買的方案');
            $table->integer('bill_id')->nullable()->comment('帳單');
            $table->integer('group_id')->nullable()->comment('群組id');
            $table->string('commodity_type', 10)->nullable()->comment('product/service');
            $table->integer('commodity_id')->nullable()->comment('產品/服務id');
            $table->integer('count')->nullable()->comment('數量');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_program_logs');
    }
}
