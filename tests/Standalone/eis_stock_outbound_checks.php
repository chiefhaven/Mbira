<?php

require dirname(__DIR__, 2).'\vendor\autoload.php';

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use Illuminate\Config\Repository;
use Modules\EIS\Models\EisSetting;
use App\Http\Controllers\StockAdjustmentController;
use Modules\EIS\Services\Stock\StockOperationsClient;
use Modules\EIS\Services\Stock\StockOutboundService;

class_alias(Illuminate\Support\Facades\DB::class, 'DB');

$checks = 0;
function outboundCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: '.$message);
    }
    $checks++;
}

$app = new class extends Container {
    public function abort($code, $message = '', array $headers = [])
    {
        throw new Symfony\Component\HttpKernel\Exception\HttpException($code, $message, null, $headers);
    }
};
Container::setInstance($app);
$config = new Repository([
    'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]],
    'eis' => ['base_url' => 'https://eis.invalid/api/v1', 'environment' => 'development'],
]);
$app->instance('config', $config);
$app->instance(ConfigRepository::class, $config);
$capsule = new Capsule($app);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'sqlite');
$capsule->getDatabaseManager()->setDefaultConnection('sqlite');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('db', $capsule->getDatabaseManager());
$app->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
$app->instance('log', new Psr\Log\NullLogger());
$bus = new class implements Dispatcher {
    public array $jobs = [];
    public function dispatch($command) { $this->jobs[] = $command; return $command; }
    public function dispatchSync($command, $handler = null) { $this->jobs[] = $command; return $command; }
    public function dispatchNow($command, $handler = null) { $this->jobs[] = $command; return $command; }
    public function hasCommandHandler($command) { return false; }
    public function getCommandHandler($command) { return false; }
    public function pipeThrough(array $pipes) { return $this; }
    public function map(array $map) { return $this; }
};
$app->instance(Dispatcher::class, $bus);
$factory = new Factory();
$requests = [];
$adjustmentAttempt = 0;
$transferAttempt = 0;
$factory->fake(function ($request) use (&$requests, &$adjustmentAttempt, &$transferAttempt) {
    $requests[] = $request;
    $url = $request->url();
    if (str_ends_with($url, '/stock/getStockAdjustmentReasons')) {
        return Factory::response(['statusCode' => 1, 'data' => [
            ['description' => 'Damaged'], ['description' => 'Expired'],
        ]], 200);
    }
    if (str_ends_with($url, '/stock/submit-adjustment')) {
        $adjustmentAttempt++;
        return $adjustmentAttempt === 1
            ? Factory::response(['statusCode' => 1, 'remark' => 'accepted'], 200)
            : Factory::response(['statusCode' => 1], 503);
    }
    if (str_ends_with($url, '/stock/transfer-inventory')) {
        $transferAttempt++;
        if ($transferAttempt > 1) {
            throw new Illuminate\Http\Client\ConnectionException('simulated connection failure');
        }
        return Factory::response(['statusCode' => 0, 'remark' => 'Invalid destination'], 400);
    }
    if (str_ends_with($url, '/stock/submit-informal-purchase')) {
        return Factory::response(['statusCode' => 1, 'remark' => 'received for approval'], 200);
    }
    throw new RuntimeException('Unexpected fake EIS request: '.$url);
});
$factory->preventStrayRequests();
$app->instance(Factory::class, $factory);
Facade::setFacadeApplication($app);

