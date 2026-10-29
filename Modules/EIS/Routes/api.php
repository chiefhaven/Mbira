<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\EIS\Http\Controllers\API\ConfigurationController;
use Modules\EIS\Http\Controllers\API\TaxRateController;
use Modules\EIS\Http\Controllers\API\TerminalActivationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:api', \Modules\EIS\Http\Middleware\RequireEisSubscription::class])->get('/eis', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| EIS API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware(\Modules\EIS\Http\Middleware\RequireEisSubscription::class)->group(function () {
    
    /**
     * Terminal Activation Routes
     */
    Route::prefix('terminal')->group(function () {
        
        // Terminal Activation
        Route::post('activate', [TerminalActivationController::class, 'activateTerminal'])
            ->name('eis.terminal.activate');
        
        // Terminal Deactivation
        Route::post('deactivate', [TerminalActivationController::class, 'deactivate'])
            ->name('eis.terminal.deactivate');
        
        // Toggle Terminal Status
        Route::post('toggle', [TerminalActivationController::class, 'toggle'])
            ->name('eis.terminal.toggle');

        Route::post('get-latest-config', [TerminalActivationController::class, 'fetchLatestConfig'])
            ->name('eis.terminal.get-latest-config');
        
        // Get Terminal Status
        Route::get('status/{businessId}', [TerminalActivationController::class, 'status'])
            ->name('eis.terminal.status');
        
        // Check if Terminal is Active
        Route::get('is-active/{businessId}', [TerminalActivationController::class, 'isActive'])
            ->name('eis.terminal.is-active');
        
        // Get Terminal Activation History
        Route::get('history/{businessId}', [TerminalActivationController::class, 'history'])
            ->name('eis.terminal.history');
        
        // Get Terminal Credentials
        Route::get('credentials/{businessId}', [TerminalActivationController::class, 'getCredentials'])
            ->name('eis.terminal.credentials');
        
        // Regenerate Terminal Credentials
        Route::post('regenerate-credentials', [TerminalActivationController::class, 'regenerateCredentials'])
            ->name('eis.terminal.regenerate-credentials');
        
        // Bulk Activate Terminals
        Route::post('bulk-activate', [TerminalActivationController::class, 'bulkActivate'])
            ->name('eis.terminal.bulk-activate');
        
        // Bulk Deactivate Terminals
        Route::post('bulk-deactivate', [TerminalActivationController::class, 'bulkDeactivate'])
            ->name('eis.terminal.bulk-deactivate');
        
        // Get Terminal Details
        Route::get('details/{businessId}', [TerminalActivationController::class, 'details'])
            ->name('eis.terminal.details');
        
        // Sync Terminal Configuration
        Route::post('sync', [TerminalActivationController::class, 'syncTerminal'])
            ->name('eis.terminal.sync');
        
        // Get Terminal Health Status
        Route::get('health/{businessId}', [TerminalActivationController::class, 'health'])
            ->name('eis.terminal.health');
    });

    /**
     * Configuration Routes
     */
    Route::prefix('configuration')->group(function () {
        
        // Sync Configuration
        Route::post('sync', [ConfigurationController::class, 'sync'])
            ->name('eis.configuration.sync');
        
        // Force Sync Configuration
        Route::post('force-sync', [ConfigurationController::class, 'forceSync'])
            ->name('eis.configuration.force-sync');
        
        // Get Configuration
        Route::get('{businessId}', [ConfigurationController::class, 'getConfiguration'])
            ->name('eis.configuration.get');
        
        // Get Configuration Status
        Route::get('status/{businessId}', [ConfigurationController::class, 'getStatus'])
            ->name('eis.configuration.status');
        
        // Get Configuration Versions
        Route::get('versions/{businessId}', [ConfigurationController::class, 'getVersions'])
            ->name('eis.configuration.versions');
    });

    /**
     * Tax Rate Routes
     */
    Route::prefix('tax-rates')->group(function () {
        
        // Get Tax Rates
        Route::get('{businessId}', [TaxRateController::class, 'index'])
            ->name('eis.tax-rates.index');
        
        // Get Activated Tax Rates
        Route::get('activated/{businessId}', [TaxRateController::class, 'activated'])
            ->name('eis.tax-rates.activated');
        
        // Get Tax Rate by ID
        Route::get('{businessId}/{taxRateId}', [TaxRateController::class, 'show'])
            ->name('eis.tax-rates.show');
        
        // Calculate Tax
        Route::post('calculate', [TaxRateController::class, 'calculate'])
            ->name('eis.tax-rates.calculate');
        
        // Get Tax Rate Summary
        Route::get('summary/{businessId}', [TaxRateController::class, 'summary'])
            ->name('eis.tax-rates.summary');
    });

    /**
     * Webhook Routes
     */
    Route::prefix('webhooks')->group(function () {
        
        // Activation Callback Webhook
        Route::post('activation-callback', [TerminalActivationController::class, 'activationCallback'])
            ->name('eis.webhooks.activation-callback');
        
        // Deactivation Callback Webhook
        Route::post('deactivation-callback', [TerminalActivationController::class, 'deactivationCallback'])
            ->name('eis.webhooks.deactivation-callback');
        
        // Configuration Update Webhook
        Route::post('configuration-update', [ConfigurationController::class, 'webhookUpdate'])
            ->name('eis.webhooks.configuration-update');
    });
});

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/public')->middleware(\Modules\EIS\Http\Middleware\RequireEisSubscription::class)->group(function () {
    
    // Check Terminal Health (for monitoring)
    Route::get('terminal/health/{businessId}', [TerminalActivationController::class, 'health'])
        ->name('eis.public.terminal.health');
    
    // Get Terminal Status (public)
    Route::get('terminal/status/{businessId}', [TerminalActivationController::class, 'status'])
        ->name('eis.public.terminal.status');

    Route::get('configuration/{businessId}', [ConfigurationController::class, 'getConfiguration'])
        ->name('eis.public.configuration.get');
});