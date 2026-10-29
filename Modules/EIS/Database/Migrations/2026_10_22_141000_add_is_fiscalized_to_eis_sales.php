<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eis_sales', function (Blueprint $table) {
            // Add is_fiscalized field to track if sale has been successfully fiscalized
            if (!Schema::hasColumn('eis_sales', 'is_fiscalized')) {
                $table->boolean('is_fiscalized')
                    ->default(false)
                    ->after('status')
                    ->comment('Flag indicating if sale has been successfully fiscalized to EIS');
            }

            // Add fiscalized_at timestamp to track when sale was fiscalized
            if (!Schema::hasColumn('eis_sales', 'fiscalized_at')) {
                $table->timestamp('fiscalized_at')
                    ->nullable()
                    ->after('submitted_at')
                    ->comment('Timestamp when sale was successfully fiscalized');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eis_sales', function (Blueprint $table) {
            if (Schema::hasColumn('eis_sales', 'is_fiscalized')) {
                $table->dropColumn('is_fiscalized');
            }

            if (Schema::hasColumn('eis_sales', 'fiscalized_at')) {
                $table->dropColumn('fiscalized_at');
            }
        });
    }
};
