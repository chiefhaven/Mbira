<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eis_tax_mappings', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('business_id');

            // Local POS tax
            $table->unsignedBigInteger('tax_id');

            // EIS tax identifier (VERY IMPORTANT)
            $table->string('eis_tax_rate_id');

            $table->string('tax_name')->nullable();

            $table->decimal('rate', 5, 2)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['business_id', 'tax_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eis_tax_mappings');
    }
};