<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('land_mark')->nullable()->after('contact_type');
            $table->string('street_name')->nullable()->after('land_mark');
            $table->string('building_number')->nullable()->after('street_name');
            $table->string('additional_number')->nullable()->after('building_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contact', function (Blueprint $table) {
            //
        });
    }
};
