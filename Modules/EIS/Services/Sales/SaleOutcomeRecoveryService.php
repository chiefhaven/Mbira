<?php

namespace Modules\EIS\Services\Sales;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisSale;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;

class SaleOutcomeRecoveryService
{
    public function __construct(private SalesApiClient $client) {}

    public function sync(EisSale $sale, EisSetting $setting): array
    {
        $payload = $sale->request_payload;
        $invoiceNumber = $sale->getRawOriginal('fiscal_invoice_number');
        if (!is_array($payload) || !is_string($invoiceNumber) || trim($invoiceNumber) === ''
            || ($payload['invoiceHeader']['invoiceNumber'] ?? null) !== $invoiceNumber
            || (int) $sale->business_id !== (int) $setting->business_id
            || (int) $sale->location_id !== (int) $setting->location_id
            || (int) $sale->eis_setting_id !== (int) $setting->id) {
            throw new \InvalidArgumentException('This sale has no complete invoice payload for the selected terminal. No local data was changed.');
        }

        $lookup = $this->client->send($setting, 'get-invoice-by-number', ['invoiceNumber' => $invoiceNumber]);
        $data = $lookup['response']['data'] ?? [];
        
        Log::info('EIS invoice lookup completed', [
            'invoice_number' => $invoiceNumber,
            'outcome' => $lookup['outcome'] ?? null,
            'http_status' => $lookup['http_status'] ?? null,
        ]);
        
        if (($lookup['outcome'] ?? null) !== 'accepted' || !$this->matches($data, $payload, $setting)) {
            throw new \UnexpectedValueException('EIS did not return an invoice that exactly matches this saved sale. No local data was changed.');
        }

        $url = array_key_exists('validationURL', $data)
            ? $data['validationURL']
            : ($data['invoiceSummary']['validationURL'] ?? null);
        $url = is_string($url) && $url !== '' ? $url : null;
        $receiptNumber = $this->receiptNumber($url) ?? $this->explicitReceiptNumber($data);
        $receiptSignature = $this->receiptSignature($url);

        return $sale->getConnection()->transaction(function () use (
            $sale,
            $setting,
            $payload,
            $lookup,
            $data,
            $url,
            $receiptNumber,
            $receiptSignature,
            $invoiceNumber
        ) {
            $current = EisSale::where('business_id', $sale->business_id)
                ->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ((int) $current->eis_setting_id !== (int) $setting->id
                || (int) $current->location_id !== (int) $setting->location_id
                || $current->getRawOriginal('fiscal_invoice_number') !== $invoiceNumber
                || $current->request_payload !== $payload) {
                throw new \UnexpectedValueException('The saved sale changed during EIS lookup. No local data was synchronized.');
            }

            // Offline-signed invoices keep their original offline QR/receipt when EIS returns no official URL.
            $offlineUrl = !empty($payload['invoiceSummary']['offlineSignature'])
                && is_string($payload['invoiceSummary']['validationURL'] ?? null)
                && $payload['invoiceSummary']['validationURL'] !== ''
                    ? $payload['invoiceSummary']['validationURL'] : null;
            $keepOffline = $url === null && $receiptNumber === null && $offlineUrl !== null;

            $updates = [
                'status' => 'submitted',
                'fiscal_invoice_number' => $invoiceNumber,
                'receipt_number' => $receiptNumber ?? $current->receipt_number,
                'receipt_signature' => $keepOffline
                    ? ($current->receipt_signature ?: $this->receiptSignature($current->qr_code ?: $offlineUrl))
                    : ($receiptSignature ?? $current->receipt_signature),
                'qr_code' => $url ?? ($current->qr_code ?: ($keepOffline ? $offlineUrl : null)),
                'response_payload' => $lookup['response'],
                'error_message' => null,
                'submitted_at' => isset($data['dateSubmitted'])
                    ? Carbon::parse($data['dateSubmitted'])
                    : ($current->submitted_at ?? now()),
            ];
            $current->update($updates);

            return [
                'id' => $current->id,
                'transaction_id' => $current->transaction_id,
                'invoice_number' => $invoiceNumber,
                'status' => $current->status,
                'receipt_number' => $current->receipt_number,
                'receipt_available' => $receiptNumber !== null,
                'offline_receipt_preserved' => $keepOffline,
                'submitted_at' => $current->submitted_at,
                'response' => $lookup['response'],
            ];
        });
    }

