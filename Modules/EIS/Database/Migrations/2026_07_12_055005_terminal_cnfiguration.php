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
        Schema::table('eis_settings', function (Blueprint $table) {
            //$table->dropColumn('base_url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eis_terminal_configurations', function (Blueprint $table) {
            $table->string('base_url')->nullable();
        });
    }
};
