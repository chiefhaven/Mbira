<?php

// No kernel, live database, migration command, or external calls.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Http\Controllers\Install\EisMigrationController;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;

$root = dirname(__DIR__, 2);
class BrowserMigrationApplication extends Container
{
    public function abort($code, $message = '', $headers = []) { throw new Symfony\Component\HttpKernel\Exception\HttpException($code, $message); }
    public function basePath($path = '') { return dirname(__DIR__, 2).($path ? '/'.$path : ''); }
}
$container = new BrowserMigrationApplication(); Container::setInstance($container);
$container->instance('config', new Illuminate\Config\Repository(['app' => ['env' => 'testing']]));
$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en');
$container->instance('translator', $translator);
$validator = new Illuminate\Validation\Factory($translator, $container); $container->instance('validator', $validator);
Request::macro('validate', function ($rules) use ($validator) { return $validator->make($this->all(), $rules)->validate(); });
$container->instance('log', new Psr\Log\NullLogger());
Facade::setFacadeApplication($container); Facade::clearResolvedInstances();
$session = new Illuminate\Session\Store('fixture', new Illuminate\Session\ArraySessionHandler(120));
$request = Request::create('https://fixture.invalid/admin/eis-migrations'); $request->setLaravelSession($session);
$routes = new Illuminate\Routing\RouteCollection();
$routes->add((new Illuminate\Routing\Route(['GET'], 'admin/eis-migrations', []))->name('eis-migrations.index'));
$redirector = new Illuminate\Routing\Redirector(new Illuminate\Routing\UrlGenerator($routes, $request));
$redirector->setSession($session); $container->instance('redirect', $redirector);
$controller = new EisMigrationController(); $checks = 0;
function browserCheck($condition, $message) { global $checks; if (!$condition) throw new RuntimeException($message); ++$checks; }
function browserAuth(bool $allowed): void {
    global $container;
    $user = Mockery::mock(); $user->shouldReceive('can')->with('manage_modules')->andReturn($allowed);
    $auth = Mockery::mock(); $auth->shouldReceive('user')->andReturn($user);
    $container->instance(Illuminate\Contracts\Auth\Factory::class, $auth);
}
browserAuth(false);
foreach (['index', 'store'] as $method) {
    try { $method === 'index' ? $controller->index() : $controller->store($request); throw new RuntimeException('Missing access denial'); }
    catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { browserCheck($e->getStatusCode() === 403, 'Permission denied before migration'); }
}
browserAuth(true); $container->make('config')->set('app.env', 'demo');
try { $controller->store($request); throw new RuntimeException('Demo allowed'); }
catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { browserCheck($e->getStatusCode() === 403, 'Demo denied'); }
$container->make('config')->set('app.env', 'testing');
foreach ([[], ['confirm_backup' => '0']] as $input) {
    try { $controller->store(Request::create('/admin/eis-migrations', 'POST', $input)); throw new RuntimeException('No confirmation'); }
    catch (Illuminate\Validation\ValidationException $e) { browserCheck(isset($e->errors()['confirm_backup']), 'Backup confirmation enforced'); }
}
$request = Request::create('/admin/eis-migrations', 'POST', ['confirm_backup' => '1', 'command' => 'migrate:fresh', 'path' => '../../unrelated.php']);
function fakeBrowserLock(bool $available): object {
    $lock = Mockery::mock(); $lock->shouldReceive('get')->once()->andReturn($available);
    if ($available) { $lock->shouldReceive('release')->once(); }
    $store = Mockery::mock(); $store->shouldReceive('lock')->with('browser-eis-migrations', 600)->once()->andReturn($lock);
    $repository = Mockery::mock(); $repository->shouldReceive('getStore')->once()->andReturn($store);
    $cache = Mockery::mock(); $cache->shouldReceive('store')->with('file')->once()->andReturn($repository);
    Cache::swap($cache); return $lock;
}
$paths = array_map(fn ($name) => 'database/migrations/'.$name.'.php', EisMigrationController::MIGRATIONS);
foreach ([0, 1, 'exception'] as $outcome) {
    fakeBrowserLock(true);
    $artisan = Mockery::mock();
    $expectation = $artisan->shouldReceive('call')->with('migrate', ['--path' => $paths, '--force' => true])->once();
    $outcome === 'exception' ? $expectation->andThrow(new RuntimeException('private connection detail')) : $expectation->andReturn($outcome);
    Artisan::swap($artisan);
    $response = $controller->store($request);
    browserCheck($response->getTargetUrl() === 'https://fixture.invalid/admin/eis-migrations', 'Return to status page');
    browserCheck($session->get('status')['success'] === ($outcome === 0), 'Nonzero exit and exception reported as failure');
    browserCheck(!str_contains($session->get('status')['msg'], 'private connection detail'), 'Exception internals not exposed');
}
fakeBrowserLock(false); $artisan = Mockery::mock(); $artisan->shouldNotReceive('call'); Artisan::swap($artisan);
$controller->store($request);
browserCheck($session->get('status')['success'] === false, 'Concurrent run rejected without invoking command');
foreach ($paths as $path) { browserCheck(is_file($root.'/'.$path), 'Allowlisted migration exists'); }
$blade = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$view = file_get_contents($root.'/resources/views/install/eis_migrations.blade.php');
token_get_all($blade->compileString($view), TOKEN_PARSE);
browserCheck(str_contains($view, '@csrf') && str_contains($view, 'method="POST"'), 'CSRF-protected POST form');
$web = file_get_contents($root.'/routes/web.php');
browserCheck(strpos($web, "Route::get('/admin/eis-migrations'") > strpos($web, "'CheckUserLogin'"), 'Route inside authenticated group');
browserCheck(str_contains($web, "name('eis-migrations.store')->middleware('throttle:3,1')"), 'Migration POST rate limited');
Mockery::close();
echo "PASS: {$checks} browser EIS migration checks (mock commands only).\n";