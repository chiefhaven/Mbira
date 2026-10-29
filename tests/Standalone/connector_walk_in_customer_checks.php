<?php

// SQLite memory only: no application boot, .env, live data, or external calls.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Utils\ContactUtil;
use App\Utils\Util;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Modules\Connector\Http\Controllers\Api\CommonResourceController;

$container = new Container();
Container::setInstance($container);
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
Facade::setFacadeApplication($container);
Facade::clearResolvedInstances();

$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('business', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name');
    $table->integer('currency_id')->nullable();
});
foreach (['business_locations', 'printers'] as $tableName) {
    $schema->create($tableName, function (Blueprint $table) {
        $table->increments('id');
        $table->integer('business_id');
    });
}
$schema->create('currencies', function (Blueprint $table) {
    $table->increments('id');
});
$schema->create('contacts', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->string('type');
    $table->string('name');
    $table->boolean('is_default')->default(false);
    $table->integer('customer_group_id')->nullable();
    $table->string('email')->nullable();
    $table->string('tax_number')->nullable();
    $table->softDeletes();
});
$schema->create('customer_groups', function (Blueprint $table) {
    $table->increments('id');
    $table->decimal('amount')->nullable();
    $table->string('price_calculation_type')->nullable();
    $table->integer('selling_price_group_id')->nullable();
});
$db = $capsule->getConnection();
$db->table('business')->insert([
    ['id' => 7, 'name' => 'First business'],
    ['id' => 8, 'name' => 'Second business'],
]);
$db->table('contacts')->insert([
    ['id' => 1, 'business_id' => 8, 'type' => 'customer', 'name' => 'Other business default', 'is_default' => 1],
    ['id' => 2, 'business_id' => 7, 'type' => 'supplier', 'name' => 'Default supplier', 'is_default' => 1],
    ['id' => 3, 'business_id' => 7, 'type' => 'customer', 'name' => 'Ordinary customer', 'is_default' => 0],
]);
$db->table('contacts')->insert([
    'id' => 42, 'business_id' => 7, 'type' => 'both', 'name' => 'Default customer',
    'is_default' => 1, 'email' => 'private@example.invalid', 'tax_number' => 'PRIVATE',
]);

$permissions = [];
$isAdmin = false;
$user = Mockery::mock();
$user->business_id = 7;
$user->shouldReceive('can')->andReturnUsing(function ($permission) use (&$permissions) {
    return in_array($permission, $permissions, true);
});
$auth = Mockery::mock();
$auth->shouldReceive('user')->andReturn($user);
$container->instance('auth', $auth);
$common = Mockery::mock(Util::class);
$common->shouldReceive('is_admin')->andReturnUsing(function () use (&$isAdmin) {
    return $isAdmin;
});
$controller = (new ReflectionClass(CommonResourceController::class))->newInstanceWithoutConstructor();
$property = new ReflectionProperty(CommonResourceController::class, 'commonUtil');
$property->setAccessible(true);
$property->setValue($controller, $common);
$request = Request::create('/connector/api/business-details', 'GET');
$contactUtil = new ContactUtil();
$checks = 0;

function verifyWalkIn($condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}

foreach ([[], ['customer.view'], ['sell.create'], ['superadmin']] as $permissions) {
    $data = $controller->getBusinessDetails($contactUtil)->toArray($request);
    $allowed = in_array('sell.create', $permissions, true) || in_array('superadmin', $permissions, true);
    verifyWalkIn($data['id'] === 7, 'Existing business response preserved');
    verifyWalkIn(array_key_exists('locations', $data) && array_key_exists('printers', $data), 'Existing relations preserved');
    verifyWalkIn(
        $data['walk_in_customer'] === ($allowed ? ['id' => 42, 'name' => 'Default customer'] : null),
        'Only POS creators and administrators receive the default ID and name'
    );
}
$permissions = [];
$isAdmin = true;
$data = $controller->getBusinessDetails($contactUtil)->toArray($request);
verifyWalkIn($data['walk_in_customer'] === ['id' => 42, 'name' => 'Default customer'], 'Backend admin without explicit create permission');
$user->business_id = 8;
$data = $controller->getBusinessDetails($contactUtil)->toArray($request);
verifyWalkIn($data['walk_in_customer'] === ['id' => 1, 'name' => 'Other business default'], 'Default customer isolated by authenticated business');
$user->business_id = 7;
$db->table('contacts')->where('id', 42)->update(['deleted_at' => '2026-01-01 00:00:00']);
$data = $controller->getBusinessDetails($contactUtil)->toArray($request);
verifyWalkIn($data['walk_in_customer'] === null, 'Deleted default excluded; no fallback to ordinary customer or supplier');
$db->table('contacts')->where('id', 42)->update(['deleted_at' => null, 'is_default' => 0]);
$data = $controller->getBusinessDetails($contactUtil)->toArray($request);
verifyWalkIn($data['walk_in_customer'] === null, 'Missing default returns explicit null');
Mockery::close();
echo "PASS: {$checks} Connector Walk-in authorization, minimal projection, business isolation and missing-default checks.\n";
