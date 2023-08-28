<?php

use App\Http\Controllers\CmsController;
use App\Http\Controllers\CmsPageController;
use Illuminate\Support\Facades\Route;

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
Route::get('/', [Modules\Cms\Http\Controllers\CmsController::class, 'index']);
Route::get('c/page/{page}', [Modules\Cms\Http\Controllers\CmsPageController::class, 'showPage']);
Route::get('c/blogs', [Modules\Cms\Http\Controllers\CmsController::class, 'getBlogList']);
Route::get('c/blog/{slug}-{id}', [Modules\Cms\Http\Controllers\CmsController::class, 'viewBlog']);
Route::get('c/contact-us', [Modules\Cms\Http\Controllers\CmsController::class, 'contactUs'])->name('cms.contact.us');
Route::post('c/submit-contact-form', [Modules\Cms\Http\Controllers\CmsController::class, 'postContactForm'])->name('cms.submit.contact.form');

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'superadmin')->prefix('cms')->group(function () {
    Route::get('install', [\Modules\Cms\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [\Modules\Cms\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [\Modules\Cms\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [\Modules\Cms\Http\Controllers\InstallController::class, 'update']);

    Route::resource('cms-page', \Modules\Cms\Http\Controllers\CmsPageController::class)->except(['show']);
    Route::resource('site-details', \Modules\Cms\Http\Controllers\SettingsController::class);
});