    public function resolve(EisSale $sale, EisSetting $setting, array $payload, array $submissionResponse, string $kind): string
    {
        $invoiceNumber = $payload['invoiceHeader']['invoiceNumber'] ?? null;
        if (!is_string($invoiceNumber) || $invoiceNumber === '') {
            return 'unresolved';
        }

        try {
            $lookup = $this->client->send($setting, 'get-invoice-by-number', ['invoiceNumber' => $invoiceNumber]);
        } catch (\Throwable $exception) {
            Log::warning('EIS invoice outcome lookup failed', [
                'transaction_id' => $sale->transaction_id,
                'eis_invoice_number' => $invoiceNumber,
                'error' => $exception->getMessage(),
            ]);
            $this->saveUnresolved($sale, $submissionResponse, $kind, 'EIS invoice lookup was unavailable.');
            return 'unresolved';
        }

        $data = $lookup['response']['data'] ?? [];
        if ($lookup['outcome'] === 'accepted' && $this->matches($data, $payload, $setting)) {
            $url = $data['validationURL'] ?? $data['invoiceSummary']['validationURL'] ?? null;
            $url = is_string($url) && $url !== '' ? $url : null;
            $receiptNumber = $this->receiptNumber(is_string($url) ? $url : null)
                ?? $this->explicitReceiptNumber($data);
            $receiptSignature = $this->receiptSignature($url);
            $updates = [
                'status' => 'submitted',
                'response_payload' => [
                    'submission_attempt' => $submissionResponse,
                    'read_only_lookup' => $lookup['response'],
                    'recovery' => [
                        'state' => 'verified',
                        'verified_at' => now()->toIso8601String(),
                        'receipt_available' => $receiptNumber !== null,
                    ],
                ],
                'fiscal_invoice_number' => $invoiceNumber,
                'error_message' => null,
                'submitted_at' => isset($data['dateSubmitted']) ? Carbon::parse($data['dateSubmitted']) : now(),
            ];
            if ($receiptNumber !== null) {
                $updates['receipt_number'] = $receiptNumber;
            }
            if ($url !== null) {
                $updates['qr_code'] = $url;
            }
            if ($receiptSignature !== null) {
                $updates['receipt_signature'] = $receiptSignature;
            }
            $sale->update($updates);

            Log::info('EIS ambiguous sale outcome reconciled by exact invoice lookup', [
                'transaction_id' => $sale->transaction_id,
                'eis_invoice_number' => $invoiceNumber,
                'receipt_available' => $receiptNumber !== null,
            ]);
            return 'verified';
        }

        if ($kind === 'ambiguous' && $this->isExplicitlyNotFound($lookup)) {
            $sale->update([
                'response_payload' => [
                    'submission_attempt' => $submissionResponse,
                    'read_only_lookup' => $lookup['response'],
                    'recovery' => [
                        'state' => 'confirmed_absent',
                        'verified_at' => now()->toIso8601String(),
                    ],
                ],
            ]);
            return 'absent';
        }

        $message = $lookup['outcome'] === 'accepted'
            ? 'EIS lookup returned an invoice that does not exactly match the saved sale.'
            : 'EIS could not confirm whether the invoice was accepted.';
        $this->saveUnresolved($sale, $submissionResponse, $kind, $message, $lookup['response'] ?? []);
        return 'unresolved';
    }

