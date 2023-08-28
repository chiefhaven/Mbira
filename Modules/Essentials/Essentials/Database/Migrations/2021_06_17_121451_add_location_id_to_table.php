<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationIdToTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('location_id')
                ->after('id_proof_number')
                ->comment('user primary work location')
                ->nullable();
        });

        Schema::table('essentials_payroll_groups', function (Blueprint $table) {
            $table->integer('location_id')
                ->after('business_id')
                ->comment('payroll for work location')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
