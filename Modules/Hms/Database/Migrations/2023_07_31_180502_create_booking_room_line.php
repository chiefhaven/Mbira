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
        Schema::create('hms_booking_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('transaction_id');
            $table->integer('hms_room_id');
            $table->foreignId('hms_room_type_id');
            $table->integer('adults');
            $table->integer('childrens');
            $table->decimal('price', 22, 4)->default(0);
            $table->decimal('total_price', 22, 4)->default(0);
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
