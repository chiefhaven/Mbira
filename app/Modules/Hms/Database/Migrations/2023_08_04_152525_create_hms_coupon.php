<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hms_coupons', function (Blueprint $table) {
            $table->id();
            $table->integer('hms_room_type_id');
            $table->integer('business_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('coupon_code');
            $table->decimal('discount');
            $table->string('discount_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('');
    }
};
