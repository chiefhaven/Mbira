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

Route::get('/catalogue/{business_id}/{location_id}', [\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'index']);
Route::get('/show-catalogue/{business_id}/{product_id}', [\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'show']);

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('product-catalogue')->group(function () {
    Route::get('catalogue-qr', [\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'generateQr']);

    Route::get('install', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'update']);
});
