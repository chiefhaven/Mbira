<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Entities\AccountSubtype;

class PopulateAccountSubtypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $timestamp = date('Y-m-d H:i:s');

        $account_subtypes = [
            0 => [
                'id' => '1',
                'business_id' => '0',
                'account_type' => 'asset',
                'name' => 'Current Assets',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            1 => [
                'id' => '2',
                'business_id' => '0',
                'account_type' => 'asset',
                'name' => 'Non- Current Assets',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            2 => [
                'id' => '3',
                'business_id' => '0',
                'account_type' => 'asset',
                'name' => 'Fixed Assets',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            3 => [
                'id' => '4',
                'business_id' => '0',
                'account_type' => 'asset',
                'name' => 'Accounts Receivable(A/R)',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            4 => [
                'id' => '5',
                'business_id' => '0',
                'account_type' => 'asset',
                'name' => 'Cash and Cash Equivalents (CCE)',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            5 => [
                'id' => '6',
                'business_id' => '0',
                'account_type' => 'liability',
                'name' => 'Current Liabilities',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            6 => [
                'id' => '7',
                'business_id' => '0',
                'account_type' => 'liability',
                'name' => 'Non- Current Liabilities',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            7 => [
                'id' => '8',
                'business_id' => '0',
                'account_type' => 'liability',
                'name' => 'Accounts Payable (A/P)',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            8 => [
                'id' => '9',
                'business_id' => '0',
                'account_type' => 'liability',
                'name' => 'Credit Card',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            9 => [
                'id' => '10',
                'business_id' => '0',
                'account_type' => 'equity',
                'name' => 'Owner\'s Equity',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            10 => [
                'id' => '11',
                'account_type' => 'income',
                'name' => 'Income',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            11 => [
                'id' => '12',
                'business_id' => '0',
                'account_type' => 'income',
                'name' => 'Other Income',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            12 => [
                'id' => '13',
                'business_id' => '0',
                'account_type' => 'expense',
                'name' => 'Expense',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            13 => [
                'id' => '14',
                'business_id' => '0',
                'account_type' => 'expense',
                'name' => 'Cost of Sales',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            14 => [
                'id' => '15',
                'business_id' => '0',
                'account_type' => 'expense',
                'name' => 'Other Expense',
                'active' => '1',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($account_subtypes as $account_subtype) {
            AccountSubtype::insert($account_subtype);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
