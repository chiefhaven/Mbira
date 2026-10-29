<?php

// SQLite and fake HTTP only; no .env, application boot, or live token rotations.
require __DIR__.'\eis_terminal_management_checks.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Configuration\EisConfigurationClient;
use Modules\EIS\Services\Configuration\TerminalTokenService;

$configurationChecks = 0;
function configCheck(bool $condition, string $message): void
{
    global $configurationChecks;
    if (!$condition) { throw new RuntimeException($message); }
    $configurationChecks++;
}
function configReject(callable $work, string $message): void
{
    try { $work(); } catch (InvalidArgumentException | UnexpectedValueException | RuntimeException $e) {
        configCheck(true, $message); return;
    }
    throw new RuntimeException('Expected rejection: '.$message);
}
$logger = new class {
    public array $entries = [];
    public function __call($method, $args) { $this->entries[] = $args; }
};
$app->instance('log', $logger);
$app['config']->set('eis.base_url', 'https://eis.invalid');
$client = new class extends EisConfigurationClient {
    public int $waits = 0;
    protected function backoff(int $attempt): void { $this->waits++; }
};
$factory = new Factory();
Http::swap($factory);
$requests = [];
$status = 200;
$disconnect = false;
$onRequest = null;
$body = ['statusCode' => 1, 'data' => (object) [
    'globalConfiguration' => (object) ['versionNo' => 1, 'taxrates' => []],
    'terminalConfiguration' => (object) ['versionNo' => 2],
    'taxpayerConfiguration' => (object) ['versionNo' => 3],
]];
$factory->fake(function ($request) use ($factory, &$requests, &$status, &$body, &$disconnect, &$onRequest) {
    $requests[] = $request;
    if ($onRequest) { $onRequest(); }
    if ($disconnect) { throw new ConnectionException('Synthetic timeout with SECRET-NEVER-LOG'); }
    return $factory->response($body, $status);
});
$token = str_repeat('fixture-token-', 3);
foreach (['https://eis.invalid', 'https://eis.invalid/api/v1/'] as $base) {
    $app['config']->set('eis.base_url', $base);
    $result = $client->latest($token, 1);
    $sent = end($requests);
    configCheck($sent->url() === 'https://eis.invalid/api/v1/configuration/get-latest-configs'
        && $sent->method() === 'POST' && $sent->body() === '', 'Exact latest configuration path, POST and no request body');
    configCheck($sent->hasHeader('Authorization', 'Bearer '.$token), 'Selected terminal token authenticates the configuration request');
    configCheck($result->getGlobalConfiguration()->versionNo == 1 && $result->getTerminalConfiguration()->versionNo == 2
        && $result->getTaxpayerConfiguration()->versionNo == 3,
        'One response exposes all three independent configuration versions');
}
foreach ([
    [200, ['statusCode' => 0, 'data' => (object) []]],
    [200, ['statusCode' => 1, 'errors' => ['bad'], 'data' => (object) []]],
    [200, ['statusCode' => 1, 'data' => (object) ['globalConfiguration' => (object) []]]],
    [200, ['statusCode' => 1, 'data' => []]],
    [401, ['data' => (object) []]],
    [302, ['statusCode' => 1, 'data' => (object) []]],
] as [$status, $body]) {
    $before = count($requests);
    configReject(fn () => $client->latest($token, 1), 'Unsuccessful or malformed configuration is explicitly rejected');
    configCheck(count($requests) === $before + 1, 'Permanent configuration failure does not retry');
}
$status = 503; $body = ['statusCode' => 0];
$before = count($requests);
configReject(fn () => $client->latest($token, 1), 'Repeated configuration server failure is surfaced');
configCheck(count($requests) === $before + 3, 'Read-only configuration server failures retry at most three times');
$disconnect = true; $before = count($requests);
configReject(fn () => $client->latest($token, 1), 'Configuration connection failure is surfaced');
configCheck(count($requests) === $before + 3, 'Read-only connection failure retries at most three times');
$before = count($requests);
try { $client->requestNewTerminalToken($token, 1); } catch (ConnectionException $e) {}
configCheck(count($requests) === $before + 1, 'Token request never retries a lost response');
$disconnect = false; $before = count($requests);
configReject(fn () => $client->requestNewTerminalToken($token, 1), 'Token server rejection is explicit');
configCheck(count($requests) === $before + 1, 'Token request never retries a server failure');
$disconnect = false; $status = 200;
$body = ['statusCode' => 1, 'data' => (object) ['jwtToken' => str_repeat('new-token-', 4)]];
$client->requestNewTerminalToken($token, 1);
$sent = end($requests);
configCheck($sent->url() === 'https://eis.invalid/api/v1/configuration/request-new-terminal-token'
    && $sent->method() === 'POST' && $sent->body() === '', 'Exact documented new-token endpoint and no invented request fields');
$before = count($requests);
configReject(fn () => $client->latest('', 1), 'Missing token rejected before HTTP');
configCheck(count($requests) === $before, 'Invalid credentials do not contact EIS');

