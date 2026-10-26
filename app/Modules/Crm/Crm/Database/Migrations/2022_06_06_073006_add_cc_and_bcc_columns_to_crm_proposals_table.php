<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCcAndBccColumnsToCrmProposalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('crm_proposals', function (Blueprint $table) {
            $table->text('cc')->nullable()->after('body');
            $table->text('bcc')->nullable()->after('cc');
        });

        Schema::table('crm_proposal_templates', function (Blueprint $table) {
            $table->text('cc')->nullable()->after('body');
            $table->text('bcc')->nullable()->after('cc');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
