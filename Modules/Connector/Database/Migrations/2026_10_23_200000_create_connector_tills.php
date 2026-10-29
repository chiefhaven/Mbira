<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('connector_tills', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('location_id')->index();
            $table->unsignedInteger('eis_setting_id')->nullable();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'location_id', 'name']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('connector_till_id')->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['connector_till_id']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('connector_till_id');
        });
        Schema::dropIfExists('connector_tills');
    }
};
