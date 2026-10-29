<?php

require __DIR__.'\eis_location_terminal_checks.php';

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\EIS\Services\Terminal\TerminalContext;

// Exercise real activation persistence without calling the activation API.
$app['config']['eis.base_url'] = 'https://eis.invalid';
$baseUrlMigration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_23_171800_restore_eis_settings_base_url.php';
$baseUrlMigration->up();
locationCheck($schema->hasColumn('eis_settings', 'base_url')
    && DB::table('eis_settings')->whereNull('base_url')->count() === 0, 'Compatibility migration restores missing endpoint column and backfills settings');
DB::table('eis_settings')->where('id', 2)->update(['base_url' => 'https://custom.invalid']);
$baseUrlMigration->up();
locationCheck(DB::table('eis_settings')->where('id', 2)->value('base_url') === 'https://custom.invalid',
    'Repeated endpoint migration preserves existing terminal URLs');
$baseUrlMigration->down();
locationCheck($schema->hasColumn('eis_settings', 'base_url'), 'Compatibility rollback retains original-schema endpoint column');
$app->instance('cache', new class {
    private $store;
    public function __construct() { $this->store = new Illuminate\Cache\ArrayStore(); }
    public function lock($name, $seconds) { return new Illuminate\Cache\ArrayLock($this->store, $name, $seconds); }
});
$schema->create('business', function (Blueprint $table) { $table->increments('id'); $table->string('name'); });
DB::table('business')->insert([['id' => 1, 'name' => 'Business one'], ['id' => 2, 'name' => 'Business two']]);
$schema->create('users', function (Blueprint $table) {
    $table->increments('id'); $table->integer('business_id'); $table->softDeletes();
});
DB::table('users')->insert(['id' => 1, 'business_id' => 1]);
$gate = Mockery::mock(Illuminate\Contracts\Auth\Access\Gate::class);
$gate->shouldReceive('forUser')->andReturnSelf();
$allLocations = true;
$gate->shouldReceive('check')->andReturnUsing(function () use (&$allLocations) { return $allLocations; });
$app->instance(Illuminate\Contracts\Auth\Access\Gate::class, $gate);
$schema->table('business_locations', function (Blueprint $table) {
    foreach (['location_id', 'country', 'state', 'city', 'zip_code', 'invoice_scheme_id', 'sale_invoice_scheme_id',
        'invoice_layout_id', 'sale_invoice_layout_id', 'default_payment_accounts', 'mobile', 'email'] as $column) {
        $table->text($column)->nullable();
    }
});
foreach (['invoice_schemes', 'invoice_layouts'] as $name) {
    $schema->create($name, function (Blueprint $table) {
        $table->increments('id'); $table->integer('business_id'); $table->string('name');
        $table->boolean('is_default'); $table->timestamps();
    });
    DB::table($name)->insert(['business_id' => 1, 'name' => 'Default', 'is_default' => true]);
}
$schema->table('invoice_schemes', function (Blueprint $table) {
    foreach (['scheme_type', 'prefix', 'start_number', 'total_digits'] as $column) { $table->text($column)->nullable(); }
});
$schema->create('permissions', function (Blueprint $table) {
    $table->increments('id'); $table->string('name'); $table->string('guard_name'); $table->timestamps();
});
$schema->create('model_has_permissions', function (Blueprint $table) {
    $table->integer('permission_id'); $table->integer('model_id'); $table->string('model_type');
});
$app['config']['permission.models.permission'] = Spatie\Permission\Models\Permission::class;
$app['config']['permission.table_names.model_has_permissions'] = 'model_has_permissions';
$app['config']['permission.column_names.model_morph_key'] = 'model_id';
Spatie\Permission\PermissionRegistrar::$pivotPermission = 'permission_id';
DB::table('business_locations')->where('id', 1)->update([
    'default_payment_accounts' => '{"cash":{"is_enabled":1,"account":null}}', 'country' => 'Malawi',
]);
foreach ([
    'eis_settings' => ['secret_key', 'last_sync_at', 'sync_status', 'sync_error', 'successful_syncs', 'failed_syncs'],
    'eis_configurations' => ['global_version', 'terminal_version', 'taxpayer_version', 'tpin', 'is_vat_registered', 'tax_office_code', 'tax_office_name', 'raw_response', 'last_synced_at'],
    'eis_terminal_configurations' => ['version', 'terminal_label', 'email_address', 'phone_number', 'trading_name',
        'max_transaction_age', 'max_cummulative_amount', 'address_lines', 'raw_data', 'last_synced_at',
        'activated_at', 'activated_by', 'deactivated_at', 'deactivated_by', 'deactivation_reason', 'toggled_at',
        'toggled_by', 'activation_code', 'activation_environment', 'activation_date', 'jwt_token', 'secret_key'],
] as $name => $columns) {
    $schema->table($name, function (Blueprint $table) use ($columns) {
        foreach ($columns as $column) { $table->text($column)->nullable(); }
    });
}
foreach ([
    'eis_terminal_sites' => ['site_id', 'site_name', 'raw_data', 'last_synced_at'],
    'eis_offline_limits' => ['max_transaction_age_hours', 'max_cumulative_amount', 'raw_data', 'last_synced_at'],
] as $name => $columns) {
    $schema->create($name, function (Blueprint $table) use ($columns) {
        $table->increments('id'); $table->integer('terminal_configuration_id');
        foreach ($columns as $column) { $table->text($column)->nullable(); }
        $table->timestamps();
    });
}
$sync = new class extends Modules\EIS\Services\Configuration\ConfigurationSyncService {
    public function __construct() {}
};
$activation = new Modules\EIS\Services\Terminal\EisTerminalActivationService($sync);
$process = new ReflectionMethod($activation, 'processActivationResponse');
$process->setAccessible(true);
function activationResponse(string $device, string $site, int $position): object {
    return (object) ['statusCode' => 1, 'data' => (object) [
        'activatedTerminal' => (object) ['terminalId' => $device, 'terminalPosition' => $position, 'taxpayerId' => 100,
            'terminalCredentials' => (object) ['jwtToken' => 'jwt-'.$device, 'secretKey' => 'secret-'.$device]],
        'configuration' => (object) [
            'terminalConfiguration' => (object) ['terminalId' => $device, 'isActiveTerminal' => true,
                'terminalSite' => (object) ['siteId' => $site, 'siteName' => 'Site '.$site],
                'offlineLimit' => (object) ['maxTransactionAgeInHours' => 24, 'maxCummulativeAmount' => 100000]],
            'taxpayerConfiguration' => (object) ['tin' => 'TIN1'],
        ],
    ]];
}
$beforeSettings = DB::table('eis_settings')->count();
$beforeLocations = DB::table('business_locations')->get()->toJson();
$result = $process->invoke($activation, 1, activationResponse('A2', 'A', 3), 'CODE', [], 1, 1);
$newId = $result['terminal_setting_id'];
locationCheck($result['success'] && DB::table('eis_settings')->count() === $beforeSettings + 1, 'New terminal adds rather than replaces another device');
locationCheck(TerminalContext::settings(1, null, 1)->device_id === 'A1', 'Old device identity and historical sale ownership survive activation');
locationCheck((int) TerminalContext::settings(1, null, $newId)->location_id === 1, 'New terminal belongs to the chosen existing location');
locationCheck(DB::table('business_locations')->count() === 5
    && DB::table('business_locations')->where('id', 1)->value('name') === 'Site A',
    'Activation reuses the exact business/site location and syncs its name');
