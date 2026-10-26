<?php

Route::post(
    '/webhook/order-created/{business_id}',
    [Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderCreated']
);
Route::post(
    '/webhook/order-updated/{business_id}',
    [Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderUpdated']
);
Route::post(
    '/webhook/order-deleted/{business_id}',
    [Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderDeleted']
);
Route::post(
    '/webhook/order-restored/{business_id}',
    [Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderRestored']
);

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->prefix('woocommerce')->group(function () {
    Route::get('/install', [Modules\Woocommerce\Http\Controllers\InstallController::class, 'index']);
    Route::get('/install/update', [Modules\Woocommerce\Http\Controllers\InstallController::class, 'update']);
    Route::get('/install/uninstall', [Modules\Woocommerce\Http\Controllers\InstallController::class, 'uninstall']);

    Route::get('/', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'index']);
    Route::get('/api-settings', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'apiSettings']);
    Route::post('/update-api-settings', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'updateSettings']);
    Route::get('/sync-categories', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncCategories']);
    Route::get('/sync-products', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncProducts']);
    Route::get('/sync-log', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'getSyncLog']);
    Route::get('/sync-orders', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncOrders']);
    Route::post('/map-taxrates', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'mapTaxRates']);
    Route::get('/view-sync-log', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'viewSyncLog']);
    Route::get('/get-log-details/{id}', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'getLogDetails']);
    Route::get('/reset-categories', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'resetCategories']);
    Route::get('/reset-products', [Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'resetProducts']);
});
