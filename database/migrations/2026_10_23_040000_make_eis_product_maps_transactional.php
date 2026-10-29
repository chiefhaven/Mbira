<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasTable('eis_product_maps')) {
            $table = str_replace('`', '``', DB::connection()->getTablePrefix().'eis_product_maps');
            DB::statement('ALTER TABLE `'.$table.'` ENGINE=InnoDB');
        }
    }

    public function down(): void
    {
        // Retain transaction safety; reverting to MyISAM would permit partial sync commits.
    }
};
