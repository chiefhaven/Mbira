<?php

namespace Modules\EIS\Services\Sales;

use App\Transaction;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisSale;

class SaleResponseService
{
    /**
     * Handle EIS response
     */
    public function handle(Transaction $transaction, array $response): void
    {
        Log::debug('Processing EIS sale response', [
            'transaction_id' => $transaction->id,
            'status_code' => $response['data']['statusCode'] ?? null,
            'remark' => $response['data']['remark'] ?? null,
        ]);

        $eisSale = EisSale::where('transaction_id', $transaction->id)->first();

        if (!$eisSale) {
            Log::warning('EIS sale record not found', [
                'transaction_id' => $transaction->id,
            ]);
            return;
        }

        // Check if response indicates success
        if (!$this->isSuccessfulResponse($response)) {
            $this->handleErrorResponse($transaction, $eisSale, $response);
            return;
        }

        // Extract data from response
        $data = $response['data']['data'] ?? [];
        $validationUrl = $data['validationURL'] ?? null;
        $receiptNumber = $this->extractReceiptFromUrl($validationUrl);

        // -------------------------
        // UPDATE EIS TRACKING
        // -------------------------
        $updates = [
            'status' => 'submitted',
            'response_payload' => $response,
            'fiscal_invoice_number' => $eisSale->eis_invoice_number
                ?: $eisSale->getRawOriginal('fiscal_invoice_number'),
            'submitted_at' => now(),
        ];
        if ($receiptNumber !== null && $receiptNumber !== '') {
            $updates['receipt_number'] = $receiptNumber;
        }
        if (is_string($validationUrl) && trim($validationUrl) !== '') {
            $updates['qr_code'] = $validationUrl;
        }
        $eisSale->update($updates);

        // -------------------------
        // UPDATEPOS TRANSACTION
        // -------------------------
        \App\Services\Sales\SavedSaleWriteGuard::recordFiscalReceipt($transaction->getConnection(), $transaction->id);
        $transaction->refresh();

        // -------------------------
        // LOG SUCCESS
        // -------------------------
        Log::info('EIS sale completed successfully', [
            'transaction_id' => $transaction->id,
            'receipt_number' => $receiptNumber,
            'validation_url' => $validationUrl,
        ]);
    }

    /**
     * Handle error response
     */
    private function handleErrorResponse(Transaction $transaction, EisSale $eisSale, array $response): void
    {
        $errorMessage = $response['data']['remark'] ?? 'Unknown error';
        
        // Check for validation errors
        $validationErrors = $response['data']['validationErrors'] ?? null;
        if ($validationErrors) {
            if (is_array($validationErrors)) {
                $errorMessage .= ': ' . implode(', ', $validationErrors);
            } else {
                $errorMessage .= ': ' . $validationErrors;
            }
        }

        $eisSale->update([
            'status' => 'failed',
            'response_payload' => $response,
            'error_message' => $errorMessage,
            'submitted_at' => now(),
        ]);

        Log::error('EIS sale failed', [
            'transaction_id' => $transaction->id,
            'status_code' => $response['data']['statusCode'] ?? null,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Check if response indicates success
     */
    private function isSuccessfulResponse(array $response): bool
    {
        return ($response['data']['statusCode'] ?? null) === 1;
    }

    /**
     * Extract receipt number from validation URL
     */
    private function extractReceiptFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        // Parse URL to extract query parameters
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);
        
        // Extract 'vc' parameter from URL
        return $params['vc'] ?? null;
    }

    /**
     * Get validation URL from response
     */
    public function getValidationUrl(array $response): ?string
    {
        return $response['data']['validationURL'] ?? null;
    }

    /**
     * Check if response requires terminal block
     */
    public function shouldBlockTerminal(array $response): bool
    {
        return (bool) ($response['data']['shouldBlockTerminal'] ?? false);
    }

    /**
     * Check if response requires config download
     */
    public function shouldDownloadConfig(array $response): bool
    {
        return (bool) ($response['data']['shouldDownloadLatestConfig'] ?? false);
    }

    /**
     * Get validation errors from response
     */
    public function getValidationErrors(array $response): ?array
    {
        $errors = $response['data']['validationErrors'] ?? null;
        
        if ($errors === null) {
            return null;
        }

        return is_array($errors) ? $errors : [$errors];
    }
}