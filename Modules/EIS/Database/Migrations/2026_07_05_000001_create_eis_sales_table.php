<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eis_sales', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('transaction_id');

            $table->string('invoice_number')->nullable();

            // EIS References
            $table->string('fiscal_invoice_number')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('receipt_signature')->nullable();

            $table->text('qr_code')->nullable();

            $table->enum('status', [
                'pending',
                'submitted',
                'failed',
                'cancelled'
            ])->default('pending');

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index('business_id');
            $table->index('transaction_id');
            $table->index('status');

            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eis_sales');
    }
};