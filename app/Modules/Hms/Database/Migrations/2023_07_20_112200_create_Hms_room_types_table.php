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
        Schema::create('hms_room_types', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->integer('no_of_adult');
            $table->integer('no_of_child');
            $table->integer('max_occupancy');
            $table->string('amenities')->nullable();
            $table->text('description')->nullable();
            $table->integer('business_id');
            $table->foreignId('created_by');
            $table->softDeletes();
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
        Schema::dropIfExists('hms_room_types');
    }
};
