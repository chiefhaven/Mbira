<?php

namespace App\Services\Sales;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/** Locked historical partitions. Checkout is a distinct, restricted cash contract;
 * no client-supplied product/header data ever reaches the legacy update path.
 */
class SavedSaleSplitService
{
    private $db;
    private const OPERATIONS = 'saved_sale_split_operations';
    private const TABLES = ['transactions', 'transaction_sell_lines',
        'transaction_sell_lines_purchase_lines', 'purchase_lines', 'transaction_payments',
        'account_transactions', 'cash_register_transactions', 'sell_line_warranties',
        'business', 'business_locations', 'users', 'contacts', 'products', 'variations', 'units', 'tax_rates', self::OPERATIONS];
    private const HEADER_COPY = ['business_id', 'location_id', 'type', 'status', 'payment_status',
        'contact_id', 'customer_group_id', 'transaction_date', 'created_by', 'is_suspend',
        'is_direct_sale', 'is_created_from_api', 'exchange_rate', 'additional_notes', 'staff_note',
        'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4',
        'selling_price_group_id', 'pay_term_number', 'pay_term_type', 'source',
        'res_table_id', 'res_waiter_id', 'is_kitchen_order', 'res_order_status', 'connector_till_id'];
    private const LINE_COPY = ['product_id', 'variation_id', 'quantity', 'unit_price_before_discount',
        'unit_price', 'unit_price_inc_tax', 'item_tax', 'tax_id', 'sell_line_note', 'children_type'];
    // Inactive configuration defaults do not represent a linked sale.
    private const INERT_HEADER = ['discount_type', 'packing_charge_type', 'mfg_production_cost_type',
        'recur_interval', 'recur_interval_type', 'recur_repetitions', 'subscription_repeat_on'];

    public function __construct(Connection $db, private ?int $connectorTillId = null)
    {
        $this->db = $db;
    }

    private function fail($code, $message, $status = 409)
    {
        throw new SavedSplitException($code, $message, $status);
    }

    public static function decimal($value)
    {
        // DB decimal values and API quantities must never pass through binary floats.
        if ((!is_string($value) && !is_int($value)) ||
            !preg_match('/^-?\d{1,18}(?:\.\d{1,4})?$/D', (string) $value)) {
            throw new SavedSplitException('invalid_decimal', 'Use decimal strings with at most four decimal places.', 422);
        }
        return bcadd((string) $value, '0', 4);
    }

    private static function zero($value)
    {
        return $value === null || $value === '' || $value === false ||
            (is_scalar($value) && preg_match('/^-?0+(?:\.0+)?$/D', (string) $value));
    }

    private static function round4($value)
    {
        return bcadd($value, '0.00005', 4); // Supported values are nonnegative.
    }

