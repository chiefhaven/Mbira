<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'vat5_certificate_details')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->json('vat5_certificate_details')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('vat5_certificate_details');
        });
    }
};