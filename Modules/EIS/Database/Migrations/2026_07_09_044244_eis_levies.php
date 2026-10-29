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
        Schema::create('eis_levies', function(Blueprint $table){

            $table->id();

            $table->foreignId('configuration_id')
                ->constrained('eis_configurations')
                ->cascadeOnDelete();

            $table->string('eis_levy_id');

            $table->string('name')
                ->nullable();

            $table->string('charge_mode')
                ->nullable();

            $table->decimal(
                'rate',
                10,
                2
            );

            $table->boolean('is_active')
                ->default(false);

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
        //
    }
};
