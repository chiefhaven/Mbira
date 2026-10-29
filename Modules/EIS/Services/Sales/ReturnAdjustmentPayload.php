<?php

namespace Modules\EIS\Services\Sales;

final class ReturnAdjustmentPayload
{
    public function build(array $original, array $lines, float $returnTotal, string $invoiceNumber): array
    {
        $items = $original['invoiceLineItems'] ?? [];
        $summary = $original['invoiceSummary'] ?? [];
        if (!is_array($items) || !array_is_list($items) || !$items || !is_array($summary)
            || count($items) !== count($lines) || !empty($summary['levyBreakDown'])) {
            throw new \InvalidArgumentException('This invoice requires a reviewed levy/service-line adjustment; automatic return mapping is unavailable.');
        }
        $remaining = []; $taxes = []; $total = 0; $vat = 0; $originalTotal = 0; $originalVat = 0; $returnedTotal = 0;
        foreach (array_values($lines) as $index => $line) {
            $item = $items[$index];
            if (!is_array($item)) { throw new \InvalidArgumentException('Original fiscal line is invalid.'); }
            $quantity = (float) $line['quantity'];
            $returned = (float) $line['quantity_returned'];
            if (!is_finite($quantity) || !is_finite($returned) || $quantity <= 0 || $returned < 0 || $returned > $quantity
                || abs((float) ($item['quantity'] ?? -1) - $quantity) > 0.00001
                || ($item['isProduct'] ?? null) !== true || empty($item['productCode'])
                || empty($item['taxRateId']) || !empty($line['parent_sell_line_id'])) {
                throw new \InvalidArgumentException('Return quantities cannot be mapped exactly to the original fiscal invoice.');
            }
            foreach (['unitPrice', 'total', 'totalVAT'] as $field) {
                if (!isset($item[$field]) || !is_numeric($item[$field]) || !is_finite((float) $item[$field]) || $item[$field] < 0) {
                    throw new \InvalidArgumentException('Original fiscal invoice contains invalid monetary values.');
                }
                if ((string) $item['productCode'] !== (string) ($line['sku'] ?? '')
                    || round((float) $item['unitPrice'], 2) !== round((float) ($line['unit_price_inc_tax'] ?? -1), 2)
                    || round((float) $item['totalVAT'], 2) !== round((float) ($line['item_tax'] ?? -1) * $quantity, 2)) {
                    throw new \InvalidArgumentException('Fiscal product identity, price or tax changed after the original sale.');
                }
                if (isset($item['discount']) && (!is_numeric($item['discount']) || !is_finite((float) $item['discount']) || $item['discount'] < 0)) {
                    throw new \InvalidArgumentException('Original fiscal line discount is invalid.');
                }
            }
            if (round((float) $item['total'], 2) !== round((float) $item['unitPrice'] * $quantity, 2)) {
                throw new \InvalidArgumentException('Original fiscal line totals require manual adjustment review.');
            }
            $originalTotal += (float) $item['total'];
            $originalVat += (float) $item['totalVAT'];
            $ratio = ($quantity - $returned) / $quantity;
            $copy = array_intersect_key($item, array_flip(['id', 'productCode', 'description', 'unitPrice',
                'quantity', 'discount', 'total', 'totalVAT', 'taxRateId', 'isProduct']));
            $copy['quantity'] = $quantity - $returned;
            $copy['total'] = round((float) $item['total'] * $ratio, 2);
            $copy['totalVAT'] = round((float) $item['totalVAT'] * $ratio, 2);
            $returnedTotal += (float) $item['total'] - $copy['total'];
            $total += $copy['total']; $vat += $copy['totalVAT'];
            $tax = $copy['taxRateId'];
            $taxes[$tax] ??= ['rateId' => $tax, 'taxableAmount' => 0, 'taxAmount' => 0];
            $taxes[$tax]['taxableAmount'] = round($taxes[$tax]['taxableAmount'] + $copy['total'] - $copy['totalVAT'], 2);
            $taxes[$tax]['taxAmount'] = round($taxes[$tax]['taxAmount'] + $copy['totalVAT'], 2);
            // Keep zero-quantity lines so a full return retains the original line identity.
            $remaining[] = $copy;
        }
        if (!is_finite($returnTotal) || $returnTotal < 0 || round($originalTotal, 2) !== round((float) ($summary['invoiceTotal'] ?? -1), 2)
            || round($originalVat, 2) !== round((float) ($summary['totalVAT'] ?? -1), 2)
            || round($returnedTotal, 2) !== round($returnTotal, 2)) {
            throw new \InvalidArgumentException('POS return value does not match the original fiscal line values. Invoice discounts, charges or changed prices require manual review.');
        }
        $header = array_intersect_key($original['invoiceHeader'] ?? [], array_flip(['invoiceNumber', 'invoiceDateTime',
            'sellerTIN', 'buyerTIN', 'buyerName', 'buyerAuthorizationCode', 'siteId', 'globalConfigVersion',
            'taxpayerConfigVersion', 'terminalConfigVersion', 'isExport', 'isReliefSupply', 'vat5CertificateDetails', 'paymentMethod']));
        foreach (['invoiceDateTime', 'sellerTIN', 'siteId', 'globalConfigVersion', 'taxpayerConfigVersion', 'terminalConfigVersion'] as $key) {
            if (!isset($header[$key])) { throw new \InvalidArgumentException('Original fiscal invoice header is incomplete.'); }
        }
        $header['invoiceNumber'] = $invoiceNumber;
        return ['invoiceHeader' => $header, 'invoiceLineItems' => $remaining,
            'invoiceSummary' => ['taxBreakDown' => array_values($taxes), 'levyBreakDown' => [],
                'totalVAT' => round($vat, 2), 'invoiceTotal' => round($total, 2), 'amountTendered' => round($total, 2)],
            'reasonForAdjustment' => 'POS return / return reduction'];
    }
}
