<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_sale_adjustments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('eis_setting_id');
            $table->unsignedInteger('sale_id');
            $table->unsignedInteger('return_id');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('submitted_by')->nullable();
            $table->string('endpoint');
            $table->string('tin');
            $table->string('site_id');
            $table->string('device_id');
            $table->string('status', 30);
            $table->string('note_type', 20);
            $table->string('invoice_number')->nullable();
            $table->longText('payload');
            $table->longText('local_snapshot');
            $table->longText('response')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'sale_id', 'status'], 'eis_sale_adjustment_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_sale_adjustments');
    }
};
