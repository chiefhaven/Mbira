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
            $table->timestamp('last_sync_at')->nullable();
            $table->string('sync_status')->default('pending')->after('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eis_settings', function (Blueprint $table) {
            $table->dropColumn('last_sync_at');
            $table->dropColumn('sync_status');
        });
    }
};
