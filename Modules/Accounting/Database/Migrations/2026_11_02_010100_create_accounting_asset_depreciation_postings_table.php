<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountingAssetDepreciationPostingsTable extends Migration
{
    public function up()
    {
        Schema::create('accounting_asset_depreciation_postings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id');
            $table->unsignedInteger('asset_id');
            $table->date('period');
            $table->decimal('amount', 22, 4);
            $table->integer('created_by');
            $table->timestamps();
            $table->unique(['business_id', 'asset_id', 'period'], 'asset_depreciation_period_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounting_asset_depreciation_postings');
    }
}