locationCheck(DB::table('eis_terminal_sites')->count() === 1 && DB::table('eis_offline_limits')->count() === 1, 'Activation persists site and offline configuration');
locationCheck(DB::table('eis_settings')->where('id', $newId)->value('base_url') === 'https://eis.invalid', 'New settings include required base URL');
$again = $process->invoke($activation, 1, activationResponse('A2', 'A', 3), 'CODE', [], 1, 1);
locationCheck($again['terminal_setting_id'] === $newId && DB::table('eis_settings')->count() === $beforeSettings + 1, 'Reactivation updates the same terminal without duplicating settings');
// Activation must also support installations that use only the environment endpoint.
DB::beginTransaction();
$schema->table('eis_settings', fn (Blueprint $table) => $table->dropColumn('base_url'));
$withoutEndpoint = $process->invoke($activation, 1, activationResponse('NO-DB-URL', 'A', 9), 'CODE', [], 1, 1);
locationCheck($withoutEndpoint['success']
    && TerminalContext::settings(1, null, $withoutEndpoint['terminal_setting_id'])->device_id === 'NO-DB-URL',
    'Activation creates settings successfully when legacy base_url column is absent');
DB::rollBack();
locationCheck(DB::table('eis_configurations')->where('eis_setting_id', $newId)->count() === 1, 'Each terminal has exactly one configuration');
DB::beginTransaction();
DB::table('business_locations')->where('id', 1)->update(['default_eis_setting_id' => $newId]);
$moved = $process->invoke($activation, 1, activationResponse('A2', 'B', 3), 'CODE', [], 1);
locationCheck($moved['terminal_setting_id'] === $newId && (int) TerminalContext::settings(1, null, $newId)->location_id === 2,
    'API reassignment moves the existing terminal to its assigned site without duplicating identity');
locationCheck(DB::table('business_locations')->where('id', 1)->value('default_eis_setting_id') === null,
    'Moving a default terminal clears its old location default rather than pointing across sites');
locationCheck((int) DB::table('eis_sales')->where('id', 1)->value('location_id') === 1,
    'Terminal reassignment does not rewrite historical fiscal location');
