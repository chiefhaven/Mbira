<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('eis_product_maps', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // SaaS isolation
            $table->unsignedBigInteger('business_id')->index();

            // Local POS product
            $table->unsignedBigInteger('product_id')->index();

            // EIS product identifier (from MRA system)
            $table->string('eis_product_id')->index();

            // Optional identifiers
            $table->string('sku')->nullable()->index();

            // Sync tracking
            $table->timestamp('last_synced_at')->nullable();

            // Status tracking
            $table->string('sync_status')->default('synced'); 
            // synced | pending | failed

            // Optional version control (useful for delta sync)
            $table->timestamp('eis_updated_at')->nullable();

            // Raw last response (debugging / audit)
            $table->json('meta')->nullable();

            $table->timestamps();

            // IMPORTANT: prevent duplicates per tenant
            $table->unique(['business_id', 'eis_product_id']);
            $table->unique(['business_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eis_product_maps');
    }
};