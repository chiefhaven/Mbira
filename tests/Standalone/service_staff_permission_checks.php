<?php

// Real Spatie permission scopes with SQLite memory; no live application data.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\User;
use App\Utils\Util;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Spatie\Permission\Models\Permission;

$container = new Container();
Container::setInstance($container);
$db = new Capsule($container);
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->setAsGlobal();
$db->bootEloquent();
$container->instance('config', new Repository([
    'database' => ['default' => 'default', 'connections' => [
        'default' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ]],
    'permission' => require dirname(__DIR__, 2).'/config/permission.php',
    'auth' => ['defaults' => ['guard' => 'web'],
        'guards' => ['web' => ['provider' => 'users']],
        'providers' => ['users' => ['model' => User::class]]],
]));
$container->instance('db', $db->getDatabaseManager());
$logger = new class extends Psr\Log\NullLogger {
    public array $warnings = [];
    public function warning($message, array $context = []): void { $this->warnings[] = $context; }
};
$container->instance('log', $logger);
Facade::setFacadeApplication($container);
$registrar = new Spatie\Permission\PermissionRegistrar(new Illuminate\Cache\CacheManager($container));
$container->instance(Spatie\Permission\PermissionRegistrar::class, $registrar);
$schema = $db->getConnection()->getSchemaBuilder();
$schema->create('users', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->softDeletes();
});
foreach (['roles', 'permissions'] as $table) {
    $schema->create($table, function (Blueprint $t) use ($table) {
        $t->increments('id'); $t->string('name'); $t->string('guard_name');
        if ($table === 'roles') { $t->integer('business_id'); $t->boolean('is_service_staff'); }
    });
}
foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $table => $key) {
    $schema->create($table, function (Blueprint $t) use ($key) {
        $t->integer($key); $t->string('model_type'); $t->integer('model_id');
    });
}
$schema->create('role_has_permissions', function (Blueprint $t) {
    $t->integer('role_id'); $t->integer('permission_id');
});
$connection = $db->getConnection();
$connection->table('roles')->insert([
    ['id' => 1, 'name' => 'waiter', 'guard_name' => 'web', 'business_id' => 7, 'is_service_staff' => 1],
    ['id' => 2, 'name' => 'other-waiter', 'guard_name' => 'web', 'business_id' => 8, 'is_service_staff' => 1],
]);
foreach ([1, 2, 3, 4] as $id) {
    $connection->table('users')->insert(['id' => $id, 'business_id' => $id === 4 ? 8 : 7]);
    $connection->table('model_has_roles')->insert([
        'role_id' => $id === 4 ? 2 : 1, 'model_type' => User::class, 'model_id' => $id,
    ]);
}
$connection->table('permissions')->insert([
    ['id' => 1, 'name' => 'access_all_locations', 'guard_name' => 'web'],
    ['id' => 2, 'name' => 'location.39', 'guard_name' => 'api'],
    ['id' => 3, 'name' => 'location.40', 'guard_name' => 'web'],
]);
foreach ([[1, 1], [2, 2], [3, 3], [4, 1]] as [$user, $permission]) {
    $connection->table('model_has_permissions')->insert([
        'permission_id' => $permission, 'model_type' => User::class, 'model_id' => $user,
    ]);
}
$checks = 0;
function staffCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
}
$util = new Util();
staffCheck($util->getServiceStaff(7, 39)->pluck('id')->all() === [1],
    'Missing web location definition permits only explicit all-location staff, not other guards or tenants');
staffCheck($logger->warnings[0]['permissions'] === ['location.39'], 'Missing definition is explicitly logged');
$connection->table('permissions')->insert(['id' => 4, 'name' => 'location.39', 'guard_name' => 'web']);
$connection->table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 4]);
staffCheck($util->getServiceStaff(7, 39)->pluck('id')->all() === [1, 2, 3],
    'Existing role-level location grants remain supported');
$connection->table('role_has_permissions')->delete();
$connection->table('model_has_permissions')->insert([
    'permission_id' => 4, 'model_type' => User::class, 'model_id' => 2,
]);
staffCheck($util->getServiceStaff(7, 39)->pluck('id')->all() === [1, 2],
    'Direct web location grants remain supported without granting other staff access');
$connection->table('model_has_permissions')->where('permission_id', 4)->delete();
$connection->table('permissions')->where('id', 4)->delete();
$connection->table('permissions')->where('id', 1)->delete();
staffCheck($util->getServiceStaff(7, 39)->isEmpty(), 'No matching definitions returns no staff, not unrestricted staff');
staffCheck($util->getServiceStaff(7)->count() === 3, 'Unfiltered service staff query retains existing behavior');
echo "PASS: {$checks} service staff permission checks (SQLite memory, no network).\n";