DB::rollBack();
DB::beginTransaction();
$process->invoke($activation, 1, activationResponse('A1', 'B', 1), 'CODE', [], 1);
$historical = new App\Transaction();
$historical->setRawAttributes(['id' => 1, 'business_id' => 1, 'location_id' => 1]);
locationCheck((int) DB::table('eis_sales')->where('id', 1)->value('location_id') === 1
    && (int) DB::table('eis_sales')->where('id', 1)->value('eis_setting_id') === 1,
    'Moving a terminal with fiscal history preserves its original sale ownership');
locationReject(fn () => TerminalContext::forSale($historical),
    'Historical retry cannot silently submit using its moved terminal new site');
DB::rollBack();
locationReject(fn () => $process->invoke($activation, 2, activationResponse('A2', 'A', 3), 'CODE', [], 1, 3), 'A terminal cannot be stolen by another business');
DB::beginTransaction();
$created = $process->invoke($activation, 1, activationResponse('NEW', 'NEW-SITE', 4), 'CODE', [], 1);
$createdLocation = TerminalContext::settings(1, null, $created['terminal_setting_id'])->location;
locationCheck($createdLocation->eis_site_id === 'NEW-SITE' && $createdLocation->name === 'Site NEW-SITE',
    'Unknown API site creates the assigned business location automatically');
locationCheck((int) $createdLocation->invoice_scheme_id === 1 && (int) $createdLocation->invoice_layout_id === 1
    && $createdLocation->default_payment_accounts === DB::table('business_locations')->where('id', 1)->value('default_payment_accounts'),
    'API-created location inherits business invoice and payment defaults');
locationCheck(DB::table('permissions')->where('name', 'location.'.$createdLocation->id)->exists(),
    'API-created location has a corresponding role permission');
$process->invoke($activation, 1, activationResponse('NEW-SECOND', 'NEW-SITE', 5), 'CODE', [], 1);
locationCheck(DB::table('business_locations')->where('business_id', 1)->where('eis_site_id', 'NEW-SITE')->count() === 1,
    'Another terminal at the same API site reuses its location');
DB::rollBack();
$allLocations = false;
$beforePermissionCheck = DB::table('business_locations')->count();
locationReject(fn () => $process->invoke($activation, 1, activationResponse('FORBIDDEN', 'NEW-SITE', 6), 'CODE', [], 1),
    'Restricted users cannot create a new API location without all-location access');
locationReject(fn () => $process->invoke($activation, 1, activationResponse('FORBIDDEN', 'B', 6), 'CODE', [], 1),
    'Restricted users cannot attach terminals to an unpermitted API site');
locationCheck(DB::table('business_locations')->count() === $beforePermissionCheck,
    'Forbidden API site activation creates no partial location');
$allLocations = true;
DB::beginTransaction();
$freshLocation = $sync->locationForActivation(2, activationResponse('FRESH', 'FIRST', 1)->data->configuration);
locationCheck($freshLocation->business_id === 2 && $freshLocation->invoice_scheme_id && $freshLocation->invoice_layout_id,
    'Businesses without invoice defaults receive usable default schemes and layouts');
DB::rollBack();
$missingSite = activationResponse('MISSING', 'A', 6);
$missingSite->data->configuration->terminalConfiguration->terminalSite->siteName = '';
locationReject(fn () => $process->invoke($activation, 1, $missingSite, 'CODE', [], 1),
    'Missing API site name is rejected before location creation');
$duplicateSite = activationResponse('DUPLICATE', 'D', 6);
locationReject(fn () => $process->invoke($activation, 3, $duplicateSite, 'CODE', [], null),
    'Duplicate business locations with one API site ID are not resolved arbitrarily');
