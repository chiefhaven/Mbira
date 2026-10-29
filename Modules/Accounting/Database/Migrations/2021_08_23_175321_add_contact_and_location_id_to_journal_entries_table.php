<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddContactAndLocationIdToJournalEntriesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropIndex('client_id_index');
                $table->dropIndex('branch_id_index');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `client_id` `contact_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `branch_id` `location_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
                $table->index('contact_id');
                $table->index('location_id');
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::transaction(function () {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropIndex('journal_entries_contact_id_index');
                $table->dropIndex('journal_entries_location_id_index');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `contact_id` `client_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
                DB::statement('ALTER TABLE `journal_entries` CHANGE `location_id` `branch_id` INT(10) UNSIGNED NULL DEFAULT NULL;');
                $table->index('client_id', 'client_id_index');
                $table->index('branch_id', 'branch_id_index');
            });
        });
    }
}