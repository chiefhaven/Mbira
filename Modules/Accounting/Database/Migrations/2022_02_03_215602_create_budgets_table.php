<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBudgetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id');
            $table->bigInteger('chart_of_account_id')->unsigned();
            $table->string('financial_year');
            $table->float('month_1');
            $table->float('month_2');
            $table->float('month_3');
            $table->float('month_4');
            $table->float('month_5');
            $table->float('month_6');
            $table->float('month_7');
            $table->float('month_8');
            $table->float('month_9');
            $table->float('month_10');
            $table->float('month_11');
            $table->float('month_12');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('budgets');
    }
}
