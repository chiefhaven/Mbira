<?php

// No application kernel, database, network, or published assets required.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Modules\Cms\Http\Controllers\CmsAssetController;
use Modules\Cms\Utils\CmsUtil;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$root = dirname(__DIR__, 2);
$app = new Application($root);
$app->instance('path.public', sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-unpublished-'.bin2hex(random_bytes(8)));
$app->instance('request', Request::create('http://localhost/'));
$router = new Router(new Dispatcher($app), $app);
$app->instance('router', $router);
Facade::setFacadeApplication($app);
Facade::clearResolvedInstances();
$factory = Mockery::mock(ResponseFactory::class);
$factory->shouldReceive('file')->andReturnUsing(fn ($path, $headers) => new BinaryFileResponse($path, 200, $headers));
$app->instance(ResponseFactory::class, $factory);
$app->instance('url', new UrlGenerator($router->getRoutes(), $app['request']));
require $root.'/Modules/Cms/Routes/web.php';
$checks = 0;
$check = function (bool $valid, string $message) use (&$checks) {
    if (!$valid) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
$controller = new CmsAssetController();
foreach (CmsAssetController::ASSETS as $asset => $type) {
    $request = Request::create('http://localhost/modules/cms/'.$asset);
    $route = $router->getRoutes()->match($request);
    $check($route->getName() === 'cms.asset', 'Fallback route matches '.$asset);
    $check(!is_file(public_path('modules/cms/'.$asset)), 'No published file required.');
    $response = $controller->show($route->parameter('asset'));
    $check($response->getStatusCode() === 200, 'Asset is served.');
    $check($response->headers->get('Content-Type') === $type, 'Correct MIME type.');
    $check($response->headers->get('X-Content-Type-Options') === 'nosniff', 'No MIME sniffing.');
    $source = $root.'/Modules/Cms/Resources/assets/'.$asset;
    $check(hash_file('sha256', $response->getFile()->getPathname()) === hash_file('sha256', $source), 'Exact source bytes.');
    $check(CmsUtil::assetUrl($asset) === 'http://localhost/modules/cms/'.$asset.'?v='.substr(hash_file('sha256', $source), 0, 12), 'Content-versioned URL.');
}
foreach (['../module.json', 'css/../../module.json', '.gitkeep', 'css/unknown.css', 'img/private.php'] as $asset) {
    try {
        $controller->show($asset);
        throw new RuntimeException('Unexpected file access: '.$asset);
    } catch (HttpException $e) {
        $check($e->getStatusCode() === 404, 'Unlisted asset rejected.');
    }
    try {
        $router->getRoutes()->match(Request::create('http://localhost/modules/cms/'.$asset));
        throw new RuntimeException('Unexpected route match: '.$asset);
    } catch (NotFoundHttpException $e) {
        $check(true, 'Unlisted path does not match route.');
    }
}
Mockery::close();
echo "PASS: {$checks} CMS asset checks (published directory absent, no live data).".PHP_EOL;