$schema = $capsule->getConnection()->getSchemaBuilder();
$schema->create('business', function (Blueprint $table) { $table->increments('id'); });
$capsule->getConnection()->table('business')->insert(['id' => 7]);
foreach (['business_locations' => ['business_id', 'default_eis_setting_id', 'eis_site_id'],
    'eis_settings' => ['business_id', 'location_id', 'branch_id', 'device_id', 'jwt_token', 'status'],
    'eis_configurations' => ['business_id', 'eis_setting_id'],
    'eis_terminal_configurations' => ['configuration_id', 'is_active']] as $tableName => $columns) {
    $schema->create($tableName, function (Blueprint $table) use ($columns) {
        $table->increments('id');
        foreach ($columns as $column) {
            $table->text($column)->nullable();
        }
        $table->timestamps();
    });
}
$schema->create('transactions', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->integer('location_id');
    $table->string('type');
    $table->string('source')->nullable();
    $table->integer('created_by')->nullable();
});
$schema->create('stock_adjustment_lines', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('transaction_id');
    $table->integer('product_id');
    $table->decimal('quantity', 18, 4);
});
$schema->create('products', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->string('name');
});
$schema->create('eis_product_maps', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('business_id');
    $table->integer('product_id');
    $table->string('eis_product_id');
    $table->timestamp('deleted_at')->nullable();
});
DB::table('business_locations')->insert([
    ['id' => 1, 'business_id' => 7, 'default_eis_setting_id' => 1, 'eis_site_id' => 'SITE-A'],
    ['id' => 2, 'business_id' => 7, 'default_eis_setting_id' => null, 'eis_site_id' => 'SITE-B'],
]);
DB::table('eis_settings')->insert([
    'id' => 1, 'business_id' => 7, 'location_id' => 1, 'branch_id' => 'SITE-A',
    'device_id' => 'TERM-A', 'jwt_token' => 'fake-token', 'status' => 1,
]);
DB::table('eis_configurations')->insert(['id' => 1, 'business_id' => 7, 'eis_setting_id' => 1]);
DB::table('eis_terminal_configurations')->insert(['id' => 1, 'configuration_id' => 1, 'is_active' => 1]);

$migration = require dirname(__DIR__, 2).'\Modules\EIS\Database\Migrations\2026_10_23_234000_create_eis_stock_operations.php';
$migration->up();
$client = new StockOperationsClient();
$service = new StockOutboundService($client);
$setting = EisSetting::findOrFail(1);

$reasons = $service->adjustmentReasons($setting);
outboundCheck($reasons === ['Damaged', 'Expired'], 'Reasons are read from the official EIS reason endpoint');
$config->set('eis.base_url', 'https://eis.invalid');
$service->adjustmentReasons($setting);
outboundCheck($requests[1]->url() === 'https://eis.invalid/api/v1/stock/getStockAdjustmentReasons',
    'Host-only EIS base URLs receive the documented API prefix exactly once');
$config->set('eis.base_url', 'https://eis.invalid/api/v1');