    private function matches(array $data, array $payload, EisSetting $setting): bool
    {
        $expectedHeader = $payload['invoiceHeader'] ?? [];
        $header = $data['invoiceHeader'] ?? [];
        if (($header['invoiceNumber'] ?? null) !== ($expectedHeader['invoiceNumber'] ?? null)
            || (string) ($header['sellerTIN'] ?? '') !== (string) ($expectedHeader['sellerTIN'] ?? '')
            || (string) ($header['siteId'] ?? '') !== (string) TerminalContext::site($setting)
            || !$this->sameDate($header['invoiceDateTime'] ?? null, $expectedHeader['invoiceDateTime'] ?? null)) {
            return false;
        }

        $expectedLines = $payload['invoiceLineItems'] ?? null;
        $actualLines = $data['invoiceLineItems'] ?? null;
        if (!is_array($expectedLines) || !is_array($actualLines) || count($expectedLines) !== count($actualLines)) {
            return false;
        }
        if ($this->normalizeLines($expectedLines) !== $this->normalizeLines($actualLines)) {
            return false;
        }

        $expectedSummary = $payload['invoiceSummary'] ?? [];
        $summary = $data['invoiceSummary'] ?? [];
        foreach (['invoiceTotal', 'totalVAT'] as $field) {
            if (!is_numeric($expectedSummary[$field] ?? null) || !is_numeric($summary[$field] ?? null)
                || round((float) $expectedSummary[$field], 2) !== round((float) $summary[$field], 2)) {
                return false;
            }
        }
        return true;
    }

    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                return [];
            }
            foreach (['productCode', 'quantity', 'unitPrice', 'discount', 'total', 'taxRateId', 'isProduct'] as $field) {
                if (!array_key_exists($field, $line)) {
                    return [];
                }
            }
            if (!is_numeric($line['quantity']) || !is_numeric($line['unitPrice'])
                || ($line['discount'] !== null && !is_numeric($line['discount']))
                || !is_numeric($line['total']) || !is_bool($line['isProduct'])) {
                return [];
            }
            $normalized[] = [
                'productCode' => (string) $line['productCode'],
                'quantity' => number_format((float) $line['quantity'], 2, '.', ''),
                'unitPrice' => number_format((float) $line['unitPrice'], 2, '.', ''),
                'discount' => number_format((float) ($line['discount'] ?? 0), 2, '.', ''),
                'total' => number_format((float) $line['total'], 2, '.', ''),
                'taxRateId' => (string) $line['taxRateId'],
                'isProduct' => $line['isProduct'],
            ];
        }
        usort($normalized, fn ($left, $right) => $left <=> $right);
        return $normalized;
    }

    private function sameDate(mixed $actual, mixed $expected): bool
    {
        if (!is_string($actual) || !is_string($expected) || $actual === '' || $expected === '') {
            return false;
        }
        try {
            $actualHasTimezone = preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $actual) === 1;
            $expectedHasTimezone = preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $expected) === 1;
            if ($actualHasTimezone && $expectedHasTimezone) {
                return Carbon::parse($actual)->timestamp === Carbon::parse($expected)->timestamp;
            }
            return Carbon::parse($actual)->format('Y-m-d H:i:s')
                === Carbon::parse($expected)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return false;
        }
    }

    private function isExplicitlyNotFound(array $lookup): bool
    {
        if (($lookup['outcome'] ?? null) !== 'rejected') {
            return false;
        }
        $remark = $lookup['response']['remark'] ?? '';
        return is_string($remark)
            && preg_match('/\binvoice\b.*\b(not found|does not exist|doesn\'t exist)\b/i', $remark) === 1;
    }

    private function saveUnresolved(
        EisSale $sale,
        array $submissionResponse,
        string $kind,
        string $message,
        array $lookupResponse = []
    ): void {
        $sale->update([
            'status' => 'failed',
            'response_payload' => [
                'submission_attempt' => $submissionResponse,
                'read_only_lookup' => $lookupResponse,
                'recovery' => ['state' => 'lookup_required', 'kind' => $kind],
            ],
            'error_message' => $message,
        ]);
    }

    private function receiptNumber(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parameters);
        return isset($parameters['vc']) && is_string($parameters['vc']) && $parameters['vc'] !== ''
            ? $parameters['vc']
            : null;
    }

    private function receiptSignature(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parameters);
        foreach (['S', 'c'] as $key) {
            if (isset($parameters[$key]) && is_string($parameters[$key]) && $parameters[$key] !== '') {
                return $parameters[$key];
            }
        }
        return null;
    }

    private function explicitReceiptNumber(array $data): ?string
    {
        $number = $data['receiptNumber'] ?? $data['invoiceSummary']['receiptNumber'] ?? null;
        return is_string($number) && trim($number) !== '' ? trim($number) : null;
    }
}
