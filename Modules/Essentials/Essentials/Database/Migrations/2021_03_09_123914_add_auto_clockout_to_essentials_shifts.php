<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAutoClockoutToEssentialsShifts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('essentials_shifts', function (Blueprint $table) {
            $table->boolean('is_allowed_auto_clockout')
                ->after('end_time')
                ->default(false);

            $table->time('auto_clockout_time')
                ->after('is_allowed_auto_clockout')
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
