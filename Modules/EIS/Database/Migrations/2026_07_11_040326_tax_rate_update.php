<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_offline_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('terminal_configuration_id')->unique();
            $table->integer('max_transaction_age_hours')->default(72);
            $table->decimal('max_cumulative_amount', 15, 2)->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('terminal_configuration_id')
                ->references('id')
                ->on('terminal_configurations')
                ->onDelete('cascade');

            // Indexes
            $table->index('max_transaction_age_hours');
            $table->index('max_cumulative_amount');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_offline_limits');
    }
};