<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Migration auto-generated by Sequel Pro Laravel Export (1.5.0)
 * @see https://github.com/cviebrock/sequel-pro-laravel-export
 */
class CreatePhoneCheckTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_check', function (Blueprint $table) {
            $table->increments('id');
            $table->string('phone', 10)->nullable();
            $table->string('phone_check', 10)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('created_at')->nullable();

            

            

        });

        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_check');
    }
}