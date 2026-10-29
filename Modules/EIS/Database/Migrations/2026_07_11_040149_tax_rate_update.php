<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_terminal_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('configuration_id')->unique();
            $table->integer('version')->nullable();
            $table->string('terminal_label', 100)->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('email_address', 100)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('trading_name', 255)->nullable();
            $table->json('address_lines')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('configuration_id')
                ->references('id')
                ->on('eis_configurations')
                ->onDelete('cascade');

            // Indexes
            $table->index('is_active');
            $table->index('trading_name');
            $table->index('email_address');
            $table->index('phone_number');
            $table->index('last_synced_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_terminal_configurations');
    }
};
