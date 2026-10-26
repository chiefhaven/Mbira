<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'accounting', 'namespace' => '\Modules\Accounting\Http\Controllers'], function()
{
    Route::get('dashboard', 'AccountingController@dashboard');

    Route::get('accounts-dropdown', 'AccountingController@AccountsDropdown')->name('accounts-dropdown');

    Route::get('get-account-sub-types', 'CoaController@getAccountSubTypes');
    Route::get('get-account-details-types', 'CoaController@getAccountDetailsType');
    Route::resource('chart-of-accounts', 'CoaController');
    Route::get('ledger/{id}', 'CoaController@ledger')->name('accounting.ledger');
    Route::get('activate-deactivate/{id}', 'CoaController@activateDeactivate');
    Route::get('create-default-accounts', 'CoaController@createDefaultAccounts')->name('accounting.create-default-accounts');

    Route::resource('journal-entry', 'JournalEntryController');

    Route::get('settings', 'SettingsController@index');
    Route::get('reset-data', 'SettingsController@resetData');

    Route::resource('account-type', 'AccountTypeController');

    Route::resource('transfer', 'TransferController')->except(['show']);

    Route::resource('budget', 'BudgetController')->except(['show', 'edit', 'update', 'destroy']);

    Route::get('reports', 'ReportController@index');
    Route::get('reports/trial-balance', 'ReportController@trialBalance')->name('accounting.trialBalance');
    Route::get('reports/balance-sheet', 'ReportController@balanceSheet')->name('accounting.balanceSheet');
    Route::get('reports/account-receivable-ageing-report', 
    'ReportController@accountReceivableAgeingReport')->name('accounting.account_receivable_ageing_report');

    Route::get('reports/account-payable-ageing-report', 
    'ReportController@accountPayableAgeingReport')->name('accounting.account_payable_ageing_report');

    Route::get('transactions', 'TransactionController@index');
    Route::get('transactions/map', 'TransactionController@map');
    Route::post('transactions/save-map', 'TransactionController@saveMap');
    Route::post('save-settings', 'SettingsController@saveSettings');

    Route::get('install', 'InstallController@index');
    Route::post('install', 'InstallController@install');
    Route::get('install/uninstall', 'InstallController@uninstall');
    Route::get('install/update', 'InstallController@update');
    
});
