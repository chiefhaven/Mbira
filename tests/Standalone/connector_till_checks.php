<?php

// Isolated SQLite/API fixtures; never boots the application or reads .env.
require __DIR__.'\eis_terminal_isolation_checks.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Connector\Http\Controllers\Api\TillController;
use Modules\Connector\Http\Middleware\ConnectorTill;
use Modules\Connector\Support\TillAssignment;
use Modules\EIS\Services\Terminal\TerminalContext;

$migration = require dirname(__DIR__, 2).'\Modules\Connector\Database\Migrations\2026_10_23_200000_create_connector_tills.php';
$migration->up();
$app['config']['eis.environment'] = 'production';
$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en');
$validator = new Illuminate\Validation\Factory($translator, $app);
$app->instance('validator', $validator);
Request::macro('validate', function ($rules) use ($validator) {
    return $validator->make($this->all(), $rules)->validate();
});
$responses = Mockery::mock(Illuminate\Contracts\Routing\ResponseFactory::class);
$responses->shouldReceive('json')->andReturnUsing(fn ($data, $status = 200) => new Illuminate\Http\JsonResponse($data, $status));
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class, $responses);
$actor = new class {
    public int $id = 1;
    public int $business_id = 1;
    public bool $manage = true;
    public $locations = [1];
    public function can($permission) { return $this->manage && $permission === 'business_settings.access'; }
    public function permitted_locations($businessId) { return $businessId === $this->business_id ? $this->locations : []; }
};
function tillRequest(array $input = [], ?string $header = null): Request
{
    global $actor;
    $request = Request::create('/connector/api/tills', 'POST', $input);
    $request->setUserResolver(fn () => $actor);
    if ($header !== null) { $request->headers->set('X-POS-Till', $header); }
    return $request;
}
function tillReject(callable $work, string $message): void
{
    try { $work(); }
    catch (ValidationException | Illuminate\Database\Eloquent\ModelNotFoundException | Symfony\Component\HttpKernel\Exception\HttpException $e) {
        locationCheck(true, $message);
        return;
    }
    throw new RuntimeException($message.' was accepted');
}
$controller = new TillController();
$till = $controller->store(tillRequest(['name' => 'Front till', 'location_id' => 1, 'eis_setting_id' => 1]))->getData(true)['data'];
$tillId = $till['id'];
locationCheck($till['name'] === 'Front till' && (int) $till['eis_setting_id'] === 1, 'Named till links to its own location terminal');
$controller->store(tillRequest(['name' => 'Front till', 'location_id' => 1, 'eis_setting_id' => 1]));
locationCheck(DB::table('connector_tills')->count() === 1, 'Saving same named till updates rather than duplicates it');
tillReject(fn () => $controller->store(tillRequest(['name' => 'Wrong', 'location_id' => 1, 'eis_setting_id' => 2])),
    'Till cannot reference a terminal at another site');
tillReject(fn () => $controller->store(tillRequest(['name' => 'Wrong', 'location_id' => 2, 'eis_setting_id' => 2])),
    'Admin location permissions are enforced for till creation');
tillReject(fn () => $controller->store(tillRequest(['name' => '   ', 'location_id' => 1, 'eis_setting_id' => 1])),
    'Blank till names fail explicitly');
$actor->manage = false;
tillReject(fn () => $controller->store(tillRequest(['name' => 'Cashier', 'location_id' => 1, 'eis_setting_id' => 1])),
    'Cashiers cannot create or reassign named tills');
locationCheck(count($controller->index(tillRequest())->getData(true)['data']['tills']) === 1,
    'Cashier can resolve permitted existing tills without settings permission');
$middleware = new ConnectorTill();
$request = tillRequest(['sells' => [['location_id' => 1]]], (string) $tillId);
locationCheck($middleware->handle($request, fn ($r) => $r->attributes->get('connector_till_id')) === $tillId,
    'Connector resolves header to server-owned till context');
foreach (['0', '-1', 'bad', (string) ($tillId + 100)] as $bad) {
    tillReject(fn () => $middleware->handle(tillRequest([], $bad), fn () => true), 'Invalid till header rejected: '.$bad);
}
tillReject(fn () => $middleware->handle(tillRequest(['sells' => [['location_id' => 2]]], (string) $tillId), fn () => true),
    'Bulk sale location must match the till');