$mismatch = activationResponse('NEW', 'A', 4);
$mismatch->data->configuration->terminalConfiguration->terminalId = 'OTHER';
locationReject(fn () => $process->invoke($activation, 1, $mismatch, 'CODE', [], 1, 1), 'Activation response must consistently identify one terminal');
locationCheck(DB::table('eis_settings')->count() === $beforeSettings + 1, 'Rejected activation writes no partial terminal');
DB::table('business_locations')->where('id', 1)->update(['default_eis_setting_id' => $newId]);
locationCheck(TerminalContext::forLocation(1, 1, true)->id === $newId, 'New sales can use another designated terminal at the same location');
$sale = new App\Transaction();
$sale->setRawAttributes(['id' => 1, 'business_id' => 1, 'location_id' => 1]);
locationCheck(TerminalContext::forSale($sale)->id === 1, 'Historical retry does not follow a default-terminal change');
$deactivated = $activation->deactivate(1, 'Test', 1, $newId);
locationCheck($deactivated['success'], 'Deactivation targets the selected terminal');
locationReject(fn () => TerminalContext::forLocation(1, 1, true), 'Deactivated default blocks new submissions');
locationCheck(TerminalContext::forSale($sale)->id === 1 && TerminalContext::forLocation(1, 2, true)->id === 2, 'Deactivation leaves other terminal/location sales available');
locationCheck(!TerminalContext::settings(1, null, $newId)->status, 'Deactivation disables scheduled synchronization for the selected settings');
$selectedConfiguration = Modules\EIS\Models\EisConfiguration::where('eis_setting_id', $newId)->firstOrFail();
$sync->syncTerminalConfiguration($selectedConfiguration, activationResponse('A2', 'A', 3)->data->configuration);
locationReject(fn () => TerminalContext::forLocation(1, 1, true), 'Remote configuration refresh cannot undo local deactivation');
$previousEnvironment = $app['config']->get('eis.environment', 'development');
DB::beginTransaction();
DB::table('eis_settings')->where('id', $newId)->update(['status' => true]);
$inactiveResponse = activationResponse('A2', 'A', 3)->data->configuration;
$inactiveResponse->terminalConfiguration->isActiveTerminal = false;
$sync->syncTerminalConfiguration($selectedConfiguration, $inactiveResponse);
foreach (['development', 'dev', 'sandbox', 'production', 'invalid', ''] as $mode) {
    $app['config']['eis.environment'] = $mode;
    $development = in_array($mode, ['development', 'dev', 'sandbox'], true);
    $status = $activation->getStatus(1, $newId);
    locationCheck($status['success'] && $status['is_active'] === $development
        && $status['data']['is_active'] === $development
        && $status['data']['status'] === ($development ? 'Active' : 'Inactive'),
        'Terminal API status consistently applies environment policy: '.$mode);
    if ($development) {
        locationCheck(TerminalContext::forLocation(1, 1, true)->id === $newId,
            'Development can submit through locally enabled terminal despite inactive API flag: '.$mode);
    } else {
        locationReject(fn () => TerminalContext::forLocation(1, 1, true),
            'Non-development rejects remotely inactive terminals: '.$mode);
    }
}
locationCheck(!Modules\EIS\Models\EisTerminalConfiguration::where('configuration_id', $selectedConfiguration->id)->firstOrFail()->is_active,
    'Environment exception preserves the original remote inactive flag');
$app['config']['eis.environment'] = 'development';
$toggled = $activation->toggle(1, TerminalContext::settings(1, null, $newId)->jwt_token, 1, $newId);
locationCheck($toggled['success'] && !$activation->getStatus(1, $newId)['is_active'],
    'Toggle deactivates an operational development terminal even when remote flag is false');
$sync->syncTerminalConfiguration($selectedConfiguration, $inactiveResponse);
locationReject(fn () => TerminalContext::forLocation(1, 1, true),
    'Development exception never bypasses local deactivation after configuration refresh');
DB::rollBack();
$app['config']['eis.environment'] = $previousEnvironment;
locationCheck(!$activation->getStatus(1, $newId)['is_active'], 'Status reports local deactivation even if remote terminal flag is active');
locationCheck($activation->toggle(1, 'jwt-A2', 1, $newId)['success']
    && TerminalContext::forLocation(1, 1, true)->id === $newId, 'Explicit legacy toggle updates both local status flags');
$activation->deactivate(1, 'Test', 1, $newId);

$callActivation = new ReflectionMethod($activation, 'callActivationAPI');
$callActivation->setAccessible(true);
$http = new Illuminate\Http\Client\Factory();
$http->preventStrayRequests();
Illuminate\Support\Facades\Http::swap($http);
Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response([
    'statusCode' => 1, 'data' => ['activatedTerminal' => ['terminalId' => 'TEST']],
]));
foreach ([
    'Win32' => 'Windows', 'Win64' => 'Windows', 'Windows' => 'Windows',
    'MacIntel' => 'MacOS', 'MacOS' => 'MacOS', 'Linux x86_64' => 'Linux',
    'Linux armv8l Android' => 'Android', 'iPhone' => 'iOS', 'iOS' => 'iOS',
] as $inputOs => $expectedOs) {
    $callActivation->invoke($activation, 1, 'TEST', ['platform' => ['osName' => $inputOs]]);
    $request = Illuminate\Support\Facades\Http::recorded()->last()[0];
    locationCheck($request['environment']['platform']['osName'] === $expectedOs,
        'Activation HTTP payload normalizes '.$inputOs.' to '.$expectedOs);
}
$app->instance('request', Request::create('http://localhost/eis/terminals/activate', 'POST', [], [], [], [
    'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
]));
foreach ([[], ['platform' => ['osName' => 'Web']]] as $environment) {
    $callActivation->invoke($activation, 1, 'TEST', $environment);
    locationCheck(Illuminate\Support\Facades\Http::recorded()->last()[0]['environment']['platform']['osName'] === 'Windows',
        'Omitted/browser-placeholder OS is identified from request user agent');
}
$requestsBefore = count(Illuminate\Support\Facades\Http::recorded());
locationReject(fn () => $callActivation->invoke($activation, 1, 'TEST', ['platform' => ['osName' => 'UnrecognizedOS']]),
    'Unsupported explicit OS fails locally');
