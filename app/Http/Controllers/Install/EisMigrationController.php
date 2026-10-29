<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EisMigrationController extends Controller
{
    // Server-owned scope: never accept command names, paths or options from the browser.
    public const MIGRATIONS = [
        '2026_10_14_000001_add_authorization_code_to_contacts_table',
        '2026_10_14_000002_add_is_relief_to_transactions_table',
        '2026_10_14_000003_add_vat5_certificate_details_to_transactions_table',
    ];

    private function authorizeMigration(): void
    {
        /** @var \App\User|null $user */
        $user = auth()->user();
        abort_unless($user && $user->can('manage_modules'), 403);
        abort_if(config('app.env') === 'demo', 403, 'Migrations are disabled in demo mode.');
    }

    public function index()
    {
        $this->authorizeMigration();
        $table = config('database.migrations', 'migrations');
        $ran = Schema::hasTable($table) ? DB::table($table)->pluck('migration')->all() : [];
        $migrations = array_map(function ($name) use ($ran) {
            return ['name' => $name, 'applied' => in_array($name, $ran, true)];
        }, self::MIGRATIONS);

        return view('install.eis_migrations', compact('migrations'));
    }

    public function store(Request $request)
    {
        $this->authorizeMigration();
        $request->validate(['confirm_backup' => 'required|accepted']);
        // File cache gives browser requests on this server a shared atomic lock.
        /** @var \Illuminate\Cache\Repository $cache */
        $cache = Cache::store('file');
        /** @var \Illuminate\Contracts\Cache\LockProvider $lockProvider */
        $lockProvider = $cache->getStore();
        $lock = $lockProvider->lock('browser-eis-migrations', 600);
        if (! $lock->get()) {
            return redirect()->route('eis-migrations.index')->with('status', [
                'success' => false, 'msg' => 'EIS migrations are already running. Please retry later.',
            ]);
        }

        try {
            $paths = array_map(fn ($name) => 'database/migrations/'.$name.'.php', self::MIGRATIONS);
            foreach ($paths as $path) {
                if (! is_file(base_path($path))) {
                    throw new \RuntimeException('Required EIS migration file missing.');
                }
            }
            // DDL may commit implicitly: do not wrap migrations in a DB transaction.
            $exitCode = Artisan::call('migrate', ['--path' => $paths, '--force' => true]);
            $status = $exitCode === 0
                ? ['success' => true, 'msg' => 'EIS migrations completed. Already applied migrations were skipped.']
                : ['success' => false, 'msg' => 'Migration failed. Review the application log before retrying; earlier migrations may have completed.'];
            if ($exitCode !== 0) {
                Log::error('Browser EIS migration command failed', ['exit_code' => $exitCode]);
            }
        } catch (\Throwable $e) {
            Log::error('Browser EIS migrations failed', ['exception' => get_class($e)]);
            $status = ['success' => false, 'msg' => 'Migration failed. Check deployment files and the application log. Earlier migrations may have completed.'];
        } finally {
            $lock->release();
        }

        return redirect()->route('eis-migrations.index')->with('status', $status);
    }
}