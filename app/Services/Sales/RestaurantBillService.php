<?php

namespace App\Services\Sales;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;

class RestaurantBillService
{
    private Connection $db;
    private static array $editing = [];
    private static array $clearing = [];

    public function __construct(Connection $db, private ?int $connectorTillId = null) { $this->db = $db; }

    public static function isEditing(Connection $db, $id): bool
    {
        return isset(self::$editing[spl_object_id($db).':'.$id]) && $db->transactionLevel() > 0;
    }

    public static function isClearing(Connection $db, $id): bool
    {
        return isset(self::$clearing[spl_object_id($db).':'.$id]) && $db->transactionLevel() > 0;
    }

    private function fail(string $message, int $status = 409): never
    {
        throw new SavedSplitException('restaurant_workflow', $message, $status);
    }

    private function admin($user): bool
    {
        return $user->hasRole('Admin#'.$user->business_id) || $user->can('superadmin');
    }

    private function allows($user, string $permission): bool { return $this->admin($user) || $user->can($permission); }

    private function requirePermission($user, string $permission): void
    {
        if (!$this->allows($user, $permission)) { $this->fail('Backend permission required: '.$permission, 403); }
    }

    private function storage(): void
    {
        if (!extension_loaded('bcmath')) { $this->fail('Restaurant workflow requires BCMath.', 503); }
        foreach (['restaurant_bills', 'restaurant_tickets', 'restaurant_approvals', 'restaurant_operations', 'restaurant_waste', 'res_tables',
            'transactions', 'transaction_sell_lines', 'transaction_payments', 'saved_sale_split_operations', 'reference_counts',
            'purchase_lines', 'transaction_sell_lines_purchase_lines', 'variation_location_details', 'account_transactions', 'cash_registers', 'cash_register_transactions'] as $table) {
            if (!$this->db->getSchemaBuilder()->hasTable($table)) {
                $this->fail('Deploy the restaurant workflow migration before using restaurant mode (missing '.$table.').', 503);
            }
            if ($this->db->getDriverName() === 'mysql') {
                $engine = $this->db->table('information_schema.TABLES')->where('TABLE_SCHEMA', $this->db->getDatabaseName())
                    ->where('TABLE_NAME', $table)->value('ENGINE');
                if (strtoupper($engine ?? '') !== 'INNODB') { $this->fail('Restaurant workflow requires InnoDB: '.$table, 503); }
            } elseif ($this->db->getDriverName() !== 'sqlite' || $this->db->getDatabaseName() !== ':memory:') {
                $this->fail('Unsupported restaurant workflow storage.', 503);
            }
        }
    }

    private function location($user, int $id)
    {
        if (!$user || !$user->id || !$user->business_id) { $this->fail('A verified business user is required.', 403); }
        $location = $this->db->table('business_locations')->where('id', $id)->where('business_id', $user->business_id)
            ->lockForUpdate()->first();
        if (!$location || (!$this->admin($user) && !$user->can('access_all_locations') && !$user->can('location.'.$id))) {
            $this->fail('This business location is not permitted.', 403);
        }
        return $location;
    }