locationCheck(count(Illuminate\Support\Facades\Http::recorded()) === $requestsBefore, 'Unsupported OS never reaches EIS');

$app['config']['app.name'] = 'Development POS';
$app['config']['eis.environment'] = 'development';
$app['config']['eis.product_id'] = null;
$app['config']['eis.product_version'] = '2.0.0';
$callActivation->invoke($activation, 1, 'TEST', ['pos' => ['productID' => 'browser-override']]);
$pos = Illuminate\Support\Facades\Http::recorded()->last()[0]['environment']['pos'];
locationCheck($pos === ['productID' => 'Development POS', 'productVersion' => '2.0.0'],
    'Development accepts null product ID but sends required configured POS fields');
$app['config']['eis.environment'] = 'production';
$requestsBefore = count(Illuminate\Support\Facades\Http::recorded());
locationReject(fn () => $callActivation->invoke($activation, 1, 'TEST', ['pos' => ['productID' => 'browser-override']]),
    'Production requires server-configured registered product ID');
locationCheck(count(Illuminate\Support\Facades\Http::recorded()) === $requestsBefore, 'Missing production product ID blocks HTTP');
$app['config']['eis.product_id'] = 'REGISTERED-POS';
$callActivation->invoke($activation, 1, 'TEST', ['pos' => ['productID' => 'browser-override', 'productVersion' => 'wrong']]);
locationCheck(Illuminate\Support\Facades\Http::recorded()->last()[0]['environment']['pos']
    === ['productID' => 'REGISTERED-POS', 'productVersion' => '2.0.0'], 'Production sends registered product ID and version, ignoring browser overrides');
$app['config']['eis.environment'] = 'invalid';
locationReject(fn () => $callActivation->invoke($activation, 1, 'TEST', []), 'Unknown EIS mode is rejected');
$app['config']['eis.environment'] = 'development';

$http = new Illuminate\Http\Client\Factory();
$http->preventStrayRequests();
Illuminate\Support\Facades\Http::swap($http);
Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response([
    'data' => ['jwtToken' => 'renewed-jwt', 'secretKey' => 'renewed-secret'],
]));
$renewed = $activation->regenerateCredentials(1, 'jwt-A2', $newId);
$terminalConfigurationId = DB::table('eis_configurations')->where('eis_setting_id', $newId)->value('id');
locationCheck($renewed['success'] && TerminalContext::settings(1, null, $newId)->jwt_token === 'renewed-jwt', 'Token renewal updates only the selected settings');
locationCheck(DB::table('eis_terminal_configurations')->where('configuration_id', $terminalConfigurationId)->value('secret_key') === 'renewed-secret', 'Token renewal keeps terminal credentials consistent with settings');
locationCheck(TerminalContext::settings(1, null, 1)->jwt_token === 'token1' && TerminalContext::settings(1, null, 2)->jwt_token === 'token2', 'Token renewal leaves other terminals unchanged');
$http = new Illuminate\Http\Client\Factory(); $http->preventStrayRequests();
Illuminate\Support\Facades\Http::swap($http);
Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response(['data' => ['jwtToken' => 'incomplete']]));
$renewed = $activation->regenerateCredentials(1, 'renewed-jwt', $newId);
locationCheck(!$renewed['success'] && TerminalContext::settings(1, null, $newId)->jwt_token === 'renewed-jwt', 'Incomplete renewal leaves both stored credentials unchanged');
$renewed = $activation->regenerateCredentials(1, 'token2', $newId);
locationCheck(!$renewed['success'] && count(Illuminate\Support\Facades\Http::recorded()) === 1, 'Foreign renewal token fails before HTTP');