    private function storage(array $extra = [])
    {
        if (!extension_loaded('bcmath')) {
            $this->fail('split_storage_unavailable', 'Saved splitting requires BCMath.', 503);
        }

        $requiredTables = array_merge(self::TABLES, $extra, ['eis_sales', 'eis_failed_transactions']);
        $innoDbTables = array_merge(self::TABLES, $extra);

        $schema = $this->db->getSchemaBuilder();
        foreach ($requiredTables as $table) {
            if (!$schema->hasTable($table)) {
                $this->fail('split_storage_unavailable', 'Missing required table: '.$table.'. Deploy the split migration; no automatic migration is performed.', 503);
            }
        }
        if ($this->db->getDriverName() === 'sqlite' && $this->db->getDatabaseName() === ':memory:') {
            return; // Standalone synthetic tests only; never file-backed SQLite.
        }
        if ($this->db->getDriverName() !== 'mysql') {
            $this->fail('split_storage_unavailable', 'Saved splitting requires verified MySQL InnoDB tables.', 503);
        }

        // EIS sales tables are read-only for split preview and may be MyISAM in legacy deployments.
        // Only the split operational tables require InnoDB transactional guarantees.
        $engines = $this->db->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->db->getDatabaseName())
            ->whereIn('TABLE_NAME', $innoDbTables)
            ->pluck('ENGINE', 'TABLE_NAME');
        foreach ($innoDbTables as $table) {
            if (strtoupper($engines[$table] ?? '') !== 'INNODB') {
                $this->fail('split_storage_unavailable', 'Non-transactional or missing table: '.$table.'. Splitting is disabled.', 503);
            }
        }
    }

    public function stockReconciliationStorage()
    {
        $this->storage(['variation_location_details']);
    }

    private function actor($user, bool $editing = false)
    {
        if (!$user || !$user->id || !$user->business_id) {
            $this->fail('split_forbidden', 'A verified business user is required.', 403);
        }
        $admin = $user->hasRole('Admin#'.$user->business_id);
        if ($editing) {
            if (!$admin && !$user->can('superadmin') && !$user->can('sell.update')) {
                $this->fail('split_forbidden', 'Backend sell.update permission is required to edit bill items.', 403);
            }
            return $admin;
        }
        if (!$admin && (!$user->can('sell.create') || !$user->can('sell.update'))) {
            $this->fail('split_forbidden', 'Both sell.create and sell.update are required.', 403);
        }
        return $admin;
    }

    private function graph($id, $user, $admin, bool $allowEditRestaurantFields = false)
    {
        $header = $this->db->table('transactions')->where('id', $id)
            ->where('business_id', $user->business_id)->where('type', 'sell')->lockForUpdate()->first();
        if (!$header) {
            $this->fail('split_not_found', 'Sale not found.', 404);
        }
        $h = (array) $header;
        $restaurantManaged = $this->db->getSchemaBuilder()->hasTable('restaurant_bills') &&
            $this->db->table('restaurant_bills')->where('transaction_id', $id)->where('business_id', $user->business_id)->exists();
        if ($restaurantManaged && $user->can('superadmin')) { $admin = true; }
        if (!$restaurantManaged) {
            foreach (['res_table_id', 'res_waiter_id', 'is_kitchen_order', 'res_order_status'] as $field) {
                if ($allowEditRestaurantFields && in_array($field, ['is_kitchen_order', 'res_table_id', 'res_waiter_id'], true)) { continue; }
                if (!self::zero($h[$field] ?? null)) {
                    $this->fail('unsupported_restaurant_history', 'Legacy restaurant preparation history must be edited through its backend workflow, not the saved retail editor.');
                }
            }
        }
        if (!$admin && (string) $h['created_by'] !== (string) $user->id &&
            !($restaurantManaged &&
                ((string) ($h['res_waiter_id'] ?? '') === (string) $user->id ||
                    ($user->can('sell.update') && ($user->can('sell.view') ||
                        ($user->can('restaurant.approve_void') && RestaurantBillService::isEditing($this->db, $id))))))) {
            $this->fail('split_forbidden', 'Only the creator or a business admin may modify this saved sale.', 403);
        }
        if (!$this->db->table('users')->where('id', $h['created_by'])->where('business_id', $user->business_id)->exists()) {
            $this->fail('split_forbidden', 'Sale creator does not belong to this tenant.', 403);
        }
        if (!$this->db->table('business_locations')->where('id', $h['location_id'])
            ->where('business_id', $user->business_id)->exists() ||
            (!$admin && !$user->can('access_all_locations') && !$user->can('location.'.$h['location_id']))) {
            $this->fail('split_forbidden', 'Sale location is not permitted.', 403);
        }
        return $this->loadGraph($h);
    }

    /** Internal stock reconciliation reads the same locked graph, without granting
     * sale editing/checkout authority or relaxing its unpaid/fiscal eligibility.
     */
    public function lockedStockGraph($id, $businessId)
    {
        if ($this->db->transactionLevel() < 1) {
            $this->fail('stock_reconciliation_invalid', 'Stock reconciliation requires a transaction.');
        }
        $header = $this->db->table('transactions')->where('id', $id)->where('business_id', $businessId)
            ->where('type', 'sell')->where('status', 'final')->lockForUpdate()->first();
        if (!$header) {
            $this->fail('stock_reconciliation_invalid', 'Stock reconciliation requires an existing tenant final sale.');
        }
        return $this->loadGraph((array) $header);
    }

    private function loadGraph(array $h)
    {
        $id = $h['id'];
        $lines = $this->db->table('transaction_sell_lines')->where('transaction_id', $id)
            ->orderBy('id')->lockForUpdate()->get()->map(function ($r) { return (array) $r; })->all();
        $lineIds = array_column($lines, 'id');
        $mappings = $this->db->table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $lineIds)
            ->orderBy('id')->lockForUpdate()->get()->map(function ($r) { return (array) $r; })->all();
        $purchaseIds = array_values(array_unique(array_filter(array_column($mappings, 'purchase_line_id'))));
        $purchases = $this->db->table('purchase_lines')->whereIn('id', $purchaseIds)->orderBy('id')
            ->lockForUpdate()->get()->map(function ($r) { return (array) $r; })->all();
        $payments = $this->db->table('transaction_payments')->where('transaction_id', $id)
            ->orderBy('id')->lockForUpdate()->get()->map(function ($r) { return (array) $r; })->all();
        $related = [];
        foreach (['account_transactions', 'cash_register_transactions', 'eis_sales', 'eis_failed_transactions'] as $table) {
            $q = $this->db->table($table)->where('transaction_id', $id)->orderBy('id');
            if ($table === 'account_transactions') {
                $q->orWhere('transfer_transaction_id', $id);
            }
            // EIS may be MyISAM. Read only; never dispatch, mutate or claim rollback.
            $related[$table] = $q->get()->map(function ($r) { return (array) $r; })->all();
        }
        $related['warranties'] = $this->db->table('sell_line_warranties')->whereIn('sell_line_id', $lineIds)
            ->orderBy('sell_line_id')->orderBy('warranty_id')->get()->map(function ($r) { return (array) $r; })->all();
        $related['links'] = $this->db->table('transactions')->where(function ($q) use ($id) {
            $q->where('return_parent_id', $id)->orWhere('transfer_parent_id', $id)->orWhere('recur_parent_id', $id)
                ->orWhere('mfg_parent_production_purchase_id', $id);
        })->orderBy('id')->lockForUpdate()->get()->map(function ($r) { return (array) $r; })->all();
        $related['external_children'] = $this->db->table('transaction_sell_lines')->whereIn('parent_sell_line_id', $lineIds)
            ->orderBy('id')->lockForUpdate()->get()->map(function ($r) { return (array) $r; })->all();
        return ['header' => $h, 'lines' => $lines, 'payments' => $payments,
            'mappings' => $mappings, 'purchases' => $purchases, 'related' => $related];
    }

    private static function fingerprint(array $graph)
    {
        return hash('sha256', json_encode($graph, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function total(array $lines)
    {
        $sum = '0.00000000';
        foreach ($lines as $line) {
            $sum = bcadd($sum, bcmul(self::decimal($line['quantity']), self::decimal($line['unit_price_inc_tax']), 8), 8);
        }
        return self::round4($sum);
    }

    private function eligible(array $g)
    {
        $h = $g['header'];
        if (!in_array($h['status'], ['draft', 'final'], true)) {
            $this->fail('unsupported_sale_status', 'Only ordinary unpaid drafts and final sales are supported.');
        }
        if (!in_array($h['payment_status'], [null, 'due'], true) || $g['payments']) {
            $this->fail('sale_not_completely_unpaid', 'Any payment row or paid/partial state prevents splitting.');
        }
        if ($h['status'] === 'draft' && ($g['mappings'] || $g['purchases'])) {
            $this->fail('draft_has_purchase_allocations', 'A draft with purchase mappings cannot be split.');
        }
        foreach ($g['related'] as $name => $rows) {
            if ($rows) {
                $this->fail('unsupported_sale_graph', 'Unsupported saved sale relationship: '.$name.'.');
            }
        }
        // Desktop origin is provenance, not a linked external order. Preserve it
        // on both bills; unknown integration sources must still fail closed.
        if (!self::zero($h['source'] ?? null) && $h['source'] !== 'desktop_pos') {
            $this->fail('unsupported_header', 'Unsupported nonempty header field: source.');
        }
        $allowed = array_merge(self::HEADER_COPY, self::INERT_HEADER,
            ['id', 'invoice_no', 'invoice_token', 'created_at', 'updated_at', 'total_before_tax', 'final_total']);
        // Explicit clone allowlist plus fail-closed unknown nonzero metadata. Never
        // duplicate complex links, header tax/discount/charges, rewards or module state.
        foreach ($h as $key => $value) {
            if (!in_array($key, $allowed, true) && !self::zero($value)) {
                $this->fail('unsupported_header', 'Unsupported nonempty header field: '.$key.'.');
            }
        }
        if (bccomp(self::decimal($h['exchange_rate']), '1', 4) !== 0 || !$g['lines']) {
            $this->fail('unsupported_header', 'A base-currency sale with saved lines is required.');
        }
        $business = $this->db->table('business')->where('id', $h['business_id'])->first();
        if (!$business) {
            $this->fail('unsupported_header', 'Business configuration is missing.');
        }
        $editDays = $business->transaction_edit_days ?? null;
        if ($editDays !== null && (int) $editDays >= 0 &&
            strtotime($h['transaction_date']) < strtotime('-'.(int) $editDays.' days', strtotime(date('Y-m-d')))) {
            $this->fail('sale_edit_expired', 'Sale is outside the configured edit window.');
        }
        if (!$this->db->table('contacts')->where('id', $h['contact_id'])->where('business_id', $h['business_id'])->whereNull('deleted_at')
            ->whereIn('type', ['customer', 'both'])->exists()) {
            $this->fail('unsupported_sale_graph', 'Customer does not belong to this tenant.');
        }
        foreach (['customer_group_id' => 'customer_groups', 'selling_price_group_id' => 'selling_price_groups'] as $field => $table) {
            if (!self::zero($h[$field] ?? null) &&
                (!$this->db->getSchemaBuilder()->hasTable($table) || !$this->db->table($table)->where('id', $h[$field])
                    ->where('business_id', $h['business_id'])->exists())) {
                $this->fail('unsupported_sale_graph', 'Saved pricing/customer group does not belong to this tenant.');
            }
        }
        foreach ($g['lines'] as $line) {
            if (!self::zero($line['children_type'])) {
                $this->fail('unsupported_line', 'Combo/modifier child types are not supported.');
            }
            foreach ($line as $key => $value) {
                if (!in_array($key, array_merge(self::LINE_COPY, ['id', 'transaction_id', 'created_at', 'updated_at', 'line_discount_type']), true)
                    && !self::zero($value)) {
                    $this->fail('unsupported_line', 'Unsupported nonempty line field: '.$key.'.');
                }
            }
            $q = self::decimal($line['quantity']);
            $price = self::decimal($line['unit_price']);
            $tax = self::decimal($line['item_tax']);
            $gross = self::decimal($line['unit_price_inc_tax']);
            if (bccomp($q, '0', 4) <= 0 || bccomp($price, '0', 4) < 0 || bccomp($tax, '0', 4) < 0 ||
                bccomp(bcadd($price, $tax, 4), $gross, 4) !== 0 ||
                bccomp(self::decimal($line['unit_price_before_discount']), $price, 4) !== 0) {
                $this->fail('unexplained_line_pricing', 'Saved line pricing cannot be reconciled without a discount or correction.');
            }
            $product = $this->db->table('products')->where('id', $line['product_id'])->where('business_id', $h['business_id'])->whereNull('deleted_at')->first();
            if (!$product || !in_array($product->type, ['single', 'variable'], true) ||
                !$this->db->table('variations')->where('id', $line['variation_id'])->where('product_id', $line['product_id'])->exists()) {
                $this->fail('unsupported_sale_graph', 'Product/variation tenant or ordinary product type is invalid.');
            }
            $unit = $this->db->table('units')->where('id', $product->unit_id)->where('business_id', $h['business_id'])->first();
            if (!$unit || (!$unit->allow_decimal && bccomp($q, bcadd($q, '0', 0), 4) !== 0)) {
                $this->fail('invalid_unit_quantity', 'Saved quantity is invalid for its base unit.');
            }
            if (!self::zero($line['tax_id']) && !$this->db->table('tax_rates')->where('id', $line['tax_id'])
                ->where('business_id', $h['business_id'])->exists()) {
                $this->fail('unsupported_sale_graph', 'Saved line tax belongs to another tenant.');
            }
        }
        $total = $this->total($g['lines']);
        if (bccomp($total, self::decimal($h['total_before_tax']), 4) !== 0 ||
            bccomp($total, self::decimal($h['final_total']), 4) !== 0) {
            $this->fail('unexplained_totals', 'Saved totals do not equal historical tax-inclusive line totals.');
        }
        if ($h['status'] === 'final') {
            $this->validateMappings($g);
        }
    }

    public function validateMappings(array $g, $allowSentinel = null)
    {
        if ($allowSentinel === null) {
            $settings = $this->db->table('business')->where('id', $g['header']['business_id'])->lockForUpdate()->value('pos_settings');
            $settings = empty($settings) ? [] : json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
            $allowSentinel = is_array($settings) && !empty($settings['allow_overselling']);
        }
        $consumed = [];
        foreach ($g['mappings'] as $map) {
            $key = $map['purchase_line_id'];
            if ((!is_int($key) && !is_string($key)) || !preg_match('/^(?:0|[1-9]\d*)$/D', (string) $key)) {
                $this->fail('unsupported_purchase_mapping', 'An explicit numeric purchase reference is required; only 0 denotes overselling.');
            }
            $consumed[$key] = bcadd($consumed[$key] ?? '0.0000', self::decimal($map['quantity']), 4);
        }
        foreach ($g['purchases'] as $purchase) {
            $capacity = self::decimal($purchase['quantity']);
            $used = '0.0000';
            foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) {
                $value = self::decimal($purchase[$field] ?? '0');
                if (bccomp($value, '0', 4) < 0) {
                    $this->fail('unsupported_purchase_mapping', 'Negative recorded purchase consumption is invalid.');
                }
                $used = bcadd($used, $value, 4);
            }
            if (bccomp($capacity, '0', 4) <= 0 || bccomp($used, $capacity, 4) > 0) {
                $this->fail('unsupported_purchase_mapping', 'Real purchase consumption exceeds positive received supply.');
            }
            if (bccomp(self::decimal($purchase['quantity_sold']), $consumed[$purchase['id']] ?? '0.0000', 4) < 0) {
                $this->fail('unsupported_purchase_mapping', 'Aggregate saved allocations exceed recorded purchase consumption.');
            }
        }
        foreach ($g['lines'] as $line) {
            $product = $this->db->table('products')->where('id', $line['product_id'])
                ->where('business_id', $g['header']['business_id'])->whereNull('deleted_at')->first();
            if (!$product || !$this->db->table('variations')->where('id', $line['variation_id'])
                ->where('product_id', $line['product_id'])->exists() || bccomp(self::decimal($line['quantity']), '0', 4) <= 0) {
                $this->fail('unsupported_purchase_mapping', 'Allocation product/variation or positive saved quantity is invalid.');
            }
            $stock = $product->enable_stock;
            $sum = '0.0000';
            foreach ($g['mappings'] as $map) {
                if ($map['sell_line_id'] != $line['id']) { continue; }
                foreach ($map as $field => $value) {
                    if (!in_array($field, ['id', 'sell_line_id', 'purchase_line_id', 'quantity', 'created_at', 'updated_at'], true)
                        && !self::zero($value)) {
                        $this->fail('unsupported_purchase_mapping', 'Returned/adjustment or unknown purchase mapping state is unsupported.');
                    }
                }
                $quantity = self::decimal($map['quantity']);
                if (bccomp($quantity, '0', 4) <= 0) {
                    $this->fail('unsupported_purchase_mapping', 'Nonpositive purchase allocation is unsupported.');
                }
                if ((string) $map['purchase_line_id'] === '0') {
                    if (!$allowSentinel) {
                        $this->fail('unsupported_purchase_mapping', 'The business must allow overselling for saved sentinel allocations.');
                    }
                    $sum = bcadd($sum, $quantity, 4);
                    continue;
                }
                $purchase = null;
                foreach ($g['purchases'] as $p) { if ($p['id'] == $map['purchase_line_id']) { $purchase = $p; break; } }
                $parent = $purchase ? $this->db->table('transactions')->where('id', $purchase['transaction_id'])->lockForUpdate()->first() : null;
                if (!$parent || $parent->business_id != $g['header']['business_id'] || $parent->location_id != $g['header']['location_id']
                    || $parent->status !== 'received' || !in_array($parent->type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                    || $purchase['product_id'] != $line['product_id'] || $purchase['variation_id'] != $line['variation_id']
                    || bccomp(self::decimal($purchase['quantity_sold']), $quantity, 4) < 0) {
                    $this->fail('unsupported_purchase_mapping', 'Purchase allocation does not reconcile with tenant, location, product or consumption.');
                }
                $sum = bcadd($sum, $quantity, 4);
            }
            if (($stock && bccomp($sum, $line['quantity'], 4) !== 0) || (!$stock && bccomp($sum, '0', 4) !== 0)) {
                $this->fail('unsupported_purchase_mapping', 'Stock-enabled final lines require complete ordinary purchase allocations; nonstock lines require none.');
            }
        }
    }

    private function partitionMappings(array $g, $sourceLine, $newLine, $quantity, $now)
    {
        if ($sourceLine == $newLine) { return; } // Whole line moved with all its mappings.
        $remaining = $quantity;
        foreach ($g['mappings'] as $map) {
            if ($map['sell_line_id'] != $sourceLine || bccomp($remaining, '0', 4) === 0) { continue; }
            $take = bccomp($remaining, $map['quantity'], 4) < 0 ? $remaining : self::decimal($map['quantity']);
            if (bccomp($take, $map['quantity'], 4) === 0) {
                $this->db->table('transaction_sell_lines_purchase_lines')->where('id', $map['id'])
                    ->update(['sell_line_id' => $newLine, 'updated_at' => $now]);
            } else {
                $copy = $map; unset($copy['id']);
                $copy['sell_line_id'] = $newLine; $copy['quantity'] = $take;
                $copy['created_at'] = $now; $copy['updated_at'] = $now;
                $this->db->table('transaction_sell_lines_purchase_lines')->insert($copy);
                $this->db->table('transaction_sell_lines_purchase_lines')->where('id', $map['id'])
                    ->update(['quantity' => bcsub($map['quantity'], $take, 4), 'updated_at' => $now]);
            }
            $remaining = bcsub($remaining, $take, 4);
        }
        // Nonstock lines have no mapping; eligibility has already verified coverage.
    }

    private function bill(array $g)
    {
        $h = $g['header'];
        return ['id' => (int) $h['id'], 'invoice_no' => $h['invoice_no'], 'status' => $h['status'],
            'is_kitchen_order' => !empty($h['is_kitchen_order']),
            'table_id' => empty($h['res_table_id']) ? null : (int) $h['res_table_id'],
            'payment_status' => $h['payment_status'], 'total_before_tax' => self::decimal($h['total_before_tax']),
            'tax_amount' => self::decimal($h['tax_amount']), 'final_total' => self::decimal($h['final_total']),
            'version' => self::fingerprint($g), 'lines' => array_map(function ($line) {
                $r = array_intersect_key($line, array_flip(array_merge(['id'], self::LINE_COPY)));
                foreach (['quantity', 'unit_price_before_discount', 'unit_price', 'unit_price_inc_tax', 'item_tax'] as $key) {
                    $r[$key] = self::decimal($r[$key]);
                }
                return $r;
            }, $g['lines'])];
    }

    public function preview($id, $user)
    {
        $admin = $this->actor($user);
        $this->storage();
        return $this->db->transaction(function () use ($id, $user, $admin) {
            $g = $this->graph($id, $user, $admin);
            $reason = null;
            try {
                $this->notRestaurantSplit($id);
                $this->eligible($g);
            } catch (SavedSplitException $e) {
                $reason = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            }
            return ['eligible' => $reason === null, 'reason' => $reason,
                'supported_statuses' => ['draft', 'final'], 'expected_version' => self::fingerprint($g), 'source' => $this->bill($g)];
        });
    }

    private function request(array $input)
    {
        if (array_diff(array_keys($input), ['operation_id', 'expected_version', 'lines']) ||
            !isset($input['operation_id'], $input['expected_version'], $input['lines']) ||
            !is_string($input['operation_id']) || !Str::isUuid($input['operation_id']) ||
            !is_string($input['expected_version']) || !preg_match('/^[a-f0-9]{64}$/D', $input['expected_version']) ||
            !is_array($input['lines']) || !$input['lines'] || count($input['lines']) > 1000 ||
            array_keys($input['lines']) !== range(0, count($input['lines']) - 1)) {
            $this->fail('invalid_split_request', 'Provide only operation_id UUID, expected_version SHA256, and a nonempty lines array.', 422);
        }
        $quantities = [];
        foreach ($input['lines'] as $line) {
            if (!is_array($line) || array_diff(array_keys($line), ['id', 'quantity']) ||
                !isset($line['id'], $line['quantity']) ||
                (!is_int($line['id']) && !is_string($line['id'])) || !preg_match('/^[1-9]\d{0,9}$/D', (string) $line['id']) ||
                !is_string($line['quantity']) || isset($quantities[(int) $line['id']])) {
                $this->fail('invalid_split_request', 'Each unique saved line id requires a decimal-string quantity.', 422);
            }
            $q = self::decimal($line['quantity']);
            if (bccomp($q, '0', 4) <= 0) {
                $this->fail('invalid_split_request', 'Bill B quantities must be positive.', 422);
            }
            $quantities[(int) $line['id']] = $q;
        }
        ksort($quantities, SORT_NUMERIC);
        return [strtolower($input['operation_id']), $quantities];
    }

    private function replay($row, $user, $hash)
    {
        if ((string) $row->user_id !== (string) $user->id || !hash_equals($row->payload_hash, $hash)) {
            $this->fail('operation_collision', 'Operation UUID already belongs to another actor or payload.');
        }
        if (!$row->response_json) {
            $this->fail('operation_incomplete', 'Stored split operation is incomplete; manual investigation is required.');
        }
        return json_decode($row->response_json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function split($id, $user, array $input)
    {
        $admin = $this->actor($user);
        $this->storage(['reference_counts', 'invoice_schemes']);
        [$operation, $quantities] = $this->request($input);
        $hash = hash('sha256', json_encode([(string) $id, $input['expected_version'], $quantities], JSON_THROW_ON_ERROR));
        try {
            return $this->db->transaction(function () use ($id, $user, $admin, $input, $operation, $quantities, $hash) {
                $g = $this->graph($id, $user, $admin); // Lock header first, deterministic graph order.
                $this->notRestaurantSplit($id);
                $existing = $this->db->table(self::OPERATIONS)->where('business_id', $user->business_id)
                    ->where('operation_id', $operation)->lockForUpdate()->first();
                if ($existing) {
                    return $this->replay($existing, $user, $hash);
                }
                if (!hash_equals(self::fingerprint($g), $input['expected_version'])) {
                    $this->fail('stale_split_version', 'The saved sale graph changed. Fetch a new split preview.');
                }
                $this->eligible($g);
                $a = []; $b = [];
                $remaining = $quantities;
                foreach ($g['lines'] as $line) {
                    $q = $quantities[$line['id']] ?? '0.0000';
                    unset($remaining[$line['id']]);
                    if (bccomp($q, $line['quantity'], 4) > 0) {
                        $this->fail('invalid_split_quantity', 'Requested quantity exceeds the saved line quantity.', 422);
                    }
                    $product = $this->db->table('products')->where('id', $line['product_id'])->first();
                    $unit = $this->db->table('units')->where('id', $product->unit_id)->first();
                    if (!$unit->allow_decimal && bccomp($q, bcadd($q, '0', 0), 4) !== 0) {
                        $this->fail('invalid_unit_quantity', 'This base unit does not allow fractional quantities.', 422);
                    }
                    if (bccomp($q, '0', 4) > 0) {
                        $portion = $line; $portion['quantity'] = $q; $b[] = $portion;
                    }
                    $rest = bcsub($line['quantity'], $q, 4);
                    if (bccomp($rest, '0', 4) > 0) {
                        $line['quantity'] = $rest; $a[] = $line;
                    }
                }
                if ($remaining || !$a || !$b) {
                    $this->fail('invalid_split_partition', 'Only saved source lines are allowed and both bills must retain positive quantities.', 422);
                }
                $totalA = $this->total($a); $totalB = $this->total($b);
                if (bccomp(bcadd($totalA, $totalB, 4), $g['header']['final_total'], 4) !== 0) {
                    $this->fail('split_rounding_not_conserved', 'These quantities cannot conserve historical totals at four decimal places.', 422);
                }
                $now = date('Y-m-d H:i:s');
                $invoice = $this->systemInvoiceNumber($g['header']);
                $opId = $this->db->table(self::OPERATIONS)->insertGetId(['business_id' => $user->business_id,
                    'user_id' => $user->id, 'operation_id' => $operation, 'source_id' => $id,
                    'payload_hash' => $hash, 'expected_version' => $input['expected_version'],
                    'allocation_json' => json_encode(['before' => $g, 'bill_b_quantities' => $quantities], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now]);
                $header = array_intersect_key($g['header'], array_flip(self::HEADER_COPY));
                $header = array_merge($header, ['invoice_no' => $invoice, 'invoice_token' => (string) Str::uuid(),
                    'total_before_tax' => $totalB, 'final_total' => $totalB, 'tax_amount' => '0.0000',
                    'essentials_duration' => '0.0000', 'essentials_amount_per_unit_duration' => '0.0000',
                    'created_at' => $now, 'updated_at' => $now]);
                $newId = $this->db->table('transactions')->insertGetId($header);
                $lineMap = [];
                foreach ($b as $line) {
                    $oldId = $line['id'];
                    if (bccomp($line['quantity'], $this->findQuantity($g['lines'], $oldId), 4) === 0) {
                        $this->db->table('transaction_sell_lines')->where('id', $oldId)
                            ->update(['transaction_id' => $newId, 'updated_at' => $now]);
                        $newLine = $oldId;
                    } else {
                        $copy = array_intersect_key($line, array_flip(self::LINE_COPY));
                        $copy = array_merge($copy, ['transaction_id' => $newId, 'created_at' => $now, 'updated_at' => $now]);
                        $newLine = $this->db->table('transaction_sell_lines')->insertGetId($copy);
                    }
                    $lineMap[] = ['source_line_id' => (int) $oldId, 'bill_b_line_id' => (int) $newLine, 'quantity' => $line['quantity']];
                    $this->partitionMappings($g, $oldId, $newLine, $line['quantity'], $now);
                }
                foreach ($a as $line) {
                    $this->db->table('transaction_sell_lines')->where('id', $line['id'])
                        ->update(['quantity' => $line['quantity'], 'updated_at' => $now]);
                }
                $this->db->table('transactions')->where('id', $id)
                    ->update(['total_before_tax' => $totalA, 'final_total' => $totalA, 'updated_at' => $now]);
                $ga = $this->graph($id, $user, $admin); $gb = $this->graph($newId, $user, $admin);
                $this->eligible($ga); $this->eligible($gb); // Revalidate persisted results, rollback on failure.
                $response = ['operation_id' => $operation, 'source_id' => (int) $id, 'result_id' => (int) $newId,
                    'original_total' => self::decimal($g['header']['final_total']), 'bill_a' => $this->bill($ga),
                    'bill_b' => $this->bill($gb), 'line_allocations' => $lineMap, 'legacy_writes_blocked' => true];
                $this->db->table(self::OPERATIONS)->where('id', $opId)->update(['result_id' => $newId,
                    'response_json' => json_encode($response, JSON_THROW_ON_ERROR), 'updated_at' => $now]);
                return $response;
            });
        } catch (QueryException $e) {
            // Simultaneous same UUID on different source headers: unique-key loser
            // rolls back completely, then reads the winner on a fresh transaction.
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                $row = $this->db->table(self::OPERATIONS)->where('business_id', $user->business_id)->where('operation_id', $operation)->first();
                if ($row) {
                    return $this->replay($row, $user, $hash);
                }
            }
            throw $e;
        }
    }

    private function systemInvoiceNumber(array $header): string
    {
        $businessId = $header['business_id'];
        // The normal helper updates either the draft counter or the invoice scheme.
        $this->db->table('business')->where('id', $businessId)->lockForUpdate()->first();
        if ($header['status'] === 'final') {
            $schemeId = $this->db->table('business_locations')->where('business_id', $businessId)
                ->where('id', $header['location_id'])->value('invoice_scheme_id');
            $scheme = $schemeId ? $this->db->table('invoice_schemes')->where('business_id', $businessId)
                ->where('id', $schemeId)->lockForUpdate()->first() : null;
            $scheme = $scheme ?? $this->db->table('invoice_schemes')->where('business_id', $businessId)
                ->where('is_default', 1)->lockForUpdate()->first();
            if (!$scheme || !in_array($scheme->number_type, ['sequential', 'random'], true)) {
                $this->fail('split_invoice_number_unavailable', 'Configure a valid system invoice scheme before splitting.', 503);
            }
        }
        $number = (new \App\Utils\TransactionUtil())->getInvoiceNumber(
            $businessId, $header['status'], $header['location_id'], $scheme->id ?? null, 'sell');
        if ($this->db->table('transactions')->where('business_id', $businessId)->where('invoice_no', $number)->exists()) {
            $this->fail('split_invoice_number_unavailable', 'The system invoice number already exists. Check the invoice counter.', 503);
        }
        return $number;
    }

    private function findQuantity($lines, $id)
    {
        foreach ($lines as $line) {
            if ($line['id'] == $id) {
                return $line['quantity'];
            }
        }
        $this->fail('invalid_split_partition', 'Source line disappeared.');
    }

    private function notRestaurantSplit($id): void
    {
        if ($this->db->getSchemaBuilder()->hasTable('restaurant_bills') &&
            $this->db->table('restaurant_bills')->where('transaction_id', $id)->exists()) {
            $this->fail('restaurant_workflow_required', 'Use mixed/partial cashier tenders for restaurant bills; splitting dispatched bills requires a ticket-aware partition.');
        }
    }

    private function checkoutEligible(array $g, $user, $admin)
    {
        $this->eligible($g);
        if (bccomp(self::decimal($g['header']['final_total']), '0', 4) <= 0) {
            $this->fail('checkout_unsupported', 'Cash checkout requires a positive saved total.');
        }
        $business = $this->db->table('business')->where('id', $g['header']['business_id'])->lockForUpdate()->first();
        $settings = empty($business->pos_settings) ? [] : json_decode($business->pos_settings, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($settings) || !empty($settings['disable_express_checkout']) || (!$admin && $user->can('disable_express_checkout'))) {
            $this->fail('checkout_forbidden', 'Express cash checkout is disabled by business settings or user permissions.', 403);
        }
        $location = $this->db->table('business_locations')->where('id', $g['header']['location_id'])->lockForUpdate()->first();
        $accounts = empty($location->default_payment_accounts) ? [] : json_decode($location->default_payment_accounts, true, 512, JSON_THROW_ON_ERROR);
        if (empty($accounts['cash']['is_enabled'])) {
            $this->fail('checkout_forbidden', 'Cash is not an enabled tender at this sale location.', 403);
        }
        // No silently lost account posting or reward accrual; these need a separate
        // audited checkout contract, not a generic payload passthrough.
        if (!empty($accounts['cash']['account']) || !empty($business->enable_rp) || !empty($business->enable_product_expiry)) {
            $this->fail('checkout_unsupported', 'Cash accounts, rewards and product-expiry workflows require an audited checkout adapter; this checkout is unavailable for that configuration.');
        }
        if (!in_array($business->accounting_method ?? 'fifo', ['fifo', 'lifo'], true)) {
            $this->fail('checkout_unsupported', 'Unsupported stock accounting method.');
        }
        return [$business, $settings];
    }

    public function checkoutPreview($id, $user)
    {
        $admin = $this->actor($user);
        $this->storage(['reference_counts', 'variation_location_details']);
        return $this->db->transaction(function () use ($id, $user, $admin) {
            $g = $this->graph($id, $user, $admin);
            $reason = null;
            try { $this->checkoutEligible($g, $user, $admin); }
            catch (SavedSplitException $e) { $reason = ['code' => $e->errorCode, 'message' => $e->getMessage()]; }
            return ['eligible' => $reason === null, 'reason' => $reason, 'method' => 'cash',
                'expected_version' => self::fingerprint($g), 'source' => $this->bill($g)];
        });
    }

    private function additionsEligible(array $g)
    {
        $this->eligible($g);
        if (!RestaurantBillService::isEditing($this->db, $g['header']['id']) &&
            !empty($g['header']['is_kitchen_order']) &&
            !($this->db->getSchemaBuilder()->hasTable('restaurant_bills') &&
                $this->db->table('restaurant_bills')->where('transaction_id', $g['header']['id'])->exists())) {
            $this->fail('order_already_sent', 'Orders already sent to the kitchen cannot be edited.');
        }
        $business = $this->db->table('business')->where('id', $g['header']['business_id'])->lockForUpdate()->first();
        if (!empty($business->enable_product_expiry) || !empty($business->enable_rp) ||
            !in_array($business->accounting_method ?? 'fifo', ['fifo', 'lifo'], true) ||
            !self::zero($g['header']['selling_price_group_id'] ?? null) ||
            !self::zero($g['header']['customer_group_id'] ?? null)) {
            $this->fail('add_items_unsupported', 'Expiry, rewards and special pricing require the backend POS editor.');
        }
        return $business;
    }

    public function additionsPreview($id, $user)
    {
        $admin = $this->actor($user, true);
        $this->storage(['variation_location_details', 'product_locations']);
        return $this->db->transaction(function () use ($id, $user, $admin) {
            $g = $this->graph($id, $user, $admin, true);
            $reason = null;
            try { $this->additionsEligible($g); }
            catch (SavedSplitException $e) { $reason = ['code' => $e->errorCode, 'message' => $e->getMessage()]; }
            return ['eligible' => $reason === null, 'reason' => $reason,
                'location_id' => (int) $g['header']['location_id'],
                'restaurant_managed' => $this->db->getSchemaBuilder()->hasTable('restaurant_bills') &&
                    $this->db->table('restaurant_bills')->where('transaction_id', $id)->exists(),
                'expected_version' => self::fingerprint($g), 'source' => $this->bill($g)];
        });
    }

    public function addItems($id, $user, array $input)
    {
        return $this->writeItems($id, $user, $input, false);
    }

    public function editItems($id, $user, array $input)
    {
        return $this->writeItems($id, $user, $input, true);
    }

    public function createRestaurantBill($user, int $locationId, int $contactId, int $tableId, array $items)
    {
        if ($this->db->transactionLevel() < 1) {
            $this->fail('restaurant_requires_transaction', 'Restaurant creation requires an enclosing workflow transaction.');
        }
        $this->storage(['variation_location_details', 'product_locations', 'reference_counts', 'invoice_schemes']);
        if (!$user->hasRole('Admin#'.$user->business_id) && !$user->can('superadmin') && !$user->can('sell.create')) {
            $this->fail('restaurant_forbidden', 'Backend sell.create permission is required.', 403);
        }
        $now = date('Y-m-d H:i:s');
        $inlineTaxEnabled = (int) $this->db->table('business')->where('id', $user->business_id)
            ->value('enable_inline_tax') === 1;
        $header = ['business_id' => $user->business_id, 'location_id' => $locationId, 'type' => 'sell',
            'status' => 'final', 'payment_status' => 'due', 'contact_id' => $contactId, 'created_by' => $user->id,
            'transaction_date' => $now, 'invoice_token' => Str::random(32), 'source' => 'desktop_pos',
            'tax_amount' => '0.0000', 'exchange_rate' => '1.0000', 'is_suspend' => 0, 'is_direct_sale' => 0,
            'is_created_from_api' => 1, 'res_table_id' => $tableId, 'res_waiter_id' => $user->id,
            'created_at' => $now, 'updated_at' => $now];
        $lines = [];
        foreach ($items as $variationId => $quantity) {
            $lines[] = $this->newRestaurantLine($header, (int) $variationId, $quantity, $now, $inlineTaxEnabled);
        }
        $header['total_before_tax'] = $header['final_total'] = self::decimal($this->total($lines));
        if (bccomp($header['final_total'], '0', 4) <= 0) {
            $this->fail('restaurant_positive_total_required', 'A restaurant bill must have a positive total; zero-value/full-void clearing is not supported.', 422);
        }
        $header['invoice_no'] = $this->systemInvoiceNumber($header);
        $id = $this->db->table('transactions')->insertGetId($header);
        foreach ($lines as $line) {
            $line['transaction_id'] = $id;
            $this->db->table('transaction_sell_lines')->insert($line);
        }
        $g = $this->loadGraph((array) $this->db->table('transactions')->where('id', $id)->first());
        // Check ordinary pricing/metadata before stock; final mapping verification follows allocation.
        $draft = $g;
        $draft['header']['status'] = 'draft';
        $business = $this->additionsEligible($draft);
        $settings = empty($business->pos_settings) ? [] : json_decode($business->pos_settings, true, 512, JSON_THROW_ON_ERROR);
        if (!empty($settings['disable_credit_sale_button']) ||
            (!$user->hasRole('Admin#'.$user->business_id) && !$user->can('superadmin') && $user->can('disable_credit_sale'))) {
            $this->fail('restaurant_forbidden', 'Unpaid order placement is disabled by backend POS settings.', 403);
        }
        SavedSaleWriteGuard::checkout($this->db, $id, function () use ($g, $settings, $business, $id, $locationId, $user) {
            $this->decreaseSavedStock($g, $settings);
            (new \App\Utils\TransactionUtil())->mapPurchaseSell(['id' => $user->business_id, 'location_id' => $locationId,
                'accounting_method' => $business->accounting_method, 'pos_settings' => $settings],
                \App\TransactionSellLine::where('transaction_id', $id)->orderBy('id')->get(), 'purchase', false);
        });
        $this->eligible($this->loadGraph((array) $this->db->table('transactions')->where('id', $id)->first()));
        return $id;
    }

    private function newRestaurantLine(array $h, int $variationId, string $quantity, string $now, bool $inlineTaxEnabled)
    {
        $variation = $this->db->table('variations')->where('id', $variationId)->whereNull('deleted_at')->lockForUpdate()->first();
        $product = $variation ? $this->db->table('products')->where('id', $variation->product_id)
            ->where('business_id', $h['business_id'])->whereNull('deleted_at')->lockForUpdate()->first() : null;
        if (!$product || !in_array($product->type, ['single', 'variable'], true) ||
            !empty($product->not_for_selling) || !empty($product->is_inactive) ||
            !$this->db->table('product_locations')->where('product_id', $product->id)->where('location_id', $h['location_id'])->exists()) {
            $this->fail('invalid_add_product', 'A selected product is unavailable at this order location.', 422);
        }
        $unit = $this->db->table('units')->where('id', $product->unit_id)->where('business_id', $h['business_id'])->first();
        if (!$unit || (!$unit->allow_decimal && bccomp($quantity, bcadd($quantity, '0', 0), 4) !== 0)) {
            $this->fail('invalid_unit_quantity', 'Quantity is invalid for the product base unit.', 422);
        }
        $price = self::decimal($variation->default_sell_price);
        $rate = '0.0000';
        if ($inlineTaxEnabled && !self::zero($product->tax ?? null)) {
            $tax = $this->db->table('tax_rates')->where('id', $product->tax)->where('business_id', $h['business_id'])
                ->select('tax_rates.*')->selectRaw('CAST(CAST(amount AS DECIMAL(22,4)) AS CHAR) AS decimal_amount')->first();
            if (!$tax || !empty($tax->is_tax_group)) {
                $this->fail('invalid_add_product', 'A supported product tax rate is required.', 422);
            }
            $rate = self::decimal($tax->decimal_amount);
        }
        if (bccomp($price, '0', 4) < 0 || bccomp($rate, '0', 4) < 0) {
            $this->fail('invalid_add_product', 'Product price and tax must be nonnegative.', 422);
        }
        // Match web /pos/create and desktop retail: the catalog tax-inclusive price is authoritative and
        // the line tax is its difference from the net price, so totals never drift from catalog prices.
        $catalogInclusive = isset($variation->sell_price_inc_tax) ? self::decimal($variation->sell_price_inc_tax) : null;
        if (bccomp($rate, '0', 4) > 0 && $catalogInclusive !== null && bccomp($catalogInclusive, $price, 4) >= 0) {
            $grossPrice = $catalogInclusive;
            $taxAmount = bcsub($catalogInclusive, $price, 4);
        } else {
            $taxAmount = self::round4(bcdiv(bcmul($price, $rate, 8), '100', 8));
            $grossPrice = bcadd($price, $taxAmount, 4);
        }
        return ['product_id' => $product->id, 'variation_id' => $variationId, 'quantity' => $quantity,
            'unit_price_before_discount' => $price, 'unit_price' => $price, 'item_tax' => $taxAmount,
            'unit_price_inc_tax' => $grossPrice,
            'tax_id' => $inlineTaxEnabled ? ($product->tax ?? null) : null,
            'children_type' => '', 'created_at' => $now, 'updated_at' => $now];
    }

    private function writeItems($id, $user, array $input, bool $editing)
    {
        $admin = $this->actor($user, true);
        $this->storage(['variation_location_details', 'product_locations']);
        if (array_diff(array_keys($input), $editing ? ['operation_id', 'expected_version', 'items', 'is_kitchen_order', 'table_id'] : ['operation_id', 'expected_version', 'items']) ||
            (array_key_exists('table_id', $input) && $input['table_id'] !== null &&
                (!is_int($input['table_id']) || $input['table_id'] <= 0)) ||
            (array_key_exists('is_kitchen_order', $input) && !is_bool($input['is_kitchen_order'])) ||
            !isset($input['operation_id'], $input['expected_version'], $input['items']) ||
            !is_string($input['operation_id']) || !Str::isUuid($input['operation_id']) ||
            !is_string($input['expected_version']) || !preg_match('/^[a-f0-9]{64}$/D', $input['expected_version']) ||
            !is_array($input['items']) || !$input['items'] || count($input['items']) > 100) {
            $this->fail('invalid_add_items_request', 'Provide UUID operation_id, expected_version and 1-100 item quantities.', 422);
        }
        $items = [];
        foreach ($input['items'] as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['variation_id', 'quantity']) ||
                !isset($item['variation_id'], $item['quantity']) || !is_int($item['variation_id']) ||
                $item['variation_id'] <= 0 || !is_string($item['quantity']) ||
                bccomp(self::decimal($item['quantity']), '0', 4) <= 0 || isset($items[$item['variation_id']])) {
                $this->fail('invalid_add_items_request', 'Items require unique variation IDs and positive decimal-string quantities only.', 422);
            }
            $items[$item['variation_id']] = self::decimal($item['quantity']);
        }
        ksort($items, SORT_NUMERIC);
        $operation = strtolower($input['operation_id']);
        $kind = $editing ? 'edit_items' : 'add_items';
        $payload = [$kind, (string) $id, $input['expected_version'], $items];
        if (!empty($input['is_kitchen_order'])) { $payload[] = true; }
        if (array_key_exists('table_id', $input)) { $payload[] = ['table_id' => $input['table_id']]; }
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        try {
            return $this->db->transaction(function () use ($id, $user, $admin, $input, $items, $operation, $hash, $editing, $kind) {
                $g = $this->graph($id, $user, $admin, true);
                $existing = $this->db->table(self::OPERATIONS)->where('business_id', $user->business_id)
                    ->where('operation_id', $operation)->lockForUpdate()->first();
                if ($existing) { return $this->replay($existing, $user, $hash); }
                if (!hash_equals(self::fingerprint($g), $input['expected_version'])) {
                    $this->fail('stale_split_version', 'The order changed. Refresh before saving items.');
                }
                if ($this->db->getSchemaBuilder()->hasTable('restaurant_bills') &&
                    $this->db->table('restaurant_bills')->where('transaction_id', $id)->exists() &&
                    !RestaurantBillService::isEditing($this->db, $id)) {
                    $this->fail('restaurant_workflow_required', 'Use the restaurant bill editor for this order.');
                }
                $business = $this->additionsEligible($g);
                $inlineTaxEnabled = (int) ($business->enable_inline_tax ?? 0) === 1;
                $tableChanged = array_key_exists('table_id', $input) &&
                    ($input['table_id'] ?? 0) !== (int) ($g['header']['res_table_id'] ?? 0);
                if ($tableChanged && $input['table_id'] !== null) {
                    (new RestaurantBillService($this->db))->validateTableAssignment($user,
                        (int) $g['header']['location_id'], $input['table_id'], (int) $id);
                }
                if (!empty($input['is_kitchen_order'])) {
                    $modules = json_decode($business->enabled_modules ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                    if (!in_array('kitchen', $modules, true) || $g['header']['status'] !== 'final') {
                        $this->fail('kitchen_unavailable', 'Enable the Kitchen module and use a final unpaid sale before sending kitchen orders.', 422);
                    }
                }
                $h = $g['header'];
                $now = date('Y-m-d H:i:s');
                $opId = $this->db->table(self::OPERATIONS)->insertGetId(['business_id' => $user->business_id,
                    'user_id' => $user->id, 'operation_id' => $operation, 'source_id' => $id, 'result_id' => $id,
                    'payload_hash' => $hash, 'expected_version' => $input['expected_version'],
                    'allocation_json' => json_encode(['kind' => $kind, 'before' => $g, 'items' => $items], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now]);
                $newIds = [];
                $updatedIds = [];
                $deltaLines = [];
                $expectedLines = [];
                $canonicalIds = [];
                $mergedIds = [];
                foreach ($g['lines'] as $line) {
                    $variationId = (int) $line['variation_id'];
                    if (!isset($canonicalIds[$variationId])) {
                        $canonicalIds[$variationId] = count($expectedLines);
                        $expectedLines[] = $line;
                        continue;
                    }
                    $index = $canonicalIds[$variationId];
                    $canonical = &$expectedLines[$index];
                    $canonical['quantity'] = self::decimal(bcadd(self::decimal($canonical['quantity']), self::decimal($line['quantity']), 4));
                    $canonical['updated_at'] = $now;
                    $mergedIds[$line['id']] = $canonical['id'];
                    $this->db->table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line['id'])
                        ->update(['sell_line_id' => $canonical['id']]);
                    $this->db->table('transaction_sell_lines')->where('id', $line['id'])->delete();
                    $this->db->table('transaction_sell_lines')->where('id', $canonical['id'])->update([
                        'quantity' => $canonical['quantity'], 'updated_at' => $now]);
                    $updatedIds[] = (int) $canonical['id'];
                    unset($canonical);
                }
                $expectedMappings = array_map(function ($map) use ($mergedIds) {
                    if (isset($mergedIds[$map['sell_line_id']])) {
                        $canonicalId = $mergedIds[$map['sell_line_id']];
                        $map['sell_line_id'] = is_string($map['sell_line_id']) ? (string) $canonicalId : (int) $canonicalId;
                    }
                    return $map;
                }, $g['mappings']);
                $returnedLines = [];
                $removedIds = [];
                if ($editing) {
                    foreach ($expectedLines as $index => &$line) {
                        $variationId = (int) $line['variation_id'];
                        $desired = $items[$variationId] ?? '0.0000';
                        $difference = bcsub($desired, self::decimal($line['quantity']), 4);
                        if (bccomp($difference, '0', 4) > 0) {
                            $items[$variationId] = $difference;
                            continue;
                        }
                        unset($items[$variationId]);
                        if (bccomp($difference, '0', 4) === 0) { continue; }
                        if ($h['status'] === 'final') {
                            $this->releaseItemMappings($line, bcsub('0', $difference, 4), $expectedMappings);
                            $returnedLines[] = array_replace($line, ['quantity' => $difference]);
                        }
                        if (bccomp($desired, '0', 4) === 0) {
                            $this->db->table('transaction_sell_lines')->where('id', $line['id'])->delete();
                            $removedIds[] = (int) $line['id'];
                            unset($expectedLines[$index]);
                        } else {
                            $line['quantity'] = $desired;
                            $line['updated_at'] = $now;
                            $this->db->table('transaction_sell_lines')->where('id', $line['id'])->update([
                                'quantity' => $desired, 'updated_at' => $now]);
                            $updatedIds[] = (int) $line['id'];
                        }
                    }
                    unset($line);
                    $expectedLines = array_values($expectedLines);
                }
                foreach ($items as $variationId => $quantity) {
                    $variation = $this->db->table('variations')->where('id', $variationId)->whereNull('deleted_at')->lockForUpdate()->first();
                    $product = $variation ? $this->db->table('products')->where('id', $variation->product_id)
                        ->where('business_id', $h['business_id'])->whereNull('deleted_at')->lockForUpdate()->first() : null;
                    if (!$product || !in_array($product->type, ['single', 'variable'], true) ||
                        !empty($product->not_for_selling) || !empty($product->is_inactive) ||
                        !$this->db->table('product_locations')->where('product_id', $product->id)
                            ->where('location_id', $h['location_id'])->exists()) {
                        $this->fail('invalid_add_product', 'A selected product is unavailable at this order location.', 422);
                    }
                    $unit = $this->db->table('units')->where('id', $product->unit_id)->where('business_id', $h['business_id'])->first();
                    if (!$unit || (!$unit->allow_decimal && bccomp($quantity, bcadd($quantity, '0', 0), 4) !== 0)) {
                        $this->fail('invalid_unit_quantity', 'Quantity is invalid for the product base unit.', 422);
                    }
                    // Retain the earliest saved price/tax for the consolidated variation.
                    $matched = false;
                    foreach ($expectedLines as &$savedLine) {
                        if ((int) $savedLine['variation_id'] !== $variationId) { continue; }
                        $deltaLines[] = array_replace($savedLine, ['quantity' => $quantity]);
                        $savedLine['quantity'] = self::decimal(bcadd(self::decimal($savedLine['quantity']), $quantity, 4));
                        $savedLine['updated_at'] = $now;
                        $this->db->table('transaction_sell_lines')->where('id', $savedLine['id'])->update([
                            'quantity' => $savedLine['quantity'], 'updated_at' => $now]);
                        $updatedIds[] = (int) $savedLine['id'];
                        $matched = true;
                        break;
                    }
                    unset($savedLine);
                    if ($matched) { continue; }
                    $newIds[] = $this->db->table('transaction_sell_lines')->insertGetId(
                        ['transaction_id' => $id] + $this->newRestaurantLine($h, $variationId, $quantity, $now, $inlineTaxEnabled));
                }
                $newLines = $this->db->table('transaction_sell_lines')->whereIn('id', $newIds)->orderBy('id')
                    ->get()->map(fn ($line) => (array) $line)->all();
                $deltaLines = array_merge($deltaLines, $newLines);
                if ($h['status'] === 'final') {
                    $settings = empty($business->pos_settings) ? [] : json_decode($business->pos_settings, true, 512, JSON_THROW_ON_ERROR);
                    // Reuse the exact decimal stock adapter; no payment or header model writes enter this scope.
                    SavedSaleWriteGuard::checkout($this->db, $id, function () use ($g, $deltaLines, $returnedLines, $settings, $business, $h) {
                        $stockGraph = $g; $stockGraph['lines'] = array_merge($returnedLines, $deltaLines);
                        $this->decreaseSavedStock($stockGraph, $settings);
                        // Hydrate only the added quantities; never remap the full saved quantity.
                        (new \App\Utils\TransactionUtil())->mapPurchaseSell(['id' => $h['business_id'],
                            'location_id' => $h['location_id'], 'accounting_method' => $business->accounting_method,
                            'pos_settings' => $settings], \App\TransactionSellLine::hydrate($deltaLines)->sortBy('id'), 'purchase', false);
                    });
                }
                $total = self::decimal($this->total(array_merge($expectedLines, $newLines)));
                $this->db->table('transactions')->where('id', $id)->update([
                    'total_before_tax' => $total, 'final_total' => $total, 'updated_at' => $now]);
                $after = $this->graph($id, $user, $admin, true);
                $this->eligible($after);
                $originalLines = array_values(array_filter($after['lines'], fn ($line) => !in_array($line['id'], $newIds)));
                $mappingIds = array_column($g['mappings'], 'id');
                $originalMappings = array_values(array_filter($after['mappings'], fn ($map) => in_array($map['id'], $mappingIds)));
                if ($originalLines !== $expectedLines || $after['payments'] !== $g['payments'] ||
                    $originalMappings !== $expectedMappings ||
                    count(array_unique(array_column($after['lines'], 'variation_id'))) !== count($after['lines']) ||
                    $after['header'] !== array_replace($h, ['total_before_tax' => $total, 'final_total' => $total, 'updated_at' => $now])) {
                    $this->fail('add_items_invariant_failed', 'Saving items changed unexpected order data; rolled back.');
                }
                $restaurantChanges = [];
                if ($tableChanged) { $restaurantChanges['res_table_id'] = $input['table_id']; }
                if (!empty($input['is_kitchen_order'])) { $restaurantChanges['is_kitchen_order'] = 1; }
                if ($restaurantChanges) {
                    $this->db->table('transactions')->where('id', $id)->update($restaurantChanges);
                    $after = $this->graph($id, $user, $admin, true);
                }
                $response = ['operation_id' => $operation, 'source_id' => (int) $id,
                    'bill' => $this->bill($after), 'added_line_ids' => $newIds,
                    'updated_line_ids' => array_values(array_diff(array_unique($updatedIds), $removedIds)),
                    'merged_line_ids' => array_map('intval', array_keys($mergedIds)),
                    'removed_line_ids' => $removedIds];
                $this->db->table(self::OPERATIONS)->where('id', $opId)->update([
                    'response_json' => json_encode($response, JSON_THROW_ON_ERROR), 'updated_at' => $now]);
                return $response;
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                $row = $this->db->table(self::OPERATIONS)->where('business_id', $user->business_id)->where('operation_id', $operation)->first();
                if ($row) { return $this->replay($row, $user, $hash); }
            }
            throw $e;
        }
    }

    private function releaseItemMappings(array $line, string $quantity, array &$mappings)
    {
        if (!$this->db->table('products')->where('id', $line['product_id'])->value('enable_stock')) { return; }
        $remaining = $quantity;
        // Release newest allocations first; retained quantities keep their oldest purchase costs.
        foreach (array_reverse(array_keys($mappings)) as $index) {
            $map = $mappings[$index];
            if ($map['sell_line_id'] != $line['id'] || bccomp($remaining, '0', 4) === 0) { continue; }
            $allocated = self::decimal($map['quantity']);
            $released = bccomp($remaining, $allocated, 4) < 0 ? $remaining : $allocated;
            if ((string) $map['purchase_line_id'] !== '0') {
                $purchase = $this->db->table('purchase_lines')->where('id', $map['purchase_line_id'])->lockForUpdate()->first();
                if (!$purchase || bccomp(self::decimal($purchase->quantity_sold), $released, 4) < 0) {
                    $this->fail('unsupported_purchase_mapping', 'Cannot release inconsistent purchase consumption.');
                }
                $this->db->table('purchase_lines')->where('id', $purchase->id)->update([
                    'quantity_sold' => bcsub(self::decimal($purchase->quantity_sold), $released, 4)]);
            }
            $remaining = bcsub($remaining, $released, 4);
            if (bccomp($released, $allocated, 4) === 0) {
                $this->db->table('transaction_sell_lines_purchase_lines')->where('id', $map['id'])->delete();
                unset($mappings[$index]);
            } else {
                $mappings[$index]['quantity'] = bcsub($allocated, $released, 4);
                $this->db->table('transaction_sell_lines_purchase_lines')->where('id', $map['id'])
                    ->update(['quantity' => $mappings[$index]['quantity']]);
            }
        }
        $mappings = array_values($mappings);
        if (bccomp($remaining, '0', 4) !== 0) {
            $this->fail('unsupported_purchase_mapping', 'Cannot release the full removed item quantity.');
        }
    }

    /** The only allowed checkout is one exact cash payment. No repricing,
     * replacement lines, change, partial payments, account override or store().
     */
    public function checkout($id, $user, array $input)
    {
        $admin = $this->actor($user);
        $this->storage(['reference_counts', 'variation_location_details']);
        if (array_diff(array_keys($input), ['operation_id', 'expected_version', 'method', 'amount']) ||
            !isset($input['operation_id'], $input['expected_version'], $input['method'], $input['amount']) ||
            !is_string($input['operation_id']) || !Str::isUuid($input['operation_id']) ||
            !is_string($input['expected_version']) || !preg_match('/^[a-f0-9]{64}$/D', $input['expected_version']) ||
            $input['method'] !== 'cash' || !is_string($input['amount'])) {
            $this->fail('invalid_checkout_request', 'Provide only UUID operation_id, expected_version, method cash and decimal-string amount.', 422);
        }
        $amount = self::decimal($input['amount']);
        $operation = strtolower($input['operation_id']);
        $hash = hash('sha256', json_encode(['checkout', (string) $id, $input['expected_version'], 'cash', $amount], JSON_THROW_ON_ERROR));
        try {
            return $this->db->transaction(function () use ($id, $user, $admin, $input, $amount, $operation, $hash) {
                $g = $this->graph($id, $user, $admin);
                if ($this->db->getSchemaBuilder()->hasTable('restaurant_bills') &&
                    $this->db->table('restaurant_bills')->where('transaction_id', $id)->exists()) {
                    $this->fail('restaurant_workflow_required', 'Use cashier clearing for restaurant bills.');
                }
                $existing = $this->db->table(self::OPERATIONS)->where('business_id', $user->business_id)
                    ->where('operation_id', $operation)->lockForUpdate()->first();
                // Replay precedes unpaid/version validation: a paid retry is not
                // a new payment and must not trigger stock or fiscal side effects.
                if ($existing) { return $this->replay($existing, $user, $hash); }
                if (!hash_equals(self::fingerprint($g), $input['expected_version'])) {
                    $this->fail('stale_split_version', 'Saved bill changed. Fetch a fresh checkout preview.');
                }
                [$business, $settings] = $this->checkoutEligible($g, $user, $admin);
                \Modules\Connector\Support\TillAssignment::bind($user, (int) $id, $this->connectorTillId);
                if (bccomp($amount, self::decimal($g['header']['final_total']), 4) !== 0) {
                    $this->fail('invalid_checkout_amount', 'Cash must equal the exact saved total; change and partial payments are unsupported.', 422);
                }
                $now = date('Y-m-d H:i:s');
                $opId = $this->db->table(self::OPERATIONS)->insertGetId(['business_id' => $user->business_id,
                    'user_id' => $user->id, 'operation_id' => $operation, 'source_id' => $id, 'result_id' => $id,
                    'payload_hash' => $hash, 'expected_version' => $input['expected_version'],
                    'allocation_json' => json_encode(['kind' => 'checkout', 'before' => $g, 'method' => 'cash', 'amount' => $amount], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now]);
                SavedSaleWriteGuard::checkout($this->db, $id, function () use ($g, $user, $business, $settings, $amount) {
                    $this->executeCheckout($g, $user, $business, $settings, $amount);
                });
                $after = $this->graph($id, $user, $admin);
                if ($after['header']['status'] !== 'final' || $after['header']['payment_status'] !== 'paid' ||
                    count($after['payments']) !== 1 || bccomp(self::decimal($after['payments'][0]['amount']), $amount, 4) !== 0 ||
                    $after['lines'] !== $g['lines'] || bccomp(self::decimal($after['header']['final_total']), $amount, 4) !== 0) {
                    $this->fail('checkout_invariant_failed', 'Checkout did not preserve saved lines and exact payment; rolled back.');
                }
                $this->validateMappings($after);
                if ($g['header']['status'] === 'final' && ($after['mappings'] !== $g['mappings'] || $after['purchases'] !== $g['purchases'])) {
                    $this->fail('checkout_invariant_failed', 'Final checkout changed existing purchase allocations; rolled back.');
                }
                $response = ['operation_id' => $operation, 'source_id' => (int) $id, 'bill' => $this->bill($after), 'legacy_writes_blocked' => true];
                $this->db->table(self::OPERATIONS)->where('id', $opId)->update([
                    'response_json' => json_encode($response, JSON_THROW_ON_ERROR), 'updated_at' => $now]);
                $this->scheduleFiscal($id);
                return $response;
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                $row = $this->db->table(self::OPERATIONS)->where('business_id', $user->business_id)->where('operation_id', $operation)->first();
                if ($row) { return $this->replay($row, $user, $hash); }
            }
            throw $e;
        }
    }

    protected function executeCheckout(array $g, $user, $business, array $settings, $amount)
    {
        $h = $g['header'];
        $limit = \Modules\EIS\Models\EisSale::checkOfflineLimits($h['business_id'], $amount, (int) $h['location_id'],
            $this->connectorTillId ?? (isset($h['connector_till_id']) ? (int) $h['connector_till_id'] : null));
        if (!$limit['success'] || ($limit['code'] ?? null) === 'CHECK_ERROR') {
            $this->fail('checkout_fiscal_limits', $limit['message']); // Unlike legacy store, fail closed on check failure.
        }
        $util = new \App\Utils\TransactionUtil();
        if ($h['status'] === 'draft') {
            $this->decreaseSavedStock($g, $settings);
        }
        $this->db->table('transactions')->where('id', $h['id'])->update(['status' => 'final', 'is_suspend' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $transaction = \App\Transaction::findOrFail($h['id']);
        if ($h['status'] === 'draft') {
            $util->mapPurchaseSell(['id' => $h['business_id'], 'location_id' => $h['location_id'],
                'accounting_method' => $business->accounting_method, 'pos_settings' => $settings], $transaction->sell_lines->sortBy('id'), 'purchase', false);
        }
        $util->createOrUpdatePaymentLines($transaction, [['method' => 'cash', 'amount' => $amount]], $h['business_id'], $user->id, false, true);
        // Exact payment was validated in decimal arithmetic. Do not invoke the
        // legacy float status calculation / arbitrary module hooks here.
        $this->db->table('transactions')->where('id', $h['id'])->update(['payment_status' => 'paid']);
    }

    private function decreaseSavedStock(array $g, array $settings)
    {
        $h = $g['header'];
        $productUtil = new \App\Utils\ProductUtil();
        // Lock all stock rows deterministically before decreasing any of them.
        // mapPurchaseSell takes current locks on available purchase rows.
        $quantities = [];
        foreach ($g['lines'] as $line) {
            if ($this->db->table('products')->where('id', $line['product_id'])->value('enable_stock')) {
                $key = $line['variation_id'];
                if (!isset($quantities[$key])) { $quantities[$key] = [$line['product_id'], '0.0000']; }
                $quantities[$key][1] = bcadd($quantities[$key][1], $line['quantity'], 4);
            }
        }
        ksort($quantities, SORT_NUMERIC);
        foreach ($quantities as $variation => [$product, $quantity]) {
            $stock = $this->db->table('variation_location_details')->where('product_id', $product)
                ->where('variation_id', $variation)->where('location_id', $h['location_id'])->orderBy('id')->lockForUpdate()->get();
            if ($stock->count() > 1 || (empty($settings['allow_overselling']) &&
                ($stock->count() !== 1 || bccomp(self::decimal($stock[0]->qty_available), $quantity, 4) < 0))) {
                $this->fail('checkout_stock_unavailable', 'Insufficient or ambiguous saved product stock at the sale location.');
            }
        }
        foreach ($quantities as $variation => [$product, $quantity]) {
            $beforeValue = $this->db->table('variation_location_details')->where('product_id', $product)
                ->where('variation_id', $variation)->where('location_id', $h['location_id'])->value('qty_available');
            $before = $beforeValue === null ? '0.0000' : self::decimal($beforeValue);
            $productUtil->decreaseProductQuantity($product, $variation, $h['location_id'], $quantity, 0, $h['id']);
            $after = self::decimal($this->db->table('variation_location_details')->where('product_id', $product)
                ->where('variation_id', $variation)->where('location_id', $h['location_id'])->value('qty_available'));
            if (bccomp($after, bcsub($before, $quantity, 4), 4) !== 0) {
                $this->fail('checkout_invariant_failed', 'Stock utility did not decrease the exact saved quantity; rolled back.');
            }
        }
    }

    protected function scheduleFiscal($id)
    {
        $this->db->afterCommit(function () use ($id) {
            try { \Modules\EIS\Services\Sales\SaleSubmissionRule::initiate(\App\Transaction::findOrFail($id)); }
            catch (\Throwable $e) { \Illuminate\Support\Facades\Log::error('Saved checkout fiscal dispatch failed after commit', ['transaction_id' => $id, 'message' => $e->getMessage()]); }
        });
    }
}