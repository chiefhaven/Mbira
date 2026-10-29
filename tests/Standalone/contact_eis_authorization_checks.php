<?php

// Isolated SQLite fixture: never boots Laravel, reads .env, or calls EIS.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Contact;
use App\Http\Controllers\ContactController;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;

$capsule = new Capsule(new Container());
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$container = $capsule->getContainer();
Container::setInstance($container);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
$container->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
$container->instance('events', new Illuminate\Events\Dispatcher($container));
$container->instance(Illuminate\Contracts\Events\Dispatcher::class, $container['events']);
$container->instance('translator', new Translator(new ArrayLoader(), 'en'));
$container->instance(Illuminate\Contracts\Translation\Translator::class, $container['translator']);
Facade::setFacadeApplication($container);
Facade::clearResolvedInstances();
if (! class_exists('DB')) {
    class_alias(Illuminate\Support\Facades\DB::class, 'DB');
}

$validator = new Factory($container['translator'], $container);
Request::macro('validate', function ($rules) use ($validator) {
    return $validator->make($this->all(), $rules)->validate();
});
$user = Mockery::mock();
$user->shouldReceive('can')->andReturn(true);
$auth = Mockery::mock();
$auth->shouldReceive('user')->andReturn($user);
$container->instance('auth', $auth);
$container->instance(Illuminate\Contracts\Auth\Factory::class, $auth);

$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('contacts', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->integer('created_by');
    $table->string('contact_id');
    $table->string('type');
    $table->string('name');
    $table->string('first_name');
    $table->string('tax_number')->nullable();
    $table->string('contact_type')->nullable();
    $table->decimal('credit_limit')->nullable();
    $table->boolean('is_export')->default(false);
    $table->timestamps();
    $table->softDeletes();
});
$schema->create('transactions', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('contact_id');
    $table->string('type');
});

$migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_14_000001_add_authorization_code_to_contacts_table.php';
$migration->up();
$migration->up(); // Compatible with installations where the column already exists.

$common = Mockery::mock(Util::class);
$common->shouldReceive('num_uf')->andReturnUsing(fn ($value) => (float) $value);
$module = Mockery::mock(ModuleUtil::class);
$module->shouldReceive('isSubscribed')->andReturn(true);
$module->shouldReceive('getModuleData')->andReturn([]);
$contacts = Mockery::mock(ContactUtil::class)->makePartial();
$contacts->shouldReceive('setAndGetReferenceCount')->andReturn(1);
$contacts->shouldReceive('activityLog')->andReturnNull();
$controller = new ContactController($common, $module, Mockery::mock(TransactionUtil::class), Mockery::mock(NotificationUtil::class), $contacts);
$session = new Illuminate\Session\Store('fixture', new Illuminate\Session\ArraySessionHandler(120));
$session->put('user', ['business_id' => 7, 'id' => 1]);

function contactRequest(array $data, string $method = 'POST'): Request
{
    global $container, $session;
    $request = Request::create('/contacts', $method, $data);
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $request->setLaravelSession($session);
    $container->instance('request', $request);
    return $request;
}

$checks = 0;
function checkContact($condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}

foreach (['customer', 'supplier', 'both'] as $type) {
    $base = ['type' => $type, 'first_name' => 'Fixture', 'contact_id' => 'FIX-'.$type];
    $result = $controller->store(contactRequest($base + ['authorization_code' => '001-EIS-'.$type]));
    checkContact($result['success'], 'Create '.$type);
    $id = $result['data']->id;
    checkContact(Contact::findOrFail($id)->authorization_code === '001-EIS-'.$type, 'Persist code for '.$type);

    $result = $controller->update(contactRequest($base + ['authorization_code' => 'UPDATED-001'], 'PUT'), $id);
    checkContact($result['success'], 'Update '.$type);
    checkContact(Contact::findOrFail($id)->authorization_code === 'UPDATED-001', 'Persist updated code');
    $controller->update(contactRequest($base, 'PUT'), $id);
    checkContact(Contact::findOrFail($id)->authorization_code === 'UPDATED-001', 'Omitted field preserves code');
    $controller->update(contactRequest($base + ['authorization_code' => null], 'PUT'), $id);
    checkContact(Contact::findOrFail($id)->authorization_code === null, 'Clear code');

    foreach (['POST', 'PUT'] as $method) {
        foreach ([str_repeat('A', 256), ['invalid']] as $invalid) {
            try {
                $request = contactRequest($base + ['authorization_code' => $invalid], $method);
                $method === 'POST' ? $controller->store($request) : $controller->update($request, $id);
                throw new RuntimeException('Invalid code was accepted');
            } catch (ValidationException $e) {
                checkContact(isset($e->errors()['authorization_code']), 'Reject invalid code before saving');
            }
        }
    }
}