$configurationClient = new class extends Modules\EIS\Services\Configuration\EisConfigurationClient {
    public object $data;
    public int $calls = 0;
    public function __construct() {}
    public function latest(string $token): Modules\EIS\Services\Configuration\EISConfigurationResponse {
        ++$this->calls;
        return new Modules\EIS\Services\Configuration\EISConfigurationResponse((object) ['statusCode' => 1, 'data' => $this->data]);
    }
};
$configurationClient->data = activationResponse('B1', 'B', 2)->data->configuration;
$configurationClient->data->globalConfiguration = (object) ['versionNo' => 1];
$configurationClient->data->terminalConfiguration->versionNo = 1;
$configurationClient->data->taxpayerConfiguration->versionNo = 1;
DB::table('eis_configurations')->where('id', 2)->update([
    'global_version' => 1, 'terminal_version' => 1, 'taxpayer_version' => 1,
    'tpin' => 'TIN1', 'is_vat_registered' => 0,
    'raw_response' => json_encode(['data' => $configurationClient->data]),
]);
$configurationValidator = Mockery::mock(Modules\EIS\Services\Configuration\Validators\ConfigurationValidator::class);
$configurationValidator->shouldReceive('validate')->times(5)->andReturnNull();
$configService = new Modules\EIS\Services\Configuration\ConfigurationSyncService(
    $configurationClient, $configurationValidator
);
DB::table('eis_settings')->where('id', 7)->update(['jwt_token' => 'token2']);
locationCheck($configService->sync(1, 'token2', false, 2)->id === 2, 'Latest configuration uses explicit terminal selection even when JWTs are shared');
$otherLocations = DB::table('business_locations')->where('id', '!=', 2)->orderBy('id')->get()->toJson();
$configurationClient->data->terminalConfiguration->terminalSite->siteName = 'Renamed EIS site';
$renamed = $configService->sync(1, 'token2', false, 2);
$storedResponse = $renamed->raw_response;
if (is_string($storedResponse)) { $storedResponse = json_decode($storedResponse, true, 512, JSON_THROW_ON_ERROR); }
locationCheck(data_get($storedResponse, 'data.terminalConfiguration.terminalSite.siteName') === 'Renamed EIS site',
    'Site-name-only changes refresh the stored response without an EIS version increment');
$terminalConfigurationId = DB::table('eis_terminal_configurations')->where('configuration_id', 2)->value('id');
locationCheck(DB::table('eis_terminal_sites')->where('terminal_configuration_id', $terminalConfigurationId)->value('site_name') === 'Renamed EIS site',
    'Site-name-only refresh updates normalized terminal site details');
locationCheck(DB::table('business_locations')->where('id', 2)->value('name') === 'Renamed EIS site',
    'Site-name-only refresh updates the assigned business location');
locationCheck(DB::table('business_locations')->where('id', '!=', 2)->orderBy('id')->get()->toJson() === $otherLocations,
    'Site rename preserves other locations and tenants');
locationCheck($configService->sync(1, 'token2', false, 2)->id === 2,
    'Repeated unchanged configuration still skips validation and duplicate persistence');
$configurationClient->data->terminalConfiguration->terminalLabel = 'Updated till label';
$configurationClient->data->terminalConfiguration->tradingName = 'Updated trading name';
$configurationClient->data->terminalConfiguration->emailAddress = 'site@example.invalid';
$configurationClient->data->terminalConfiguration->phoneNumber = '0999000000';
$configurationClient->data->terminalConfiguration->addressLines = ['Updated EIS address'];
$configurationClient->data->terminalConfiguration->isActiveTerminal = false;
$configurationClient->data->terminalConfiguration->offlineLimit->maxTransactionAgeInHours = 12;
$configurationClient->data->terminalConfiguration->offlineLimit->maxCummulativeAmount = 4500;
$configurationClient->data->globalConfiguration->newGlobalField = 'updated';
$configurationClient->data->taxpayerConfiguration->taxOffice = (object) ['name' => 'Updated tax office'];
$refreshed = $configService->sync(1, 'token2', false, 2);
locationCheck(data_get($refreshed->raw_response, 'data.globalConfiguration.newGlobalField') === 'updated'
    && data_get($refreshed->raw_response, 'data.taxpayerConfiguration.taxOffice.name') === 'Updated tax office',
    'All global and taxpayer fields refresh at unchanged versions and remain accessible without double JSON decoding');
$terminalRow = DB::table('eis_terminal_configurations')->where('configuration_id', 2)->first();
locationCheck($terminalRow->terminal_label === 'Updated till label' && $terminalRow->trading_name === 'Updated trading name'
    && $terminalRow->email_address === 'site@example.invalid' && $terminalRow->phone_number === '0999000000'
    && json_decode($terminalRow->address_lines, true) === ['Updated EIS address'] && !$terminalRow->is_active,
    'Unversioned terminal labels, contact details, addresses and remote activity refresh');
$limitRow = DB::table('eis_offline_limits')->where('terminal_configuration_id', $terminalRow->id)->first();
locationCheck((int) $limitRow->max_transaction_age_hours === 12 && (float) $limitRow->max_cumulative_amount === 4500.0,
    'Unversioned offline limit changes refresh normalized limits');
$locationRow = DB::table('business_locations')->where('id', 2)->first();
locationCheck($locationRow->mobile === '0999000000' && $locationRow->email === 'site@example.invalid'
    && (string) $locationRow->eis_site_id === 'B', 'Location contact projection refreshes without moving its site');
locationCheck(DB::table('business_locations')->where('id', '!=', 2)->orderBy('id')->get()->toJson() === $otherLocations,
    'Full configuration refresh leaves other locations and tenants unchanged');
