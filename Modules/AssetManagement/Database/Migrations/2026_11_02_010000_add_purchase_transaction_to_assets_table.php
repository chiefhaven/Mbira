<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPurchaseTransactionToAssetsTable extends Migration
{
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('purchase_transaction_id')->nullable()->after('purchase_type');
            $table->index(['business_id', 'purchase_transaction_id'], 'assets_business_purchase_transaction_index');
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('assets_business_purchase_transaction_index');
            $table->dropColumn('purchase_transaction_id');
        });
    }
}
