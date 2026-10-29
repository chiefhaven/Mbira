<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eis_terminal_configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('eis_terminal_configurations', 'max_transaction_age_hours')) {
                $table->integer('max_transaction_age_hours')->nullable()->after('trading_name');
            }

            if (!Schema::hasColumn('eis_terminal_configurations', 'max_cumulative_amount')) {
                $table->decimal('max_cumulative_amount', 15, 2)->nullable()->after('trading_name');
            }
        });

        DB::table('eis_terminal_configurations')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $age = $row->max_transaction_age_hours ?? $row->max_transaction_age ?? 72;
                $amount = $row->max_cumulative_amount ?? $row->max_cummulative_amount ?? 0;

                DB::table('eis_terminal_configurations')
                    ->where('id', $row->id)
                    ->update([
                        'max_transaction_age_hours' => $age,
                        'max_cumulative_amount' => $amount,
                    ]);
            }
        });

        DB::table('eis_offline_limits')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $age = $row->max_transaction_age_hours ?? $row->max_transaction_age ?? 72;
                $amount = $row->max_cumulative_amount ?? $row->max_cummulative_amount ?? 0;

                DB::table('eis_offline_limits')
                    ->where('id', $row->id)
                    ->update([
                        'max_transaction_age_hours' => $age,
                        'max_cumulative_amount' => $amount,
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eis_terminal_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('eis_terminal_configurations', 'max_transaction_age_hours')) {
                $table->dropColumn('max_transaction_age_hours');
            }

            if (Schema::hasColumn('eis_terminal_configurations', 'max_cumulative_amount')) {
                $table->dropColumn('max_cumulative_amount');
            }
        });
    }
};
