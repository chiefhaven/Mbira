<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'authorization_code')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('authorization_code', 255)->nullable()->after('tax_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('authorization_code');
        });
    }
};