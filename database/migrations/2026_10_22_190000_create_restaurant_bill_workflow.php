<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restaurant_bills', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->unsignedInteger('transaction_id')->primary();
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('location_id');
            $t->unsignedInteger('table_id');
            $t->unsignedInteger('waiter_id');
            $t->string('state', 24)->default('open');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['business_id', 'location_id', 'state']);
        });
        Schema::create('restaurant_tickets', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->bigIncrements('id');
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('variation_id');
            $t->string('station', 16);
            $t->decimal('quantity', 22, 4);
            $t->decimal('original_quantity', 22, 4);
            $t->string('status', 16)->default('received');
            $t->unsignedInteger('created_by');
            $t->unsignedInteger('completed_by')->nullable();
            $t->timestamps();
            $t->index(['transaction_id', 'variation_id']);
        });
        Schema::create('restaurant_approvals', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->bigIncrements('id');
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('requested_by');
            $t->unsignedInteger('approved_by')->nullable();
            $t->char('expected_version', 64);
            $t->longText('items_json');
            $t->string('reason', 500);
            $t->string('disposition', 16);
            $t->string('status', 16)->default('pending');
            $t->timestamps();
            $t->index(['transaction_id', 'status']);
        });
        Schema::create('restaurant_operations', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('user_id');
            $t->uuid('operation_id');
            $t->char('payload_hash', 64);
            $t->longText('response_json');
            $t->timestamps();
            $t->unique(['business_id', 'operation_id']);
        });
        Schema::create('restaurant_waste', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->bigIncrements('id');
            $t->unsignedBigInteger('approval_id');
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('variation_id');
            $t->unsignedInteger('purchase_line_id')->nullable();
            $t->decimal('quantity', 22, 4);
            $t->timestamps();
        });
        foreach (['restaurant.kitchen', 'restaurant.bar', 'restaurant.approve_void'] as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        Schema::dropIfExists('restaurant_waste');
        Schema::dropIfExists('restaurant_operations');
        Schema::dropIfExists('restaurant_approvals');
        Schema::dropIfExists('restaurant_tickets');
        Schema::dropIfExists('restaurant_bills');
    }
};
