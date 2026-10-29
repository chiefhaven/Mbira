<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('eis_stock_reconciliations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('eis_setting_id');
            $table->string('site_id');
            $table->unsignedInteger('created_by');
            $table->string('status', 20)->default('preview');
            $table->longText('inventory');
            $table->longText('lines');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('eis_stock_reconciliations');
    }
};
