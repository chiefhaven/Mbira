<?php

// No Laravel kernel, .env, real database, credentials, or external requests.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Contact;
use App\Transaction;
use App\Http\Controllers\SellPosController;
use App\Utils\TransactionUtil;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Utilities\EisValidationClient;
use Modules\EIS\Services\Sales\PosBuyerValidationService;
use Modules\EIS\Services\Sales\SaleTransformer;

$root = dirname(__DIR__, 2);
$container = new Container();
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setEventDispatcher(new Illuminate\Events\Dispatcher($container));
$capsule->setAsGlobal();
$capsule->bootEloquent();
Container::setInstance($container);
$container->instance('db', $capsule->getDatabaseManager());
$schema = $capsule->getConnection()->getSchemaBuilder();
$container->instance('db.schema', $schema);
$container['config']['eis.base_url'] = 'https://eis.invalid';
$container['config']['eis.timeout'] = 1;
$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en');
$container->instance('translator', $translator);
$factory = new Illuminate\Validation\Factory($translator, $container);
$container->instance('validator', $factory);
Facade::setFacadeApplication($container);
Facade::clearResolvedInstances();
if (! class_exists('DB')) { class_alias(DB::class, 'DB'); }
if (! class_exists('Log')) { class_alias(Illuminate\Support\Facades\Log::class, 'Log'); }
$container->instance('log', new Psr\Log\NullLogger());

