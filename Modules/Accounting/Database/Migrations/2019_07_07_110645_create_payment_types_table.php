<?php

use Modules\Accounting\Entities\PaymentType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreatePaymentTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('payment_types');
        
        Schema::create('payment_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('business_id');
            $table->string('name');
            $table->string('system_name')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('is_cash')->default(0);
            $table->tinyInteger('is_online')->default(0);
            $table->tinyInteger('is_system')->default(0);
            $table->tinyInteger('active')->default(1);
            $table->integer('position')->nullable();
            $table->text('options')->nullable();
            $table->string('unique_id')->nullable();
            $table->timestamps();
        });

        $payment_types = array(
            array('id' => '1', 'name' => 'Cash', 'business_id' => 0, 'system_name' => NULL, 'description' => NULL, 'is_cash' => '0', 'is_online' => '0', 'is_system' => '0', 'active' => '1', 'position' => NULL, 'options' => NULL, 'unique_id' => NULL, 'created_at' => date('Y-m-d'), 'updated_at' => date('Y-m-d')),
            array('id' => '2', 'name' => 'Ecocash', 'business_id' => 0, 'system_name' => NULL, 'description' => 'ecocash', 'is_cash' => '0', 'is_online' => '0', 'is_system' => '0', 'active' => '1', 'position' => '1', 'options' => NULL, 'unique_id' => NULL, 'created_at' => date('Y-m-d'), 'updated_at' => date('Y-m-d')),
            array('id' => '3', 'name' => 'Mpesa', 'business_id' => 0, 'system_name' => NULL, 'description' => 'Pay via Mpesa', 'is_cash' => '0', 'is_online' => '0', 'is_system' => '0', 'active' => '1', 'position' => NULL, 'options' => NULL, 'unique_id' => NULL, 'created_at' => date('Y-m-d'), 'updated_at' => date('Y-m-d'))
        );

        if (!PaymentType::where('business_id', 0)->exists()) {
            DB::table('payment_types')->insert($payment_types);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_types');
    }
}
