<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    // Server-owned scope: never accept command names or options from the browser.
    private const CLEAR_COMMANDS = [
        'view:clear',
        'route:clear',
        'config:clear',
        'cache:clear',
        'event:clear',
        'clear-compiled',
    ];

    public function clearCache()
    {
        /** @var \App\User|null $user */
        $user = auth()->user();
        abort_unless($user && $user->can('manage_modules'), 403);
        abort_if(config('app.env') === 'demo', 403, 'Maintenance commands are disabled in demo mode.');

        $results = [];
        foreach (self::CLEAR_COMMANDS as $command) {
            try {
                Artisan::call($command);
                $results[$command] = 'cleared';
            } catch (\Throwable $e) {
                $results[$command] = 'failed';
                Log::error('Browser cache clear command failed', [
                    'command' => $command,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (function_exists('opcache_reset')) {
            $results['opcache_reset'] = @opcache_reset() ? 'cleared' : 'skipped';
        }

        $failed = in_array('failed', $results, true);

        return response()->json([
            'success' => ! $failed,
            'message' => $failed
                ? 'Some caches could not be cleared. Check the application log.'
                : 'All caches cleared successfully.',
            'results' => $results,
        ], $failed ? 500 : 200);
    }
}
