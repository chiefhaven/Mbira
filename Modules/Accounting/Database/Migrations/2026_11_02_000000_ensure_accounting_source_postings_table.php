<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureAccountingSourcePostingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('accounting_source_postings')) {
            Schema::create('accounting_source_postings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('business_id');
                $table->string('source_type', 20);
                $table->unsignedBigInteger('source_id');
                $table->string('fingerprint', 64)->nullable();
                $table->longText('snapshot')->nullable();
                $table->timestamps();
                $table->unique(
                    ['business_id', 'source_type', 'source_id'],
                    'accounting_source_unique'
                );
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $tableStatus = DB::selectOne(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['accounting_source_postings']
            );

            if ($tableStatus !== null && strtoupper($tableStatus->ENGINE) !== 'INNODB') {
                DB::statement('ALTER TABLE accounting_source_postings ENGINE=InnoDB');
            }
        }
    }

    public function down()
    {
        // Keep existing posting snapshots and history when rolling back application code.
    }
}
