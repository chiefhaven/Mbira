<?php

// Isolated migration fixture. Never loads .env, the application kernel or EIS HTTP.
require dirname(__DIR__, 2).'\vendor\autoload.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;

$app = new class extends Container {
    public function abort($code, $message = '', array $headers = []) {
        throw new Symfony\Component\HttpKernel\Exception\HttpException($code, $message, null, $headers);
    }
};
Container::setInstance($app);
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
$app->instance('log', new Psr\Log\NullLogger());
Facade::setFacadeApplication($app);
$schema = $capsule->getConnection()->getSchemaBuilder();
$app->instance('db.schema', $schema);
foreach ([
    'business_locations' => ['business_id', 'eis_site_id', 'name'],
    'eis_settings' => ['business_id', 'branch_id', 'device_id', 'jwt_token', 'tpin', 'status'],
    'eis_configurations' => ['business_id'],
    'eis_terminal_configurations' => ['configuration_id', 'terminal_id', 'terminal_position', 'taxpayer_id', 'is_active'],
    'eis_sales' => ['business_id', 'transaction_id', 'fiscal_invoice_number', 'request_payload', 'status'],
    'eis_failed_transactions' => ['business_id', 'transaction_id'],
    'transactions' => ['business_id', 'location_id'],
] as $name => $columns) {
    $schema->create($name, function (Blueprint $table) use ($columns) {
        $table->increments('id');
        foreach ($columns as $column) {
            $table->text($column)->nullable();
        }
        $table->timestamps();
    });
}
DB::table('business_locations')->insert([
    ['id' => 1, 'business_id' => 1, 'eis_site_id' => 'A', 'name' => 'First'],
    ['id' => 2, 'business_id' => 1, 'eis_site_id' => 'B', 'name' => 'Second'],
    ['id' => 3, 'business_id' => 2, 'eis_site_id' => 'A', 'name' => 'Other tenant'],
    ['id' => 4, 'business_id' => 3, 'eis_site_id' => 'D', 'name' => 'Ambiguous first'],
    ['id' => 5, 'business_id' => 3, 'eis_site_id' => 'D', 'name' => 'Ambiguous second'],
]);
DB::table('eis_settings')->insert([
    ['id' => 1, 'business_id' => 1, 'branch_id' => 'A', 'device_id' => 'A1', 'jwt_token' => 'token1', 'tpin' => 'TIN1', 'status' => 1],
    ['id' => 2, 'business_id' => 1, 'branch_id' => 'B', 'device_id' => 'B1', 'jwt_token' => 'token2', 'tpin' => 'TIN1', 'status' => 1],
    ['id' => 3, 'business_id' => 2, 'branch_id' => 'UNKNOWN', 'device_id' => 'X', 'jwt_token' => 'token3', 'tpin' => 'TIN2', 'status' => 1],
    ['id' => 4, 'business_id' => 3, 'branch_id' => 'D', 'device_id' => 'D1', 'jwt_token' => 'token4', 'tpin' => 'TIN3', 'status' => 1],
    ['id' => 5, 'business_id' => 1, 'branch_id' => 'A', 'device_id' => 'SHARED', 'jwt_token' => 'token5', 'tpin' => 'TIN1', 'status' => 1],
    ['id' => 6, 'business_id' => 2, 'branch_id' => 'A', 'device_id' => 'SHARED', 'jwt_token' => 'token6', 'tpin' => 'TIN2', 'status' => 1],
    ['id' => 7, 'business_id' => 1, 'branch_id' => 'A', 'device_id' => 'A3', 'jwt_token' => 'token7', 'tpin' => 'TIN1', 'status' => 1],
]);
DB::table('eis_configurations')->insert([['id' => 1, 'business_id' => 1], ['id' => 2, 'business_id' => 1], ['id' => 3, 'business_id' => 1]]);
DB::table('eis_terminal_configurations')->insert([
    ['configuration_id' => 1, 'terminal_id' => 'A1', 'terminal_position' => 1, 'taxpayer_id' => 100, 'is_active' => 1],
    ['configuration_id' => 2, 'terminal_id' => 'B1', 'terminal_position' => 2, 'taxpayer_id' => 100, 'is_active' => 1],
    ['configuration_id' => 3, 'terminal_id' => 'A3', 'terminal_position' => 4, 'taxpayer_id' => 100, 'is_active' => 1],
]);
$generator = new Modules\EIS\Services\Sales\InvoiceNumberGenerator();
$number = $generator->base10ToBase64(100).'-B-'.$generator->base10ToBase64($generator->toJulianDate(now())).'-B';
DB::table('transactions')->insert([['id' => 1, 'business_id' => 1, 'location_id' => 1], ['id' => 2, 'business_id' => 1, 'location_id' => null]]);
DB::table('eis_sales')->insert([
    ['id' => 1, 'business_id' => 1, 'transaction_id' => 1, 'fiscal_invoice_number' => 'RECEIPT', 'request_payload' => json_encode(['invoiceHeader' => ['invoiceNumber' => $number]]), 'status' => 'submitted'],
    ['id' => 2, 'business_id' => 1, 'transaction_id' => 2, 'fiscal_invoice_number' => null, 'request_payload' => null, 'status' => 'failed'],
]);
DB::table('eis_failed_transactions')->insert(['business_id' => 1, 'transaction_id' => 1]);
DB::table('eis_failed_transactions')->insert(['business_id' => 1, 'transaction_id' => 99]);
$beforeSales = DB::table('eis_sales')->count();
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_23_140000_scope_eis_to_location_terminals.php';
$migration->up();
$checks = 0;
function locationCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    ++$checks;
}
function locationReject(callable $work, string $message): void {
    try { $work(); } catch (InvalidArgumentException $e) { locationCheck(true, $message); return; }
    throw new RuntimeException($message);
}
locationCheck(DB::table('eis_sales')->count() === $beforeSales, 'Migration preserves historical sales');
locationCheck((int) DB::table('eis_settings')->where('id', 2)->value('location_id') === 2, 'Settings backfill uses exact tenant/site');
locationCheck(DB::table('eis_settings')->where('id', 3)->value('location_id') === null, 'Unknown site is not guessed');
locationCheck(DB::table('eis_migration_issues')->count() === 7, 'Ambiguous mappings are reported persistently');
locationCheck(DB::table('eis_settings')->whereIn('id', [4, 5, 6])->whereNotNull('location_id')->count() === 0, 'Duplicate sites and duplicate device ownership are not guessed');
locationCheck(DB::table('business_locations')->where('id', 1)->value('default_eis_setting_id') === null, 'Migration does not invent a default where multiple terminals share a location');
DB::table('business_locations')->where('id', 1)->update(['default_eis_setting_id' => 1]);
locationCheck((int) DB::table('eis_configurations')->where('id', 2)->value('eis_setting_id') === 2, 'Configuration maps by terminal identity');
locationCheck((int) DB::table('eis_sales')->where('id', 1)->value('eis_setting_id') === 1, 'Historical sale maps by location and invoice identity');
locationCheck(DB::table('eis_sales')->where('id', 1)->value('eis_invoice_number') === $number, 'Immutable invoice identity survives receipt replacement');
locationCheck((int) DB::table('eis_failed_transactions')->where('transaction_id', 1)->value('eis_setting_id') === 1, 'Retry records inherit terminal ownership');
locationCheck(TerminalContext::forLocation(1, 1, true)->id === 1, 'First location selects its own default');
locationCheck(TerminalContext::forLocation(1, 2, true)->id === 2, 'Second location selects its own default');
locationReject(fn () => TerminalContext::settings(1), 'No arbitrary terminal selection for a multi-terminal business');
locationReject(fn () => TerminalContext::settings(2, null, 1), 'Cross-business terminal selection is rejected');
locationReject(fn () => TerminalContext::settings(1, null, 1, 2), 'Cross-location terminal selection is rejected');
locationReject(fn () => TerminalContext::settings(1, 'token2', 1), 'Foreign terminal token is rejected');
locationCheck($generator->getDailyCount(1, 1) === 1 && $generator->getDailyCount(1, 2) === 0, 'Daily invoice counts are terminal-scoped');
DB::table('eis_terminal_configurations')->where('configuration_id', 1)->update(['is_active' => 0]);
locationReject(fn () => TerminalContext::forLocation(1, 1, true), 'Inactive default does not silently select another terminal');
DB::table('business_locations')->where('id', 1)->update(['default_eis_setting_id' => null]);
locationReject(fn () => TerminalContext::forLocation(1, 1), 'Missing designated default blocks routing');
$sale = new App\Transaction();
$sale->setRawAttributes(['id' => 1, 'business_id' => 1, 'location_id' => 1]);
locationReject(fn () => TerminalContext::forSale($sale), 'Pinned inactive terminal blocks retries');
DB::table('eis_terminal_configurations')->where('configuration_id', 1)->update(['is_active' => 1]);
locationCheck(TerminalContext::forSale($sale)->id === 1, 'Retries use recorded terminal independently of the location default');
$sale->id = 2;
locationReject(fn () => TerminalContext::forSale($sale), 'Unreconciled historical terminal is not silently reassigned');
$sale->id = 99;
locationReject(fn () => TerminalContext::forSale($sale), 'Unreconciled orphan retry does not silently use a new terminal');
try {
    $migration->down();
    throw new RuntimeException('Rollback should reject multi-terminal ownership');
} catch (RuntimeException $e) {
    locationCheck(str_contains($e->getMessage(), 'Cannot roll back multi-terminal')
        && $schema->hasColumn('eis_settings', 'location_id'), 'Unsafe singleton rollback is rejected before schema changes');
}
echo "PASS: {$checks} location-terminal migration and routing checks (SQLite, no network).\n";
