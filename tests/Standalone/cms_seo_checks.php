<?php

// Isolated SQLite and Blade compilation; no application kernel or live CMS writes.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Http\Controllers\CmsPageController;
use Modules\Cms\Http\Controllers\SeoController;
use Modules\Cms\Utils\CmsSeo;
use Symfony\Component\HttpKernel\Exception\HttpException;

$root = dirname(__DIR__, 2);
$app = new Application($root);
$app->instance('config', new Repository(['app' => ['name' => 'Mbira & ERP']]));
$request = Request::create('https://example.test/c/blog/old-slug-2?utm_source=test');
$app->instance('request', $request);
$events = new Dispatcher($app);
$router = new Router($events, $app);
$app->instance('router', $router);
$url = new UrlGenerator($router->getRoutes(), $request);
$app->instance('url', $url);
Facade::setFacadeApplication($app);
Facade::clearResolvedInstances();
require $root.'/Modules/Cms/Routes/web.php';
$url->setRoutes($router->getRoutes());
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->schema()->create('cms_pages', function (Blueprint $table) {
    $table->increments('id');
    $table->string('title');
    $table->string('type');
    $table->string('layout')->nullable();
    $table->boolean('is_enabled')->default(true);
    $table->text('content')->nullable();
    $table->text('meta_description')->nullable();
    $table->string('feature_image')->nullable();
    $table->timestamps();
});
$files = new Filesystem();
$temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-seo-'.bin2hex(random_bytes(8));
$files->makeDirectory($temporary);
$resolver = new EngineResolver();
$resolver->register('blade', fn () => new CompilerEngine(new BladeCompiler($files, $temporary), $files));
$finder = new FileViewFinder($files, []);
$finder->addNamespace('cms', $root.'/Modules/Cms/Resources/views');
$views = new Factory($resolver, $finder, $events);
$views->setContainer($app);
$views->share('__env', $views);
$app->instance('view', $views);
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class, new ResponseFactory($views, new Redirector($url)));
$checks = 0;
$check = function (bool $valid, string $message) use (&$checks) {
    if (!$valid) {
        throw new RuntimeException($message);
    }
    ++$checks;
};

