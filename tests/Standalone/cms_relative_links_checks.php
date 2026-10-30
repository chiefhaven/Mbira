<?php

// Isolated SQLite and requests; no application kernel or live CMS writes.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Cms\Utils\CmsUtil;

$app = new Application(dirname(__DIR__, 2));
$app->instance('request', Request::create('http://127.0.0.1:8000/c/contact-us'));
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->getConnection()->getSchemaBuilder()->create('cms_site_details', function (Blueprint $table) {
    $table->increments('id');
    $table->string('site_key');
    $table->text('site_value');
    $table->timestamps();
});
$checks = 0;
$check = function (bool $valid, string $message) use (&$checks) {
    if (!$valid) {
        throw new RuntimeException($message);
    }
    ++$checks;
};
foreach ([
    'https://old.example/public/business/register' => '/business/register',
    'https://old.example/public/business/register?package=5#details' => '/business/register?package=5#details',
    '//old.example/public/pricing' => '/pricing',
    'http://127.0.0.1:8000/login' => '/login',
    '/business/register' => '/business/register',
    'business/register?package=5' => '/business/register?package=5',
    '/public/business/register' => '/business/register',
    '#' => '#',
    '#features' => '#features',
    '?package=5' => '?package=5',
    'mailto:team@example.test' => 'mailto:team@example.test',
    'tel:+265991234567' => 'tel:+265991234567',
] as $input => $expected) {
    $check(CmsUtil::relativeButtonLink($input) === $expected, 'Relative link: '.$input);
}
$buttons = ['navbar' => ['text' => 'Try For Free', 'link' => 'https://old.example/public/business/register?package=5']];
CmsSiteDetail::create(['site_key' => 'btns', 'site_value' => json_encode($buttons)]);
$check(CmsSiteDetail::getSiteDetails()['btns']['navbar']['link'] === '/business/register?package=5', 'Existing absolute settings render relative.');
$check(CmsSiteDetail::getValue('btns')['navbar']['link'] === '/business/register?package=5', 'Direct settings reads are relative.');
$check(CmsSiteDetail::getSiteDetails()['btns']['navbar']['text'] === 'Try For Free', 'Button text preserved.');
CmsSiteDetail::createOrUpdateSiteDetails([
    'btns' => $buttons,
    'follow_us' => ['facebook' => 'https://www.facebook.com/example'],
]);
$stored = json_decode(CmsSiteDetail::where('site_key', 'btns')->value('site_value'), true);
$check($stored['navbar']['link'] === '/business/register?package=5', 'New saves persist relative links.');
$check(CmsSiteDetail::getSiteDetails()['follow_us']['facebook'] === 'https://www.facebook.com/example', 'External social links preserved.');

$request = Request::create('https://example.test/Mbira/public/c/contact-us', 'GET', [], [], [], [
    'SCRIPT_NAME' => '/Mbira/public/index.php',
    'SCRIPT_FILENAME' => 'C:\\www\\Mbira\\public\\index.php',
    'PHP_SELF' => '/Mbira/public/index.php/c/contact-us',
]);
$app->instance('request', $request);
$check($request->getBaseUrl() === '/Mbira/public', 'Subdirectory request fixture.');
$check(CmsUtil::relativeButtonLink('https://old.example/public/business/register?package=5') === '/Mbira/public/business/register?package=5', 'Legacy buttons use current deployment base.');
$check(CmsUtil::relativeButtonLink('/Mbira/public/business/register') === '/Mbira/public/business/register', 'Existing base is not duplicated.');
$check(CmsUtil::relativeButtonLink('/business/register') === '/Mbira/public/business/register', 'Root paths use current deployment base.');
echo "PASS: {$checks} CMS relative link checks (SQLite memory only).".PHP_EOL;
