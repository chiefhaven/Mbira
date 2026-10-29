<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('eis_settings', 'base_url')) {
            Schema::table('eis_settings', function (Blueprint $table) {
                $table->string('base_url')->nullable();
            });
        }

        $baseUrl = trim((string) config('eis.base_url'));
        if ($baseUrl !== '') {
            DB::table('eis_settings')->where(function ($query) {
                $query->whereNull('base_url')->orWhere('base_url', '');
            })->update(['base_url' => $baseUrl]);
        }
    }

    public function down(): void
    {
        // Retain the original-schema column and any pre-existing endpoint values.
    }
};
