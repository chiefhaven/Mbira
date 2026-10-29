<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eis_failed_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('transaction_id');

            $table->json('payload');
            $table->text('error_message')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'transaction_id']);
            $table->index('next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eis_failed_transactions');
    }
};