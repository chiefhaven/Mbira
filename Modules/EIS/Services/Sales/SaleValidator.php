<?php

namespace Modules\EIS\Services\Sales;

use App\Transaction;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisTaxMapping;
use Modules\EIS\Models\EisProductMap;
use Modules\EIS\Exceptions\EisSaleException;

class SaleValidator
{
    /**
     * Validate transaction before sending to EIS
     */
    public function validate(Transaction $transaction, object $settings): void
    {
        \Modules\EIS\Services\Terminal\TerminalContext::assertBusiness($settings, (int) $transaction->business_id);
        $transaction->loadMissing([
            'sell_lines.product',
            'contact',
        ]);

        // -------------------------
        // 1. CHECK CUSTOMER (TIN RULES)
        // -------------------------
        if (empty($transaction->contact)) {
            throw new EisSaleException("Customer is required for EIS invoice.");
        }
        if ((int) $transaction->contact->business_id !== (int) $transaction->business_id) {
            throw new EisSaleException('Sale customer belongs to another business terminal.');
        }

        if (empty($transaction->contact->name)) {
            throw new EisSaleException("Customer name is required.");
        }

        // Optional TIN validation (depends on MRA rules)
        if (empty($transaction->contact->tax_number) && $settings->require_tin) {
            throw new EisSaleException("Customer TIN is required for this business.");
        }

        // -------------------------
        // 2. CHECK ITEMS
        // -------------------------
        if ($transaction->sell_lines->isEmpty()) {
            throw new EisSaleException("Invoice has no items.");
        }

        foreach ($transaction->sell_lines as $line) {

            // Product existence
            if (!$line->product) {
                throw new EisSaleException("Missing product in invoice line.");
            }
            if ((int) $line->product->business_id !== (int) $transaction->business_id) {
                throw new EisSaleException('Sale product belongs to another business terminal.');
            }

            // SKU / mapping check
            $map = EisProductMap::where('business_id', $transaction->business_id)
                ->where('product_id', $line->product_id)
                ->first();

            if (!$map) {
                throw new EisSaleException(
                    "Product '{$line->product->name}' is not mapped to EIS."
                );
            }

            // Price validation
            if ($line->unit_price_inc_tax < 0) {
                throw new EisSaleException("Invalid price detected.");
            }

            // Quantity validation
            if ($line->quantity <= 0) {
                throw new EisSaleException("Invalid quantity detected.");
            }
        }

        // -------------------------
        // 3. TAX VALIDATION
        // -------------------------
        foreach ($transaction->sell_lines as $line) {

            $taxId = $line->tax_id;

            if (!$taxId) {
                continue; // allowed for exempt items
            }

            $map = EisTaxMapping::where('business_id', $transaction->business_id)
                ->where('tax_id', $taxId)
                ->first();

            if (!$map) {
                Log::warning("Missing EIS tax mapping", [
                    'business_id' => $transaction->business_id,
                    'tax_id' => $taxId,
                ]);

                throw new EisSaleException(
                    "Tax configuration missing for product '{$line->product->name}'."
                );
            }
        }

        // -------------------------
        // 4. TOTAL VALIDATION (BASIC CHECK)
        // -------------------------
        $calculatedTotal = $transaction->sell_lines->sum(function ($line) {
            return $line->quantity * $line->unit_price_inc_tax;
        });

        if ($calculatedTotal <= 0) {
            throw new EisSaleException("Invalid invoice total.");
        }

        // -------------------------
        // VALIDATION PASSED
        // -------------------------
        Log::info("EIS validation passed", [
            'transaction_id' => $transaction->id,
        ]);
    }
}