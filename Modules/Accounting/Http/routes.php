<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'], 'prefix' => 'accounting', 'namespace' => 'Modules\Accounting\Http\Controllers'], function () {

    Route::get('/install', 'InstallController@index');
    Route::post('/install', 'InstallController@install');
    Route::get('/install/uninstall', 'InstallController@uninstall');
    Route::get('/install/update', 'InstallController@update');

    /** Dashboard Routes */
    Route::prefix('dashboard')->group(function () {
        Route::get('/', 'DashboardController@index');
        Route::get('get_totals', 'DashboardController@get_totals');
    });

    /**Accounting routes */
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    Route::get('trial_balance', 'AccountingController@trial_balance');
    Route::get('ledger', 'AccountingController@ledger');
    Route::get('balance_sheet', 'AccountingController@balance_sheet');
    Route::get('profit_and_loss', 'AccountingController@profit_and_loss');
    Route::get('cash_flow', 'AccountingController@cash_flow');

    //chart of accounts
    Route::prefix('chart_of_account')->group(function () {
        Route::get('/', 'ChartOfAccountController@index');
        Route::get('get_chart_of_accounts', 'ChartOfAccountController@get_chart_of_accounts');
        Route::get('create', 'ChartOfAccountController@create');
        Route::post('store', 'ChartOfAccountController@store');
        Route::get('{id}/show', 'ChartOfAccountController@show');
        Route::get('{id}/edit', 'ChartOfAccountController@edit');
        Route::post('{id}/update', 'ChartOfAccountController@update');
        Route::get('{id}/destroy', 'ChartOfAccountController@destroy');
        Route::get('export', 'ChartOfAccountController@export');
    });

    //journal entries
    Route::prefix('journal_entry')->group(function () {
        Route::get('/', 'JournalEntryController@index');
        Route::get('get_journal_entries', 'JournalEntryController@get_journal_entries');
        Route::get('create', 'JournalEntryController@create');
        Route::post('store', 'JournalEntryController@store');
        Route::get('{id}/show', 'JournalEntryController@show');
        Route::get('{id}/edit', 'JournalEntryController@edit');
        Route::get('{id}/reverse', 'JournalEntryController@reverse');
        Route::post('{id}/update', 'JournalEntryController@update');
        Route::get('{id}/destroy', 'JournalEntryController@destroy');
    });

    //Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('sales', 'AccountingTransactionController@sales');
        Route::get('expenses', 'AccountingTransactionController@expenses');
        Route::get('purchases', 'AccountingTransactionController@purchases');
        Route::post('map_to_chart_of_account', 'AccountingTransactionController@map_to_chart_of_account');
    });

    //Transfers
    Route::prefix('transfers')->group(function () {
        Route::get('/', 'AccountingController@transfers');
        Route::get('create', 'AccountingController@create_transfer');
        Route::post('store', 'AccountingController@store_transfer');
    });

    //Budget
    Route::prefix('budget')->group(function () {
        Route::get('/', 'BudgetController@index');
        Route::post('update_monthly_budget', 'BudgetController@update_monthly_budget');
        Route::post('update_quarterly_budget', 'BudgetController@update_quarterly_budget');
        Route::post('update_yearly_budget', 'BudgetController@update_yearly_budget');
        Route::post('store_financial_year_start', 'BudgetController@store_financial_year_start');
    });

    //Reconcile
    Route::prefix('reconcile')->group(function () {
        Route::get('/', 'ReconcileController@index');
        Route::post('start', 'ReconcileController@start_reconcile');
        Route::post('store', 'ReconcileController@store_reconcile');
        Route::post('undo', 'ReconcileController@undo_reconcile');
    });

    //Settings
    Route::prefix('settings')->group(function () {

        Route::prefix('account_subtypes')->group(function () {
            Route::get('/', 'AccountingSettingsController@account_subtypes');
            Route::post('store', 'AccountingSettingsController@store_account_subtypes');
            Route::get('{id}/edit', 'AccountingSettingsController@edit_account_subtypes');
            Route::post('{id}/update', 'AccountingSettingsController@update_account_subtypes');
            Route::delete('{id}/destroy', 'AccountingSettingsController@destroy_account_subtype');
        });

        Route::prefix('detail_types')->group(function () {
            Route::get('/', 'AccountingSettingsController@detail_types');
            Route::post('store', 'AccountingSettingsController@store_detail_types');
            Route::get('{id}/edit', 'AccountingSettingsController@edit_detail_types');
            Route::post('{id}/update', 'AccountingSettingsController@update_detail_types');
            Route::delete('{id}/destroy', 'AccountingSettingsController@destroy_detail_type');
        });

    });
});

//Media
Route::group(['middleware' => ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'], 'prefix' => 'media', 'namespace' => 'Modules\Accounting\Http\Controllers'], function () {
    Route::post('{id}/download', 'MediaController@download')->name('media.download');
    Route::delete('{id}/delete', 'MediaController@delete')->name('media.delete');
});


Route::group(['middleware' => ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'], 'prefix' => 'report', 'namespace' => 'Modules\Accounting\Http\Controllers'], function () {
    Route::get('accounting', 'ReportController@index');

    //Business Overview
     Route::get('accounting/balance_sheet', 'ReportController@balance_sheet');
    Route::get('accounting/cash_flow', 'ReportController@cash_flow');
    Route::get('accounting/profit_and_loss', 'ReportController@profit_and_loss');

    // Bookkeeping
    Route::get('accounting/ledger', 'ReportController@ledger');
    Route::get('accounting/trial_balance', 'ReportController@trial_balance');
    Route::get('accounting/journal', 'ReportController@journal');

    // Budget
    Route::get('accounting/budget_overview', 'ReportController@budget_overview');

    // Who owes you
    Route::get('accounting/accounts_receivable_ageing_summary', 'ReportController@accounts_receivable_ageing_summary');
    Route::get('accounting/accounts_receivable_ageing_detail', 'ReportController@accounts_receivable_ageing_detail');

    // What you owe
    Route::get('accounting/accounts_payable_ageing_summary', 'ReportController@accounts_payable_ageing_summary');
    Route::get('accounting/accounts_payable_ageing_detail', 'ReportController@accounts_payable_ageing_detail');
});
