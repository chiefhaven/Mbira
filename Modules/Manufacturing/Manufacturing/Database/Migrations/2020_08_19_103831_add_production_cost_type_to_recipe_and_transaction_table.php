<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductionCostTypeToRecipeAndTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('mfg_production_cost_type')->nullable()->after('mfg_production_cost')->default('percentage');
        });

        Schema::table('mfg_recipes', function (Blueprint $table) {
            $table->string('production_cost_type')->nullable()->after('extra_cost')->default('percentage');
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
