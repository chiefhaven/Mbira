<?php

// Isolated SQLite checks for EIS package entitlements; no application kernel or EIS API calls.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Utils\ModuleUtil;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Modules\EIS\Http\Middleware\RequireEisSubscription;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\EisSubscriptionAccess;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;

if (!class_exists('Carbon')) {
    class_alias(Carbon\Carbon::class, 'Carbon');
}

if (!class_exists('Module')) {
    class Module
    {
        public static function has(string $module): bool { return true; }
    }
}

class FixtureModuleUtil extends ModuleUtil
{
    public bool $superadminInstalled = true;

    public function __construct() {}

    public function isSuperadminInstalled()
    {
        return $this->superadminInstalled;
    }
}

$container = new Application(dirname(__DIR__, 2));
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Application::setInstance($container);
$container->instance('db', $capsule->getDatabaseManager());
Facade::setFacadeApplication($container);
$moduleUtil = new FixtureModuleUtil();
$container->instance(ModuleUtil::class, $moduleUtil);

$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('subscriptions', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->string('status');
    $table->longText('package_details')->nullable();
    $table->softDeletes();
});

$checks = 0;
function eisSubscriptionCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function addSubscription(int $businessId, array $details, string $endDate = '2999-12-31'): void
{
    Illuminate\Support\Facades\DB::table('subscriptions')->insert([
        'business_id' => $businessId,
        'start_date' => '2000-01-01',
        'end_date' => $endDate,
        'status' => 'approved',
        'package_details' => json_encode($details),
    ]);
}

$access = new EisSubscriptionAccess();
eisSubscriptionCheck(!$access->allows(0), 'Invalid business IDs are denied');
$moduleUtil->superadminInstalled = false;
eisSubscriptionCheck($access->allows(1), 'Existing installations without Superadmin subscriptions remain unrestricted');
$moduleUtil->superadminInstalled = true;
$access = new EisSubscriptionAccess();
eisSubscriptionCheck(!$access->allows(2), 'Businesses without an active subscription are denied');
addSubscription(3, ['eis_module' => false]);
eisSubscriptionCheck(!$access->allows(3), 'Packages without the EIS entitlement are denied');
addSubscription(4, ['eis_module' => true]);
eisSubscriptionCheck($access->allows(4), 'Active packages with the EIS entitlement are allowed');
try {
    (new EisSubscriptionAccess())->assertAllowed(3);
    throw new RuntimeException('An unsubscribed business must not use a direct EIS service call');
} catch (Modules\EIS\Exceptions\EisSubscriptionRequiredException $exception) {
    eisSubscriptionCheck(true, 'Direct EIS service operations throw when the package excludes EIS');
}
try {
    (new EisUtilitiesClient())->send(new EisSetting(['business_id' => 3]), 'ping', []);
    throw new RuntimeException('An unsubscribed EIS client request must be denied');
} catch (Modules\EIS\Exceptions\EisSubscriptionRequiredException $exception) {
    eisSubscriptionCheck(true, 'Direct EIS HTTP clients check package access before making requests');
}
addSubscription(5, ['eis_module' => true], '2000-01-02');
eisSubscriptionCheck(!$access->allows(5), 'Expired package subscriptions are denied');

$middleware = new RequireEisSubscription();
$request = Request::create('/v1/public/configuration/4');
$request->setRouteResolver(fn () => new class {
    public function parameter(string $name) { return $name === 'businessId' ? 4 : null; }
});
eisSubscriptionCheck(
    $middleware->handle($request, fn () => 'allowed') === 'allowed',
    'Public EIS API requests use their route business ID to check package access'
);

$request = Request::create('/v1/public/configuration/3');
$request->setRouteResolver(fn () => new class {
    public function parameter(string $name) { return $name === 'businessId' ? 3 : null; }
});
try {
    $middleware->handle($request, fn () => 'unexpected');
    throw new RuntimeException('An unsubscribed public EIS request should be denied');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    eisSubscriptionCheck($exception->getStatusCode() === 403, 'Unsubscribed businesses are denied at the route middleware');
}

$request = Request::create('/v1/webhooks/activation-callback', 'POST', ['business_id' => 4]);
eisSubscriptionCheck(
    $middleware->handle($request, fn () => 'allowed') === 'allowed',
    'Subscribed EIS webhook callbacks remain available without user authentication'
);

$request = Request::create('/v1/webhooks/activation-callback', 'POST', ['business_id' => 3]);
try {
    $middleware->handle($request, fn () => 'unexpected');
    throw new RuntimeException('An unsubscribed webhook must be denied');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    eisSubscriptionCheck($exception->getStatusCode() === 403, 'Webhook callbacks are also gated by the business subscription');
}

$request = Request::create('/eis/terminals');
$request->setUserResolver(fn () => new class {
    public function can(string $permission): bool { return $permission === 'superadmin'; }
});
eisSubscriptionCheck(
    $middleware->handle($request, fn () => 'allowed') === 'allowed',
    'Superadmins retain EIS access independent of business subscriptions'
);

echo 'PASS: '.$checks." EIS subscription entitlement checks.\n";
