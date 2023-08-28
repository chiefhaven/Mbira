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

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')
    ->prefix('aiassistance')->group(function() {
    Route::get('/dashboard', 'AiAssistanceController@index');
    Route::get('/create/{tool}', 'AiAssistanceController@create');
    Route::post('/generate/{tool}', 'AiAssistanceController@generate');
    Route::get('/history', 'AiAssistanceController@history');

    Route::get('install', [\Modules\AiAssistance\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [\Modules\AiAssistance\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [\Modules\AiAssistance\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [\Modules\AiAssistance\Http\Controllers\InstallController::class, 'update']);
});