$result = $controller->store(contactRequest(['type' => 'customer', 'first_name' => 'Optional', 'contact_id' => 'OPTIONAL']));
checkContact($result['success'] && $result['data']->fresh()->authorization_code === null, 'Code is optional');
$blade = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
foreach (['create', 'edit'] as $view) {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/contact/'.$view.'.blade.php');
    token_get_all($blade->compileString($source), TOKEN_PARSE);
    checkContact(str_contains($source, "Form::text('authorization_code'"), 'Authorization input and valid Blade syntax: '.$view);
}
$container->instance('session', $session);
function renderContactBlade(string $source, array $data): string
{
    global $blade;
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        eval('?>'.$blade->compileString($source));
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

$detailSource = file_get_contents(dirname(__DIR__, 2).'/resources/views/contact/contact_more_info.blade.php');
foreach (['customer', 'supplier', 'both'] as $type) {
    $contact = new Contact(['type' => $type, 'authorization_code' => '001-<EIS>&']);
    $html = renderContactBlade($detailSource, compact('contact'));
    checkContact(str_contains($html, '001-&lt;EIS&gt;&amp;'), 'Detail displays escaped code: '.$type);
    $contact->authorization_code = null;
    checkContact(str_contains(renderContactBlade($detailSource, compact('contact')), 'contact.eis_authorization_code'), 'Detail label remains for empty code');
}

$indexSource = file_get_contents(dirname(__DIR__, 2).'/resources/views/contact/index.blade.php');
preg_match('/<table[^>]*id="contact_table"[\s\S]*?<\/table>/', $indexSource, $tableMatch);
foreach ([['supplier', false, 25], ['customer', false, 27], ['customer', true, 28]] as [$type, $reward_enabled, $expectedColumns]) {
    $html = renderContactBlade($tableMatch[0], compact('type', 'reward_enabled'));
    $document = new DOMDocument();
    $document->loadHTML($html);
    $headers = $document->getElementsByTagName('th');
    checkContact($headers->length === $expectedColumns, 'List header count: '.$type);
    checkContact($headers->item(6)->textContent === 'contact.eis_authorization_code', 'Authorization follows tax number');
    $footerColumns = 0;
    $dueColumn = null;
    foreach ($document->getElementsByTagName('td') as $cell) {
        if ($cell->getAttribute('class') === 'footer_contact_due') {
            $dueColumn = $footerColumns;
        }
        $footerColumns += (int) ($cell->getAttribute('colspan') ?: 1);
    }
    checkContact($footerColumns === $expectedColumns, 'Footer matches header column count');
    checkContact($dueColumn === $expectedColumns - 12, 'Due total column remains aligned');
}
$javascript = file_get_contents(dirname(__DIR__, 2).'/public/js/app.js');
checkContact(substr_count($javascript, "data: 'authorization_code', name: 'contacts.authorization_code'") === 2, 'Both DataTables include searchable authorization column');
$migration->down();
checkContact(! $schema->hasColumn('contacts', 'authorization_code'), 'Migration rollback');
Mockery::close();
echo "PASS: {$checks} contact EIS authorization checks (SQLite memory only).\n";