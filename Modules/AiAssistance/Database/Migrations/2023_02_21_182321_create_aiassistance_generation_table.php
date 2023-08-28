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
        Schema::create('aiassistance_history', function (Blueprint $table) {
            $table->id();

            $table->integer('business_id');
            $table->integer('user_id');
            $table->string('tool_type');
            $table->text('input_data')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->text('output_data')->nullable();

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
        Schema::dropIfExists('aiassistance_history');
    }
};
