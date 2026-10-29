<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eis_sales')) {
            return;
        }

        Schema::table('eis_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('eis_sales', 'void_status')) {
                $table->string('void_status')->nullable()->index();
            }
            if (!Schema::hasColumn('eis_sales', 'void_approval_status')) {
                $table->string('void_approval_status')->nullable();
            }
            if (!Schema::hasColumn('eis_sales', 'void_synced_at')) {
                $table->timestamp('void_synced_at')->nullable();
            }
            if (!Schema::hasColumn('eis_sales', 'void_response_payload')) {
                $table->json('void_response_payload')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Keep synchronized receipt history when rolling back application code.
    }
};
