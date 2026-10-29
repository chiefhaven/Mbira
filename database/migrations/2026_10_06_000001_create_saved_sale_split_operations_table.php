<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('saved_sale_split_operations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            $table->uuid('operation_id');
            $table->unsignedInteger('source_id');
            $table->unsignedInteger('result_id')->nullable();
            $table->char('payload_hash', 64);
            $table->char('expected_version', 64);
            $table->longText('allocation_json');
            $table->longText('response_json')->nullable();
            $table->timestamps();
            // Operation UUID belongs to one tenant/actor/payload, not just one bill.
            $table->unique(['business_id', 'operation_id'], 'saved_split_operation_unique');
            $table->index(['business_id', 'source_id']);
            $table->index(['business_id', 'result_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('saved_sale_split_operations');
    }
};