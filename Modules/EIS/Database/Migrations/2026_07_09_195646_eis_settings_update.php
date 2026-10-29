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
            // Add new columns for better tracking
            $table->timestamp('last_sync_attempt')->nullable()->after('last_sync_at');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->timestamp('sync_error_retry_after')->nullable()->after('sync_error');
            $table->integer('successful_syncs')->default(0)->after('sync_error_retry_after');
            $table->integer('failed_syncs')->default(0)->after('successful_syncs');
            
            // Version tracking
            $table->integer('global_version')->nullable()->after('failed_syncs');
            $table->integer('terminal_version')->nullable()->after('global_version');
            $table->integer('taxpayer_version')->nullable()->after('terminal_version');
            
            // Add indexes
            $table->index(['status', 'sync_status', 'last_sync_at']);
            $table->index(['sync_status', 'sync_error_retry_after']);
            $table->index('business_id');
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
            $table->dropColumn([
                'last_sync_attempt',
                'sync_status',
                'sync_error',
                'sync_error_retry_after',
                'successful_syncs',
                'failed_syncs',
                'global_version',
                'terminal_version',
                'taxpayer_version'
            ]);
        });
    }
};
