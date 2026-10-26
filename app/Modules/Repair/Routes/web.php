<?php

Route::get('/repair-status', [Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index'])->name('repair-status');
Route::post('/post-repair-status', [Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'postRepairStatus'])->name('post-repair-status');
Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('repair')->group(function () {
    Route::get('edit-repair/{id}/status', [Modules\Repair\Http\Controllers\RepairController::class, 'editRepairStatus']);
    Route::post('update-repair-status', [Modules\Repair\Http\Controllers\RepairController::class, 'updateRepairStatus']);
    Route::get('delete-media/{id}', [Modules\Repair\Http\Controllers\RepairController::class, 'deleteMedia']);
    Route::get('print-label/{id}', [Modules\Repair\Http\Controllers\RepairController::class, 'printLabel']);
    Route::get('print-repair/{transaction_id}/customer-copy', [Modules\Repair\Http\Controllers\RepairController::class, 'printCustomerCopy'])->name('repair.customerCopy');
    Route::resource('/repair', 'Modules\Repair\Http\Controllers\RepairController')->except(['create', 'edit']);
    Route::resource('/status', 'Modules\Repair\Http\Controllers\RepairStatusController')->except('show');

    Route::resource('/repair-settings', 'Modules\Repair\Http\Controllers\RepairSettingsController')->only('index', 'store');

    Route::get('/install', [Modules\Repair\Http\Controllers\InstallController::class, 'index']);
    Route::post('/install', [Modules\Repair\Http\Controllers\InstallController::class, 'install']);
    Route::get('/install/uninstall', [Modules\Repair\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('/install/update', [Modules\Repair\Http\Controllers\InstallController::class, 'update']);

    Route::get('get-device-models', [Modules\Repair\Http\Controllers\DeviceModelController::class, 'getDeviceModels']);
    Route::get('models-repair-checklist', [Modules\Repair\Http\Controllers\DeviceModelController::class, 'getRepairChecklists']);
    Route::resource('device-models', 'Modules\Repair\Http\Controllers\DeviceModelController')->except(['show']);
    Route::resource('dashboard', 'Modules\Repair\Http\Controllers\DashboardController');

    Route::post('job-sheet-post-upload-docs', [Modules\Repair\Http\Controllers\JobSheetController::class, 'postUploadDocs']);
    Route::get('job-sheet/{id}/upload-docs', [Modules\Repair\Http\Controllers\JobSheetController::class, 'getUploadDocs']);
    Route::get('job-sheet/print/{id}', [Modules\Repair\Http\Controllers\JobSheetController::class, 'print']);
    Route::get('job-sheet/delete/{id}/image', [Modules\Repair\Http\Controllers\JobSheetController::class, 'deleteJobSheetImage']);
    Route::get('job-sheet/{id}/status', [Modules\Repair\Http\Controllers\JobSheetController::class, 'editStatus']);
    Route::put('job-sheet-update/{id}/status', [Modules\Repair\Http\Controllers\JobSheetController::class, 'updateStatus']);
    Route::get('job-sheet/add-parts/{id}', [Modules\Repair\Http\Controllers\JobSheetController::class, 'addParts']);
    Route::post('job-sheet/save-parts/{id}', [Modules\Repair\Http\Controllers\JobSheetController::class, 'saveParts']);
    Route::post('job-sheet/get-part-row', [Modules\Repair\Http\Controllers\JobSheetController::class, 'jobsheetPartRow']);
    Route::resource('job-sheet', 'Modules\Repair\Http\Controllers\JobSheetController');
    Route::post('update-repair-jobsheet-settings', [Modules\Repair\Http\Controllers\RepairSettingsController::class, 'updateJobsheetSettings']);
    Route::get('job-sheet/print-label/{id}', [Modules\Repair\Http\Controllers\JobSheetController::class, 'printLabel']);
});
