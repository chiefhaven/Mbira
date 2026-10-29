<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Utils\ModuleUtil;

class ModuleAssetServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share module assets with all views
        View::composer('*', function ($view) {
            // Skip asset loading for AJAX requests
            if (request()->ajax()) {
                return;
            }
            
            $moduleAssets = $this->getModuleAssets();
            $view->with('moduleAssets', $moduleAssets);
        });
    }

    /**
     * Get module assets using the standard ModuleUtil pattern with 1-hour cache.
     */
    protected function getModuleAssets(): array
    {
        // Skip cache in local environment for development
        if (app()->environment('local')) {
            return $this->buildModuleAssets();
        }

        // Cache assets for 1 hour in non-local environments
        return Cache::remember('module_assets', 3600, function () {
            return $this->buildModuleAssets();
        });
    }

    /**
     * Build module assets array from ModuleUtil data.
     */
    protected function buildModuleAssets(): array
    {
        $moduleUtil = new ModuleUtil();
        
        // Get asset data from all module DataControllers
        $moduleAssetsData = $moduleUtil->getModuleData('getAssets');
        
        // Combine all module assets
        $assets = ['js' => [], 'css' => []];
        
        foreach ($moduleAssetsData as $moduleName => $moduleAssets) {
            if (is_array($moduleAssets)) {
                // Add JS assets
                if (!empty($moduleAssets['js']) && is_array($moduleAssets['js'])) {
                    foreach ($moduleAssets['js'] as $js) {
                        $assets['js'][] = [
                            'path' => $js,
                            'module' => $moduleName,
                        ];
                    }
                }
                
                // Add CSS assets
                if (!empty($moduleAssets['css']) && is_array($moduleAssets['css'])) {
                    foreach ($moduleAssets['css'] as $css) {
                        $assets['css'][] = [
                            'path' => $css,
                            'module' => $moduleName,
                        ];
                    }
                }
            }
        }
        
        return $assets;
    }


} 