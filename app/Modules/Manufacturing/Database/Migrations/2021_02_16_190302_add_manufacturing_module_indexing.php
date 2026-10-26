<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManufacturingModuleIndexing extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('mfg_parent_production_purchase_id');
        });

        Schema::table('mfg_recipe_ingredients', function (Blueprint $table) {
            $table->index('mfg_recipe_id');
            $table->index('variation_id');
            $table->index('sub_unit_id');
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
