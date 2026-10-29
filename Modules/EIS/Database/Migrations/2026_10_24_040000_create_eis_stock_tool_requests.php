<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_stock_tool_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('eis_setting_id');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('submitted_by')->nullable();
            $table->string('operation', 40);
            $table->string('status', 30);
            $table->uuid('request_key')->unique();
            $table->char('identity', 64)->unique();
            $table->string('endpoint');
            $table->string('tin');
            $table->string('site_id');
            $table->string('device_id');
            $table->longText('payload');
            $table->longText('response')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_stock_tool_requests');
    }
};
