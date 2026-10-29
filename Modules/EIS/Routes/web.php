<?php

use Illuminate\Support\Facades\Route;
use Modules\EIS\Http\Controllers\StockOutboundController;
use Modules\EIS\Http\Controllers\TerminalManagementController;
use Modules\EIS\Http\Controllers\UtilitiesController;
use Modules\EIS\Http\Controllers\SalesController;
use Modules\EIS\Http\Controllers\StockToolsController;
use Modules\EIS\Http\Controllers\ProductStatusController;

Route::prefix('eis')->middleware('auth')->group(function () {
    Route::get('install', [\Modules\EIS\Http\Controllers\InstallController::class, 'index'])
        ->withoutMiddleware(\Modules\EIS\Http\Middleware\EnforceTerminalBusiness::class);
    Route::get('install/update', [\Modules\EIS\Http\Controllers\InstallController::class, 'update'])
        ->withoutMiddleware(\Modules\EIS\Http\Middleware\EnforceTerminalBusiness::class);
    Route::get('install/uninstall', [\Modules\EIS\Http\Controllers\InstallController::class, 'uninstall'])
        ->withoutMiddleware(\Modules\EIS\Http\Middleware\EnforceTerminalBusiness::class);
});

Route::prefix('eis/stock/tools')->middleware('auth')->name('eis.stock.tools.')->group(function () {
    Route::get('/', [StockToolsController::class, 'index'])->name('index');
    Route::post('execute', [StockToolsController::class, 'execute'])->name('execute');
    Route::get('requests/{id}', [StockToolsController::class, 'show'])->whereNumber('id')->name('show');
    Route::post('requests/{id}/submit', [StockToolsController::class, 'submit'])->whereNumber('id')->name('submit');
    Route::post('requests/{id}/verify', [StockToolsController::class, 'verify'])->whereNumber('id')->name('verify');
});

Route::prefix('eis/sales')->middleware('auth')->name('eis.sales.')->group(function () {
    Route::get('/', [SalesController::class, 'index'])->name('index');
    Route::post('lookup', [SalesController::class, 'lookup'])->name('lookup');
    Route::post('{id}/sync', [SalesController::class, 'syncSale'])->whereNumber('id')->name('sync');
    Route::get('adjustments/{id}', [SalesController::class, 'show'])->whereNumber('id')->name('show');
    Route::post('adjustments/{id}/submit', [SalesController::class, 'submit'])->whereNumber('id')->name('submit');
});

Route::post('eis/products/{productId}/status', [ProductStatusController::class, 'show'])
    ->whereNumber('productId')->middleware('auth')->name('eis.products.status');

Route::prefix('eis/sales/operations')->middleware('auth')->name('eis.sales.operations.')->group(function () {
    Route::post('execute', [UtilitiesController::class, 'execute'])->name('execute');
    Route::get('requests/{requestId}', [UtilitiesController::class, 'show'])->whereNumber('requestId')->name('show');
    Route::post('requests/{requestId}/submit', [UtilitiesController::class, 'submit'])->whereNumber('requestId')->name('submit');
});

Route::get('eis', function () {
    $user = auth()->user();

    if ($user->can('business_settings.access')) {
        return redirect()->route('eis.terminals.page');
    }

    if ($user->can('stock_adjustment.view')) {
        return redirect()->route('eis.stock.index');
    }

    if ($user->can('stock_adjustment.create')
        || $user->can('stock_transfer.create')
        || $user->can('purchase.create')) {
        return redirect()->route('eis.stock.outbound.index');
    }

    abort(403);
})->middleware('auth')->name('eis.index');

Route::prefix('eis/stock')->middleware('auth')->name('eis.stock.')->group(function () {
    Route::get('/', [\Modules\EIS\Http\Controllers\StockReconciliationController::class, 'index'])->name('index');
    Route::post('preview', [\Modules\EIS\Http\Controllers\StockReconciliationController::class, 'preview'])->name('preview');
    Route::post('{snapshotId}/apply', [\Modules\EIS\Http\Controllers\StockReconciliationController::class, 'apply'])
        ->whereNumber('snapshotId')->name('apply');
});

// Session authentication and the web middleware's CSRF protection are required.
Route::prefix('eis/terminals')->middleware(['web', 'auth'])->name('eis.terminals.')->group(function () {
    Route::get('/', [TerminalManagementController::class, 'page'])->name('page');
    Route::get('data', [TerminalManagementController::class, 'index'])->name('index');
    Route::post('activate', [TerminalManagementController::class, 'activate'])->name('activate');
    Route::post('{terminalId}/latest-config', [TerminalManagementController::class, 'latestConfig'])
        ->whereNumber('terminalId')->name('latest-config');
    Route::get('{terminalId}/configuration', [TerminalManagementController::class, 'configuration'])
        ->whereNumber('terminalId')->name('configuration');
    Route::post('{terminalId}/request-new-token', [TerminalManagementController::class, 'requestNewToken'])
        ->whereNumber('terminalId')->name('request-new-token');
    Route::post('{terminalId}/deactivate', [TerminalManagementController::class, 'deactivate'])
        ->whereNumber('terminalId')->name('deactivate');
    Route::post('{terminalId}/renew-tokens', [TerminalManagementController::class, 'renewTokens'])
        ->whereNumber('terminalId')->name('renew-tokens');
    Route::post('{terminalId}/default', [TerminalManagementController::class, 'setDefault'])
        ->whereNumber('terminalId')->name('default');
});

Route::prefix('eis/stock/outbound')->middleware(['web', 'auth'])->name('eis.stock.outbound.')->group(function () {
    Route::get('/', [StockOutboundController::class, 'index'])->name('index');
    Route::post('/', [StockOutboundController::class, 'store'])->name('store');
    Route::post('{operationId}/dispatch', [StockOutboundController::class, 'dispatchQueued'])
        ->whereNumber('operationId')->name('dispatch');
    Route::post('{operationId}/verify', [StockOutboundController::class, 'verify'])
        ->whereNumber('operationId')->name('verify');
    Route::get('locations/{locationId}/adjustment-reasons', [StockOutboundController::class, 'reasons'])
        ->whereNumber('locationId')->name('reasons');
});
