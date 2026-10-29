<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_stock_operations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('destination_location_id')->nullable();
            $table->unsignedBigInteger('eis_setting_id');
            $table->unsignedBigInteger('source_stock_adjustment_id')->nullable();
            $table->string('operation_type', 32);
            $table->string('status', 20)->default('queued');
            $table->string('site_id');
            $table->longText('request_payload');
            $table->longText('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'location_id', 'status'], 'eis_stock_ops_location_status_idx');
            $table->index(['business_id', 'destination_location_id', 'status'], 'eis_stock_ops_destination_status_idx');
            $table->unique('source_stock_adjustment_id', 'eis_stock_ops_adjustment_unique');
        });

        Schema::create('eis_stock_operation_lines', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('eis_stock_operation_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('barcode');
            $table->decimal('quantity', 18, 4);
            $table->string('status', 20)->default('queued');
            $table->longText('request_payload');
            $table->longText('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['eis_stock_operation_id', 'status'], 'eis_stock_op_lines_status_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_stock_operation_lines');
        Schema::dropIfExists('eis_stock_operations');
    }
};
