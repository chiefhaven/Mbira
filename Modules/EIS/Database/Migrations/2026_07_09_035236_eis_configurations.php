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
        Schema::create('eis_configurations', function (Blueprint $table) {

            $table->id();
            $table->string('business_id');
            $table->integer('global_version')->nullable();
            $table->integer('terminal_version')->nullable();
            $table->integer('taxpayer_version')->nullable();
            $table->json('global_configuration')
                ->nullable();
            $table->json('terminal_configuration')
                ->nullable();
            $table->json('taxpayer_configuration')
                ->nullable();
            $table->timestamp('last_synced_at')
                ->nullable();
            $table->timestamps();

        });

        Schema::create('eis_tax_rates', function(Blueprint $table){

            $table->id();
            $table->foreignId('configuration_id')
                ->constrained('eis_configurations')
                ->cascadeOnDelete();
            $table->string('eis_tax_rate_id');
            $table->string('name');
            $table->string('charge_mode')
                ->nullable();
            $table->decimal('rate',10,2);
            $table->integer('ordinal')
                ->nullable();
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
        Schema::dropIfExists('eis_configurations');
        Schema::dropIfExists('eis_tax_rates');
    }
};
