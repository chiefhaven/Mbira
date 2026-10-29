<?php

namespace Modules\EIS\Services\Stock;

use Illuminate\Database\Connection;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Products\OpeningStockInitializer;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\EisSubscriptionAccess;

class StockReconciliationService
{
    public function __construct(private Connection $db, private StockInventoryClient $client) {}

    public function preview(int $businessId, int $terminalId, int $actorId): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);

        $this->storage();
        $setting = TerminalContext::settings($businessId, null, $terminalId);
        TerminalContext::assertActive($setting);
        $inventory = $this->client->fetch($setting);
        return $this->db->transaction(function () use ($businessId, $setting, $inventory, $actorId) {
            $this->lockLocation($setting);
            $this->assertSettled($businessId, (int) $setting->location_id, (string) $setting->tpin);
            $lines = $this->lines($setting, $inventory);
            $id = $this->db->table('eis_stock_reconciliations')->insertGetId([
                'business_id' => $businessId, 'location_id' => $setting->location_id,
                'eis_setting_id' => $setting->id, 'site_id' => TerminalContext::site($setting),
                'created_by' => $actorId, 'status' => 'preview',
                'inventory' => json_encode($inventory, JSON_THROW_ON_ERROR),
                'lines' => json_encode($lines, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $this->db->table('eis_stock_reconciliations')->find($id);
        });
    }

    public function apply(int $businessId, int $snapshotId, int $actorId): object
    {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);

        $this->storage();
        return $this->db->transaction(function () use ($businessId, $snapshotId, $actorId) {
            $snapshot = $this->db->table('eis_stock_reconciliations')->where('business_id', $businessId)
                ->where('id', $snapshotId)->lockForUpdate()->first();
            if (!$snapshot) {
                throw new \InvalidArgumentException('EIS stock comparison does not belong to this business.');
            }
            if ($snapshot->status === 'applied') {
                return $snapshot;
            }
            if (strtotime($snapshot->created_at) < now()->subMinutes(5)->getTimestamp()) {
                throw new \InvalidArgumentException('Stock comparison expired. Fetch a fresh comparison before reconciling.');
            }
            $setting = TerminalContext::settings($businessId, null, (int) $snapshot->eis_setting_id, (int) $snapshot->location_id);
            TerminalContext::assertActive($setting);
            if (TerminalContext::site($setting) !== $snapshot->site_id) {
                throw new \InvalidArgumentException('Terminal site changed. Fetch a fresh stock comparison.');
            }
            $this->lockLocation($setting);
            $this->assertSettled($businessId, (int) $setting->location_id, (string) $setting->tpin);
            $inventory = $this->client->fetch($setting);
            if ($inventory !== json_decode($snapshot->inventory, true, 512, JSON_THROW_ON_ERROR)) {
                throw new \InvalidArgumentException('EIS stock changed since comparison. Fetch a fresh comparison.');
            }
            $lines = $this->lines($setting, $inventory);
            $expected = json_decode($snapshot->lines, true, 512, JSON_THROW_ON_ERROR);
            if ($lines !== $expected) {
                throw new \InvalidArgumentException('Local stock or purchase allocations changed since comparison. Fetch a fresh comparison.');
            }
            foreach ($lines as &$line) {
                $delta = bcsub($line['remote'], $line['local'], 4);
                if (bccomp($delta, '0', 4) !== 0) {
                    $line['transaction_id'] = $this->move($setting, $line, $delta, $actorId, $snapshotId);
                }
            }
            unset($line);
            $this->db->table('eis_stock_reconciliations')->where('id', $snapshotId)->update([
                'status' => 'applied', 'lines' => json_encode($lines, JSON_THROW_ON_ERROR),
                'applied_at' => now(), 'updated_at' => now(),
            ]);
            return $this->db->table('eis_stock_reconciliations')->find($snapshotId);
        });
    }

    private function storage(): void
    {
        if ($this->db->getDriverName() === 'sqlite') {
            return;
        }
        if ($this->db->getDriverName() !== 'mysql') {
            throw new \RuntimeException('EIS stock reconciliation requires MySQL InnoDB or SQLite.');
        }
        foreach (['business', 'business_locations', 'products', 'product_variations', 'variations', 'eis_product_maps',
            'transactions', 'purchase_lines', 'variation_location_details', 'stock_adjustment_lines',
            'transaction_sell_lines', 'transaction_sell_lines_purchase_lines', 'eis_stock_reconciliations',
            'eis_settings', 'eis_stock_operations', 'eis_utility_requests'] as $table) {
            $row = $this->db->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$this->db->getTablePrefix().$table]);
            if (!$row || strtoupper((string) $row->engine) !== 'INNODB') {
                throw new \RuntimeException('EIS stock reconciliation requires InnoDB storage for '.$table.'.');
            }
        }
    }

    private function lockLocation(EisSetting $setting): void
    {
        $this->db->table('business')->where('id', $setting->business_id)->lockForUpdate()->first();
        $location = $this->db->table('business_locations')->where('business_id', $setting->business_id)
            ->where('id', $setting->location_id)->lockForUpdate()->first();
        if (!$location || (string) $location->eis_site_id !== TerminalContext::site($setting)) {
            throw new \InvalidArgumentException('EIS stock requires an exact terminal location/site match.');
        }
        $current = EisSetting::where('business_id', $setting->business_id)->whereKey($setting->id)->lockForUpdate()->firstOrFail();
        if ((int) $current->location_id !== (int) $setting->location_id
            || TerminalContext::site($current) !== TerminalContext::site($setting)
            || (string) $current->device_id !== (string) $setting->device_id
            || (string) $current->tpin !== (string) $setting->tpin) {
            throw new \InvalidArgumentException('Terminal ownership changed while fetching stock. Fetch a fresh comparison.');
        }
        TerminalContext::assertActive($current);
    }

    private function assertSettled(int $businessId, int $locationId, string $tin): void
    {
        if ($this->db->getSchemaBuilder()->hasTable('eis_stock_tool_requests')
            && $this->db->table('eis_stock_tool_requests')->where('business_id', $businessId)->where('tin', $tin)
                ->where('endpoint', \Modules\EIS\Services\Utilities\EisUtilitiesClient::baseUrl())
                ->whereIn('status', ['sending', 'unknown', 'needs_review'])->lockForUpdate()->exists()) {
            throw new \InvalidArgumentException('Verify unresolved EIS warehouse/production requests before reconciling site stock.');
        }
        if ($this->db->getSchemaBuilder()->hasTable('eis_sale_adjustments')
            && $this->db->table('eis_sale_adjustments')->where('business_id', $businessId)->where('location_id', $locationId)
                ->where('status', '!=', 'accepted')->lockForUpdate()->exists()) {
            throw new \InvalidArgumentException('Resolve linked EIS return credit/debit notes before reconciling site stock.');
        }
        $uploads = $this->db->table('eis_utility_requests')->where('business_id', $businessId)
            ->where('endpoint', \Modules\EIS\Services\Utilities\EisUtilitiesClient::baseUrl())
            ->where('tin', $tin)
            ->where('operation', 'taxpayer-initial-inventory-upload')
            ->whereIn('status', ['sending', 'unknown', 'needs_review', 'accepted'])->lockForUpdate()->get();
        $finalized = $uploads->contains(fn ($upload) => $upload->status === 'accepted'
            && json_decode($upload->payload, true, 512, JSON_THROW_ON_ERROR)['isLastBatch'] === true);
        foreach ($uploads as $upload) {
            if ($upload->status !== 'accepted' || !$finalized) {
                throw new \InvalidArgumentException('Initial-inventory upload #'.$upload->id
                    .' is staged or unresolved. Finish/verify the taxpayer upload with MRA before reconciling site stock.');
            }
        }
        $outbound = $this->db->table('eis_stock_operations')->where('business_id', $businessId)
            ->where(fn ($q) => $q->where('location_id', $locationId)->orWhere('destination_location_id', $locationId))
            ->whereIn('status', StockOutboundService::UNSETTLED_STATUSES)
            ->select('id')->lockForUpdate()->first();
        if ($outbound) {
            throw new \InvalidArgumentException('Resolve outstanding EIS stock operation #'.$outbound->id.' before reconciling this location. Check both transfer sites and any pending approvals; never blindly resubmit an unknown outcome.');
        }
        $pending = $this->db->table('transactions as t')->where('t.business_id', $businessId)
            ->where('t.location_id', $locationId)->where('t.type', 'sell')->where('t.status', 'final')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('eis_sales as e')->whereColumn('e.transaction_id', 't.id')
                    ->whereColumn('e.business_id', 't.business_id')->where('e.status', 'submitted');
            })->select('t.id')->lockForUpdate()->first();
        if ($pending) {
            throw new \InvalidArgumentException('Submit or reconcile all final sales at this location before pulling authoritative EIS stock (including credit/part-paid bills).');
        }
    }

    private function lines(EisSetting $setting, array $inventory): array
    {
        $business = $this->db->table('business')->where('id', $setting->business_id)->first();
        $allocationOrder = ($business->accounting_method ?? 'fifo') === 'lifo' ? 'desc' : 'asc';
        $maps = $this->db->table('eis_product_maps')->where('business_id', $setting->business_id)
            ->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
        $lines = [];
        foreach ($maps as $map) {
            $product = $this->db->table('products')->where('business_id', $setting->business_id)
                ->where('id', $map->product_id)->whereNull('deleted_at')->lockForUpdate()->first();
            if (!$product || !$product->enable_stock
                || !$this->db->table('product_locations')->where('product_id', $product->id)->where('location_id', $setting->location_id)->exists()) {
                continue;
            }
            if ($product->type !== 'single' || (string) $product->eis_product_id !== (string) $map->eis_product_id
                || !array_key_exists((string) $map->eis_product_id, $inventory)
                || $this->db->table('eis_product_maps')->where('product_id', $product->id)->count() !== 1) {
                throw new \InvalidArgumentException('Missing or ambiguous EIS stock identity for product '.$product->id.'. Pull its catalog or reconcile the mapping first.');
            }
            $variations = $this->db->table('variations')->where('product_id', $product->id)->whereNull('deleted_at')
                ->orderBy('id')->lockForUpdate()->get();
            if ($variations->count() !== 1
                || $this->db->table('product_variations')->where('product_id', $product->id)->count() !== 1
                || !$this->db->table('product_variations')->where('product_id', $product->id)
                    ->where('id', $variations->first()->product_variation_id)->exists()) {
                throw new \InvalidArgumentException('EIS stock reconciliation requires exactly one variation per mapped product.');
            }
            $variation = $variations->first();
            $this->assertLedgerReferences($setting, (int) $product->id, (int) $variation->id);
            if (bccomp($this->decimal($variation->dpp_inc_tax), $this->decimal($variation->default_purchase_price), 4) < 0) {
                throw new \InvalidArgumentException('Invalid mapped product purchase cost/tax configuration.');
            }
            $stocks = $this->db->table('variation_location_details')->where('variation_id', $variation->id)
                ->where('location_id', $setting->location_id)->orderBy('id')->lockForUpdate()->get();
            if ($stocks->count() > 1 || ($stocks->count() === 1
                && ((int) $stocks->first()->product_id !== (int) $product->id
                    || (int) $stocks->first()->product_variation_id !== (int) $variation->product_variation_id))) {
                throw new \InvalidArgumentException('Ambiguous local stock row for product '.$product->id.'.');
            }
            $local = $this->decimal($stocks->first()->qty_available ?? '0');
            $purchases = $this->db->table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                ->where('t.business_id', $setting->business_id)->where('t.location_id', $setting->location_id)
                ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
                ->where('t.status', 'received')->where('p.product_id', $product->id)->where('p.variation_id', $variation->id)
                ->orderBy('t.transaction_date')->orderBy('p.id')->lockForUpdate()->get(['p.*']);
            $supply = '0.0000';
            foreach ($purchases as $purchase) {
                $remaining = $this->decimal($purchase->quantity);
                foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) {
                    $remaining = bcsub($remaining, $this->decimal($purchase->$field ?? '0'), 4);
                }
                if (bccomp($remaining, '0', 4) < 0) {
                    throw new \InvalidArgumentException('Negative purchase availability requires stock-ledger reconciliation before EIS synchronization.');
                }
                $supply = bcadd($supply, $remaining, 4);
            }
            if (bccomp($supply, $local, 4) !== 0) {
                throw new \InvalidArgumentException('Product '.$product->id.' local quantity differs from its purchase ledger. Repair allocations before EIS synchronization.');
            }
            $lines[] = [
                'product_id' => (int) $product->id, 'variation_id' => (int) $variation->id,
                'product_variation_id' => (int) $variation->product_variation_id,
                'code' => (string) $map->eis_product_id, 'name' => $product->name,
                'local' => $local, 'remote' => $inventory[(string) $map->eis_product_id],
                'cost' => $this->decimal($variation->default_purchase_price),
                'cost_inc' => $this->decimal($variation->dpp_inc_tax), 'tax_id' => $product->tax,
                'allocation_order' => $allocationOrder,
                'fingerprint' => hash('sha256', json_encode([$map, $product, $variation, $stocks, $purchases], JSON_THROW_ON_ERROR)),
            ];
        }
        if (!$lines && $inventory) {
            throw new \InvalidArgumentException('No mapped stock products at this location. Import the EIS product catalog first.');
        }
        return $lines;
    }

    private function decimal($value): string
    {
        $value = OpeningStockInitializer::decimal($value);
        if ($value === null) {
            throw new \InvalidArgumentException('Stock reconciliation requires nonnegative exact decimals with at most four decimal places.');
        }
        return $value;
    }

    private function assertLedgerReferences(EisSetting $setting, int $productId, int $variationId): void
    {
        foreach (['purchase_lines', 'transaction_sell_lines', 'stock_adjustment_lines'] as $table) {
            $invalid = $this->db->table($table.' as line')->leftJoin('transactions as t', 't.id', '=', 'line.transaction_id')
                ->where(fn ($q) => $q->where('line.product_id', $productId)->orWhere('line.variation_id', $variationId))
                ->where(fn ($q) => $q->whereNull('t.id')->orWhereNull('t.location_id')
                    ->orWhere('t.business_id', '!=', $setting->business_id)
                    ->orWhere('line.product_id', '!=', $productId)->orWhere('line.variation_id', '!=', $variationId))
                ->select(['line.id', 'line.transaction_id'])->lockForUpdate()->first();
            if ($invalid) {
                throw new \InvalidArgumentException('Product #'.$productId.': orphaned or inconsistent '.$table
                    .' row #'.$invalid->id.' references transaction #'.$invalid->transaction_id
                    .'. Restore or explicitly resolve its ledger history before EIS reconciliation; its original location cannot be inferred safely.');
            }
        }
        $unbacked = $this->db->table('transaction_sell_lines_purchase_lines as allocation')
            ->join('transaction_sell_lines as sale', 'sale.id', '=', 'allocation.sell_line_id')
            ->join('transactions as t', 't.id', '=', 'sale.transaction_id')
            ->leftJoin('purchase_lines as purchase', 'purchase.id', '=', 'allocation.purchase_line_id')
            ->where('t.business_id', $setting->business_id)->where('t.location_id', $setting->location_id)
            ->where('sale.variation_id', $variationId)->whereNull('purchase.id')
            ->select(['allocation.id', 'sale.transaction_id'])->lockForUpdate()->first();
        if ($unbacked) {
            throw new \InvalidArgumentException('Product #'.$productId.' at location #'.$setting->location_id
                .': unresolved overselling allocation #'.$unbacked->id.' for transaction #'.$unbacked->transaction_id
                .' must be repaired against received purchase supply before EIS reconciliation.');
        }
    }

    private function move(EisSetting $setting, array $line, string $delta, int $actorId, int $snapshotId): int
    {
        $increase = bccomp($delta, '0', 4) > 0;
        $quantity = $increase ? $delta : bcsub('0', $delta, 4);
        $total = $this->decimal(bcmul($quantity, $line['cost_inc'], 4));
        $time = now();
        $header = [
            'business_id' => $setting->business_id, 'location_id' => $setting->location_id,
            'type' => $increase ? 'opening_stock' : 'stock_adjustment', 'status' => 'received',
            'payment_status' => 'paid', 'created_by' => $actorId, 'transaction_date' => $time,
            'total_before_tax' => $this->decimal(bcmul($quantity, $line['cost'], 4)),
            'final_total' => $total, 'tax_amount' => '0.0000', 'source' => 'eis_reconciliation',
            'ref_no' => 'EIS-STOCK-'.$snapshotId.'-'.$line['variation_id'],
            'additional_notes' => 'Authoritative EIS stock reconciliation #'.$snapshotId.': '.$line['local'].' -> '.$line['remote'],
            'created_at' => $time, 'updated_at' => $time,
        ];
        if ($increase) {
            $header['opening_stock_product_id'] = $line['product_id'];
            $header['tax_amount'] = bcsub($total, $header['total_before_tax'], 4);
        } else {
            $header['adjustment_type'] = 'normal';
        }
        $transactionId = $this->db->table('transactions')->insertGetId($header);
        $movement = ['transaction_id' => $transactionId, 'product_id' => $line['product_id'],
            'variation_id' => $line['variation_id'], 'quantity' => $quantity, 'created_at' => $time, 'updated_at' => $time];
        if ($increase) {
            $this->db->table('purchase_lines')->insert($movement + [
                'pp_without_discount' => $line['cost'], 'purchase_price' => $line['cost'],
                'purchase_price_inc_tax' => $line['cost_inc'], 'item_tax' => bcsub($line['cost_inc'], $line['cost'], 4),
                'tax_id' => $line['tax_id'], 'quantity_sold' => '0.0000', 'quantity_adjusted' => '0.0000',
                'quantity_returned' => '0.0000', 'mfg_quantity_used' => '0.0000',
            ]);
        } else {
            $adjustmentId = $this->db->table('stock_adjustment_lines')->insertGetId($movement + ['unit_price' => $line['cost_inc']]);
            $remaining = $quantity;
            $purchases = $this->db->table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                ->where('t.business_id', $setting->business_id)->where('t.location_id', $setting->location_id)
                ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
                ->where('t.status', 'received')->where('p.product_id', $line['product_id'])->where('p.variation_id', $line['variation_id'])
                ->orderBy('t.transaction_date', $line['allocation_order'])->orderBy('p.id', $line['allocation_order'])
                ->lockForUpdate()->get(['p.*']);
            foreach ($purchases as $purchase) {
                $available = $this->decimal($purchase->quantity);
                foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) {
                    $available = bcsub($available, $this->decimal($purchase->$field ?? '0'), 4);
                }
                if (bccomp($available, '0', 4) <= 0) {
                    continue;
                }
                $take = bccomp($available, $remaining, 4) > 0 ? $remaining : $available;
                $this->db->table('purchase_lines')->where('id', $purchase->id)->update([
                    'quantity_adjusted' => bcadd($purchase->quantity_adjusted ?? '0', $take, 4), 'updated_at' => $time,
                ]);
                $this->db->table('transaction_sell_lines_purchase_lines')->insert([
                    'stock_adjustment_line_id' => $adjustmentId, 'purchase_line_id' => $purchase->id,
                    'quantity' => $take, 'created_at' => $time, 'updated_at' => $time,
                ]);
                $remaining = bcsub($remaining, $take, 4);
                if (bccomp($remaining, '0', 4) === 0) {
                    break;
                }
            }
            if (bccomp($remaining, '0', 4) !== 0) {
                throw new \RuntimeException('Stock adjustment could not allocate the full decrease. No reconciliation was saved.');
            }
        }
        $stock = $this->db->table('variation_location_details')->where('variation_id', $line['variation_id'])
            ->where('location_id', $setting->location_id);
        if ($stock->exists()) {
            $stock->update(['qty_available' => $line['remote'], 'updated_at' => $time]);
        } else {
            $stock->insert(['product_id' => $line['product_id'], 'product_variation_id' => $line['product_variation_id'],
                'variation_id' => $line['variation_id'], 'location_id' => $setting->location_id,
                'qty_available' => $line['remote'], 'created_at' => $time, 'updated_at' => $time]);
        }
        return $transactionId;
    }
}
