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
        Schema::table('eis_configurations', function (Blueprint $table) {
            $table->string('tpin')->nullable()->after('taxpayer_version');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eis_configurations', function (Blueprint $table) {
            $table->dropColumn('tpin');
        });
    }
};
