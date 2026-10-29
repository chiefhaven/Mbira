<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJournalEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('created_by_id')->unsigned()->nullable();
            $table->string('transaction_number')->nullable();
            $table->bigInteger('payment_detail_id')->unsigned()->nullable();
            $table->bigInteger('branch_id')->unsigned()->nullable();
            $table->bigInteger('currency_id')->unsigned()->nullable();
            $table->bigInteger('chart_of_account_id')->unsigned()->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('transaction_sub_type')->nullable();
            $table->text('name')->nullable();
            $table->date('date')->nullable();
            $table->string('month')->nullable();
            $table->string('year')->nullable();
            $table->string('reference')->nullable();
            $table->integer('client_id')->nullable();
            $table->decimal('debit', 65, 4)->nullable();
            $table->decimal('credit', 65, 4)->nullable();
            $table->decimal('balance', 65, 4)->nullable();
            $table->tinyInteger('active')->default(1);
            $table->tinyInteger('reversed')->default(0);
            $table->tinyInteger('reversible')->default(1);
            $table->tinyInteger('manual_entry')->default(0);
            $table->string('receipt')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('branch_id', 'branch_id_index');
            $table->index('chart_of_account_id', 'chart_of_account_id_index');
            $table->index('currency_id', 'currency_id_index');
            $table->index('created_by_id', 'created_by_id_index');
            $table->index('client_id', 'client_id_index');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journal_entries');
    }
}
