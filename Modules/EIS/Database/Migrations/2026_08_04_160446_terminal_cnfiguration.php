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
        Schema::table('eis_terminal_configurations', function (Blueprint $table) {
            $table->string('max_transaction_age')->nullable()->after('trading_name');
            $table->string('max_cummulative_amount')->nullable()->after('trading_name');
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
            $table->dropColumn('max_transaction_age');
            $table->dropColumn('max_cummulative_amount');
        });
    }
};