try {
    $page = CmsPage::create(['title' => 'About Us', 'type' => 'page', 'content' => '<p>Manage &amp; grow</p>']);
    $blog = CmsPage::create([
        'title' => 'Latest News', 'type' => 'blog',
        'meta_description' => '<b>Business</b> &amp; inventory updates',
        'feature_image' => 'news photo.png',
    ]);
    $hidden = CmsPage::create(['title' => 'Hidden', 'type' => 'page', 'is_enabled' => false]);
    $testimonial = CmsPage::create(['title' => 'Testimonial', 'type' => 'testimonial']);
    $home = CmsPage::create(['title' => 'Home', 'type' => 'page', 'layout' => 'home']);
    $contact = CmsPage::create(['title' => 'Contact', 'type' => 'page', 'layout' => 'contact']);
    CmsPage::create(['title' => 'Internal Layout', 'type' => 'page', 'layout' => 'internal']);
    CmsPage::create(['title' => 'Disabled Blog', 'type' => 'blog', 'is_enabled' => false]);

    $seo = CmsSeo::metadata($blog, 'News <script>alert("x")</script>');
    $check($seo['url'] === 'https://example.test/c/blog/latest-news-'.$blog->id, 'Current blog slug is canonical, without query.');
    $check($seo['description'] === 'Business & inventory updates', 'Explicit description wins and is plain text.');
    $check($seo['type'] === 'article', 'Blog gets article social type.');
    $check($seo['image'] === 'https://example.test/uploads/cms/news%20photo.png', 'Featured image is absolute and encoded.');
    $check(CmsSeo::metadata($page, 'About')['description'] === 'Manage & grow', 'Content excerpt fallback.');
    $page->meta_description = str_repeat('Long description ', 30);
    $check(mb_strlen(CmsSeo::metadata($page, 'About')['description']) <= 160, 'Description is limited to 160 characters.');
    $app['config']->set('branding.logo', 'https://example.test/uploads/branding/logo.png');
    $check(CmsSeo::metadata(null, 'Blogs')['image'] === 'https://example.test/uploads/branding/logo.png', 'Branding logo fallback.');
    $app['config']->set('branding.logo', null);
    $check(CmsSeo::metadata(null, 'Blogs')['image'] === 'https://example.test/img/logo-small.png', 'Default logo fallback.');
    $check(CmsSeo::metadata(null, 'Blogs')['url'] === 'https://example.test/c/blog/old-slug-2', 'Non-record canonical excludes tracking query.');
    $check(CmsSeo::pageUrl($home) === 'https://example.test', 'Home canonical.');
    $check(CmsSeo::pageUrl($contact) === 'https://example.test/c/contact-us', 'Contact canonical.');

    $views->startSection('title', 'News " & <unsafe>');
    $html = $views->make('cms::frontend.seo.metadata', ['blog' => $blog])->render();
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    foreach (['description', 'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'] as $name) {
        $check($xpath->query('//meta[@name="'.$name.'"]')->length === 1, 'One '.$name.' tag.');
    }
    foreach (['og:title', 'og:description', 'og:url', 'og:image', 'og:type'] as $property) {
        $check($xpath->query('//meta[@property="'.$property.'"]')->length === 1, 'One '.$property.' tag.');
    }
    $check($xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href') === CmsSeo::pageUrl($blog), 'Rendered canonical.');
    $check(!str_contains($html, '<unsafe>'), 'Metadata escapes markup and quotes.');

    $controller = new SeoController();
    $response = $controller->sitemap();
    $check($response->headers->get('Content-Type') === 'application/xml; charset=UTF-8', 'XML content type.');
    $xml = simplexml_load_string($response->getContent());
    $check($xml !== false, 'Valid sitemap XML.');
    $locations = [];
    foreach ($xml->url as $entry) {
        $locations[] = (string) $entry->loc;
    }
    $expected = ['https://example.test', 'https://example.test/c/contact-us', 'https://example.test/c/blogs', CmsSeo::pageUrl($page), CmsSeo::pageUrl($blog)];
    sort($locations);
    sort($expected);
    $check($locations === $expected, 'Exactly public URLs, deduplicated and without disabled/internal content.');
    $check(count($xml->xpath('//*[local-name()="lastmod"]')) === 4, 'Published record update timestamps.');

    $robots = $controller->robots();
    $check($robots->headers->get('Content-Type') === 'text/plain; charset=UTF-8', 'Robots text content type.');
    $check(str_contains($robots->getContent(), 'Sitemap: https://example.test/sitemap.xml'), 'Absolute sitemap discovery.');
    $check(str_contains($robots->getContent(), 'Disallow: /cms'), 'Admin crawl rule.');
    $check(!str_contains($robots->getContent(), "Disallow: /\n"), 'Public crawling is not globally blocked.');
    $check(!is_file($root.'/public/robots.txt'), 'No static robots file shadows Laravel.');
    foreach (['sitemap.xml' => 'cms.sitemap', 'robots.txt' => 'cms.robots'] as $path => $name) {
        $check($router->getRoutes()->match(Request::create('https://example.test/'.$path))->getName() === $name, 'Public route '.$name);
    }

    $pageController = new CmsPageController(Mockery::mock(App\Utils\Util::class));
    foreach (['hidden', 'testimonial', 'home'] as $slug) {
        try {
            $pageController->showPage($slug);
            throw new RuntimeException('Unexpected non-public custom page: '.$slug);
        } catch (HttpException $e) {
            $check($e->getStatusCode() === 404, 'Non-public custom page excluded: '.$slug);
        }
    }
    $check($pageController->showPage('About-Us')->getData()['page']->id === $page->id, 'Enabled custom page still renders.');

    $subdirectory = Request::create('https://example.test/mbira/c/blogs?tracking=1', 'GET', [], [], [], [
        'SCRIPT_NAME' => '/mbira/index.php',
        'SCRIPT_FILENAME' => 'C:\\www\\mbira\\index.php',
        'PHP_SELF' => '/mbira/index.php/c/blogs',
    ]);
    $url->setRequest($subdirectory);
    $check(CmsSeo::pageUrl($page) === 'https://example.test/mbira/c/page/about-us', 'Canonical retains deployment base.');
    $check(str_contains($controller->robots()->getContent(), 'Sitemap: https://example.test/mbira/sitemap.xml'), 'Subdirectory sitemap discovery.');
    $check(str_contains($controller->robots()->getContent(), 'Disallow: /mbira/cms'), 'Subdirectory crawler rules.');
    echo "PASS: {$checks} CMS SEO checks (isolated SQLite, no live data).".PHP_EOL;
} finally {
    Mockery::close();
    $files->deleteDirectory($temporary);
}
