<?php

// Isolated SQLite and rendered modal checks; no live database or HTTP writes.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Http\Controllers\Restaurant\TableController;
use App\Restaurant\ResTable;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

$app = new Container();
Container::setInstance($app);
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
$app->instance('log', new Psr\Log\NullLogger());
if (!class_exists('Log')) {
    class_alias(Illuminate\Support\Facades\Log::class, 'Log');
}
$kernel = Mockery::mock(Illuminate\Contracts\Http\Kernel::class);
$kernel->shouldReceive('hasMiddleware')->andReturn(false);
$app->instance(Illuminate\Contracts\Http\Kernel::class, $kernel);
$app->instance('translator', new Illuminate\Translation\Translator(
    new Illuminate\Translation\FileLoader(new Illuminate\Filesystem\Filesystem(), dirname(__DIR__, 2).'\lang'), 'en'
));
Facade::setFacadeApplication($app);
Facade::clearResolvedInstances();
$app->instance('validator', new Illuminate\Validation\Factory($app['translator'], $app));
Request::macro('validate', function (array $rules) {
    return app('validator')->make($this->all(), $rules)->validate();
});
$user = Mockery::mock();
$user->shouldReceive('can')->with('access_tables')->andReturn(true);
$auth = Mockery::mock();
$auth->shouldReceive('user')->andReturn($user);
$app->instance(Illuminate\Contracts\Auth\Factory::class, $auth);
$session = new Illuminate\Session\Store('tables', new Illuminate\Session\ArraySessionHandler(120));
$session->put('user', ['id' => 1, 'business_id' => 7]);
$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('business_locations', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
});
$capsule->table('business_locations')->insert([
    ['id' => 2, 'business_id' => 7], ['id' => 3, 'business_id' => 7], ['id' => 4, 'business_id' => 8],
]);
$schema->create('res_tables', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->integer('location_id');
    $table->integer('created_by');
    $table->string('name');
    $table->string('description')->nullable();
    $table->boolean('allow_multiple_orders')->default(false);
    $table->boolean('is_default')->default(false);
    $table->timestamps();
    $table->softDeletes();
});
$controller = new TableController();
$checks = 0;
function tableCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}
function tableRequest(array $data): Request
{
    global $app, $session;
    $request = Request::create('http://localhost/modules/tables', 'POST', $data, [], [], [
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);
    $request->setLaravelSession($session);
    $app->instance('request', $request);
    return $request;
}
$base = ['name' => 'Table 1', 'description' => 'Test table', 'location_id' => 2];
foreach ([0, 1] as $enabled) {
    $result = $controller->store(tableRequest($base + ['allow_multiple_orders' => $enabled]));
    tableCheck($result['success'] === true, 'Table creation succeeds');
    $table = $result['data']->fresh();
    tableCheck($table->allow_multiple_orders === (bool) $enabled, 'Create persists checkbox state');
    foreach ([1, 0] as $updated) {
        $result = $controller->update(tableRequest($base + ['allow_multiple_orders' => $updated]), $table->id);
        tableCheck($result['success'] === true && $table->fresh()->allow_multiple_orders === (bool) $updated,
            'Edit can enable and disable multiple orders');
    }
}
try {
    $controller->store(tableRequest($base + ['allow_multiple_orders' => 'invalid']));
    throw new RuntimeException('Expected boolean validation failure');
} catch (Illuminate\Validation\ValidationException $e) {
    tableCheck(isset($e->errors()['allow_multiple_orders']), 'Invalid checkbox values are rejected');
}

$first = $controller->store(tableRequest($base + ['is_default' => 1]))['data'];
$second = $controller->store(tableRequest($base + ['is_default' => 1]))['data'];
tableCheck(!$first->fresh()->is_default && $second->fresh()->is_default, 'Create replaces the previous location default');
$other = $controller->store(tableRequest(array_replace($base, ['location_id' => 3, 'is_default' => 1])))['data'];
$controller->update(tableRequest($base + ['is_default' => 1]), $first->id);
tableCheck($first->fresh()->is_default && !$second->fresh()->is_default && $other->fresh()->is_default,
    'Edit replaces only the default in its own location');
$controller->update(tableRequest($base + ['is_default' => 1]), $first->id);
tableCheck($first->fresh()->is_default, 'Saving an existing default preserves it');
$controller->update(tableRequest($base + ['is_default' => 0]), $first->id);
tableCheck(!ResTable::where('location_id', 2)->where('is_default', true)->exists(), 'Unchecking clears the location default');
try {
    $controller->store(tableRequest($base + ['is_default' => 'invalid']));
    throw new RuntimeException('Expected default boolean validation failure');
} catch (Illuminate\Validation\ValidationException $e) {
    tableCheck(isset($e->errors()['is_default']), 'Invalid default values are rejected');
}

$viewFactory = Mockery::mock(Illuminate\Contracts\View\Factory::class);
$app->instance(Illuminate\Contracts\View\Factory::class, $viewFactory);
$viewFactory->shouldReceive('make')->andReturnUsing(function ($name) {
    $view = Mockery::mock(Illuminate\Contracts\View\View::class);
    $view->shouldReceive('with')->andReturnUsing(fn ($data) => $data);
    return $view;
});
$util = Mockery::mock(App\Utils\Util::class);
$util->shouldReceive('isModuleEnabled')->with('tables')->andReturn(true);
$util->shouldReceive('isModuleEnabled')->with('service_staff')->andReturn(false);
$dataController = new App\Http\Controllers\Restaurant\DataController($util);
$controller->update(tableRequest($base + ['is_default' => 1]), $first->id);
$session->put('business.pos_settings', '{}');
$details = $dataController->getPosDetails(tableRequest(['location_id' => 2]));
tableCheck((int) $details['view_data']['res_table_id'] === $first->id, 'New POS orders select the location default');
$details = $dataController->getPosDetails(tableRequest(['location_id' => 3]));
tableCheck((int) $details['view_data']['res_table_id'] === $other->id, 'Changing POS location selects its own default');
$schema->create('transactions', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('res_table_id')->nullable();
    $table->integer('res_waiter_id')->nullable();
});
$capsule->table('transactions')->insert(['id' => 1, 'res_table_id' => $second->id]);
$details = $dataController->getPosDetails(tableRequest(['location_id' => 2, 'transaction_id' => 1]));
tableCheck((int) $details['view_data']['res_table_id'] === $second->id, 'Existing orders keep their selected table');
$capsule->table('transactions')->where('id', 1)->update(['res_table_id' => null]);
$details = $dataController->getPosDetails(tableRequest(['location_id' => 2, 'transaction_id' => 1]));
tableCheck($details['view_data']['res_table_id'] === null, 'Existing orders without a table are not assigned a default');
$controller->update(tableRequest($base + ['is_default' => 0]), $first->id);
$details = $dataController->getPosDetails(tableRequest(['location_id' => 2]));
tableCheck($details['view_data']['res_table_id'] === null, 'Locations without a default retain an empty selection');
$other->delete();
$details = $dataController->getPosDetails(tableRequest(['location_id' => 3]));
tableCheck($details['view_data']['res_table_id'] === null, 'Deleted defaults are not selected');

