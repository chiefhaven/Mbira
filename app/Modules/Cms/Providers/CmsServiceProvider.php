<?php

namespace Modules\Cms\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsSiteDetail;

class CmsServiceProvider extends ServiceProvider
{
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

        view::composer(['cms::frontend.layouts.navbar', 'cms::frontend.layouts.footer'], function ($view) {
            $__blog_count = CmsPage::getPagesCount('blog');
            $__pages = CmsPage::getEnabledPages('page');

            $logo = CmsSiteDetail::getValue('logo', false);
            $__logo_url = 'https://ui-avatars.com/api/?size=60&rounded=true&bold=true&background=0A1F44&color=ffffff&name='.config('app.name');
            if (! empty($logo) && $logo->logo_url) {
                $__logo_url = $logo->logo_url;
            }

            $view->with(compact('__blog_count', '__pages', '__logo_url'));
        });

        view::composer(['cms::frontend.layouts.app', 'cms::frontend.pages.contact_us', 'cms::frontend.pages.home'], function ($view) {
            $__site_details = CmsSiteDetail::getSiteDetails();
            $view->with(compact('__site_details'));
        });
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
            __DIR__.'/../Config/config.php' => config_path('cms.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'cms'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/cms');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/cms';
        }, config('view.paths')), [$sourcePath]), 'cms');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/cms');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'cms');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'cms');
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
