<?php

namespace Modules\EIS\Services\Utilities;

use Illuminate\Support\Facades\DB;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\EisSubscriptionAccess;

final class VoidReceiptSynchronizer
{
    public function sync(EisSetting $setting, array $items): array
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $setting->business_id);

        foreach (['void_status', 'void_approval_status', 'void_synced_at', 'void_response_payload'] as $column) {
            if (!DB::getSchemaBuilder()->hasColumn('eis_sales', $column)) {
                throw new \RuntimeException('EIS void receipt sync storage is missing. Run the EIS migrations before syncing.');
            }
        }

        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['invoiceNumber'] ?? null)
                || trim($item['invoiceNumber']) === '' || !is_string($item['approvalStatus'] ?? null)
                || trim($item['approvalStatus']) === '') {
                throw new \UnexpectedValueException('EIS returned a void receipt without a valid invoiceNumber and approvalStatus. No local invoices were changed.');
            }
            $validated[] = $item;
        }

        return DB::transaction(function () use ($setting, $validated) {
            $synced = 0;
            $voided = 0;
            $unmatched = 0;
            $ambiguous = 0;
            $duplicateNumbers = array_keys(array_filter(array_count_values(array_map(
                fn ($item) => trim($item['invoiceNumber']),
                $validated
            )), fn ($count) => $count > 1));

            foreach ($validated as $item) {
                $invoiceNumber = trim($item['invoiceNumber']);
                if (in_array($invoiceNumber, $duplicateNumbers, true)) {
                    ++$ambiguous;
                    continue;
                }

                $sales = DB::table('eis_sales')->where('business_id', $setting->business_id)
                    ->where('location_id', $setting->location_id)->where('eis_setting_id', $setting->id)
                    ->where('status', 'submitted')
                    ->where(function ($query) use ($invoiceNumber) {
                        foreach (['invoice_number', 'eis_invoice_number', 'fiscal_invoice_number', 'receipt_number'] as $column) {
                            if (DB::getSchemaBuilder()->hasColumn('eis_sales', $column)) {
                                $query->orWhere($column, $invoiceNumber);
                            }
                        }
                    })->lockForUpdate()->get();

                if ($sales->isEmpty()) {
                    ++$unmatched;
                    continue;
                }
                if ($sales->count() !== 1) {
                    ++$ambiguous;
                    continue;
                }

                $sale = $sales->first();
                $isApproved = $item['approvalStatus'] === 'Approved';
                $alreadyVoid = ($sale->void_status ?? null) === 'void';
                $update = [
                    'void_approval_status' => $alreadyVoid && !$isApproved
                        ? ($sale->void_approval_status ?: 'Approved') : trim($item['approvalStatus']),
                    'void_synced_at' => now(),
                    'void_response_payload' => json_encode($item, JSON_THROW_ON_ERROR),
                ];
                if (DB::getSchemaBuilder()->hasColumn('eis_sales', 'updated_at')) {
                    $update['updated_at'] = now();
                }
                if ($isApproved || $alreadyVoid) {
                    $update['void_status'] = 'void';
                }

                DB::table('eis_sales')->where('id', $sale->id)->update($update);
                ++$synced;
                if ($isApproved || $alreadyVoid) {
                    ++$voided;
                }
            }

            return [
                'received' => count($validated),
                'synced' => $synced,
                'voided' => $voided,
                'unmatched' => $unmatched,
                'ambiguous' => $ambiguous,
            ];
        });
    }
}
