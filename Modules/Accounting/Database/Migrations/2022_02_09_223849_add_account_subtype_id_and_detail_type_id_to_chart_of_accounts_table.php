<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAccountSubtypeIdAndDetailTypeIdToChartOfAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->bigInteger('account_subtype_id')->unsigned()->nullable()->after('payment_type_id');
            $table->bigInteger('detail_type_id')->unsigned()->nullable()->after('account_subtype_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('account_subtype_id');
            $table->dropColumn('detail_type_id');
        });
    }
}