$schema->create('business', function (Blueprint $t) { $t->increments('id'); });
DB::table('business')->insert(['id' => 1]);
$schema->table('eis_settings', function (Blueprint $t) {
    $t->string('branch_id')->nullable(); $t->string('tpin')->nullable();
});
$schema->table('eis_terminal_configurations', function (Blueprint $t) {
    $t->string('jwt_token')->nullable(); $t->string('secret_key')->nullable();
});
$app->instance('db.schema', $schema);
$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_24_023000_create_eis_terminal_token_requests.php';
$migration->up();
DB::table('eis_settings')->where('id', 101)->update(['branch_id' => 'SITE-A', 'tpin' => '12345678', 'jwt_token' => $token]);
$tokens = new TerminalTokenService($client);
$setting = EisSetting::findOrFail(101);
$body['data']->secretKey = 'new-secret';
$before = count($requests);
$result = $tokens->request($setting, 7);
configCheck($result['success'] && count($requests) === $before + 1, 'One audited token request sends once');
$saved = EisSetting::findOrFail(101);
configCheck($saved->jwt_token === $body['data']->jwtToken && $saved->secret_key === 'new-secret'
    && DB::table('eis_terminal_configurations')->where('configuration_id', 1101)->value('jwt_token') === $saved->jwt_token,
    'Renewed credentials are persisted atomically in the setting and its own terminal configuration');
configCheck(DB::table('eis_settings')->where('id', 201)->value('jwt_token') === 'fixture-token-201'
    && (int) DB::table('business_locations')->where('id', 10)->value('default_eis_setting_id') === 102,
    'Token request leaves other tenant credentials and local default designation unchanged');
$audit = DB::table('eis_terminal_token_requests')->first();
configCheck($audit->status === 'accepted' && (int) $audit->created_by === 7 && (int) $audit->location_id === 10,
    'Audit retains actor, selected terminal location and result');
configCheck(!str_contains(json_encode($result), $saved->jwt_token)
    && !str_contains(json_encode($audit), $saved->jwt_token) && !str_contains(json_encode($audit), 'new-secret'),
    'Response and audit never contain renewed credentials');
$body['data'] = (object) ['jwtToken' => str_repeat('jwt-only-', 4)];
$result = $tokens->request($saved, 7);
configCheck($result['success'] && EisSetting::findOrFail(101)->secret_key === 'new-secret'
    && DB::table('eis_terminal_configurations')->where('configuration_id', 1101)->value('secret_key') === 'new-secret',
    'JWT-only response preserves both copies of the existing secret key');
configReject(fn () => $tokens->request($setting, 7), 'Stale credentials cannot start a token request');
$saved = EisSetting::findOrFail(101);
DB::beginTransaction();
configReject(fn () => $tokens->request($saved, 7), 'Token claim cannot run inside an outer transaction');
DB::rollBack();

$disconnect = true; $before = count($requests);
$result = $tokens->request($saved, 7);
configCheck(!$result['success'] && count($requests) === $before + 1
    && DB::table('eis_terminal_token_requests')->orderByDesc('id')->value('status') === 'unknown',
    'Lost token response persists unknown with no retry');
configReject(fn () => $tokens->request($saved, 7), 'Unknown token outcome blocks subsequent requests');
configCheck(count($requests) === $before + 1 && EisSetting::findOrFail(101)->jwt_token === $saved->jwt_token,
    'Unknown outcome never overwrites credentials or sends again');
DB::table('eis_terminal_token_requests')->delete();
$disconnect = false; $body = ['statusCode' => 1, 'data' => (object) ['unrecognized' => 'SECRET-NEVER-LOG']];
$result = $tokens->request($saved, 7);
configCheck(!$result['success'] && EisSetting::findOrFail(101)->jwt_token === $saved->jwt_token,
    'Opaque Swagger response is never guessed or stored as successful credentials');
configCheck(!str_contains(json_encode($logger->entries), 'SECRET-NEVER-LOG')
    && !str_contains(json_encode($logger->entries), $saved->jwt_token), 'Errors log safe metadata, not response bodies or credentials');
DB::table('eis_terminal_token_requests')->delete();
$body = ['statusCode' => 1, 'data' => (object) ['jwtToken' => str_repeat('another-token-', 3)]];
$onRequest = function () { DB::table('eis_settings')->where('id', 101)->update(['device_id' => 'MOVED']); };
$result = $tokens->request($saved, 7);
configCheck(!$result['success'] && EisSetting::findOrFail(101)->jwt_token === $saved->jwt_token,
    'Concurrent terminal identity change prevents credential persistence and requires verification');
$onRequest = null;
DB::table('eis_settings')->where('id', 101)->update(['device_id' => 'DEVICE-101']);
DB::table('eis_terminal_token_requests')->delete();

$user->locations = [10];
managementReject(fn () => $controller->requestNewToken(managementRequest(['confirmed' => 1]), 201, $tokens), 404,
    'New token action rejects a foreign tenant before HTTP');
$user->allowed = false;
managementReject(fn () => $controller->requestNewToken(managementRequest(['confirmed' => 1]), 101, $tokens), 403,
    'New token action requires settings permission');
$user->allowed = true;
try {
    $controller->requestNewToken(managementRequest(), 101, $tokens);
    throw new RuntimeException('Unconfirmed token request accepted');
} catch (Illuminate\Validation\ValidationException $e) { configCheck(true, 'Explicit token-request confirmation is required'); }
$before = count($requests);
$result = $controller->requestNewToken(managementRequest(['confirmed' => 1, 'token' => 'attacker-input']), 101, $tokens)->getData(true);
configCheck($result['success'] && count($requests) === $before + 1 && end($requests)->hasHeader('Authorization', 'Bearer '.$saved->jwt_token),
    'Controller ignores supplied credentials, uses its scoped terminal and returns no secret');
$migration->down();
echo "PASS: {$configurationChecks} Configuration checks (plus management fixture), fake HTTP and SQLite only.\n";
