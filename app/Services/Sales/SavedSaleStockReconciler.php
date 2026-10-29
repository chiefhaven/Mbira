<?php

namespace App\Services\Sales;

use App\TransactionSellLinesPurchaseLines;
use Illuminate\Database\Connection;

/** Internal receipt reconciliation, NOT a sale editor. Receipt stock has already
 * been added by the canonical purchase path. Only debt allocations and the exact
 * corresponding purchase consumption change here; available stock never does.
 */
final class SavedSaleStockReconciler
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    private function fail($message)
    {
        throw new SavedSplitException('stock_reconciliation_invalid', $message);
    }

    private function remaining(array $purchase)
    {
        $remaining = SavedSaleSplitService::decimal($purchase['quantity']);
        foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) {
            $q = SavedSaleSplitService::decimal($purchase[$field] ?? '0');
            if (bccomp($q, '0', 4) < 0) { $this->fail('Negative receipt consumption is invalid.'); }
            $remaining = bcsub($remaining, $q, 4);
        }
        if (bccomp($remaining, '0', 4) < 0) { $this->fail('Receipt consumption exceeds received supply.'); }
        return $remaining;
    }

    private function allocationTotals(array $graph)
    {
        $totals = [];
        foreach ($graph['mappings'] as $map) {
            $totals[$map['sell_line_id']] = bcadd($totals[$map['sell_line_id']] ?? '0', $map['quantity'], 4);
        }
        ksort($totals, SORT_NUMERIC);
        return $totals;
    }

    private function purchaseTotals(array $graphs)
    {
        $totals = [];
        foreach ($graphs as $graph) {
            foreach ($graph['mappings'] as $map) {
                $totals[$map['purchase_line_id']] = bcadd($totals[$map['purchase_line_id']] ?? '0', $map['quantity'], 4);
            }
        }
        return $this->normalizeTotals($totals);
    }

    private function normalizeTotals(array $totals)
    {
        $totals = array_filter($totals, function ($q) { return bccomp($q, '0', 4) !== 0; });
        ksort($totals, SORT_NUMERIC);
        return $totals;
    }

    /** Called only by the guard, which owns the allocation-saving capability.
     * A savepoint also rolls back partial work if a caller catches an exception.
     */
    public function reconcile($receiptId, callable $saveAllocation)
    {
        $validator = new SavedSaleSplitService($this->db);
        $validator->stockReconciliationStorage();
        return $this->db->transaction(function () use ($receiptId, $saveAllocation, $validator) {
            // Use a CURRENT locking discovery read, not an MVCC snapshot taken
            // before waiting for a concurrent split. Otherwise a moved bill B
            // could be missed while the receipt still has available supply.
            // No purchase rows are locked by this discovery. All sale graphs
            // are then loaded in id order before mapping/purchase writes.
            // Receipt controllers may already hold receipt locks: a MySQL
            // deadlock must roll back/retry the whole receipt, never skip a debt.
            $receipt = $this->db->table('transactions')->where('id', $receiptId)->first();
            if (!$receipt || $receipt->status !== 'received' ||
                !in_array($receipt->type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)) {
                $this->fail('A received ordinary purchase transaction is required.');
            }
            if (!$this->db->table('business_locations')->where('id', $receipt->location_id)
                ->where('business_id', $receipt->business_id)->exists()) {
                $this->fail('Receipt location does not belong to its business.');
            }
            $receiptLines = $this->db->table('purchase_lines')->where('transaction_id', $receiptId)->orderBy('id')->get();
            $pairs = [];
            foreach ($receiptLines as $p) { $pairs[$p->product_id.':'.$p->variation_id] = true; }
            $candidates = $this->db->table('transaction_sell_lines_purchase_lines as m')
                ->join('transaction_sell_lines as l', 'l.id', '=', 'm.sell_line_id')
                ->join('transactions as t', 't.id', '=', 'l.transaction_id')
                ->where('m.purchase_line_id', 0)->where('t.business_id', $receipt->business_id)
                ->where('t.location_id', $receipt->location_id)->where('t.type', 'sell')->where('t.status', 'final')
                ->where(function ($q) use ($receiptLines) {
                    if ($receiptLines->isEmpty()) { $q->whereRaw('1 = 0'); }
                    foreach ($receiptLines as $p) {
                        $q->orWhere(function ($pair) use ($p) {
                            $pair->where('l.product_id', $p->product_id)->where('l.variation_id', $p->variation_id);
                        });
                    }
                })
                ->orderBy('t.id')->orderBy('m.id')->lockForUpdate()->get(['t.id', 'l.product_id', 'l.variation_id']);
            $graphs = [];
            foreach ($candidates as $candidate) {
                if (!isset($pairs[$candidate->product_id.':'.$candidate->variation_id]) || isset($graphs[$candidate->id])) { continue; }
                $graphs[$candidate->id] = $validator->lockedStockGraph($candidate->id, $receipt->business_id);
                // Historical debt remains reconcilable even if overselling was
                // subsequently disabled. This grants no new sale/checkout rights.
                $validator->validateMappings($graphs[$candidate->id], true);
            }
            $lockedReceipt = $this->db->table('transactions')->where('id', $receiptId)->lockForUpdate()->first();
            if ((array) $lockedReceipt !== (array) $receipt) { $this->fail('Receipt changed during reconciliation.'); }
            $purchases = $this->db->table('purchase_lines')->where('transaction_id', $receiptId)->orderBy('id')->lockForUpdate()->get();
            if ($purchases->toJson() !== $receiptLines->toJson()) { $this->fail('Receipt lines changed during reconciliation.'); }
            $stockBefore = $this->db->table('variation_location_details')->where('location_id', $receipt->location_id)
                ->whereIn('product_id', $receiptLines->pluck('product_id')->all())->orderBy('id')->lockForUpdate()->get()->toJson();
            $realMappings = [];
            $originalGraphs = $graphs;
            $expectedTotals = $this->purchaseTotals($graphs);
            $expectedPurchases = [];
            foreach ($graphs as $graph) {
                foreach ($graph['mappings'] as $map) {
                    if ((string) $map['purchase_line_id'] !== '0') { $realMappings[$map['id']] = $map; }
                }
            }
            foreach ($purchases as $row) {
                $purchase = (array) $row;
                $product = $this->db->table('products')->where('id', $row->product_id)->where('business_id', $receipt->business_id)
                    ->whereNull('deleted_at')->first();
                if (!$product || !$this->db->table('variations')->where('id', $row->variation_id)->where('product_id', $row->product_id)->exists()) {
                    $this->fail('Receipt product/variation belongs to another business or is missing.');
                }
                if (!$product->enable_stock) { continue; }
                $available = $this->remaining($purchase);
                $used = '0.0000';
                foreach ($graphs as $id => $graph) {
                    foreach ($graph['mappings'] as $map) {
                        if (bccomp($available, '0', 4) === 0) { break; }
                        if ((string) $map['purchase_line_id'] !== '0') { continue; }
                        $line = null;
                        foreach ($graph['lines'] as $l) { if ($l['id'] == $map['sell_line_id']) { $line = $l; break; } }
                        if (!$line || $line['product_id'] != $row->product_id || $line['variation_id'] != $row->variation_id) { continue; }
                        // Refresh under locks: an earlier receipt line may have
                        // consumed this same sentinel within this reconciliation.
                        $model = TransactionSellLinesPurchaseLines::on($this->db->getName())->findOrFail($map['id']);
                        if ((string) $model->purchase_line_id !== '0') { continue; }
                        $quantity = SavedSaleSplitService::decimal($model->getRawOriginal('quantity'));
                        $take = bccomp($quantity, $available, 4) <= 0 ? $quantity : $available;
                        $rest = bcsub($quantity, $take, 4);
                        // Preserve inert metadata on the residual, not just qty.
                        $residual = $model->replicate();
                        $model->purchase_line_id = $row->id;
                        $model->quantity = $take;
                        $saveAllocation($model);
                        if (bccomp($rest, '0', 4) > 0) {
                            $residual->quantity = $rest;
                            $saveAllocation($residual);
                        }
                        $used = bcadd($used, $take, 4);
                        $available = bcsub($available, $take, 4);
                        $expectedTotals[0] = bcsub($expectedTotals[0], $take, 4);
                        $expectedTotals[$row->id] = bcadd($expectedTotals[$row->id] ?? '0', $take, 4);
                    }
                    // Include residual ids for subsequent receipt lines, while
                    // preserving the ORIGINAL protected graph for assertions.
                    $graphs[$id]['mappings'] = $this->db->table('transaction_sell_lines_purchase_lines')
                        ->whereIn('sell_line_id', array_column($graph['lines'], 'id'))->orderBy('id')->get()
                        ->map(function ($r) { return (array) $r; })->all();
                }
                $expected = $purchase;
                if (bccomp($used, '0', 4) > 0) {
                    $expected['quantity_sold'] = bcadd(SavedSaleSplitService::decimal($purchase['quantity_sold'] ?? '0'), $used, 4);
                    $this->db->table('purchase_lines')->where('id', $row->id)->update(['quantity_sold' => $expected['quantity_sold']]);
                    if ((array) $this->db->table('purchase_lines')->where('id', $row->id)->first() !== $expected) {
                        $this->fail('Receipt write changed fields other than exact consumption.');
                    }
                }
                $expectedPurchases[$row->id] = $expected;
            }
            $afterGraphs = [];
            foreach ($originalGraphs as $id => $before) {
                $after = $validator->lockedStockGraph($id, $receipt->business_id);
                $afterGraphs[$id] = $after;
                $validator->validateMappings($after, true);
                if ($before['header'] !== $after['header'] || $before['lines'] !== $after['lines'] ||
                    $before['payments'] !== $after['payments'] || $before['related'] !== $after['related'] ||
                    $this->allocationTotals($before) !== $this->allocationTotals($after)) {
                    $this->fail('Reconciliation changed the protected sale/payment/fiscal graph or allocation coverage.');
                }
                foreach ($before['purchases'] as $purchase) {
                    if ($purchase['transaction_id'] != $receiptId &&
                        (array) $this->db->table('purchase_lines')->where('id', $purchase['id'])->first() !== $purchase) {
                        $this->fail('Reconciliation changed consumption of an unrelated real purchase.');
                    }
                }
            }
            if ($this->purchaseTotals($afterGraphs) !== $this->normalizeTotals($expectedTotals)) {
                $this->fail('Sentinel reduction and new purchase allocations do not equal exact receipt consumption.');
            }
            if ((array) $this->db->table('transactions')->where('id', $receiptId)->first() !== (array) $receipt) {
                $this->fail('Reconciliation changed the protected receipt header.');
            }
            foreach ($expectedPurchases as $id => $expected) {
                if ((array) $this->db->table('purchase_lines')->where('id', $id)->first() !== $expected) {
                    $this->fail('Reconciliation changed receipt supply or unrelated consumption.');
                }
            }
            foreach ($realMappings as $id => $before) {
                if ((array) $this->db->table('transaction_sell_lines_purchase_lines')->where('id', $id)->first() !== $before) {
                    $this->fail('Reconciliation changed an existing real purchase allocation.');
                }
            }
            $stockAfter = $this->db->table('variation_location_details')->where('location_id', $receipt->location_id)
                ->whereIn('product_id', $receiptLines->pluck('product_id')->all())->orderBy('id')->get()->toJson();
            if ($stockBefore !== $stockAfter) { $this->fail('Reconciliation must not change available stock.'); }
            return true;
        });
    }
}