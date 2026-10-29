<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eis_configurations', function (Blueprint $table) {
            $table->boolean('is_vat_registered')->default(false)->after('tpin');
            $table->string('tax_office_code')->nullable()->after('is_vat_registered');
            $table->json('raw_response')->nullable()->after('tax_office_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eis_configurations', function (Blueprint $table) {
            $table->dropColumn(['tpin', 'is_vat_registered', 'tax_office_code', 'raw_response']);
        });
    }
};