function addOperation(string $type, array $payload, array $linePayloads, ?int $destinationLocationId = null): int
{
    if ($type === 'transfer' && $destinationLocationId === null) {
        $destinationLocationId = 2;
    }
    $now = now();
    $id = DB::table('eis_stock_operations')->insertGetId([
        'business_id' => 7,
        'location_id' => 1,
        'destination_location_id' => $destinationLocationId,
        'eis_setting_id' => 1,
        'operation_type' => $type,
        'status' => 'queued',
        'site_id' => 'SITE-A',
        'request_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    foreach ($linePayloads as $line) {
        DB::table('eis_stock_operation_lines')->insert([
            'eis_stock_operation_id' => $id,
            'product_id' => $line['product_id'] ?? null,
            'barcode' => $line['barcode'],
            'quantity' => $line['quantity'],
            'status' => 'queued',
            'request_payload' => json_encode($line['payload'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    return $id;
}

$adjustmentLines = [];
foreach (['BARCODE-1', 'BARCODE-2'] as $barcode) {
    $payload = [
        'barcode' => $barcode, 'quantity' => 1, 'adjustmentReason' => 'Damaged',
        'adjustmentType' => 'Decrease', 'siteId' => 'SITE-A',
    ];
    $adjustmentLines[] = [
        'barcode' => $barcode, 'quantity' => 1, 'payload' => $payload,
    ];
}
$adjustmentId = addOperation('adjustment', ['siteId' => 'SITE-A'], $adjustmentLines);
$service->submit($adjustmentId);
$adjustment = DB::table('eis_stock_operations')->find($adjustmentId);
$lineStatuses = DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $adjustmentId)
    ->orderBy('id')->pluck('status')->all();
outboundCheck($adjustment->status === 'partial', 'Partial line success is surfaced as a reconciliation blocker');
outboundCheck($lineStatuses === ['submitted', 'unknown'], 'Each adjustment request line keeps its individual outcome');
$sentAdjustmentCount = $adjustmentAttempt;
$service->submit($adjustmentId);
outboundCheck($adjustmentAttempt === $sentAdjustmentCount, 'Partial or unknown operations are never resent');

$transferPayload = [
    'fromWarehouseToSite' => false, 'siteToWarehouse' => false, 'fromSiteId' => 'SITE-A',
    'toSiteId' => 'SITE-B', 'items' => [['barcode' => 'BARCODE-3', 'quantity' => 2, 'price' => 10]],
];
$transferId = addOperation('transfer', $transferPayload, [[
    'barcode' => 'BARCODE-3', 'quantity' => 2, 'payload' => $transferPayload['items'][0],
]], 2);
$service->submit($transferId);
outboundCheck(DB::table('eis_stock_operations')->find($transferId)->status === 'rejected',
    'Explicit HTTP business rejection is settled and visible');
outboundCheck(DB::table('eis_stock_operations')->find($transferId)->destination_location_id === 2,
    'Site-to-site transfer scope includes its local destination location');
$queuedTransferPayload = $transferPayload;
$queuedTransferPayload['items'] = [['barcode' => 'BARCODE-7', 'quantity' => 1, 'price' => 10]];
$queuedTransferId = $service->enqueue(7, 1, 1, 'transfer', 'SITE-A', $queuedTransferPayload, [[
    'product_id' => 9, 'barcode' => 'BARCODE-7', 'quantity' => 1, 'payload' => $queuedTransferPayload['items'][0],
]], 5, null, 2);
outboundCheck(DB::table('eis_stock_operations')->find($queuedTransferId)->destination_location_id === 2,
    'Transfer outbox records the verified local destination mapping');
outboundCheck(!$service->dispatchQueued($queuedTransferId, 7, [1])
    && $service->dispatchQueued($queuedTransferId, 7, [1, 2]),
    'Queued transfers require permitted access to both source and destination locations');

$purchasePayload = [
    'supplierId' => 3, 'receivingDate' => '2026-10-23', 'receivedBy' => 'Receiver',
    'totalItems' => 1, 'totalValue' => 20,
    'items' => [[
        'itemCode' => 'BARCODE-4', 'description' => 'Test product', 'quantityOrdered' => 2,
        'quantityReceived' => 2, 'unitOfMeasure' => 'Each', 'unitPrice' => 10,
        'totalPrice' => 20, 'isFinishedProduct' => true,
    ]],
];
$purchaseId = addOperation('informal_purchase', $purchasePayload, [[
    'barcode' => 'BARCODE-4', 'quantity' => 2, 'payload' => $purchasePayload['items'][0],
]]);
$service->submit($purchaseId);
outboundCheck(DB::table('eis_stock_operations')->find($purchaseId)->status === 'awaiting_approval',
    'Accepted informal purchases remain blocked while awaiting EIS approval');
$controller = (new ReflectionClass(StockAdjustmentController::class))->newInstanceWithoutConstructor();
$guard = new ReflectionMethod(StockAdjustmentController::class, 'assertStockAdjustmentMutable');
$guard->setAccessible(true);
$reconciliationHeader = new App\Transaction();
$reconciliationHeader->id = 88;
$reconciliationHeader->source = 'eis_reconciliation';
$sourceGuarded = false;
try {
    $guard->invoke($controller, $reconciliationHeader);
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    $sourceGuarded = $exception->getStatusCode() === 409;
}
outboundCheck($sourceGuarded, 'EIS reconciliation stock adjustments cannot be edited or deleted');
$linkedOperationId = DB::table('eis_stock_operations')->insertGetId([
    'business_id' => 7, 'location_id' => 1, 'eis_setting_id' => 1,
    'source_stock_adjustment_id' => 89, 'operation_type' => 'adjustment',
    'status' => 'unknown', 'site_id' => 'SITE-A', 'request_payload' => '{}',
    'created_at' => now(), 'updated_at' => now(),
]);
$unknownHeader = new App\Transaction();
$unknownHeader->id = 89;
$remoteGuarded = false;
try {
    $guard->invoke($controller, $unknownHeader);
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    $remoteGuarded = $exception->getStatusCode() === 409;
}
outboundCheck($linkedOperationId > 0 && $remoteGuarded,
    'Stock adjustments linked to unknown EIS writes cannot be edited or deleted');

$localTransaction = (object) [
    'id' => 77, 'business_id' => 7, 'location_id' => 1, 'type' => 'stock_adjustment',
    'source' => null, 'created_by' => 5,
];
DB::table('products')->insert(['id' => 9, 'business_id' => 7, 'name' => 'Mapped item']);
DB::table('eis_product_maps')->insert([
    'business_id' => 7, 'product_id' => 9, 'eis_product_id' => 'LOCAL-BARCODE',
]);
DB::table('stock_adjustment_lines')->insert([
    'transaction_id' => 77, 'product_id' => 9, 'quantity' => 3,
]);
$jobsBeforeLocalAdjustment = count($bus->jobs);
$localOperationId = $service->enqueueLocalAdjustment($localTransaction, 'Damaged', 1, 'SITE-A');
$localOperation = DB::table('eis_stock_operations')->find($localOperationId);
$localLine = DB::table('eis_stock_operation_lines')->where('eis_stock_operation_id', $localOperationId)->first();
outboundCheck($localOperation->source_stock_adjustment_id === 77
    && json_decode($localLine->request_payload, true)['adjustmentType'] === 'Decrease',
    'Local stock adjustments create transactional, reasoned outbound outbox records');
outboundCheck(count($bus->jobs) === $jobsBeforeLocalAdjustment + 1,
    'Local adjustment outbox job is queued only after commit');
outboundCheck($service->enqueueLocalAdjustment($localTransaction, 'Damaged', 1, 'SITE-A') === $localOperationId
    && count($bus->jobs) === $jobsBeforeLocalAdjustment + 1,
    'Duplicate local adjustment events reuse the existing outbox record without a second job');
outboundCheck($service->dispatchQueued($localOperationId, 7, ['all'])
    && count($bus->jobs) === $jobsBeforeLocalAdjustment + 2,
    'An explicitly queued, never-started operation can be safely dispatched again');
outboundCheck(!$service->dispatchQueued($adjustmentId, 7, ['all'])
    && !$service->dispatchQueued($localOperationId, 7, [2]),
    'Partial or out-of-scope operations cannot be dispatched again');
$reconciliationTransaction = (object) [
    'id' => 78, 'business_id' => 7, 'location_id' => 1, 'type' => 'stock_adjustment',
    'source' => 'eis_reconciliation', 'created_by' => 5,
];
outboundCheck($service->enqueueLocalAdjustment($reconciliationTransaction, 'Damaged', 1, 'SITE-A') === null,
    'Reconciliation-sourced local movements do not echo outbound');
$unmappedTransaction = (object) [
    'id' => 79, 'business_id' => 7, 'location_id' => 1, 'type' => 'stock_adjustment',
    'source' => null, 'created_by' => 5,
];
DB::table('stock_adjustment_lines')->insert([
    'transaction_id' => 79, 'product_id' => 10, 'quantity' => 1,
]);
$unmappedId = $service->enqueueLocalAdjustment($unmappedTransaction, 'Damaged', 1, 'SITE-A');
outboundCheck(DB::table('eis_stock_operations')->find($unmappedId)->status === 'rejected',
    'Unmapped local adjustment items are persisted as explicit unsent rejections');

$networkId = addOperation('transfer', $transferPayload, [[
    'barcode' => 'BARCODE-6', 'quantity' => 1, 'payload' => $transferPayload['items'][0],
]]);
$service->submit($networkId);
outboundCheck(DB::table('eis_stock_operations')->find($networkId)->status === 'unknown',
    'Network failures after submission begins are ambiguous, not retried');

$unknownId = addOperation('transfer', $transferPayload, [[
    'barcode' => 'BARCODE-5', 'quantity' => 1, 'payload' => $transferPayload['items'][0],
]]);
DB::table('eis_stock_operations')->where('id', $unknownId)->update(['status' => 'submitting']);
$service->markJobFailureUnknown($unknownId);
outboundCheck(DB::table('eis_stock_operations')->find($unknownId)->status === 'unknown',
    'Worker loss while submitting is conservatively marked unknown');

outboundCheck(count($requests) === 7, 'All outbound calls used the fake HTTP transport');
outboundCheck($requests[2]->url() === 'https://eis.invalid/api/v1/stock/submit-adjustment',
    'Adjustment uses the configured EIS base URL and documented endpoint');
outboundCheck($requests[2]->header('Authorization') === ['Bearer fake-token'],
    'Outbound requests use terminal bearer authentication');
outboundCheck($requests[2]->data()['siteId'] === 'SITE-A'
    && $requests[2]->data()['adjustmentType'] === 'Decrease',
    'Adjustment payload is pinned to its EIS site and documented direction');

$requestCount = count($requests);
foreach ([
    [$purchaseId, 8, ['all']],
    [$purchaseId, 7, [2]],
    [$localOperationId, 7, ['all']],
] as [$operationId, $businessId, $permitted]) {
    $blocked = false;
    try {
        $service->verifyOutcome($businessId, $operationId, 5, 'Checked final EIS reference INV-12345', $permitted);
    } catch (InvalidArgumentException $exception) { $blocked = true; }
    outboundCheck($blocked, 'Outcome verification enforces tenant, locations and unresolved state');
}
$service->verifyOutcome(7, $purchaseId, 5, 'Checked MRA approval reference GRN-12345, approved and final.', ['all']);
$verified = DB::table('eis_stock_operations')->find($purchaseId);
outboundCheck($verified->status === 'verified' && (int) $verified->verified_by === 5
    && str_contains($verified->verification_notes, 'GRN-12345') && $verified->verified_at !== null,
    'Manual portal verification persists actor, timestamp and final EIS reference without rewriting item results');
outboundCheck(count($requests) === $requestCount, 'Verifying a final EIS outcome never resends stock or receiving');
outboundCheck(!$service->dispatchQueued($purchaseId, 7, ['all']), 'Verified outcomes cannot be resubmitted');

$locationUser = new class extends App\User {
    public $locations = 'all';
    public function permitted_locations($business_id = null) { return $this->locations; }
};
$locationRequest = Illuminate\Http\Request::create('/eis/stock/outbound');
$locationRequest->setUserResolver(fn () => $locationUser);
$outboundController = new Modules\EIS\Http\Controllers\StockOutboundController($service);
$locationScope = new ReflectionMethod($outboundController, 'permittedLocations');
$locationScope->setAccessible(true);
DB::table('business_locations')->insert([
    'id' => 3, 'business_id' => 8, 'default_eis_setting_id' => 1, 'eis_site_id' => 'FOREIGN',
]);
foreach (['all', [1], [2], []] as $permitted) {
    $locationUser->locations = $permitted;
    $rows = $locationScope->invoke($outboundController, $locationRequest, 7)
        ->join('eis_settings as setting', function ($join) {
            $join->on('setting.id', '=', 'business_locations.default_eis_setting_id')
                ->on('setting.business_id', '=', 'business_locations.business_id')
                ->on('setting.location_id', '=', 'business_locations.id');
        })->get(['business_locations.id', 'setting.device_id']);
    $expected = $permitted === 'all' || $permitted === [1] ? [1] : [];
    outboundCheck($rows->pluck('id')->map(fn ($id) => (int) $id)->all() === $expected,
        'Joined outbound location scope qualifies business and location IDs for '.json_encode($permitted));
}

$migration->down();
echo 'EIS stock outbound checks passed: '.$checks." checks\n";
