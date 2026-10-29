<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        foreach (['is_fiscalized', 'fiscal_invoice_number', 'receipt_number'] as $column) {
            if (!Schema::hasColumn('transactions', $column)) {
                Schema::table('transactions', function (Blueprint $table) use ($column) {
                    if ($column === 'is_fiscalized') {
                        $table->boolean($column)->default(false);
                    } else {
                        $table->string($column)->nullable();
                    }
                });
            }
        }
    }

    public function down()
    {
        // Compatibility repair: existing deployments may already own these columns.
    }
};
