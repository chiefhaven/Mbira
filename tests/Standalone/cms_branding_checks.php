<?php

// Isolated database and temporary public directory; no kernel, live data, or network.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\CmsBranding;
use App\System;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use Modules\Superadmin\Http\Controllers\SuperadminSettingsController;

$root = dirname(__DIR__, 2);
$temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jazz-branding-'.bin2hex(random_bytes(8));
$files = new Illuminate\Filesystem\Filesystem();
$files->makeDirectory($temporary);
$app = new Application($root);
$app->instance('path.public', $temporary);
$config = new Repository([
    'app' => ['name' => 'Original CMS', 'title' => 'Original tagline', 'env' => 'testing'],
    'database' => ['default' => 'default', 'connections' => [
        'default' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    ]],
]);
$app->instance('config', $config);
$app->instance('files', $files);
$app->instance('log', new Psr\Log\NullLogger());
$events = new Illuminate\Events\Dispatcher($app);
$app->instance('events', $events);
$capsule = new Capsule($app);
$capsule->addConnection($config->get('database.connections.default'));
$capsule->setEventDispatcher($events);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
$app->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
Facade::setFacadeApplication($app);
Facade::clearResolvedInstances();
if (!class_exists('Log')) {
    class_alias(Illuminate\Support\Facades\Log::class, 'Log');
}
$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('system', function (Blueprint $table) {
    $table->increments('id');
    $table->string('key')->unique();
    $table->text('value')->nullable();
});
$schema->create('business', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name');
    $table->string('logo');
});
$capsule->table('business')->insert(['name' => 'Business name', 'logo' => 'receipt-logo.png']);
System::addProperty('unrelated_setting', 'preserved');

$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en');
$app->instance('translator', $translator);
$app->instance('validator', new Illuminate\Validation\Factory($translator, $app));
Request::macro('validate', function (array $rules) {
    return app('validator')->make($this->all(), $rules)->validate();
});
$session = new Illuminate\Session\Store('branding', new Illuminate\Session\ArraySessionHandler(120));
$request = Request::create('http://localhost/superadmin/settings/general');
$request->setLaravelSession($session);
$app->instance('request', $request);
$app->instance('session', $session);
$routes = new Illuminate\Routing\RouteCollection();
$routes->add((new Illuminate\Routing\Route('GET', 'superadmin/settings/general', []))
    ->name('superadmin.settings.general'));
$url = new Illuminate\Routing\UrlGenerator($routes, $request);
$app->instance('url', $url);
$redirect = new Illuminate\Routing\Redirector($url);
$redirect->setSession($session);
$app->instance('redirect', $redirect);
$user = Mockery::mock();
$allowed = true;
$user->shouldReceive('can')->with('superadmin')->andReturnUsing(function () use (&$allowed) {
    return $allowed;
});
$auth = Mockery::mock();
$auth->shouldReceive('user')->andReturn($user);
$app->instance(Illuminate\Contracts\Auth\Factory::class, $auth);
$controller = new SuperadminSettingsController(Mockery::mock(App\Utils\BusinessUtil::class));
$branding = new CmsBranding();
$checks = 0;

function brandingCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}

function brandingRequest(array $input, array $uploads = []): Request
{
    global $session;
    $request = Request::create('http://localhost/superadmin/settings/general', 'PUT', $input, [], $uploads);
    $request->setLaravelSession($session);
    return $request;
}

function brandingUpload(string $filename = 'logo.png', ?string $contents = null): UploadedFile
{
    global $temporary;
    $path = $temporary.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8));
    file_put_contents($path, $contents ?? base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
    ));
    return new UploadedFile($path, $filename, null, null, true);
}

