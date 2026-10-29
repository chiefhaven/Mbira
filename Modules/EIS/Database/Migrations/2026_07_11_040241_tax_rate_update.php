<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_terminal_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_configuration_id')->unique();
            $table->string('site_id', 50)->nullable();
            $table->string('site_name', 255)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('terminal_configuration_id')
                ->references('id')
                ->on('terminal_configurations')
                ->onDelete('cascade');

            // Indexes
            $table->index('site_id');
            $table->index('site_name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_terminal_sites');
    }
};