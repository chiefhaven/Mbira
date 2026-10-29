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
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->unsignedBigInteger('configuration_id');
            $table->string('tax_rate_id', 50);
            $table->string('charge_mode', 20)->default('Item');
            $table->integer('ordinal')->default(100);
            $table->decimal('rate', 10, 3)->default(0);
            $table->boolean('is_activated')->default(false);
            $table->string('activation_id', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn([
                'configuration_id',
                'tax_rate_id',
                'charge_mode',
                'ordinal',
                'rate',
                'is_activated',
                'activation_id'
            ]);
        });
    }
};