$otherSiteRequest = Request::create('/connector/api/restaurant/retry-fiscal/4', 'POST');
$otherSiteRequest->setUserResolver(fn () => $actor);
$otherSiteRequest->headers->set('X-POS-Till', (string) $tillId);
$route = new Illuminate\Routing\Route('POST', 'connector/api/restaurant/retry-fiscal/{id}', fn () => null);
$route->bind($otherSiteRequest);
$otherSiteRequest->setRouteResolver(fn () => $route);
tillReject(fn () => $middleware->handle($otherSiteRequest, fn () => true),
    'Even fiscal retry must target a sale at the selected device till location');
$actor->locations = [2];
tillReject(fn () => TillAssignment::validate($actor, $tillId), 'Cashier cannot use a till at an unpermitted location');
$actor->locations = 'all';
$actor->business_id = 2;
tillReject(fn () => TillAssignment::validate($actor, $tillId), 'Another business cannot use the till');
$actor->business_id = 1;
DB::table('business_locations')->where('id', 1)->update(['default_eis_setting_id' => null]);
DB::transaction(fn () => TillAssignment::bind($actor, 3, $tillId));
$sale = App\Transaction::findOrFail(3);
locationCheck((int) $sale->connector_till_id === $tillId && TerminalContext::forSale($sale)->id === 1,
    'Assigned till routes a new sale without requiring a location default');
$limits = Modules\EIS\Models\EisSale::checkOfflineLimits(1, 10, 1, $tillId);
locationCheck($limits['success'] && $limits['code'] !== 'CHECK_ERROR',
    'Offline-limit check uses assigned till rather than requiring the missing location default');
$settings = Modules\EIS\Models\EisSetting::findOrFail(1);
$configuration = Modules\EIS\Models\EisConfiguration::where('eis_setting_id', 1)->firstOrFail();
DB::table('eis_terminal_configurations')->where('configuration_id', $configuration->id)->update(['is_active' => false]);
$app['config']['eis.environment'] = 'development';
locationCheck(TerminalContext::forSale($sale)->id === 1, 'Development till accepts locally enabled remotely inactive terminal');
$app['config']['eis.environment'] = 'production';
locationReject(fn () => TerminalContext::forSale($sale), 'Unavailable terminal without usable default blocks submission explicitly');
DB::table('eis_settings')->where('id', $newId)->update(['status' => true]);
DB::table('eis_terminal_configurations')->where('configuration_id', $selectedConfiguration->id)->update(['is_active' => true]);
DB::table('business_locations')->where('id', 1)->update(['default_eis_setting_id' => $newId]);
locationCheck(TerminalContext::forSale($sale)->id === $newId, 'Unavailable linked terminal falls back to same location default');
DB::table('eis_sales')->insert(['business_id' => 1, 'transaction_id' => 3, 'location_id' => 1, 'eis_setting_id' => $newId, 'status' => 'pending']);
DB::table('eis_terminal_configurations')->where('configuration_id', $configuration->id)->update(['is_active' => true]);
DB::table('business_locations')->where('id', 1)->update(['default_eis_setting_id' => 1]);
locationCheck(TerminalContext::forSale($sale)->id === $newId, 'Retry stays on recorded fallback terminal after linked terminal recovers');
DB::table('eis_settings')->where('id', $newId)->update(['status' => false]);
locationReject(fn () => TerminalContext::forSale($sale), 'Recorded inactive retry never falls back to a different terminal');
DB::beginTransaction();
tillReject(fn () => TillAssignment::bind($actor, 4, $tillId), 'Saved checkout cannot bind a till from another location');
DB::rollBack();
DB::table('connector_tills')->where('id', $tillId)->update(['is_active' => false]);
tillReject(fn () => $middleware->handle(tillRequest([], (string) $tillId), fn () => true), 'Locally disabled till is not accepted from a device');
DB::table('connector_tills')->where('id', $tillId)->update(['is_active' => true]);
DB::beginTransaction();
TillAssignment::bind($actor, 1, $tillId);
locationCheck(DB::table('transactions')->where('id', 1)->value('connector_till_id') === null, 'Binding cannot rewrite an already fiscal-tracked sale');
DB::rollBack();
$migration->down();
locationCheck(!$schema->hasTable('connector_tills') && !$schema->hasColumn('transactions', 'connector_till_id'), 'Till migration rolls back its own schema only');
Mockery::close();
echo "PASS: {$checks} cumulative Connector till checks (SQLite, no network).\n";
