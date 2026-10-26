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
        Schema::create('hms_extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 22, 4)->default(0);
            $table->string('price_per');
            $table->integer('business_id');
            $table->foreignId('created_by');
            $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('hms_extras');
    }
};
