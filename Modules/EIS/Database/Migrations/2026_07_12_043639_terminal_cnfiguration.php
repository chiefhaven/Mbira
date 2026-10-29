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
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_response')->nullable()->after('confirmed_at');
        });

        // Drop terminal_configurations table if it exists
        Schema::dropIfExists('terminal_configurations');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eis_terminal_configurations', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
            $table->dropColumn('confirmation_response');
        });
    }
};
