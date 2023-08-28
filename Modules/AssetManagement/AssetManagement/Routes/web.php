<?php

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('asset')->group(function () {
    Route::get('install', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'update']);

    Route::resource('assets', Modules\AssetManagement\Http\Controllers\AssetController::class);
    Route::resource('allocation', Modules\AssetManagement\Http\Controllers\AssetAllocationController::class);
    Route::resource('revocation', Modules\AssetManagement\Http\Controllers\RevokeAllocatedAssetController::class);
    Route::resource('settings', Modules\AssetManagement\Http\Controllers\AssetSettingsController::class);
    Route::get('dashboard', [Modules\AssetManagement\Http\Controllers\AssetController::class, 'dashboard']);

    Route::resource('asset-maintenance', 'Modules\AssetManagement\Http\Controllers\AssetMaitenanceController');
});
