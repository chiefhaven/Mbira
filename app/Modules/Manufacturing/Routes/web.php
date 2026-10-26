<?php

Route::middleware('web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->prefix('manufacturing')->group(function () {
    Route::get('/install', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'index']);
    Route::post('/install', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'install']);
    Route::get('/install/update', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'update']);
    Route::get('/install/uninstall', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'uninstall']);

    Route::get('/is-recipe-exist/{variation_id}', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'isRecipeExist']);
    Route::get('/ingredient-group-form', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getIngredientGroupForm']);
    Route::get('/get-recipe-details', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getRecipeDetails']);
    Route::get('/get-ingredient-row/{variation_id}', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getIngredientRow']);
    Route::get('/add-ingredient', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'addIngredients']);
    Route::resource('/recipe', 'Modules\Manufacturing\Http\Controllers\RecipeController')->except('edit', 'update');
    Route::resource('/production', 'Modules\Manufacturing\Http\Controllers\ProductionController');
    Route::resource('/settings', 'Modules\Manufacturing\Http\Controllers\SettingsController')->only('index', 'store');

    Route::get('/report', [Modules\Manufacturing\Http\Controllers\ProductionController::class, 'getManufacturingReport']);

    Route::post('/update-product-prices', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'updateRecipeProductPrices']);
});
