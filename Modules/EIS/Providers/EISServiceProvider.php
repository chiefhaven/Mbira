<?php

namespace Modules\EIS\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\EIS\Services\Http\EisHttpClient;
use Illuminate\Console\Scheduling\Schedule;
// use Modules\EIS\Console\Commands\SyncEisConfiguration; // Command class file not found
use Modules\EIS\Services\Configuration\ConfigurationSyncService;
use Modules\EIS\Services\Configuration\EisConfigurationClient;
use Modules\EIS\Services\Configuration\Validators\ConfigurationValidator;
use Modules\EIS\Services\Sales\OfflineSignatureService;
use Modules\EIS\Services\Terminal\EisTerminalActivationService;

class EISServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'EIS';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'eis';

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
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        // $this->commands([SyncEisConfiguration::class]); // Command class file not found
        $this->app->booted(function () {

            $schedule = app(Schedule::class);

            $schedule->command(
                'eis:sync-config'
            )
            ->dailyAt('00:10');

        });

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        
        // Load views (if any)
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'eis');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->bind(
            EisHttpClient::class,
            EisHttpClient::class
        );

        // Client
        $this->app->singleton(EisConfigurationClient::class, function ($app) {
            return new EisConfigurationClient();
        });

        // Validator
        $this->app->singleton(ConfigurationValidator::class, function ($app) {
            return new ConfigurationValidator();
        });

        // Sync Service
        $this->app->singleton(ConfigurationSyncService::class, function ($app) {
            return new ConfigurationSyncService(
                $app->make(EisConfigurationClient::class),
                $app->make(ConfigurationValidator::class)
            );
        });

        // Terminal Activation Service
        $this->app->singleton(EisTerminalActivationService::class, function ($app) {
            return new EisTerminalActivationService(
                $app->make(ConfigurationSyncService::class)
            );
        });

        $this->app->singleton(OfflineSignatureService::class, function ($app) {
            return new OfflineSignatureService();
        });

    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
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

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }

    protected $listen = [
        \Modules\EIS\Events\SaleCompleted::class => [
            \Modules\EIS\Listeners\DispatchEisSaleJob::class,
        ],
    ];
}