$request = $app['request'];
$routes = new Illuminate\Routing\RouteCollection();
$routes->add((new Illuminate\Routing\Route('POST', 'modules/tables', [
    'uses' => TableController::class.'@store',
    'controller' => TableController::class.'@store',
])));
$routes->add((new Illuminate\Routing\Route('PUT', 'modules/tables/{table}', [
    'uses' => TableController::class.'@update',
    'controller' => TableController::class.'@update',
])));
$url = new Illuminate\Routing\UrlGenerator($routes, $request);
$app->instance('url', $url);
$view = Mockery::mock(Illuminate\Contracts\View\Factory::class);
$html = new Collective\Html\HtmlBuilder($url, $view);
$form = new Collective\Html\FormBuilder($html, $url, $view, $session->token());
$form->setSessionStore($session);
$app->instance('form', $form);
if (!class_exists('Form')) {
    class_alias(Collective\Html\FormFacade::class, 'Form');
}
$blade = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$business_locations = collect([2 => 'Main location']);
foreach (['create' => [false], 'edit' => [false, true]] as $modal => $states) {
    foreach ($states as $state) {
        $table = new ResTable(['name' => 'Table 1', 'description' => '', 'allow_multiple_orders' => $state, 'is_default' => $state]);
        $table->id = 1;
        $compiled = $blade->compileString(file_get_contents(
            dirname(__DIR__, 2).'\resources\views\restaurant\table\\'.$modal.'.blade.php'
        ));
        ob_start();
        try {
            eval('?>'.$compiled);
            $rendered = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $dom = new DOMDocument();
        @$dom->loadHTML($rendered);
        $xpath = new DOMXPath($dom);
        $checkbox = $xpath->query('//input[@type="checkbox" and @name="allow_multiple_orders"]')->item(0);
        tableCheck($checkbox !== null && $checkbox->getAttribute('class') === 'input-icheck',
            $modal.' renders the styled checkbox');
        tableCheck($checkbox->hasAttribute('checked') === $state, $modal.' renders the saved checked state');
        tableCheck($xpath->query('//input[@type="hidden" and @name="allow_multiple_orders" and @value="0"]')->length === 1,
            $modal.' submits zero when unchecked');
        tableCheck($xpath->query('//label[@for="table_allow_multiple_orders"]')->length === 1
            && str_contains($rendered, 'Allow multiple orders'), $modal.' renders the associated readable label');
        $default = $xpath->query('//input[@type="checkbox" and @name="is_default"]')->item(0);
        tableCheck($default !== null && $default->hasAttribute('checked') === $state
            && $default->getAttribute('class') === 'input-icheck', $modal.' renders the default checkbox state');
        tableCheck($xpath->query('//input[@type="hidden" and @name="is_default" and @value="0"]')->length === 1,
            $modal.' clears the default when unchecked');
    }
}
Mockery::close();
echo "PASS: {$checks} table settings checks (SQLite memory and rendered modals only).\n";
