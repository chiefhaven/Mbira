<?php

namespace Modules\Connector\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Console\ClientCommand;
use Laravel\Passport\Console\InstallCommand;
use Laravel\Passport\Console\KeysCommand;

class ConnectorServiceProvider extends ServiceProvider
{
    /**
     * The filters base class name.
     *
     * @var array
     */
    protected $middleware = [
        'Connector' => [
            'check-demo' => 'CheckDemo',
        ],
    ];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->registerMiddleware($this->app['router']);

        /*ADD THIS LINES*/
        $this->commands([
            InstallCommand::class,
            ClientCommand::class,
            KeysCommand::class,
        ]);
    }

    /**
     * Register the filters.
     *
     * @param  Router  $router
     * @return void
     */
    public function registerMiddleware(Router $router)
    {
        foreach ($this->middleware as $module => $middlewares) {
            foreach ($middlewares as $name => $middleware) {
                $class = "Modules\\{$module}\\Http\\Middleware\\{$middleware}";

                $router->aliasMiddleware($name, $class);
            }
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('connector.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'connector'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/connector');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/connector';
        }, config('view.paths')), [$sourcePath]), 'connector');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/connector');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'connector');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'connector');
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(__DIR__.'/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
