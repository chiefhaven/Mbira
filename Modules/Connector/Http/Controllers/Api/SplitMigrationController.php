<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SplitMigrationController extends Controller
{
    private const MIGRATION = 'database/migrations/2026_10_06_000001_create_saved_sale_split_operations_table.php';

    public function migrate(Request $request)
    {
        // This modifies shared database schema: a business Admin role is insufficient.
        if (!$request->user() || !$request->user()->can('superadmin')) {
            return response()->json(['error' => ['code' => 'migration_forbidden', 'message' => 'System superadmin permission is required.']], 403);
        }
        if (!config('connector.allow_split_migration_route', false)) {
            return response()->json(['error' => ['code' => 'migration_disabled', 'message' => 'Enable the split migration route in server configuration for the deployment window only.']], 403);
        }
        if ($request->input('confirm') !== 'create_saved_sale_split_operations'
            || array_diff(array_keys($request->all()), ['confirm'])) {
            return response()->json(['error' => ['code' => 'migration_confirmation_required', 'message' => 'Send only confirm: create_saved_sale_split_operations.']], 422);
        }
        $db = DB::connection();
        if ($db->getDriverName() !== 'mysql' || !is_file(base_path(self::MIGRATION))
            || !$db->getSchemaBuilder()->hasTable('migrations')) {
            return response()->json(['error' => ['code' => 'migration_unavailable', 'message' => 'Requires MySQL, the deployed migration file, and an initialized Laravel migration repository.']], 503);
        }
        $lockName = 'split_migrate_'.substr(hash('sha256', $db->getDatabaseName()), 0, 40);
        if ((int) ($db->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->acquired ?? 0) !== 1) {
            return response()->json(['error' => ['code' => 'migration_busy', 'message' => 'Another deployment is running; retry later.']], 409);
        }
        try {
            // No client-controlled path/connection/command; never migrate unrelated files.
            $exit = Artisan::call('migrate', ['--path' => self::MIGRATION, '--force' => true]);
            if ($exit !== 0 || !$db->getSchemaBuilder()->hasTable('saved_sale_split_operations')) {
                throw new \RuntimeException('Targeted split migration did not complete.');
            }
            Log::notice('Connector split migration completed', ['user_id' => $request->user()->id,
                'business_id' => $request->user()->business_id, 'migration' => self::MIGRATION]);
            return response()->json(['data' => ['migration' => basename(self::MIGRATION),
                'ready' => true, 'message' => 'Split ledger migration applied or already applied. Disable the migration route after deployment. Other split prerequisites remain required.']]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => ['code' => 'migration_failed', 'message' => 'Migration failed. Inspect server logs; schema changes may not roll back automatically.']], 500);
        } finally {
            $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }
}