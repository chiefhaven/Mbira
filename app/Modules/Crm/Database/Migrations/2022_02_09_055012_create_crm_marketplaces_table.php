<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmMarketplacesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crm_marketplaces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id');
            $table->string('marketplace')->nullable();
            $table->string('site_key')->nullable();
            $table->string('site_id')->nullable();
            $table->text('assigned_users')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crm_marketplaces');
    }
}