$configurationClient->data->terminalConfiguration->emailAddress = null;
$configurationClient->data->terminalConfiguration->addressLines = [];
$configService->sync(1, 'token2', false, 2);
locationCheck(DB::table('business_locations')->where('id', 2)->value('email') === null
    && json_decode(DB::table('eis_terminal_configurations')->where('configuration_id', 2)->value('address_lines'), true) === [],
    'Explicit EIS field clearing is detected even without version changes');
locationCheck($configService->sync(1, 'token2', false, 2)->id === 2,
    'Identical full payload remains a no-op after refreshing all fields');
$schema->create('tax_rates', function (Blueprint $table) {
    $table->increments('id');
    foreach (['business_id', 'configuration_id', 'tax_rate_id', 'name', 'charge_mode', 'ordinal',
        'rate', 'amount', 'is_activated', 'activation_id'] as $column) { $table->text($column)->nullable(); }
    $table->timestamps();
});
$configurationClient->data->globalConfiguration->taxrates = [
    (object) ['id' => 'VAT', 'name' => 'Updated VAT', 'rate' => 16.5, 'chargeMode' => 'Item', 'ordinal' => 1],
];
$configurationClient->data->taxpayerConfiguration->activatedTaxRateIds = ['VAT'];
DB::table('eis_settings')->where('id', 2)->update(['status' => 0]);
$configService->sync(1, 'token2', false, 2);
$vat = DB::table('tax_rates')->where('configuration_id', 2)->where('tax_rate_id', 'VAT')->first();
locationCheck($vat && (float) $vat->rate === 16.5 && (bool) $vat->is_activated,
    'Global tax rate and taxpayer activation changes synchronize even at unchanged versions');
$configurationClient->data->globalConfiguration->taxrates[0]->rate = 17;
$configurationClient->data->taxpayerConfiguration->activatedTaxRateIds = [];
$configService->sync(1, 'token2', false, 2);
$vat = DB::table('tax_rates')->where('configuration_id', 2)->where('tax_rate_id', 'VAT')->first();
locationCheck((float) $vat->rate === 17.0 && !$vat->is_activated && DB::table('tax_rates')->count() === 1,
    'Existing rate amount and activation update without duplicate rate rows');
locationCheck(!DB::table('eis_settings')->where('id', 2)->value('status'),
    'Full configuration refresh never re-enables a locally deactivated terminal');
DB::table('eis_settings')->where('id', 2)->update(['status' => 1]);
$configurationClient->data->terminalConfiguration->terminalSite->siteId = 'A';
$snapshot = DB::table('eis_configurations')->get()->toJson();
try {
    $configService->sync(1, 'token2', false, 2);
    throw new RuntimeException('Foreign configuration must be rejected');
} catch (Modules\EIS\Exceptions\SyncException $e) {
    locationCheck(str_contains($e->getMessage(), 'site and TIN') && DB::table('eis_configurations')->get()->toJson() === $snapshot,
        'Foreign latest-configuration site is rejected before any ownership changes');
}
DB::table('eis_settings')->where('id', 7)->update(['jwt_token' => 'token7']);

// Every account, including an administrator, is constrained by permitted locations.
$user = new class extends App\User {
    public $locations = [1];
    public function permitted_locations($business_id = null) { return $this->locations; }
};
$user->business_id = 1;
$middleware = new Modules\EIS\Http\Middleware\EnforceTerminalBusiness();
function isolationRequest(string $path, array $body = []): Request {
    global $user;
    $request = Request::create('http://localhost/'.$path, 'POST', $body);
    $request->setUserResolver(fn () => $user);
    return $request;
}
function isolationStatus(callable $work, int $status, string $message): void {
    try { $work(); } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        locationCheck($e->getStatusCode() === $status, $message); return;
    }
    throw new RuntimeException($message);
}
locationCheck($middleware->handle(isolationRequest('api/v1/terminal/deactivate', ['terminal_id' => 1]), fn () => true), 'Permitted terminal action passes middleware');
isolationStatus(fn () => $middleware->handle(isolationRequest('api/v1/terminal/deactivate', ['terminal_id' => 2]), fn () => true), 403, 'Unpermitted location terminal is forbidden');
isolationStatus(fn () => $middleware->handle(isolationRequest('api/v1/terminal/deactivate'), fn () => true), 422, 'Multi-terminal API requests require explicit selection');
isolationStatus(fn () => $middleware->handle(isolationRequest('api/v1/terminal/activate', ['location_id' => 3]), fn () => true), 403, 'Foreign business location is forbidden');
isolationStatus(fn () => $middleware->handle(isolationRequest('api/v1/terminal/activate', ['business_id' => 2]), fn () => true), 403, 'Body cannot change business ownership');
isolationStatus(fn () => $middleware->handle(isolationRequest('api/v1/terminal/deactivate', ['terminal_id' => [1]]), fn () => true), 422, 'Invalid terminal selector is rejected');
$user->locations = 'all';
locationCheck($middleware->handle(isolationRequest('api/v1/terminal/deactivate', ['terminal_id' => 2]), fn () => true), 'All-locations permission is honored within the business');
$anonymous = isolationRequest('api/v1/terminal/activate'); $anonymous->setUserResolver(fn () => null);
// Non-API session path does not consult the host application's API guard.
$anonymous = isolationRequest('eis/terminals/activate'); $anonymous->setUserResolver(fn () => null);
isolationStatus(fn () => $middleware->handle($anonymous, fn () => true), 401, 'Anonymous session management is forbidden');
$webhook = isolationRequest('api/v1/webhooks/configuration-update'); $webhook->setUserResolver(fn () => null);
locationCheck($middleware->handle($webhook, fn () => true), 'Existing machine webhook authentication contract is unchanged');

