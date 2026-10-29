<?php

namespace Modules\EIS\Http\Controllers;

use App\Http\Controllers\Install\ModulesController;
use App\System;
use Composer\Semver\Comparator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class InstallController extends Controller
{
    private string $moduleName = 'EIS';

    private string $moduleVersion;

    public function __construct()
    {
        $this->moduleVersion = (string) config('eis.module_version');
    }

    public function index()
    {
        $this->authorizeModuleManagement();

        if (System::getProperty('eis_version')) {
            abort(404);
        }

        return $this->migrate('installed');
    }

    public function update()
    {
        $this->authorizeModuleManagement();

        $installedVersion = System::getProperty('eis_version');
        if (!$installedVersion || !Comparator::greaterThan($this->moduleVersion, $installedVersion)) {
            abort(404);
        }

        return $this->migrate('updated');
    }

    public function uninstall()
    {
        $this->authorizeModuleManagement();

        System::removeProperty('eis_version');

        return redirect()->back()->with('status', [
            'success' => true,
            'msg' => __('lang_v1.success'),
        ]);
    }

    private function authorizeModuleManagement(): void
    {
        abort_unless(auth()->user() && auth()->user()->can('superadmin'), 403, 'Unauthorized action.');
    }

    private function migrate(string $action)
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '512M');

        try {
            $exitCode = Artisan::call('module:migrate', [
                'module' => $this->moduleName,
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: 'EIS database migrations failed.');
            }

            System::addProperty('eis_version', $this->moduleVersion);

            return redirect()
                ->action([ModulesController::class, 'index'])
                ->with('status', [
                    'success' => true,
                    'msg' => 'EIS module '.$action.' successfully.',
                ]);
        } catch (\Throwable $e) {
            \Log::error('EIS module installation or update failed.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->action([ModulesController::class, 'index'])
                ->with('status', [
                    'success' => false,
                    'msg' => $e->getMessage(),
                ]);
        }
    }
}
