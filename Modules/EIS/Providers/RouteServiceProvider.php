<?php

namespace Modules\EIS\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $moduleNamespace = 'Modules\EIS\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware([
            'web',
            'setData',
            'auth',
            'SetSessionData',
            'language',
            'timezone',
            'AdminSidebarMenu',
            'CheckUserLogin',
            \Modules\EIS\Http\Middleware\EnforceTerminalBusiness::class,
            \Modules\EIS\Http\Middleware\RequireEisSubscription::class,
        ])
            ->namespace($this->moduleNamespace)
            ->group(module_path('EIS', '/Routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware(['api', \Modules\EIS\Http\Middleware\EnforceTerminalBusiness::class])
            ->namespace($this->moduleNamespace)
            ->group(module_path('EIS', '/Routes/api.php'));
    }
}
