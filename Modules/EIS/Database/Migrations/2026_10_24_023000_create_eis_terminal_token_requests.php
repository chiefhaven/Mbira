<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_terminal_token_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('eis_setting_id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('created_by');
            $table->string('endpoint');
            $table->string('status', 30);
            $table->text('result_message')->nullable();
            $table->timestamps();
            $table->index(['eis_setting_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_terminal_token_requests');
    }
};