    private function enabled($user): void
    {
        $business = $this->db->table('business')->where('id', $user->business_id)->first();
        $modules = json_decode($business->enabled_modules ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        if (!in_array('tables', $modules ?? [], true)) { $this->fail('Enable Tables in backend business modules first.', 403); }
    }

    public function config($user, int $locationId): array
    {
        $this->storage();
        $this->enabled($user);
        return $this->db->transaction(function () use ($user, $locationId) {
            $location = $this->location($user, $locationId);
            if (!$this->admin($user) && !$user->can('access_tables') && !$user->can('sell.create') && !$user->can('sell.update') &&
                !$user->can('sell.payments') && !$user->can('restaurant.kitchen') && !$user->can('restaurant.bar') &&
                !$user->can('restaurant.approve_void')) {
                $this->fail('Your backend role cannot access the restaurant workspace.', 403);
            }
            return ['tables' => $this->db->table('res_tables')->where('business_id', $user->business_id)
                    ->where('location_id', $locationId)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'allow_multiple_orders']),
                'can_create' => $this->allows($user, 'sell.create'), 'can_edit' => $this->allows($user, 'sell.update'),
                'can_pay' => $this->allows($user, 'sell.payments'),
                'can_approve' => $this->allows($user, 'restaurant.approve_void') && $this->allows($user, 'sell.update'),
                'can_kitchen' => $this->allows($user, 'restaurant.kitchen'), 'can_bar' => $this->allows($user, 'restaurant.bar'),
                'payment_methods' => $this->methods($location)];
        });
    }

    private function methods($location): array
    {
        return (new \App\Utils\TransactionUtil())->payment_types($location, false, $location->business_id);
    }

    private function bill($user, int $id, bool $own = true): array
    {
        // Header lock is shared with saved-sale edits, payments and legacy guarded writers.
        $h = $this->db->table('transactions')->where('id', $id)->where('business_id', $user->business_id)
            ->where('type', 'sell')->lockForUpdate()->first();
        if (!$h) { $this->fail('Bill not found.', 404); }
        $this->location($user, (int) $h->location_id);
        $flow = $this->db->table('restaurant_bills')->where('transaction_id', $id)
            ->where('business_id', $user->business_id)->lockForUpdate()->first();
        if (!$flow) { $this->fail('This bill has not been opened in restaurant mode.'); }
        if ($own && !$this->admin($user) && (int) $flow->waiter_id !== (int) $user->id &&
            !($user->can('sell.view') && ($user->can('sell.payments') || $user->can('sell.update'))) &&
            !($user->can('restaurant.approve_void') && $user->can('sell.update'))) {
            $this->fail('Only the assigned waiter or an authorized location cashier can access this bill.', 403);
        }
        $lines = $this->db->table('transaction_sell_lines')->where('transaction_id', $id)->orderBy('id')->lockForUpdate()->get();
        $payments = $this->db->table('transaction_payments')->where('transaction_id', $id)->orderBy('id')->lockForUpdate()->get();
        $tickets = $this->db->table('restaurant_tickets')->where('transaction_id', $id)->orderBy('id')->lockForUpdate()->get();
        $version = hash('sha256', json_encode([$h, $flow, $lines, $payments, $tickets], JSON_THROW_ON_ERROR));
        $paid = '0.0000';
        foreach ($payments as $payment) {
            if (!empty($payment->is_return) || !empty($payment->parent_id) ||
                (isset($payment->business_id) && (int) $payment->business_id !== (int) $user->business_id)) {
                $this->fail('This bill has unsupported return/advance payment records. Use the backend payment editor.');
            }
            $amount = SavedSaleSplitService::decimal($payment->amount);
            if (bccomp($amount, '0', 4) < 0) { $this->fail('Negative payment ledger amounts require investigation.'); }
            $paid = bcadd($paid, $amount, 4);
        }
        return ['header' => $h, 'flow' => $flow, 'lines' => $lines, 'payments' => $payments,
            'tickets' => $tickets, 'version' => $version, 'paid' => $paid,
            'balance' => bcsub(SavedSaleSplitService::decimal($h->final_total), $paid, 4)];
    }

    private function view(array $bill): array
    {
        $names = $this->db->table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $bill['header']->business_id)->whereIn('v.id', $bill['lines']->pluck('variation_id'))
            ->get(['v.id', 'v.name as variation_name', 'p.name'])->keyBy('id');
        $status = $this->db->table('eis_sales')->where('transaction_id', $bill['header']->id)->value('status');
        if ($status === null && $this->db->table('eis_failed_transactions')->where('transaction_id', $bill['header']->id)->exists()) { $status = 'failed'; }
        return ['id' => (int) $bill['header']->id, 'invoice' => $bill['header']->invoice_no,
            'has_been_sent' => !empty($bill['header']->is_kitchen_order) || $bill['tickets']->isNotEmpty(),
            'contact_id' => (int) $bill['header']->contact_id,
            'table_id' => (int) $bill['flow']->table_id, 'waiter_id' => (int) $bill['flow']->waiter_id,
            'state' => $bill['flow']->state, 'version' => $bill['version'],
            'total' => SavedSaleSplitService::decimal($bill['header']->final_total),
            'paid' => $bill['paid'], 'balance' => $bill['balance'], 'payment_status' => $bill['header']->payment_status,
            'lines' => $bill['lines']->map(function ($line) use ($names) {
                $product = $names->get($line->variation_id);
                $name = $product->name ?? ('Item #'.$line->variation_id);
                if (!empty($product->variation_name) && $product->variation_name !== 'DUMMY' && $product->variation_name !== $name) {
                    $name .= ' - '.$product->variation_name;
                }
                return (array) $line + ['name' => $name];
            }), 'tickets' => $bill['tickets'],
            'fiscal_status' => $status ?? 'not_submitted'];
    }

    public function board($user, int $locationId, int $page = 1): array
    {
        if ($page < 1) { $this->fail('Invalid board page.', 422); }
        $config = $this->config($user, $locationId);
        return $this->db->transaction(function () use ($user, $locationId, $config, $page) {
            $query = $this->db->table('restaurant_bills')->where('business_id', $user->business_id)->where('location_id', $locationId);
            if (!$this->admin($user) && !($user->can('sell.view') && ($user->can('sell.payments') || $user->can('sell.update')))) {
                $query->where('waiter_id', $user->id);
            }
            // Closed bills remain visible for fiscal retry and receipt retrieval.
            $total = $query->count();
            $ids = $query->orderByRaw("CASE WHEN state = 'closed' THEN 1 ELSE 0 END")
                ->orderBy('transaction_id', 'desc')->offset(($page - 1) * 100)->limit(100)->pluck('transaction_id');
            return ['config' => $config, 'bills' => $ids->map(fn ($id) => $this->view($this->bill($user, (int) $id))),
                'page' => $page, 'has_more' => $page * 100 < $total];
        });
    }

    private function quantities($input): array
    {
        if (!is_array($input) || count($input) < 1 || count($input) > 100 || !array_is_list($input)) { $this->fail('Keep 1-100 different items.', 422); }
        $items = [];
        foreach ($input as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['variation_id', 'quantity']) ||
                !is_int($item['variation_id'] ?? null) || $item['variation_id'] <= 0 ||
                !is_string($item['quantity'] ?? null) || isset($items[$item['variation_id']])) {
                $this->fail('Provide unique variation IDs and decimal-string quantities only.', 422);
            }
            $quantity = SavedSaleSplitService::decimal($item['quantity']);
            if (bccomp($quantity, '0', 4) <= 0) { $this->fail('Item quantities must be positive.', 422); }
            $items[$item['variation_id']] = $quantity;
        }
        ksort($items, SORT_NUMERIC);
        return $items;
    }

    private function operation($user, int $id, array $input, callable $work): array
    {
        $this->storage();
        $this->enabled($user);
        if (!is_string($input['operation_id'] ?? null) || !Str::isUuid($input['operation_id'])) {
            $this->fail('A UUID operation_id is required.', 422);
        }
        $hash = hash('sha256', json_encode([$id, $input], JSON_THROW_ON_ERROR));
        return $this->db->transaction(function () use ($user, $id, $input, $hash, $work) {
            // A location lock also serializes new-bill operations before a transaction ID exists.
            if ($id === 0) { $this->location($user, (int) ($input['location_id'] ?? 0)); }
            else {
                $header = $this->db->table('transactions')->where('business_id', $user->business_id)->where('id', $id)->lockForUpdate()->first();
                if (!$header) { $this->fail('Bill not found.', 404); }
                $this->location($user, (int) $header->location_id);
            }
            $operation = strtolower($input['operation_id']);
            $previous = $this->db->table('restaurant_operations')->where('business_id', $user->business_id)
                ->where('operation_id', $operation)->lockForUpdate()->first();
            if ($previous) {
                if ((int) $previous->user_id !== (int) $user->id || !hash_equals($previous->payload_hash, $hash)) {
                    $this->fail('Operation UUID belongs to another request or actor.');
                }
                if ($id > 0) { $this->bill($user, $id, !array_key_exists('ticket_id', $input)); }
                return json_decode($previous->response_json, true, 512, JSON_THROW_ON_ERROR);
            }
            $response = json_decode(json_encode($work(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $now = date('Y-m-d H:i:s');
            $this->db->table('restaurant_operations')->insert(['business_id' => $user->business_id, 'user_id' => $user->id,
                'transaction_id' => $response['id'] ?? $id, 'operation_id' => $operation, 'payload_hash' => $hash,
                'response_json' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            return $response;
        });
    }

    private function version(array $bill, array $input): void
    {
        if (!is_string($input['expected_version'] ?? null) || !hash_equals($bill['version'], $input['expected_version'])) {
            $this->fail('Bill changed. Refresh the restaurant workspace before continuing.');
        }
    }

    private function touch(int $id, ?string $state = null): void
    {
        $values = ['version' => $this->db->raw('version + 1'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($state !== null) { $values['state'] = $state; }
        $this->db->table('restaurant_bills')->where('transaction_id', $id)->update($values);
    }

    public function validateTableAssignment($user, int $locationId, int $tableId, int $exceptId = 0): void
    {
        $this->storage();
        $this->enabled($user);
        $this->location($user, $locationId);
        $table = $this->db->table('res_tables')->where('id', $tableId)->where('business_id', $user->business_id)
            ->where('location_id', $locationId)->whereNull('deleted_at')->lockForUpdate()->first();
        if (!$table) { $this->fail('Choose a table at this business location.', 422); }
        if (empty($table->allow_multiple_orders) && $this->db->table('restaurant_bills')
            ->where('business_id', $user->business_id)->where('table_id', $tableId)
            ->where('transaction_id', '!=', $exceptId)->where('state', '!=', 'closed')->exists()) {
            $this->fail('This table already has an open bill. Choose another table.');
        }
        if (empty($table->allow_multiple_orders) && $this->db->table('transactions')
            ->where('business_id', $user->business_id)->where('location_id', $locationId)
            ->where('id', '!=', $exceptId)->where('type', 'sell')->where('res_table_id', $tableId)
            ->whereIn('status', ['draft', 'final'])->where('payment_status', '!=', 'paid')->exists()) {
            $this->fail('This table has an existing unpaid bill. Choose another table.');
        }
    }

    public function create($user, array $input): array
    {
        $this->requirePermission($user, 'sell.create');
        if (array_diff(array_keys($input), ['operation_id', 'location_id', 'table_id', 'contact_id', 'items', 'is_kitchen_order']) ||
            !is_int($input['location_id'] ?? null) || !is_int($input['table_id'] ?? null) || !is_int($input['contact_id'] ?? null) ||
            (array_key_exists('is_kitchen_order', $input) && !is_bool($input['is_kitchen_order']))) {
            $this->fail('Provide table, location, customer, item quantities and an optional boolean kitchen-order flag.', 422);
        }
        $items = $this->quantities($input['items'] ?? []);
        return $this->operation($user, 0, $input, function () use ($user, $input, $items) {
            if (!empty($input['is_kitchen_order'])) {
                $modules = json_decode($this->db->table('business')->where('id', $user->business_id)->value('enabled_modules') ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                if (!in_array('kitchen', $modules, true)) { $this->fail('Enable the Kitchen module in backend business settings before sending kitchen orders.', 422); }
            }
            $locationId = (int) ($input['location_id'] ?? 0);
            $tableId = (int) ($input['table_id'] ?? 0);
            $this->validateTableAssignment($user, $locationId, $tableId);
            $contact = $this->db->table('contacts')->where('id', (int) ($input['contact_id'] ?? 0))
                ->where('business_id', $user->business_id)->whereIn('type', ['customer', 'both'])->whereNull('deleted_at')->first();
            if (!$contact || (!$this->admin($user) && !$user->can('customer.view') && !$user->can('customer.view_own') &&
                empty($contact->is_default))) { $this->fail('Choose the permitted default Walk-in customer.', 403); }
            $id = (new SavedSaleSplitService($this->db))->createRestaurantBill($user, $locationId, (int) $contact->id, $tableId, $items);
            \Modules\Connector\Support\TillAssignment::bind($user, $id, $this->connectorTillId);
            $now = date('Y-m-d H:i:s');
            $this->db->table('restaurant_bills')->insert(['transaction_id' => $id, 'business_id' => $user->business_id,
                'location_id' => $locationId, 'table_id' => $tableId, 'waiter_id' => $user->id,
                'state' => 'open', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->db->table('saved_sale_split_operations')->insert(['business_id' => $user->business_id, 'user_id' => $user->id,
                'operation_id' => (string) Str::uuid(), 'source_id' => $id, 'result_id' => $id,
                'payload_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)),
                'expected_version' => str_repeat('0', 64), 'allocation_json' => json_encode(['kind' => 'restaurant_open'], JSON_THROW_ON_ERROR),
                'response_json' => json_encode(['id' => $id], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            if (!empty($input['is_kitchen_order'])) {
                $this->db->table('transactions')->where('id', $id)->update(['is_kitchen_order' => 1]);
                foreach ($items as $variation => $quantity) {
                    $this->db->table('restaurant_tickets')->insert(['transaction_id' => $id, 'variation_id' => $variation,
                        'station' => 'kitchen', 'quantity' => $quantity, 'original_quantity' => $quantity, 'status' => 'received',
                        'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            return $this->view($this->bill($user, $id));
        });
    }

    public function preview($user, int $id, bool $unsentOnly = false): array
    {
        $this->requirePermission($user, 'sell.update');
        $this->storage();
        return $this->db->transaction(function () use ($user, $id, $unsentOnly) {
            $bill = $this->bill($user, $id);
            if ($unsentOnly) { $this->requireUnsent($bill); }
            if ($bill['flow']->state !== 'open') { $this->fail('Only open bills can be edited. Ask the cashier to reopen the bill.'); }
            $preview = (new SavedSaleSplitService($this->db))->additionsPreview($id, $user);
            $preview['restaurant_version'] = $bill['version'];
            return $preview;
        });
    }

    private function requireUnsent(array $bill): void
    {
        if (!empty($bill['header']->is_kitchen_order) || $bill['tickets']->isNotEmpty()) {
            $this->fail('Orders already sent to a preparation station cannot be edited in the desktop.');
        }
    }

    public function edit($user, int $id, array $input, bool $unsentOnly = false): array
    {
        $this->requirePermission($user, 'sell.update');
        if (array_diff(array_keys($input), ['operation_id', 'expected_version', 'restaurant_version', 'items', 'reason', 'disposition', 'is_kitchen_order', 'table_id']) ||
            (array_key_exists('table_id', $input) && (!is_int($input['table_id']) || $input['table_id'] <= 0)) ||
            (array_key_exists('is_kitchen_order', $input) && !is_bool($input['is_kitchen_order']))) {
            $this->fail('Unsupported restaurant edit fields.', 422);
        }
        $items = $this->quantities($input['items'] ?? []);
        return $this->operation($user, $id, $input, function () use ($user, $id, $input, $items, $unsentOnly) {
            $bill = $this->bill($user, $id);
            if ($unsentOnly || !empty($input['is_kitchen_order'])) { $this->requireUnsent($bill); }
            $this->version($bill, ['expected_version' => $input['restaurant_version'] ?? null]);
            if ($bill['flow']->state !== 'open' || $bill['payments']->count()) { $this->fail('Only completely unpaid open bills can be edited.'); }
            $tableChanged = isset($input['table_id']) && $input['table_id'] !== (int) $bill['flow']->table_id;
            if ($tableChanged) {
                $this->requireUnsent($bill);
                $this->validateTableAssignment($user, (int) $bill['header']->location_id, $input['table_id'], $id);
            }
            $old = [];
            foreach ($bill['lines'] as $line) { $old[$line->variation_id] = bcadd($old[$line->variation_id] ?? '0', $line->quantity, 4); }
            $dispatched = [];
            foreach ($bill['tickets'] as $ticket) {
                if ($ticket->status !== 'cancelled') {
                    $dispatched[$ticket->variation_id] = bcadd($dispatched[$ticket->variation_id] ?? '0', $ticket->quantity, 4);
                }
            }
            $reductions = [];
            foreach ($old as $variation => $quantity) {
                $target = $items[$variation] ?? '0.0000';
                if (bccomp($target, $quantity, 4) < 0 && bccomp($target, $dispatched[$variation] ?? '0', 4) < 0) {
                    $reductions[$variation] = bcsub($quantity, $target, 4);
                }
            }
            if ($reductions) {
                if (!is_string($input['reason'] ?? null)) { $this->fail('A text reason is required.', 422); }
                $reason = trim($input['reason']);
                $disposition = $input['disposition'] ?? '';
                if (strlen($reason) < 3 || strlen($reason) > 500 || !in_array($disposition, ['return_stock', 'waste'], true)) {
                    $this->fail('Dispatched reductions require a reason and return_stock or waste disposition.', 422);
                }
                $now = date('Y-m-d H:i:s');
                $approvalId = $this->db->table('restaurant_approvals')->insertGetId(['transaction_id' => $id,
                    'requested_by' => $user->id, 'expected_version' => $bill['version'],
                    'items_json' => json_encode($input, JSON_THROW_ON_ERROR), 'reason' => $reason,
                    'disposition' => $disposition, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
                return ['id' => $id, 'approval_required' => true, 'approval_id' => $approvalId];
            }
            $result = $this->applyEdit($user, $id, $input, []);
            if ($tableChanged) {
                $this->db->table('transactions')->where('id', $id)->update(['res_table_id' => $input['table_id']]);
                $this->db->table('restaurant_bills')->where('transaction_id', $id)->update(['table_id' => $input['table_id']]);
                $this->touch($id);
                $result['bill'] = (new SavedSaleSplitService($this->db))->additionsPreview($id, $user)['source'];
            }
            if (!empty($input['is_kitchen_order'])) {
                $modules = json_decode($this->db->table('business')->where('id', $user->business_id)->value('enabled_modules') ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                if (!in_array('kitchen', $modules, true)) { $this->fail('Enable the Kitchen module in backend business settings before sending kitchen orders.', 422); }
                $current = $this->bill($user, $id);
                $dispatch = $this->dispatch($user, $id, [
                    'operation_id' => (string) Str::uuid(), 'expected_version' => $current['version'],
                    'station' => 'kitchen', 'variations' => array_keys($items)]);
                $this->db->table('transactions')->where('id', $id)->update(['is_kitchen_order' => 1]);
                return $dispatch;
            }
            return $result;
        });
    }

    private function applyEdit($user, int $id, array $input, array $waste, ?int $approvalId = null): array
    {
        $before = $this->bill($user, $id);
        $lineIds = $before['lines']->pluck('id');
        $beforeMaps = $this->db->table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $lineIds)->orderBy('id')->get();
        $key = spl_object_id($this->db).':'.$id;
        self::$editing[$key] = true;
        try {
            $result = (new SavedSaleSplitService($this->db))->editItems($id, $user,
                array_intersect_key($input, array_flip(['operation_id', 'expected_version', 'items'])));
        } finally { unset(self::$editing[$key]); }
        if (bccomp($result['bill']['final_total'], '0', 4) <= 0) {
            $this->fail('Retain a positive bill total; zero-value/full-void clearing requires a separate audited workflow.', 422);
        }
        if ($waste) {
            // The editor returned removed quantities. Waste consumes them again using their
            // original allocations, avoiding artificial stock availability or changed costs.
            foreach ($waste as $variationId => $quantity) {
                $productId = $before['lines']->firstWhere('variation_id', $variationId)->product_id;
                $stock = $this->db->table('variation_location_details')->where('product_id', $productId)
                    ->where('variation_id', $variationId)->where('location_id', $before['header']->location_id)->lockForUpdate()->get();
                if (!$this->db->table('products')->where('id', $productId)->value('enable_stock')) { continue; }
                if ($stock->count() !== 1) { $this->fail('Waste requires an unambiguous stock row.'); }
                $this->db->table('variation_location_details')->where('id', $stock[0]->id)
                    ->update(['qty_available' => bcsub(SavedSaleSplitService::decimal($stock[0]->qty_available), $quantity, 4)]);
                $unrecorded = $quantity;
                foreach ($beforeMaps->reverse() as $map) {
                    $oldLine = $before['lines']->firstWhere('id', $map->sell_line_id);
                    if ((int) $oldLine->variation_id !== (int) $variationId) { continue; }
                    $remaining = $this->db->table('transaction_sell_lines_purchase_lines')->where('id', $map->id)->value('quantity') ?? '0';
                    $released = bcsub(SavedSaleSplitService::decimal($map->quantity), SavedSaleSplitService::decimal($remaining), 4);
                    if (bccomp($released, '0', 4) <= 0) { continue; }
                    if (bccomp($unrecorded, '0', 4) === 0) { break; }
                    $released = bccomp($released, $unrecorded, 4) > 0 ? $unrecorded : $released;
                    if ((int) $map->purchase_line_id === 0) {
                        $this->fail('Receive and reconcile oversold stock before approving its waste disposition.');
                    } else {
                        $purchase = $this->db->table('purchase_lines')->where('id', $map->purchase_line_id)->lockForUpdate()->first();
                        $this->db->table('purchase_lines')->where('id', $purchase->id)->update([
                            'quantity_adjusted' => bcadd(SavedSaleSplitService::decimal($purchase->quantity_adjusted ?? '0'), $released, 4)]);
                    }
                    $now = date('Y-m-d H:i:s');
                    $this->db->table('restaurant_waste')->insert(['approval_id' => $approvalId, 'transaction_id' => $id,
                        'variation_id' => $variationId, 'purchase_line_id' => (int) $map->purchase_line_id ?: null,
                        'quantity' => $released, 'created_at' => $now, 'updated_at' => $now]);
                    $unrecorded = bcsub($unrecorded, $released, 4);
                }
                if (bccomp($unrecorded, '0', 4) !== 0) { $this->fail('Waste allocations did not reconcile; no changes were saved.'); }
            }
        }
        $this->touch($id);
        return ['id' => $id, 'bill' => $result['bill'], 'approval_required' => false];
    }

    public function approvals($user, int $locationId): array
    {
        $this->requirePermission($user, 'restaurant.approve_void');
        $this->config($user, $locationId);
        return $this->db->transaction(function () use ($user, $locationId) {
            $rows = $this->db->table('restaurant_approvals as a')->join('restaurant_bills as b', 'b.transaction_id', '=', 'a.transaction_id')
                ->join('transactions as t', 't.id', '=', 'b.transaction_id')->join('res_tables as rt', 'rt.id', '=', 'b.table_id')
                ->where('b.business_id', $user->business_id)->where('b.location_id', $locationId)
                ->where('a.status', 'pending')->orderBy('a.id')->get(['a.*', 't.invoice_no', 'rt.name as table_name']);
            return $rows->map(function ($row) use ($user) {
                $bill = $this->bill($user, (int) $row->transaction_id, false);
                $desired = $this->quantities(json_decode($row->items_json, true, 512, JSON_THROW_ON_ERROR)['items']);
                $old = []; $sent = [];
                foreach ($bill['lines'] as $line) { $old[$line->variation_id] = bcadd($old[$line->variation_id] ?? '0', $line->quantity, 4); }
                foreach ($bill['tickets'] as $ticket) {
                    if ($ticket->status !== 'cancelled') { $sent[$ticket->variation_id] = bcadd($sent[$ticket->variation_id] ?? '0', $ticket->quantity, 4); }
                }
                $ids = array_unique(array_merge(array_keys($old), array_keys($desired)));
                $products = $this->db->table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
                    ->where('p.business_id', $user->business_id)->whereIn('v.id', $ids)
                    ->get(['v.id', 'v.name as variation_name', 'p.name'])->keyBy('id');
                $changes = [];
                foreach ($ids as $variation) {
                    $from = $old[$variation] ?? '0.0000'; $to = $desired[$variation] ?? '0.0000';
                    if (bccomp($from, $to, 4) === 0) { continue; }
                    $p = $products->get($variation);
                    $name = $p->name ?? ('Item #'.$variation);
                    if (!empty($p->variation_name) && $p->variation_name !== 'DUMMY' && $p->variation_name !== $name) { $name .= ' - '.$p->variation_name; }
                    $changes[] = $name.': '.$from.' -> '.$to.' (already dispatched: '.($sent[$variation] ?? '0.0000').')';
                }
                $row->change_summary = implode("\n", $changes);
                $row->is_stale = !hash_equals($row->expected_version, $bill['version']);
                return (array) $row;
            })->all();
        });
    }

    public function approve($user, int $id, array $input): array
    {
        $this->requirePermission($user, 'restaurant.approve_void');
        $this->requirePermission($user, 'sell.update');
        if (array_diff(array_keys($input), ['operation_id', 'approval_id', 'decision']) || !is_int($input['approval_id'] ?? null)) {
            $this->fail('Provide an approval ID and approve/reject decision.', 422);
        }
        return $this->operation($user, $id, $input, function () use ($user, $id, $input) {
            $bill = $this->bill($user, $id, false);
            $approval = $this->db->table('restaurant_approvals')->where('id', (int) ($input['approval_id'] ?? 0))
                ->where('transaction_id', $id)->where('status', 'pending')->lockForUpdate()->first();
            if (!$approval) { $this->fail('Pending approval not found.'); }
            if (($input['decision'] ?? '') === 'reject') {
                $this->db->table('restaurant_approvals')->where('id', $approval->id)
                    ->update(['status' => 'rejected', 'approved_by' => $user->id, 'updated_at' => date('Y-m-d H:i:s')]);
                return ['id' => $id, 'rejected' => true];
            }
            if (($input['decision'] ?? '') !== 'approve') { $this->fail('Choose approve or reject.', 422); }
            $this->version($bill, ['expected_version' => $approval->expected_version]);
            if ($bill['flow']->state !== 'open' || $bill['payments']->count()) { $this->fail('Bill is no longer editable.'); }
            $edit = json_decode($approval->items_json, true, 512, JSON_THROW_ON_ERROR);
            $target = $this->quantities($edit['items']);
            $waste = [];
            $old = [];
            foreach ($bill['lines'] as $line) { $old[$line->variation_id] = bcadd($old[$line->variation_id] ?? '0', $line->quantity, 4); }
            foreach ($old as $variation => $quantity) {
                $removed = bcsub($quantity, $target[$variation] ?? '0', 4);
                if (bccomp($removed, '0', 4) > 0) {
                    $sent = '0.0000';
                    foreach ($bill['tickets'] as $ticket) {
                        if ($ticket->variation_id == $variation && $ticket->status !== 'cancelled') { $sent = bcadd($sent, $ticket->quantity, 4); }
                    }
                    $excess = bcsub($sent, $target[$variation] ?? '0', 4);
                    $remaining = bccomp($excess, '0', 4) > 0 ? $excess : '0.0000';
                    if ($approval->disposition === 'waste' && bccomp($remaining, '0', 4) > 0) { $waste[$variation] = $remaining; }
                    foreach ($bill['tickets']->reverse() as $ticket) {
                        if ($ticket->variation_id != $variation || $ticket->status === 'cancelled' || bccomp($remaining, '0', 4) === 0) { continue; }
                        $take = bccomp($remaining, $ticket->quantity, 4) < 0 ? $remaining : $ticket->quantity;
                        $left = bcsub($ticket->quantity, $take, 4);
                        $this->db->table('restaurant_tickets')->where('id', $ticket->id)->update(
                            bccomp($left, '0', 4) === 0 ? ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]
                                : ['quantity' => $left, 'updated_at' => date('Y-m-d H:i:s')]);
                        $remaining = bcsub($remaining, $take, 4);
                    }
                }
            }
            $result = $this->applyEdit($user, $id, $edit, $waste, (int) $approval->id);
            $this->db->table('restaurant_approvals')->where('id', $approval->id)
                ->update(['status' => 'applied', 'approved_by' => $user->id, 'updated_at' => date('Y-m-d H:i:s')]);
            return $result;
        });
    }

    public function transition($user, int $id, array $input): array
    {
        if (array_diff(array_keys($input), ['operation_id', 'expected_version', 'action'])) { $this->fail('Unsupported transition fields.', 422); }
        $action = $input['action'] ?? '';
        $this->requirePermission($user, $action === 'reopen' ? 'sell.payments' : 'sell.update');
        return $this->operation($user, $id, $input, function () use ($user, $id, $input, $action) {
            $bill = $this->bill($user, $id);
            $this->version($bill, $input);
            if ($bill['header']->payment_status === 'paid' || $bill['payments']->count()) { $this->fail('Paid/part-paid bills cannot change service state.'); }
            if ($action === 'reopen' && $bill['flow']->state === 'ready') { $this->touch($id, 'open'); }
            elseif ($action === 'ready' && $bill['flow']->state === 'open') {
                if ($this->db->table('restaurant_approvals')->where('transaction_id', $id)->where('status', 'pending')->exists()) {
                    $this->fail('A manager must approve or reject pending reductions before cashier handover.');
                }
                $this->assertDispatched($bill);
                $this->touch($id, 'ready');
            } else { $this->fail('Invalid bill state transition.'); }
            return $this->view($this->bill($user, $id));
        });
    }

    private function assertDispatched(array $bill): void
    {
        $sent = [];
        foreach ($bill['tickets'] as $ticket) {
            if ($ticket->status !== 'cancelled') { $sent[$ticket->variation_id] = bcadd($sent[$ticket->variation_id] ?? '0', $ticket->quantity, 4); }
        }
        $quantities = [];
        foreach ($bill['lines'] as $line) { $quantities[$line->variation_id] = bcadd($quantities[$line->variation_id] ?? '0', $line->quantity, 4); }
        foreach ($quantities as $variation => $quantity) {
            if (bccomp($quantity, $sent[$variation] ?? '0', 4) !== 0) { $this->fail('Dispatch all item quantities before handing the bill to the cashier.'); }
        }
    }

    public function dispatch($user, int $id, array $input): array
    {
        $this->requirePermission($user, 'sell.update');
        if (array_diff(array_keys($input), ['operation_id', 'expected_version', 'station', 'variations'])) { $this->fail('Unsupported dispatch fields.', 422); }
        return $this->operation($user, $id, $input, function () use ($user, $id, $input) {
            $bill = $this->bill($user, $id);
            $this->version($bill, $input);
            if ($bill['flow']->state !== 'open' || $bill['payments']->count()) { $this->fail('Only open unpaid bills accept a new dispatch.'); }
            $station = $input['station'] ?? '';
            if (!in_array($station, ['kitchen', 'bar'], true) || !is_array($input['variations'] ?? null) ||
                !array_is_list($input['variations']) || !$input['variations'] || count($input['variations']) > 100) {
                $this->fail('Select item variations and kitchen or bar.', 422);
            }
            $sent = [];
            foreach ($bill['tickets'] as $ticket) {
                if ($ticket->status !== 'cancelled') { $sent[$ticket->variation_id] = bcadd($sent[$ticket->variation_id] ?? '0', $ticket->quantity, 4); }
            }
            $quantities = [];
            foreach ($bill['lines'] as $line) { $quantities[$line->variation_id] = bcadd($quantities[$line->variation_id] ?? '0', $line->quantity, 4); }
            $now = date('Y-m-d H:i:s');
            $dispatched = [];
            foreach (array_unique($input['variations']) as $variation) {
                if (!is_int($variation) || !isset($quantities[$variation])) { $this->fail('Unknown bill variation.', 422); }
                $delta = bcsub($quantities[$variation], $sent[$variation] ?? '0', 4);
                if (bccomp($delta, '0', 4) <= 0) { continue; }
                $dispatched[] = $this->db->table('restaurant_tickets')->insertGetId(['transaction_id' => $id, 'variation_id' => $variation,
                    'station' => $station, 'quantity' => $delta, 'original_quantity' => $delta, 'status' => 'received', 'created_by' => $user->id,
                    'created_at' => $now, 'updated_at' => $now]);
            }
            if (!$dispatched) { $this->fail('No new quantities to dispatch. Previous rounds were not resent.'); }
            $this->touch($id);
            $result = $this->view($this->bill($user, $id));
            $result['dispatch_tickets'] = $this->db->table('restaurant_tickets')->whereIn('id', $dispatched)->orderBy('id')->get();
            return $result;
        });
    }

    public function queue($user, int $locationId, string $station): array
    {
        if (!in_array($station, ['kitchen', 'bar'], true)) { $this->fail('Unknown preparation station.', 422); }
        $this->requirePermission($user, 'restaurant.'.$station);
        $this->config($user, $locationId);
        return $this->db->table('restaurant_tickets as q')->join('restaurant_bills as b', 'b.transaction_id', '=', 'q.transaction_id')
            ->join('transactions as t', 't.id', '=', 'b.transaction_id')->join('variations as v', 'v.id', '=', 'q.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')->join('res_tables as rt', 'rt.id', '=', 'b.table_id')
            ->where('b.business_id', $user->business_id)->where('p.business_id', $user->business_id)->where('b.location_id', $locationId)
            ->where('q.station', $station)->whereIn('q.status', ['received', 'cooked'])->orderBy('q.id')
            ->get(['q.*', 't.invoice_no', 'rt.name as table_name', 'p.name as product_name', 'v.name as variation_name'])
            ->map(function ($r) {
                if (!empty($r->variation_name) && $r->variation_name !== 'DUMMY' && $r->variation_name !== $r->product_name) {
                    $r->product_name .= ' - '.$r->variation_name;
                }

                return (array) $r;
            })->all();
    }

    public function kitchenOrders($user): array
    {
        $this->requirePermission($user, 'restaurant.kitchen');
        $this->storage();
        $this->enabled($user);
        $locations = $this->db->table('business_locations')->where('business_id', $user->business_id)->orderBy('id')->pluck('id');
        $tickets = [];
        foreach ($locations as $location) {
            if (!$this->admin($user) && !$user->can('access_all_locations') && !$user->can('location.'.$location)) { continue; }
            foreach ($this->queue($user, (int) $location, 'kitchen') as $ticket) {
                if ($ticket['status'] === 'received') { $tickets[] = $ticket; }
            }
        }
        return $tickets;
    }

    public function ticket($user, int $id, array $input): array
    {
        if (array_diff(array_keys($input), ['operation_id', 'station', 'ticket_id', 'status']) || !is_int($input['ticket_id'] ?? null)) {
            $this->fail('Provide a ticket ID, station and preparation status.', 422);
        }
        $station = $input['station'] ?? '';
        if (!in_array($station, ['kitchen', 'bar'], true)) { $this->fail('Unknown preparation station.', 422); }
        $this->requirePermission($user, 'restaurant.'.$station);
        return $this->operation($user, $id, $input, function () use ($user, $id, $input, $station) {
            $this->bill($user, $id, false);
            $ticket = $this->db->table('restaurant_tickets')->where('id', (int) ($input['ticket_id'] ?? 0))
                ->where('transaction_id', $id)->where('station', $station)->lockForUpdate()->first();
            $next = $input['status'] ?? '';
            if (!$ticket || !(($ticket->status === 'received' && $next === 'cooked') || ($ticket->status === 'cooked' && $next === 'served'))) {
                $this->fail('Ticket changed. Refresh; only received -> cooked -> served is allowed.');
            }
            $this->db->table('restaurant_tickets')->where('id', $ticket->id)->update([
                'status' => $next, 'completed_by' => $user->id, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->touch($id);
            return ['id' => $id, 'ticket_id' => (int) $ticket->id, 'status' => $next];
        });
    }

    public function backendPayment($user, int $id, callable $work): array
    {
        if (!$this->db->getSchemaBuilder()->hasTable('restaurant_bills') ||
            !$this->db->table('restaurant_bills')->where('transaction_id', $id)->exists()) {
            return $work();
        }
        if ($this->db->transactionLevel() < 1) { $this->fail('Backend clearing requires a locked payment transaction.'); }
        $this->requirePermission($user, 'sell.payments');
        $before = $this->bill($user, $id, false);
        if ($before['header']->status !== 'final') { $this->fail('Only final bills accept backend payments.'); }
        if ($before['header']->payment_status !== 'paid' &&
            ($this->db->table('eis_sales')->where('transaction_id', $id)->exists() ||
                $this->db->table('eis_failed_transactions')->where('transaction_id', $id)->exists())) {
            $this->fail('Existing fiscal tracking prevents additional payments. Investigate in the backend.');
        }
        $key = spl_object_id($this->db).':'.$id;
        if (isset(self::$clearing[$key])) { $this->fail('Nested restaurant clearing is not allowed.'); }
        self::$clearing[$key] = true;
        try { $result = $work(); }
        finally { unset(self::$clearing[$key]); }
        $after = $this->bill($user, $id, false);
        if (bccomp($after['paid'], $before['paid'], 4) < 0 ||
            bccomp($after['balance'], '0', 4) < 0 || $after['lines'] != $before['lines'] ||
            $after['tickets'] != $before['tickets']) {
            $this->fail('Backend payment must preserve bill items and preparation tickets and cannot exceed the outstanding balance.', 422);
        }
        if (bccomp($after['paid'], $before['paid'], 4) > 0) {
            $paid = bccomp($after['balance'], '0', 4) === 0;
            $status = $paid ? 'paid' : 'partial';
            if ($after['header']->payment_status !== $status) {
                $this->fail('Payment status does not reconcile with the saved payment ledger.');
            }
            $this->touch($id, $paid ? 'closed' : 'partial');
        }
        return $result;
    }

    public function pay($user, int $id, array $input): array
    {
        $this->requirePermission($user, 'sell.payments');
        if (array_diff(array_keys($input), ['operation_id', 'expected_version', 'payments']) ||
            !is_array($input['payments'] ?? null) || !array_is_list($input['payments']) ||
            count($input['payments']) < 1 || count($input['payments']) > 20) {
            $this->fail('Provide 1-20 payment tenders, a version and operation UUID.', 422);
        }
        return $this->operation($user, $id, $input, function () use ($user, $id, $input) {
            $bill = $this->bill($user, $id);
            $this->version($bill, $input);
            if ($bill['header']->status !== 'final' || !in_array($bill['flow']->state, ['ready', 'partial'], true) ||
                bccomp($bill['balance'], '0', 4) <= 0) { $this->fail('Only final ready/part-paid bills accept cashier payments.'); }
            if ($this->db->table('eis_sales')->where('transaction_id', $id)->exists() ||
                $this->db->table('eis_failed_transactions')->where('transaction_id', $id)->exists()) {
                $this->fail('Existing fiscal tracking prevents additional payments. Investigate in the backend.');
            }
            $location = $this->location($user, (int) $bill['header']->location_id);
            \Modules\Connector\Support\TillAssignment::bind($user, $id, $this->connectorTillId);
            $methods = $this->methods($location);
            $accounts = json_decode($location->default_payment_accounts ?? '{}', true, 512, JSON_THROW_ON_ERROR);
            $total = '0.0000';
            $payments = [];
            foreach ($input['payments'] as $payment) {
                if (!is_array($payment) || array_diff(array_keys($payment), ['method', 'amount', 'reference']) ||
                    !is_string($payment['method'] ?? null) || !array_key_exists($payment['method'], $methods) ||
                    !is_string($payment['amount'] ?? null) || !is_string($payment['reference'] ?? '') ||
                    strlen($payment['reference'] ?? '') > 150) { $this->fail('Use a location-enabled payment method and decimal-string amount.', 422); }
                $amount = SavedSaleSplitService::decimal($payment['amount']);
                if (bccomp($amount, '0', 4) <= 0) { $this->fail('Payments must be positive.', 422); }
                $total = bcadd($total, $amount, 4);
                $accountId = $accounts[$payment['method']]['account'] ?? null;
                if ($accountId && !(new \App\Utils\ModuleUtil())->isModuleEnabled('account', $user->business_id)) {
                    $this->fail('A payment account is configured but the backend account module is disabled.');
                }
                if ($accountId && !\App\Account::where('business_id', $user->business_id)->where('id', $accountId)
                    ->where('is_closed', 0)->whereNull('deleted_at')->exists()) { $this->fail('Configured payment account is unavailable.'); }
                $tender = ['amount' => $amount, 'method' => $payment['method'], 'account_id' => $accountId, 'note' => $payment['reference'] ?? ''];
                if ($payment['method'] === 'card') { $tender['card_transaction_number'] = $payment['reference'] ?? ''; }
                if ($payment['method'] === 'cheque') { $tender['cheque_number'] = $payment['reference'] ?? ''; }
                $payments[] = $tender;
            }
            if (bccomp($total, $bill['balance'], 4) > 0) { $this->fail('Payment exceeds the outstanding balance. Enter the net tender; give change separately.', 422); }
            $paid = bccomp($total, $bill['balance'], 4) === 0;
            if ($paid) { $this->checkFiscalLimits($bill); }
            $paymentId = null;
            foreach ($payments as $payment) {
                $key = spl_object_id($this->db).':'.$id;
                self::$clearing[$key] = true;
                try {
                    $result = SavedSaleWriteGuard::splitPayment($this->db, $id, $user->business_id, $user->id,
                        (string) Str::uuid(), hash('sha256', json_encode($payment, JSON_THROW_ON_ERROR)),
                        fn () => $this->recordPayment($user, $id, $bill, $payment));
                } finally { unset(self::$clearing[$key]); }
                $paymentId = $result['payment_id'];
            }
            $status = $paid ? 'paid' : 'partial';
            $this->db->table('transactions')->where('id', $id)->update(['payment_status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
            $after = $this->bill($user, $id);
            if (bccomp($after['paid'], bcadd($bill['paid'], $total, 4), 4) !== 0 || $after['lines'] != $bill['lines']) {
                $this->fail('Payment ledger did not preserve exact amounts and bill items; no payment was saved.');
            }
            $this->touch($id, $paid ? 'closed' : 'partial');
            if ($paid) { $this->scheduleFiscal($id); }
            return ['id' => $id, 'payment_id' => $paymentId, 'payment_status' => $status,
                'balance' => bcsub($bill['balance'], $total, 4), 'fiscal_status' => $paid ? 'pending_submission' : 'not_submitted'];
        });
    }

    protected function recordPayment($user, int $id, array $bill, array $payment): array
    {
        $util = new \App\Utils\TransactionUtil();
        $ref = $util->setAndGetReferenceCount('sell_payment', $user->business_id);
        $data = $payment + ['transaction_id' => $id, 'business_id' => $user->business_id, 'created_by' => $user->id,
            'payment_for' => $bill['header']->contact_id, 'paid_on' => date('Y-m-d H:i:s'),
            'payment_ref_no' => $util->generateReferenceNumber('sell_payment', $ref, $user->business_id)];
        $model = \App\TransactionPayment::create($data);
        event(new \App\Events\TransactionPaymentAdded($model, $data + ['transaction_type' => 'sell']));
        if ($payment['account_id']) {
            $posting = $this->db->table('account_transactions')->where('transaction_payment_id', $model->id)->get();
            if ($posting->count() !== 1 || (int) $posting[0]->account_id !== (int) $payment['account_id'] ||
                bccomp(SavedSaleSplitService::decimal($posting[0]->amount), $payment['amount'], 4) !== 0 || $posting[0]->type !== 'credit') {
                $this->fail('The configured account did not receive the exact payment posting; no payment was saved.');
            }
        }
        $registers = $this->db->table('cash_registers')->where('user_id', $user->id)->where('business_id', $user->business_id)
            ->where('status', 'open')->orderBy('id')->lockForUpdate()->get();
        if ($registers->count() > 1 || ($registers->count() && (int) $registers[0]->location_id !== (int) $bill['header']->location_id)) {
            $this->fail('Close the conflicting cashier register or select its correct location before collecting payment.');
        }
        if ($registers->count()) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('cash_register_transactions')->insert(['cash_register_id' => $registers[0]->id,
                'amount' => $payment['amount'], 'pay_method' => $payment['method'], 'type' => 'credit',
                'transaction_type' => 'sell', 'transaction_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
        }
        return ['success' => true, 'payment_id' => $model->id];
    }

    protected function checkFiscalLimits(array $bill): void
    {
        $limit = \Modules\EIS\Models\EisSale::checkOfflineLimits($bill['header']->business_id,
            $bill['header']->final_total, (int) $bill['header']->location_id,
            $this->connectorTillId ?? ($bill['header']->connector_till_id ?? null));
        if (!$limit['success'] || ($limit['code'] ?? null) === 'CHECK_ERROR') { $this->fail($limit['message']); }
    }

    protected function scheduleFiscal(int $id): void
    {
        $this->db->afterCommit(function () use ($id) {
            try { \Modules\EIS\Services\Sales\SaleSubmissionRule::initiate(\App\Transaction::findOrFail($id)); }
            catch (\Throwable $e) { \Log::error('Restaurant paid bill fiscal submission failed; retry required.', [
                'transaction_id' => $id, 'message' => $e->getMessage()]); }
        });
    }

    public function retryFiscal($user, int $id): array
    {
        $this->requirePermission($user, 'sell.payments');
        $this->storage();
        return $this->db->transaction(function () use ($user, $id) {
            $bill = $this->bill($user, $id);
            if ($bill['flow']->state !== 'closed' || $bill['header']->payment_status !== 'paid' ||
                bccomp($bill['balance'], '0', 4) !== 0) { $this->fail('Only fully paid bills can retry fiscal submission.'); }
            $status = $this->view($bill)['fiscal_status'];
            if ($status !== 'submitted') { $this->scheduleFiscal($id); }
            return ['id' => $id, 'fiscal_status' => $status === 'submitted' ? 'submitted' : 'pending_submission'];
        });
    }
}