$http = new Illuminate\Http\Client\Factory(); $http->preventStrayRequests();
Illuminate\Support\Facades\Http::swap($http);
Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response(['ok' => true]));
$health = Mockery::mock(Modules\EIS\Services\Http\EisHealthService::class);
$httpClient = new Modules\EIS\Services\Http\EisHttpClient(new Modules\EIS\Services\Security\EisSignatureService(), $health);
$endpointSetting = new Modules\EIS\Models\EisSetting([
    'base_url' => 'https://stale.invalid', 'business_id' => 1, 'jwt_token' => 'test-token',
    'secret_key' => 'test-secret', 'tpin' => 'TIN1',
]);
$httpClient->get('/endpoint-check', $endpointSetting);
locationCheck(Illuminate\Support\Facades\Http::recorded()->last()[0]->url() === 'https://eis.invalid/endpoint-check',
    'Signed GET uses environment config rather than stored terminal endpoint');
$authSetting = Mockery::mock(Modules\EIS\Models\EisSetting::class)->makePartial();
$authSetting->setRawAttributes(['base_url' => 'https://stale.invalid', 'refresh_token' => 'test-refresh']);
$authSetting->shouldReceive('update')->once()->andReturn(true);
$http = new Illuminate\Http\Client\Factory(); $http->preventStrayRequests();
Illuminate\Support\Facades\Http::swap($http);
Illuminate\Support\Facades\Http::fake(fn () => Illuminate\Support\Facades\Http::response(['access_token' => 'refreshed']));
(new Modules\EIS\Services\Auth\EisAuthService())->refresh($authSetting);
locationCheck(Illuminate\Support\Facades\Http::recorded()->last()[0]->url() === 'https://eis.invalid/auth/refresh',
    'Credential refresh uses environment config rather than stored terminal endpoint');
$app['config']['eis.base_url'] = '';
$requestCount = count(Illuminate\Support\Facades\Http::recorded());
locationReject(fn () => new Modules\EIS\Services\Http\EisHttpClient(new Modules\EIS\Services\Security\EisSignatureService(), $health),
    'Signed client requires configured EIS endpoint');
locationReject(fn () => (new Modules\EIS\Services\Auth\EisAuthService())->refresh($authSetting),
    'Credential refresh requires configured EIS endpoint');
locationCheck(count(Illuminate\Support\Facades\Http::recorded()) === $requestCount, 'Missing endpoint never sends HTTP');
$app['config']['eis.base_url'] = 'https://eis.invalid';

$schema->table('transactions', function (Blueprint $table) {
    foreach (['type', 'status', 'payment_status', 'invoice_no', 'transaction_date'] as $column) { $table->text($column)->nullable(); }
});
DB::table('transactions')->insert([
    ['id' => 3, 'business_id' => 1, 'location_id' => 1, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'invoice_no' => 'SALE3'],
    ['id' => 4, 'business_id' => 1, 'location_id' => 2, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'invoice_no' => 'SALE4'],
]);
$select = new ReflectionMethod(Modules\EIS\Jobs\DispatchAllUnsubmittedSalesJob::class, 'getUnsubmittedTransactionIds');
$select->setAccessible(true);
locationCheck($select->invoke(new Modules\EIS\Jobs\DispatchAllUnsubmittedSalesJob(1, 50, null, null, [1])) === [3], 'User bulk submission is restricted to permitted locations');
locationCheck($select->invoke(new Modules\EIS\Jobs\DispatchAllUnsubmittedSalesJob(1, 50, null, null, [])) === [], 'No-location users cannot queue another location sales');
locationCheck($select->invoke(new Modules\EIS\Jobs\DispatchAllUnsubmittedSalesJob(1)) === [3, 4], 'System dispatcher retains business-wide processing');
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
token_get_all($compiler->compileString(file_get_contents(dirname(__DIR__, 2).'\resources\views\layouts\partials\header.blade.php')), TOKEN_PARSE);
locationCheck(true, 'Multi-terminal header Blade compiles');
Mockery::close();
echo "PASS: {$checks} cumulative terminal isolation checks (SQLite, no network).\n";
