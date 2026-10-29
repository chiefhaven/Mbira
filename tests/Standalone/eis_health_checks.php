<?php

// Isolated cache and HTTP fakes: no kernel, .env, database or live EIS requests.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Carbon\Carbon;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Modules\EIS\Services\Http\EisHealthService;

$container = new Container();
Container::setInstance($container);
$container->instance('config', new Config(['eis' => ['base_url' => 'https://eis.invalid']]));
$container->instance('cache', new Repository(new ArrayStore()));
$container->instance('log', new Psr\Log\NullLogger());
Facade::setFacadeApplication($container);
Facade::clearResolvedInstances();
Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00'));
$checks = 0;
function checkHealth(bool $condition, string $message): void {
    global $checks;
    if (! $condition) { throw new RuntimeException($message); }
    ++$checks;
}
function fakeHealth(callable $callback): void {
    global $container;
    $factory = new Factory();
    $factory->preventStrayRequests();
    $container->instance(Factory::class, $factory);
    Http::clearResolvedInstance(Factory::class);
    Http::fake($callback);
}
$pong = ['statusCode' => 1, 'errors' => [], 'data' => ['serverDate' => '2026-10-15T10:00:00Z']];
$health = new EisHealthService();
foreach (['https://eis.invalid', 'https://eis.invalid/', 'https://eis.invalid/api/v1', 'https://eis.invalid/api/v1/'] as $base) {
    $container['config']->set('eis.base_url', $base);
    $health->clearCache(7);
    fakeHealth(fn () => Http::response($pong));
    checkHealth($health->isOnline(7, 'fixture-token'), 'Valid pong is online');
    $request = Http::recorded()[0][0];
    checkHealth($request->url() === 'https://eis.invalid/api/v1/utilities/ping', 'Exact versioned ping URL');
    checkHealth($request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer fixture-token'), 'Authenticated POST');
}

$health->clearCache(7);
Cache::put('eis.health.7', false, 30000);
Cache::put('eis_status_7', 'offline', 300);
fakeHealth(fn () => Http::response($pong));
checkHealth($health->isOnline(7, 'fixture-token'), 'Legacy offline cache ignored');
checkHealth($health->isOnline(7, 'fixture-token') && count(Http::recorded()) === 1, 'Reuse healthy cache');
Carbon::setTestNow(Carbon::now()->addSeconds(61));
checkHealth($health->isOnline(7, 'fixture-token') && count(Http::recorded()) === 2, 'Healthy status expires after 60 seconds');
checkHealth($health->isOnline(7, 'rotated-token') && count(Http::recorded()) === 3, 'Token rotation rechecks immediately');
$container['config']->set('eis.base_url', 'https://other.invalid');
checkHealth($health->isOnline(7, 'rotated-token') && count(Http::recorded()) === 4, 'Host change rechecks immediately');
checkHealth($health->isOnline(8, 'rotated-token') && count(Http::recorded()) === 5, 'Business caches are separate');

foreach ([400, 401, 403, 404, 429, 500, 503] as $status) {
    $health->clearCache(7);
    fakeHealth(fn () => Http::response($pong, $status));
    checkHealth(! $health->isOnline(7, 'fixture-token'), 'HTTP failure remains offline: '.$status);
}
foreach (['<html>login</html>', [], ['statusCode' => 0, 'data' => $pong['data']],
    ['statusCode' => 1, 'data' => null], ['statusCode' => 1, 'data' => ['serverDate' => '']],
    ['statusCode' => '1', 'data' => $pong['data']]] as $body) {
    $health->clearCache(7);
    fakeHealth(fn () => Http::response($body));
    checkHealth(! $health->isOnline(7, 'fixture-token'), 'Invalid pong envelopes are offline');
}
$health->clearCache(7);
fakeHealth(fn () => Http::response(array_replace($pong, ['errors' => [['errorMessage' => 'failure']]])));
checkHealth(! $health->isOnline(7, 'fixture-token'), 'Envelope errors rejected');
$health->clearCache(7);
fakeHealth(function () { throw new ConnectionException('fixture offline'); });
checkHealth(! $health->isOnline(7, 'fixture-token'), 'Network failure is offline');
fakeHealth(fn () => Http::response($pong));
checkHealth(! $health->isOnline(7, 'fixture-token') && count(Http::recorded()) === 0, 'Failure is briefly cached');
Carbon::setTestNow(Carbon::now()->addSeconds(16));
checkHealth($health->isOnline(7, 'fixture-token'), 'Recovered connection rechecks after 15 seconds');
$health->clearCache(7);
checkHealth(Cache::get('eis.health.v2.7') === null && Cache::get('eis.health.7') === null
    && Cache::get('eis_status_7') === null, 'Clear current and legacy caches');
fakeHealth(fn () => Http::response($pong));
checkHealth(! $health->isOnline(7, '') && count(Http::recorded()) === 0, 'No request without token');
$container['config']->set('eis.base_url', '');
checkHealth(! $health->isOnline(7, 'fixture-token') && count(Http::recorded()) === 0, 'No request without host');
$header = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/partials/header.blade.php');
checkHealth(! str_contains($header, 'Cache::remember') && ! str_contains($header, 'eis_status_'), 'Header has no duplicate stale cache');
$source = file_get_contents(dirname(__DIR__, 2).'/Modules/EIS/Services/Http/EisHealthService.php');
checkHealth(str_contains($source, '->connectTimeout($connectTimeout)->timeout($timeout)')
    && str_contains($source, "'allow_redirects' => false"), 'Bounded probe does not follow login redirects');
$logger = new class extends Psr\Log\AbstractLogger {
    public array $entries = [];
    public function log($level, $message, array $context = []): void {
        $this->entries[] = $context;
    }
};
$container->instance('log', $logger);
Illuminate\Support\Facades\Log::clearResolvedInstance('log');
$container['config']->set('eis.base_url', 'https://eis.invalid');
foreach ([5 => 'proxy_dns_failed', 6 => 'host_dns_failed', 7 => 'connection_failed',
    28 => 'timeout', 35 => 'tls_handshake_failed', 60 => 'tls_certificate_failed',
    77 => 'tls_ca_bundle_invalid'] as $errno => $reason) {
    $health->clearCache(7);
    fakeHealth(function () use ($errno) {
        $previous = new GuzzleHttp\Exception\ConnectException('sensitive-fixture-token',
            new GuzzleHttp\Psr7\Request('POST', 'https://eis.invalid'), null, ['errno' => $errno]);
        throw new ConnectionException('sensitive-fixture-token', 0, $previous);
    });
    checkHealth(! $health->isOnline(7, 'fixture-token'), 'Connection failure stays offline');
    $entry = end($logger->entries);
    checkHealth($entry['connect_timeout_seconds'] === 20 && $entry['timeout_seconds'] === 20, 'Health probe defaults to 20 seconds');
    checkHealth($entry['curl_errno'] === $errno && $entry['connection_reason'] === $reason, 'Safe connection classification: '.$reason);
    checkHealth(! str_contains(json_encode($entry), 'fixture-token'), 'Diagnostics never log exception text or credentials');
}
Carbon::setTestNow();
echo "PASS: {$checks} EIS health checks (isolated cache and HTTP fakes).\n";