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
        Schema::create('hms_room_type_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hms_room_type_id');
            $table->string('season_type');
            $table->decimal('default_price_per_night', 22, 4)->nullable();
            $table->decimal('adults')->nullable();
            $table->decimal('childrens')->nullable();
            $table->decimal('price_monday', 22, 4)->nullable();
            $table->decimal('price_tuesday', 22, 4)->nullable();
            $table->decimal('price_wednesday', 22, 4)->nullable();
            $table->decimal('price_thursday', 22, 4)->nullable();
            $table->decimal('price_friday', 22, 4)->nullable();
            $table->decimal('price_saturday', 22, 4)->nullable();
            $table->decimal('price_sunday', 22, 4)->nullable();
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
        Schema::dropIfExists('hms_room_type_pricings');
    }
};
