<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        // Schema creation is handled by the subsequent EnsureAccountingSourcePostingsTable migration.
    }

    public function down()
    {
        // Preserve posting snapshots and history when rolling back application code.
    }
};