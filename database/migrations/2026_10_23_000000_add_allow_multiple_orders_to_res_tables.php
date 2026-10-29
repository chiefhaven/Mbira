<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('res_tables', function (Blueprint $table) {
            $table->boolean('allow_multiple_orders')->default(false);
        });
    }

    public function down()
    {
        Schema::table('res_tables', function (Blueprint $table) {
            $table->dropColumn('allow_multiple_orders');
        });
    }
};