try {
    $branding->apply();
    brandingCheck(config('app.name') === 'Original CMS' && config('app.title') === 'Original tagline',
        'Existing name and title remain unchanged before branding is saved');
    brandingCheck(config('branding.logo') === null && config('branding.favicon') === null,
        'No custom assets are assumed before setup');

    $response = $controller->updateGeneral(brandingRequest([
        'name' => 'New CMS', 'title' => 'A new tagline', 'footer' => 'Custom footer',
    ], [
        'logo' => brandingUpload(), 'compact_logo' => brandingUpload(), 'favicon' => brandingUpload('icon.png'),
    ]), $branding);
    brandingCheck($response->getStatusCode() === 302 && $session->get('status.success') === 1,
        'Authorized update redirects with success');
    $stored = $branding->settings();
    brandingCheck($stored['name'] === 'New CMS' && $stored['title'] === 'A new tagline'
        && $stored['footer'] === 'Custom footer', 'Text branding persists in the system table');
    foreach (CmsBranding::ASSETS as $asset) {
        brandingCheck($files->exists(public_path($stored[$asset])), $asset.' persists as a public image');
        brandingCheck(str_starts_with($stored[$asset], 'uploads/branding/'), $asset.' uses a generated managed filename');
    }
    $branding->apply();
    brandingCheck(config('app.name') === 'New CMS' && config('app.title') === 'A new tagline',
        'Runtime configuration uses database branding without environment changes');
    brandingCheck(config('branding.logo') === asset($stored['logo'])
        && config('branding.compact_logo') === asset($stored['compact_logo'])
        && config('branding.favicon') === asset($stored['favicon'])
        && config('branding.footer') === 'Custom footer', 'All shared branding values are applied');
    (new App\Http\Middleware\ApplyCmsBranding())->handle($request, function () {
        brandingCheck(config('app.name') === 'New CMS' && config('branding.footer') === 'Custom footer',
            'Web middleware applies branding before the downstream page');
    });

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $ico = pack('vvv', 0, 1, 1).pack('CCCCvvVV', 1, 1, 0, 0, 1, 32, strlen($png), 22).$png;
    $controller->updateGeneral(brandingRequest(['name' => 'New CMS'], [
        'favicon' => brandingUpload('favicon.ico', $ico),
    ]), $branding);
    $controller->updateGeneral(brandingRequest(['name' => 'New CMS'], [
        'logo' => brandingUpload('limit.png', $png.str_repeat("\0", 2048 * 1024 - strlen($png))),
    ]), $branding);
    $stored = $branding->settings();
    brandingCheck(filesize(public_path($stored['logo'])) === 2048 * 1024,
        'A valid image exactly at the 2 MB limit is accepted');
    brandingCheck(str_ends_with($stored['favicon'], '.ico') && $files->exists(public_path($stored['favicon'])),
        'Valid ICO favicons are accepted and keep a browser-compatible extension');

    $controller->updateGeneral(brandingRequest(['name' => 'New CMS']), $branding);
    $unchanged = $branding->settings();
    brandingCheck($unchanged['logo'] === $stored['logo'] && $unchanged['compact_logo'] === $stored['compact_logo']
        && $unchanged['favicon'] === $stored['favicon'], 'Text-only saves retain existing images');
    brandingCheck($unchanged['title'] === '' && $unchanged['footer'] === '', 'Optional text can be cleared');

    $controller->updateGeneral(brandingRequest(['name' => 'New CMS', 'remove_logo' => '1'], [
        'logo' => brandingUpload(),
    ]), $branding);
    $replacement = $branding->settings();
    brandingCheck($replacement['logo'] !== $stored['logo'] && $files->exists(public_path($replacement['logo'])),
        'A replacement upload wins over reset and uses a new cache-safe URL');
    brandingCheck(!$files->exists(public_path($stored['logo'])), 'Replaced managed files are removed');

    $controller->updateGeneral(brandingRequest([
        'name' => 'New CMS', 'remove_logo' => '1', 'remove_compact_logo' => '1', 'remove_favicon' => '1',
    ]), $branding);
    $reset = $branding->settings();
    brandingCheck(!array_intersect(CmsBranding::ASSETS, array_keys($reset)), 'Images can be reset independently of text');
    $branding->apply();
    brandingCheck(config('branding.logo') === null && config('branding.compact_logo') === null
        && config('branding.favicon') === null, 'Reset removes shared custom URLs');

    $before = System::getProperty('cms_branding');
    foreach ([
        ['name' => ''],
        ['name' => str_repeat('x', 192)],
        ['name' => 'Valid', 'title' => str_repeat('x', 192)],
        ['name' => 'Valid', 'footer' => str_repeat('x', 501)],
        ['name' => 'Valid', 'remove_logo' => 'not-a-boolean'],
    ] as $invalid) {
        try {
            $controller->updateGeneral(brandingRequest($invalid), $branding);
            throw new RuntimeException('Expected invalid branding text to be rejected');
        } catch (Illuminate\Validation\ValidationException $e) {
            brandingCheck(System::getProperty('cms_branding') === $before, 'Invalid input does not alter settings');
        }
    }
    foreach (['logo', 'compact_logo', 'favicon'] as $asset) {
        try {
            $controller->updateGeneral(brandingRequest(['name' => 'Valid'], [
                $asset => brandingUpload('script.php', '<?php echo "not an image";'),
            ]), $branding);
            throw new RuntimeException('Expected invalid asset to be rejected');
        } catch (Illuminate\Validation\ValidationException $e) {
            brandingCheck(isset($e->errors()[$asset]), $asset.' rejects executable uploads');
        }
    }
    try {
        $controller->updateGeneral(brandingRequest(['name' => 'Valid'], [
            'logo' => brandingUpload('image.php', $png),
        ]), $branding);
        throw new RuntimeException('Expected disguised PHP upload to be rejected');
    } catch (Illuminate\Validation\ValidationException $e) {
        brandingCheck(isset($e->errors()['logo']), 'A PHP filename is rejected even with valid image content');
    }
    try {
        $controller->updateGeneral(brandingRequest(['name' => 'Valid'], [
            'logo' => brandingUpload('large.png', $png.str_repeat("\0", 2048 * 1024 + 1 - strlen($png))),
        ]), $branding);
        throw new RuntimeException('Expected oversized upload to be rejected');
    } catch (Illuminate\Validation\ValidationException $e) {
        brandingCheck(isset($e->validator->failed()['logo']['Max']), 'Valid image uploads over 2 MB fail the size limit');
    }

    $allowed = false;
    foreach (['general', 'updateGeneral'] as $method) {
        try {
            $method === 'general' ? $controller->general($branding)
                : $controller->updateGeneral(brandingRequest(['name' => 'Forbidden']), $branding);
            throw new RuntimeException('Expected non-superadmin access to be rejected');
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
            brandingCheck($e->getStatusCode() === 403, $method.' requires superadmin permission');
        }
    }
    $allowed = true;
    config(['app.env' => 'demo']);
    $controller->updateGeneral(brandingRequest(['name' => 'Demo change']), $branding);
    brandingCheck(System::getProperty('cms_branding') === $before && $session->get('status.success') === 0,
        'Demo mode cannot change branding');
    config(['app.env' => 'testing']);

    System::saving(function ($model) {
        if ($model->key === 'cms_branding' && str_contains($model->value, 'force-failure')) {
            throw new RuntimeException('Forced database failure');
        }
    });
    $imagesBefore = count($files->files(public_path('uploads/branding')));
    $controller->updateGeneral(brandingRequest(['name' => 'force-failure'], ['logo' => brandingUpload()]), $branding);
    brandingCheck(System::getProperty('cms_branding') === $before && $session->get('status.success') === 0,
        'Failed saves explicitly report failure and roll back settings');
    brandingCheck(count($files->files(public_path('uploads/branding'))) === $imagesBefore,
        'Failed saves remove newly uploaded managed images');
    brandingCheck(System::getProperty('unrelated_setting') === 'preserved', 'Other system settings are unchanged');
    $business = $capsule->table('business')->first();
    brandingCheck($business->name === 'Business name' && $business->logo === 'receipt-logo.png',
        'Business names and receipt logos are unchanged');

    $loginLayout = $files->get($root.'/resources/views/layouts/auth2.blade.php');
    brandingCheck(str_contains($loginLayout, 'width: 128px;') && str_contains($loginLayout, 'width: 192px;')
        && str_contains($loginLayout, 'login-brand-page'), 'Login logo has larger responsive dimensions and reserved header space');
    brandingCheck(str_contains($loginLayout, 'min-height: 100dvh;')
        && str_contains($loginLayout, '.login-brand-page .login-shell')
        && str_contains($loginLayout, 'min-height: 0;')
        && !str_contains($loginLayout, 'overflow: hidden;'),
        'Login uses available viewport space rather than stacking a full-height form below the header');
    $sidebar = $files->get($root.'/resources/views/layouts/partials/sidebar.blade.php');
    brandingCheck(!str_contains($sidebar, "config('branding.compact_logo')")
        && str_contains($sidebar, "Session::get('business.name')"), 'Authenticated sidebar retains business name without compact logo');
    $blade = new Illuminate\View\Compilers\BladeCompiler($files, $temporary);
    foreach ([
        'Modules/Superadmin/Resources/views/superadmin_settings/general.blade.php',
        'Modules/Superadmin/Resources/views/superadmin_settings/partials/application_settings.blade.php',
        'Modules/Superadmin/Resources/views/layouts/nav.blade.php',
        'resources/views/layouts/app.blade.php',
        'resources/views/layouts/auth.blade.php',
        'resources/views/layouts/auth2.blade.php',
        'resources/views/layouts/home.blade.php',
        'resources/views/layouts/guest.blade.php',
        'resources/views/layouts/restaurant.blade.php',
        'resources/views/layouts/partials/header-auth.blade.php',
        'resources/views/layouts/partials/home_header.blade.php',
        'resources/views/layouts/partials/logo.blade.php',
        'resources/views/layouts/partials/sidebar.blade.php',
        'resources/views/layouts/partials/footer.blade.php',
        'resources/views/layouts/partials/footer_pos.blade.php',
        'resources/views/layouts/partials/footer-restaurant.blade.php',
        'resources/views/welcome.blade.php',
        'resources/views/layouts/partials/branding-icon.blade.php',
        'resources/views/layouts/partials/branding-footer.blade.php',
        'resources/views/layouts/partials/branding-public-footer.blade.php',
    ] as $view) {
        $compiled = $blade->compileString($files->get($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $view)));
        token_get_all($compiled, TOKEN_PARSE);
        brandingCheck(true, 'Branding Blade template compiles: '.$view);
    }
    Mockery::close();
    echo "PASS: {$checks} CMS branding checks (SQLite memory and temporary files only).\n";
} finally {
    $files->deleteDirectory($temporary);
}