$schema->create('contacts', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->string('name')->nullable();
    $t->string('tax_number')->nullable(); $t->string('authorization_code')->nullable(); $t->softDeletes();
});
$schema->create('eis_settings', function (Blueprint $t) {
    $t->increments('id'); $t->integer('business_id'); $t->boolean('status'); $t->string('jwt_token');
    $t->integer('location_id')->default(2);
});
foreach (['business_locations', 'eis_configurations', 'eis_terminal_configurations'] as $name) {
    $schema->create($name, function (Blueprint $t) use ($name) {
        $t->increments('id');
        if ($name === 'eis_terminal_configurations') {
            $t->integer('configuration_id'); $t->boolean('is_active');
        } else {
            $t->integer('business_id');
            $t->integer($name === 'business_locations' ? 'default_eis_setting_id' : 'eis_setting_id');
        }
    });
}
DB::table('business_locations')->insert(['id' => 2, 'business_id' => 7, 'default_eis_setting_id' => 1]);
DB::table('eis_configurations')->insert(['id' => 1, 'business_id' => 7, 'eis_setting_id' => 1]);
DB::table('eis_terminal_configurations')->insert(['configuration_id' => 1, 'is_active' => 1]);
$source = file($root.'/app/Utils/TransactionUtil.php');
$method = new ReflectionMethod(TransactionUtil::class, 'createSellTransaction');
preg_match_all('/^\s*\x27([a-z_0-9]+)\x27\s*=>/m', implode('', array_slice($source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1)), $matches);
$schema->create('transactions', function (Blueprint $t) use ($matches) {
    $t->increments('id');
    foreach (array_unique(array_diff($matches[1], ['vat5_certificate_details'])) as $column) { $t->text($column)->nullable(); }
    $t->timestamps();
});
$migration = require $root.'/database/migrations/2026_10_14_000003_add_vat5_certificate_details_to_transactions_table.php';
$migration->up(); $migration->up();
DB::table('contacts')->insert([
    ['id' => 1, 'business_id' => 7, 'tax_number' => ' TIN1 ', 'authorization_code' => ' CODE1 '],
    ['id' => 2, 'business_id' => 7, 'tax_number' => 'TIN2', 'authorization_code' => null],
    ['id' => 3, 'business_id' => 7, 'tax_number' => null, 'authorization_code' => null],
    ['id' => 4, 'business_id' => 8, 'tax_number' => 'OTHER', 'authorization_code' => 'OTHER'],
]);
DB::table('eis_settings')->insert(['business_id' => 7, 'status' => true, 'jwt_token' => 'fixture-token']);
$checks = 0;
function checkEis($condition, string $message): void {
    global $checks;
    if (! $condition) { throw new RuntimeException($message); }
    ++$checks;
}
function rejectsEis(callable $action, string $field): void {
    try { $action(); } catch (ValidationException $e) {
        checkEis(isset($e->errors()[$field]), 'Expected validation field '.$field); return;
    }
    throw new RuntimeException('Expected rejection: '.$field);
}
function fakeEis(?callable $override = null): void {
    global $container;
    $http = new HttpFactory();
    $http->preventStrayRequests();
    $container->instance(HttpFactory::class, $http);
    Http::clearResolvedInstance(HttpFactory::class);
    Http::fake(function ($request) use ($override) {
        if ($override) { return $override($request); }
        $payload = $request->data();
        $data = match (basename($request->url())) {
            'check-tin-authorization-requirement' => ['tin' => $payload['tin'], 'tinExists' => true, 'requiresAuthorizationCode' => $payload['tin'] === 'TIN1'],
            'validate-authorization-code' => ['authorizationCode' => $payload['authorizationCode'], 'isValidAuthorizationCode' => true],
            'validate-vat5-certificate' => ['projectNumber' => $payload['projectNumber'], 'vat5CertificateNumber' => $payload['certificateNumber'], 'quantity' => $payload['quantity'], 'isValid' => true],
            default => throw new RuntimeException('Unexpected URL'),
        };
        return Http::response(['statusCode' => 1, 'errors' => [], 'data' => $data]);
    });
}
$client = new EisValidationClient();
$health = Mockery::mock(Modules\EIS\Services\Http\EisHealthService::class);
$health->shouldReceive('isOnline')->andReturn(true);
// Existing buyer-contract scenarios all represent the currently selected POS location.
$service = new class($client, $health) extends PosBuyerValidationService {
    public function validate(int $businessId, array $input, ?Transaction $existing = null): ?array {
        return parent::validate($businessId, $input + ['location_id' => $existing?->location_id ?? 2], $existing);
    }
};
$direct = new PosBuyerValidationService($client, $health);
rejectsEis(fn () => $direct->validate(7, ['contact_id' => 1]), 'location_id');
$container->instance(PosBuyerValidationService::class, $service);
$certificate = ['projectNumber' => 'PROJECT1', 'certificateNumber' => 'CERT1', 'quantity' => 2.5];
$relief = ['contact_id' => 1, 'is_relief' => '1', 'vat5_project_number' => 'PROJECT1', 'vat5_certificate_number' => 'CERT1', 'vat5_quantity' => '2.5'];
$settings = EisSetting::where('business_id', 7)->first();
foreach (['https://eis.invalid', 'https://eis.invalid/', 'https://eis.invalid/api/v1/'] as $base) {
    $container['config']['eis.base_url'] = $base;
    fakeEis();
    checkEis($service->validate(7, $relief) === $certificate, 'Validated certificate for either base URL format');
    $requests = Http::recorded();
    checkEis(count($requests) === 3, 'TIN, authorization, VAT5 sequence');
    foreach ($requests as [$request]) {
        checkEis($request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer fixture-token'), 'Authenticated POST');
        checkEis(str_starts_with($request->url(), 'https://eis.invalid/api/v1/utilities/'), 'Version path exactly once');
    }
    checkEis($requests[0][0]->data() === ['tin' => 'TIN1'], 'Exact TIN contract');
    checkEis($requests[1][0]->data() === ['authorizationCode' => 'CODE1'], 'Authorization endpoint receives only code');
    checkEis($requests[2][0]->data() === $certificate, 'Exact VAT5 contract');
}
fakeEis();
checkEis($service->validate(7, ['contact_id' => 2]) === null, 'TIN without code allowed when not required');
checkEis(count(Http::recorded()) === 1, 'No unnecessary authorization or VAT5 calls');
fakeEis();
checkEis($service->validate(7, ['contact_id' => 3]) === null, 'Ordinary buyer without TIN');
checkEis(count(Http::recorded()) === 0, 'No remote calls without buyer TIN or relief');
DB::table('contacts')->where('id', 3)->update(['authorization_code' => 'INVALID']);
fakeEis(fn () => Http::response(['statusCode' => 1, 'data' => ['authorizationCode' => 'INVALID', 'isValidAuthorizationCode' => false]]));
rejectsEis(fn () => $service->validate(7, ['contact_id' => 3]), 'buyerAuthorizationCode');
checkEis(count(Http::recorded()) === 1, 'A supplied code is validated even without TIN');
DB::table('contacts')->where('id', 3)->update(['authorization_code' => null]);
checkEis($service->validate(8, ['contact_id' => 4]) === null, 'Non-EIS business unchanged');
rejectsEis(fn () => $service->validate(8, ['contact_id' => 4, 'is_relief' => 1]), 'is_relief');
rejectsEis(fn () => $service->validate(7, ['contact_id' => 4]), 'contact_id');
rejectsEis(fn () => $service->validate(7, ['contact_id' => 3, 'is_relief' => 1]), 'buyerTIN');
foreach (['vat5_project_number' => 'projectNumber', 'vat5_certificate_number' => 'certificateNumber', 'vat5_quantity' => 'quantity'] as $inputField => $field) {
    $invalid = $relief; $invalid[$inputField] = '';
    rejectsEis(fn () => $service->validate(7, $invalid), $field);
    $invalid[$inputField] = ['bad'];
    rejectsEis(fn () => $service->validate(7, $invalid), $field);
}
foreach ([0, -1, 'nan'] as $quantity) {
    rejectsEis(fn () => $service->validate(7, array_replace($relief, ['vat5_quantity' => $quantity])), 'quantity');
}
fakeEis(fn () => Http::response(['statusCode' => 1, 'data' => ['tin' => 'TIN2', 'tinExists' => true, 'requiresAuthorizationCode' => true]]));
rejectsEis(fn () => $service->validate(7, ['contact_id' => 2]), 'buyerAuthorizationCode');
foreach ([false, 'true', null] as $valid) {
    fakeEis(fn () => Http::response(['statusCode' => 1, 'data' => ['tin' => 'TIN2', 'tinExists' => $valid, 'requiresAuthorizationCode' => false]]));
    rejectsEis(fn () => $service->validate(7, ['contact_id' => 2]), 'buyerTIN');
    fakeEis(fn () => Http::response(['statusCode' => 1, 'data' => ['authorizationCode' => 'CODE1', 'isValidAuthorizationCode' => $valid]]));
    rejectsEis(fn () => $client->validateAuthorizationCode($settings, 'CODE1'), 'buyerAuthorizationCode');
    fakeEis(fn () => Http::response(['statusCode' => 1, 'data' => ['projectNumber' => 'PROJECT1', 'vat5CertificateNumber' => 'CERT1', 'quantity' => 2.5, 'isValid' => $valid]]));
    rejectsEis(fn () => $client->validateVat5Certificate($settings, $certificate), 'vat5_certificate_number');
}
foreach ([400, 401, 403, 500, 503] as $status) {
    fakeEis(fn () => Http::response(['statusCode' => 1, 'data' => []], $status));
    rejectsEis(fn () => $service->validate(7, ['contact_id' => 2]), 'buyerTIN');
}
foreach ([[], ['statusCode' => 0, 'data' => []], ['statusCode' => '1', 'data' => []], ['statusCode' => 1, 'data' => null], ['statusCode' => 1, 'data' => [], 'errors' => [['errorMessage' => 'Invalid']]]] as $body) {
    fakeEis(fn () => Http::response($body));
    rejectsEis(fn () => $service->validate(7, ['contact_id' => 2]), 'buyerTIN');
}
fakeEis(fn () => Http::response('not-json', 200));
rejectsEis(fn () => $service->validate(7, ['contact_id' => 2]), 'buyerTIN');
fakeEis(function () { throw new Illuminate\Http\Client\ConnectionException('fixture offline'); });
rejectsEis(fn () => $service->validate(7, ['contact_id' => 2]), 'buyerTIN');
fakeEis();
rejectsEis(fn () => $client->validateAuthorizationCode(new EisSetting(['business_id' => 7]), 'CODE1'), 'buyerAuthorizationCode');
checkEis(count(Http::recorded()) === 0, 'Missing credentials fail before HTTP');

// Persistence, edit omission/payment-only metadata, uncheck, and invoice header.
/** @var TransactionUtil&\Mockery\MockInterface $util */
$util = Mockery::mock(TransactionUtil::class)->makePartial();
$util->shouldReceive('calculateRewardPoints')->andReturn(0);
$input = ['type' => 'sell', 'status' => 'draft', 'location_id' => 2, 'contact_id' => 1, 'invoice_no' => 'FIXTURE', 'final_total' => 100,
    'transaction_date' => '2026-10-14 10:00:00', 'customer_group_id' => null, 'tax_rate_id' => null, 'discount_type' => 'fixed',
    'discount_amount' => 0, 'commission_agent' => null, 'pay_term_number' => 1, 'pay_term_type' => 'days'];
$totals = ['total_before_tax' => 100, 'tax' => 0];
fakeEis();
$transaction = $util->createSellTransaction(7, $input + ['is_relief' => 1, 'vat5_certificate_details' => $service->validate(7, $relief)], $totals, 1, false)->fresh();
checkEis($transaction->vat5_certificate_details === $certificate, 'JSON certificate persisted/cast');
$service->assertSaleUnchanged(clone $transaction, clone $transaction);
checkEis(true, 'Unchanged locked snapshot accepted');
foreach (['is_relief' => false, 'contact_id' => 2, 'vat5_certificate_details' => null] as $field => $value) {
    $locked = clone $transaction; $locked->$field = $value;
    rejectsEis(fn () => $service->assertSaleUnchanged($transaction, $locked), 'is_relief');
}
checkEis($service->validate(7, ['contact_id' => 1], $transaction) === $certificate, 'Omitted edit fields revalidate saved relief details');
rejectsEis(fn () => $service->validate(8, [], $transaction), 'contact_id');
rejectsEis(fn () => $service->validate(7, ['contact_id' => 2], $transaction), 'projectNumber');
$transaction = $util->updateSellTransaction($transaction, 7, $input, $totals, 1, false)->fresh();
checkEis($transaction->vat5_certificate_details === $certificate, 'Unrelated update preserves certificate');
$transformer = (new ReflectionClass(SaleTransformer::class))->newInstanceWithoutConstructor();
$header = new ReflectionMethod(SaleTransformer::class, 'buildInvoiceHeader');
$header->setAccessible(true);
$result = $header->invoke($transformer, $transaction, $settings, 'FIXTURE', '00', []);
checkEis($result['vat5CertificateDetails'] === $certificate && ! isset($result['vat5CertificateDetails']['id']), 'EIS uses persisted details without invented ID');
$transaction = $util->updateSellTransaction($transaction, 7, $input + ['is_relief' => 0], $totals, 1, false)->fresh();
checkEis($transaction->vat5_certificate_details === null, 'Uncheck clears stale certificate');
$result = $header->invoke($transformer, $transaction, $settings, 'FIXTURE', '00', []);
checkEis($result['vat5CertificateDetails'] === null, 'Ordinary invoice excludes certificate');

// Real store action rejects validation before DB writes, uploads, or sale dispatch.
Request::macro('validate', function ($rules) use ($factory) { return $factory->make($this->all(), $rules)->validate(); });
$user = Mockery::mock(); $user->shouldReceive('can')->andReturn(true);
$auth = Mockery::mock(); $auth->shouldReceive('user')->andReturn($user);
$container->instance(Illuminate\Contracts\Auth\Factory::class, $auth);
$register = Mockery::mock(App\Utils\CashRegisterUtil::class); $register->shouldReceive('countOpenedRegister')->andReturn(1);
$contactUtil = Mockery::mock(App\Utils\ContactUtil::class);
$productUtil = Mockery::mock(App\Utils\ProductUtil::class); $productUtil->shouldReceive('calculateInvoiceTotal')->andReturn($totals);
$moduleUtil = Mockery::mock(App\Utils\ModuleUtil::class); $moduleUtil->shouldReceive('isSubscribed', 'isQuotaAvailable')->andReturn(true);
$util->shouldReceive('isCustomerCreditLimitExeeded')->andReturn(false);
$controller = new SellPosController($contactUtil, $productUtil, Mockery::mock(App\Utils\BusinessUtil::class), $util, $register, $moduleUtil, Mockery::mock(App\Utils\NotificationUtil::class));
$request = Request::create('/pos', 'POST', $input + ['products' => [['fixture' => true]], 'is_relief' => 1]);
$session = new Illuminate\Session\Store('fixture', new Illuminate\Session\ArraySessionHandler(120));
$session->put('user.business_id', 7); $request->setLaravelSession($session);
$before = DB::table('transactions')->count();
/** @var array{success: int, msg: string} $response POS AJAX validation failure returns an array. */
$response = $controller->store($request);
checkEis($response['success'] === 0 && str_contains($response['msg'], 'required'), 'Controller returns actionable VAT5 error');
checkEis(DB::table('transactions')->count() === $before && DB::transactionLevel() === 0, 'Validation rejection leaves sale and transaction state unchanged');

// Real update action follows the same early-rejection contract.
if (! class_exists('Module')) {
    class Module { public static function has($name) { return false; } }
}
$request = Request::create('/pos/'.$transaction->id, 'POST', $input + ['products' => [['fixture' => true]], 'is_relief' => 1]);
$request->setLaravelSession($session);
$snapshot = $transaction->fresh()->getAttributes();
/** @var array{success: int, msg: string} $response POS AJAX validation failure returns an array. */
$response = $controller->update($request, $transaction->id);
checkEis($response['success'] === 0 && str_contains($response['msg'], 'required'), 'Update returns actionable VAT5 error');
checkEis($transaction->fresh()->getAttributes() === $snapshot && DB::transactionLevel() === 0, 'Update validation failure leaves existing sale untouched');

$blade = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$vat5Source = file_get_contents($root.'/resources/views/sale_pos/partials/vat5_fields.blade.php');
token_get_all($blade->compileString($vat5Source), TOKEN_PARSE);
checkEis(true, 'VAT5 Blade syntax');
$container->instance('session', $session);
$container->instance('request', $request);
foreach ([false, true] as $only_payment) {
    $transaction = new Transaction(['is_relief' => true, 'vat5_certificate_details' => $certificate]);
    ob_start(); eval('?>'.$blade->compileString($vat5Source)); $html = ob_get_clean();
    $dom = new DOMDocument(); $dom->loadHTML($html);
    $fields = $dom->getElementsByTagName('input');
    checkEis($fields->length === 3, 'All VAT5 controls render');
    foreach ($fields as $field) { checkEis($field->hasAttribute('disabled') === $only_payment, 'Certificate controls read-only for payment-only edit'); }
    checkEis($fields->item(0)->getAttribute('value') === 'PROJECT1' && $fields->item(1)->getAttribute('value') === 'CERT1'
        && $fields->item(2)->getAttribute('value') === '2.5', 'Edit restores persisted certificate');
}
foreach (['store', 'update'] as $action) {
    $method = new ReflectionMethod(SellPosController::class, $action);
    $source = implode('', array_slice(file($root.'/app/Http/Controllers/SellPosController.php'), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    $validation = strpos($source, 'PosBuyerValidationService::class');
    checkEis($validation !== false && $validation < strpos($source, 'DB::beginTransaction()'), 'Validation before transaction in '.$action);
    checkEis($validation < strpos($source, 'uploadFile('), 'Validation before upload in '.$action);
}
$migration->down();
checkEis(! $schema->hasColumn('transactions', 'vat5_certificate_details'), 'Migration rollback');
Mockery::close();
echo "PASS: {$checks} EIS POS validation checks (SQLite memory and HTTP fakes only).\n";