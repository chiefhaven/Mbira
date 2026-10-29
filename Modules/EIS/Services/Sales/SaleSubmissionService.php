<?php

namespace Modules\EIS\Services\Sales;

use App\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisSale;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Http\EisSaleClient;
use Modules\EIS\Exceptions\EisSaleException;
use Modules\EIS\Services\Sales\InvoiceNumberGenerator;
use Modules\EIS\Services\Sales\SaleResponseService;
use Modules\EIS\Services\EisSubscriptionAccess;

class SaleSubmissionService
{
    public function __construct(
        protected SaleTransformer $transformer,
        protected EisSaleClient $client,
        protected SaleResponseService $responseService,
        protected InvoiceNumberGenerator $invoiceGenerator,
        protected ?SaleOutcomeRecoveryService $outcomeRecovery = null
    ) {}

    /**
     * Submit sale transaction to EIS.
     *
     * @param Transaction $transaction
     * @param object $settings
     * @return EisSale
     * @throws EisSaleException
     */
    public function submit(Transaction $transaction, object $settings, bool $forceOffline = false): EisSale
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) $transaction->business_id);

        return Cache::lock('eis-sale-'.$transaction->business_id.'-'.$transaction->id, 300)
            ->block(10, fn () => $this->submitLocked($transaction, $settings, $forceOffline));
    }

    private function submitLocked(Transaction $transaction, object $settings, bool $forceOffline): EisSale
    {
        \Modules\EIS\Services\Terminal\TerminalContext::assertBusiness($settings, (int) $transaction->business_id);
        // Fence before invoice counters, tracking or any external call.
        \App\Services\Sales\SavedSaleWriteGuard::fiscal($transaction->getConnection(), $transaction->id);
        $transaction->refresh();
        \Modules\EIS\Services\Terminal\TerminalContext::assertBusiness($settings, (int) $transaction->business_id);
        // ----------------------------
        // IDEMPOTENCY CHECK (BEFORE DB TX)
        // ----------------------------
        $existing = EisSale::where('transaction_id', $transaction->id)->first();
        if ($existing && (int) $existing->business_id !== (int) $transaction->business_id) {
            throw new EisSaleException('EIS sale tracking belongs to a different business terminal.');
        }

        if ($existing && $existing->status === 'submitted') {
            $recovery = $existing->response_payload['recovery'] ?? [];
            if (($recovery['state'] ?? null) === 'verified' && empty($recovery['receipt_available'])) {
                return $existing;
            }
            \App\Services\Sales\SavedSaleWriteGuard::recordFiscalReceipt($transaction->getConnection(), $transaction->id);
            $transaction->refresh();
            return $existing;
        }

        // ----------------------------
        // GET EIS SETTINGS
        // ----------------------------
        $eisSetting = \Modules\EIS\Services\Terminal\TerminalContext::forSale($transaction);
        $settings = $eisSetting;
        if ($transaction->location_id) {
            $location = \App\BusinessLocation::where('business_id', $transaction->business_id)->find($transaction->location_id);
            if (!$location || (string) $location->eis_site_id !== \Modules\EIS\Services\Terminal\TerminalContext::site($settings)) {
                throw new EisSaleException('Sale location does not match the business terminal site.');
            }
        }

        $recovery = $existing?->response_payload['recovery'] ?? [];
        if (in_array($recovery['state'] ?? null, ['lookup_required', 'confirmed_absent'], true)) {
            $kind = ($recovery['kind'] ?? null) === 'duplicate' ? 'duplicate' : 'ambiguous';
            $resolution = $this->outcomeRecovery()->resolve(
                $existing,
                $eisSetting,
                $existing->request_payload ?? [],
                $existing->response_payload['submission_attempt'] ?? [],
                $kind
            );
            if ($resolution === 'verified') {
                return $existing->refresh();
            }
            if ($resolution !== 'absent' || $kind !== 'ambiguous') {
                throw new EisSaleException(
                    'EIS submission outcome is unresolved. The exact invoice will be looked up again before another submission.',
                    $kind === 'duplicate' ? EisSaleException::DUPLICATE_INVOICE : 0
                );
            }
        }

        // ----------------------------
        // GENERATE EIS Number
        // ----------------------------
        // Inspect history before resetting tracking; pending may have reached EIS.
        $failedTransaction = DB::table('eis_failed_transactions')
            ->where('business_id', $transaction->business_id)
            ->where('transaction_id', $transaction->id)
            ->first();
        $storedOfflineSignatureData = $this->storedOfflineSignatureData(
            is_array($existing?->request_payload) ? $existing->request_payload : null
        )
            ?? $this->storedFailedPayloadSignature($failedTransaction?->payload);
        $includeOfflineSignature = $forceOffline || ($existing && ($existing->status === 'failed' || !empty($existing->request_payload)))
            || $failedTransaction !== null;
        // Split fiscal guards require a top-level transaction; lock allocation without enclosing their model saves.
        [$eisSale, $eisInvoiceNumber] = Cache::lock('eis-invoice-terminal-'.$eisSetting->id, 60)->block(10, function () use ($transaction, $eisSetting) {
            $tracking = EisSale::where('transaction_id', $transaction->id)->first();
            $number = $tracking?->eis_invoice_number ?? $tracking?->fiscal_invoice_number
                ?? $this->generateEISInvoiceNumber($transaction, $eisSetting);
            $sale = EisSale::updateOrCreate(['transaction_id' => $transaction->id], [
                'business_id' => $transaction->business_id, 'invoice_number' => $transaction->invoice_no,
                'fiscal_invoice_number' => $number, 'receipt_number' => $number, 'status' => 'pending',
                'eis_invoice_number' => $number,
                'location_id' => $transaction->location_id, 'eis_setting_id' => $eisSetting->id,
            ]);
            return [$sale, $number];
        });

        // Only a sale paid in full is submitted (see SaleSubmissionRule).
        if (! SaleSubmissionRule::isPaid($transaction)) {
            return $eisSale;
        }

        $payload = [];
        $duplicateInvoice = false;

        try {
            // ----------------------------
            // TRANSFORM WITH EIS Number
            // ----------------------------
            // Fiscal creation has its own date; retries retain the invoice date already sent.
            $fiscalTransaction = clone $transaction;
            $fiscalTransaction->transaction_date = $existing?->request_payload['invoiceHeader']['invoiceDateTime']
                ?? $eisSale->created_at->toIso8601String();
            $payload = $this->transformer->transform(
                $fiscalTransaction,
                $settings,
                $eisInvoiceNumber,
                $includeOfflineSignature,
                $storedOfflineSignatureData
            );

            // Persist retry metadata before HTTP, without adding it to the first online request.
            $this->prepareOfflineValidationData($transaction, $settings, $eisSale, $payload);
            if ($includeOfflineSignature && $this->storedOfflineSignatureData($payload) === null) {
                throw new EisSaleException('A complete offline signature and validation URL are required for deferred EIS submission.');
            }

            // ----------------------------
            // SUBMIT (OUTSIDE DB TRANSACTION)
            // ----------------------------
            $response = $this->client->submit($payload, $settings);

            // The client returns failures instead of throwing. Persist these so
            // queue/manual retries are recognized and include the signature.
            if (! ($response['success'] ?? false) || ($response['data']['statusCode'] ?? null) !== 1) {
                $eisSale->update(['response_payload' => $response]);
                $payload = $this->prepareOfflineValidationData($transaction, $settings, $eisSale, $payload);
                if (($response['error_code'] ?? null) === 'DUPLICATE_INVOICE') {
                    $duplicateInvoice = true;
                    $resolution = $this->outcomeRecovery()->resolve($eisSale, $eisSetting, $payload, $response, 'duplicate');
                    if ($resolution === 'verified') {
                        return $eisSale->refresh();
                    }
                    throw new EisSaleException(
                        'EIS reports this invoice number already exists. Automatic lookup could not verify an exact match; no further submission will be attempted.',
                        EisSaleException::DUPLICATE_INVOICE
                    );
                }
                if ($this->isAmbiguousSubmissionResponse($response)) {
                    $resolution = $this->outcomeRecovery()->resolve($eisSale, $eisSetting, $payload, $response, 'ambiguous');
                    if ($resolution === 'verified') {
                        return $eisSale->refresh();
                    }
                    if ($resolution !== 'absent') {
                        throw new EisSaleException(
                            'EIS submission outcome is unresolved. The exact invoice will be looked up again before another submission.'
                        );
                    }
                }
                throw new EisSaleException($response['error'] ?? $response['data']['remark'] ?? 'EIS rejected the invoice');
            }

            // ----------------------------
            // SINGLE SOURCE OF TRUTHUPDAT
            // ----------------------------
            $this->responseService->handle($transaction, $response);

            return $eisSale->refresh();

        } catch (EisSaleException $e) {

            Log::error('EIS Sale Submission Failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'eis_invoice_number' => $eisInvoiceNumber ?? null,
            ]);

            $payload = $this->prepareOfflineValidationData($transaction, $settings, $eisSale, $payload);

            $eisSale->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // -------------------------
            // SAFE RETRY STORAGE
            // -------------------------
            if ($duplicateInvoice) {
                DB::table('eis_failed_transactions')
                    ->where('business_id', $transaction->business_id)
                    ->where('transaction_id', $transaction->id)
                    ->delete();
            } else {
                $this->storeFailedTransaction($transaction, $payload, $e->getMessage());
            }

            throw $e;
        }
    }

    private function outcomeRecovery(): SaleOutcomeRecoveryService
    {
        return $this->outcomeRecovery ?? app(SaleOutcomeRecoveryService::class);
    }

    private function prepareOfflineValidationData(
        Transaction $transaction,
        object $settings,
        EisSale $sale,
        array $payload
    ): array {
        try {
            $offlineData = $this->storedOfflineSignatureData($payload);
            $storedPayload = $sale->request_payload;
            if ($offlineData === null && is_array($storedPayload)
                && ($storedPayload['invoiceHeader']['invoiceNumber'] ?? null) === ($payload['invoiceHeader']['invoiceNumber'] ?? null)) {
                $offlineData = $this->storedOfflineSignatureData($storedPayload);
            }
            $offlineData ??= $this->transformer->generateOfflineValidationData($settings, $transaction, $payload);
            $validationUrl = $offlineData['validationURL'] ?? null;
            $offlineSignature = $offlineData['offlineDataSignature'] ?? null;
            if (!is_string($validationUrl) || trim($validationUrl) === ''
                || !is_string($offlineSignature) || trim($offlineSignature) === '') {
                throw new \UnexpectedValueException('EIS did not produce a complete offline validation receipt.');
            }
            $payload['offlineSignatureData'] = $offlineData;
            $payload['invoiceSummary']['offlineSignature'] = $offlineSignature;
            $payload['invoiceSummary']['validationURL'] = $validationUrl;
            $sale->update(['qr_code' => $validationUrl, 'request_payload' => $payload]);
        } catch (\InvalidArgumentException|\UnexpectedValueException $exception) {
            Log::error('Failed to create EIS offline receipt retry data', [
                'transaction_id' => $transaction->id,
                'eis_invoice_number' => $payload['invoiceHeader']['invoiceNumber'] ?? null,
                'error' => $exception->getMessage(),
            ]);
            $sale->update(['request_payload' => $payload]);
        }
        return $payload;
    }

    private function isAmbiguousSubmissionResponse(array $response): bool
    {
        $errorCode = (string) ($response['error_code'] ?? '');
        $status = (int) ($response['status'] ?? 0);
        return in_array($errorCode, ['CONNECTION_ERROR', 'REQUEST_ERROR', 'UNEXPECTED_ERROR'], true)
            || ($errorCode === 'HTTP_ERROR_'.$status && $status >= 500 && $status < 600);
    }

    private function storedOfflineSignatureData(?array $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $summary = is_array($payload['invoiceSummary'] ?? null) ? $payload['invoiceSummary'] : [];
        $signatureData = $payload['offlineSignatureData'] ?? [
            'offlineDataSignature' => $summary['offlineSignature'] ?? null,
            'validationURL' => $summary['validationURL'] ?? null,
        ];
        if (!is_array($signatureData)) {
            return null;
        }
        $signature = $signatureData['offlineDataSignature'] ?? null;
        $validationUrl = $signatureData['validationURL'] ?? null;

        if (!is_string($signature) || trim($signature) === ''
            || !is_string($validationUrl) || trim($validationUrl) === '') {
            return null;
        }

        return $signatureData;
    }

    private function storedFailedPayloadSignature(?string $payload): ?array
    {
        $decodedPayload = $payload !== null ? json_decode($payload, true) : null;

        return $this->storedOfflineSignatureData(is_array($decodedPayload) ? $decodedPayload : null);
    }

    /**
     * Generate EIS compliant invoice number.
     *
     * @param Transaction $transaction
     * @param EisSetting $setting
     * @return string
     * @throws EisSaleException
     */
    protected function generateEISInvoiceNumber(Transaction $transaction, EisSetting $setting): string
    {
        try {
            $configuration = \Modules\EIS\Models\EisConfiguration::where('eis_setting_id', $setting->id)->firstOrFail();
            $terminal = \Modules\EIS\Models\EisTerminalConfiguration::where('configuration_id', $configuration->id)->firstOrFail();
            $terminalPosition = (int) $terminal->terminal_position;
            $taxpayerId = (int) $terminal->taxpayer_id;
            $transactionDate = $transaction->transaction_date
                ? Carbon::parse($transaction->transaction_date)
                : now();
            $transactionCount = $this->invoiceGenerator->getNextDailyCount(
                (int) $transaction->business_id,
                (int) $setting->id,
                $transactionDate
            );
            $invoiceNumber = $this->invoiceGenerator->generateInvoiceNumber(
                $transaction->business_id,
                $transactionCount,
                $terminalPosition,
                $taxpayerId,
                (int) $setting->id,
                $transactionDate
            );

            Log::debug('EIS Number generated', [
                'transaction_id' => $transaction->id,
                'invoice_number' => $invoiceNumber,
            ]);

            return $invoiceNumber;

        } catch (\Exception $e) {
            Log::error('Failed to generate EIS Number', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);

            throw new EisSaleException('Unable to allocate an invoice for the selected terminal: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get terminal position from terminal configuration.
     *
     * @param int $businessId
     * @return int
     */
    protected function getTerminalPosition(int $businessId, ?int $terminalId = null): int
    {
        return $this->invoiceGenerator->getTerminalPosition($businessId, null, $terminalId);
    }

    /**
     * Get terminal position from terminal configuration.
     *
     * @param int $businessId
     * @return int
     */
    protected function getTaxpayerId(int $businessId, ?int $terminalId = null): int
    {
        return (int) $this->invoiceGenerator->getOrCreateTerminal($businessId, null, $terminalId)->taxpayer_id;
    }

    /**
     * Get the next invoice count for today's configured terminal.
     *
     * @param int $businessId
     * @return int
     */
    protected function getTransactionCount(
        int $businessId,
        ?int $terminalId = null,
        ?\DateTimeInterface $transactionDate = null
    ): int
    {
        return $this->invoiceGenerator->getNextDailyCount($businessId, $terminalId, $transactionDate);
    }

    /**
     * Generate fallback invoice number.
     *
     * @param Transaction $transaction
     * @return string
     */
    protected function generateFallbackInvoiceNumber(Transaction $transaction): string
    {
        // Format: FALLBACK-TPIN-YYYYMMDD-COUNT
        $setting = \Modules\EIS\Services\Terminal\TerminalContext::forSale($transaction);
        $tpin = $setting->tpin ?? '000000';
        $date = now()->format('Ymd');
        $count = str_pad($transaction->id, 6, '0', STR_PAD_LEFT);
        
        return 'FALLBACK-' . $tpin . '-' . $date . '-' . $count;
    }

    /**
     * Store failed transaction for retry.
     *
     * @param Transaction $transaction
     * @param array $payload
     * @param string $errorMessage
     * @return void
     */
    protected function storeFailedTransaction(Transaction $transaction, array $payload, string $errorMessage): void
    {
        $failed = DB::table('eis_failed_transactions')
            ->where('business_id', $transaction->business_id)
            ->where('transaction_id', $transaction->id)
            ->first();

        if ($failed) {
            DB::table('eis_failed_transactions')
                ->where('id', $failed->id)
                ->update([
                    'payload' => json_encode($payload),
                    'attempts' => $failed->attempts + 1,
                    'next_retry_at' => $this->calculateNextRetry($failed->attempts + 1),
                    'error_message' => $errorMessage,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('eis_failed_transactions')->insert([
                'business_id' => $transaction->business_id,
                'location_id' => $transaction->location_id,
                'eis_setting_id' => EisSale::where('transaction_id', $transaction->id)->value('eis_setting_id'),
                'transaction_id' => $transaction->id,
                'payload' => json_encode($payload),
                'error_message' => $errorMessage,
                'attempts' => 1,
                'next_retry_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Calculate next retry time with exponential backoff.
     *
     * @param int $attempts
     * @return \DateTime
     */
    protected function calculateNextRetry(int $attempts): \DateTime
    {
        // Exponential backoff: 5, 10, 20, 40, 80 minutes
        $backoffMinutes = min(5 * pow(2, $attempts - 1), 1440); // Max 24 hours
        return now()->addMinutes($backoffMinutes);
    }

    /**
     * Retry a failed transaction.
     *
     * @param int $businessId
     * @param int $transactionId
     * @return array
     */
    public function retryFailedTransaction(int $businessId, int $transactionId): array
    {
        try {
            $failed = DB::table('eis_failed_transactions')
                ->where('business_id', $businessId)
                ->where('transaction_id', $transactionId)
                ->first();

            if (!$failed) {
                return [
                    'success' => false,
                    'message' => 'Failed transaction not found'
                ];
            }

            $transaction = Transaction::where('business_id', $businessId)->find($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction not found'
                ];
            }

            $settings = \Modules\EIS\Services\Terminal\TerminalContext::forSale($transaction);
            if (!$settings) {
                return [
                    'success' => false,
                    'message' => 'EIS settings not found'
                ];
            }

            // Retry submission
            $result = $this->submit($transaction, $settings, true);
            if ($result->status !== 'submitted') {
                throw new EisSaleException($result->error_message ?: 'Sale has not been submitted; retry record retained.');
            }

            // Remove from failed table on success
            DB::table('eis_failed_transactions')
                ->where('id', $failed->id)
                ->delete();

            return [
                'success' => true,
                'message' => 'Transaction retried successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to retry transaction', [
                'business_id' => $businessId,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retry: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get pending failed transactions for retry.
     *
     * @param int $businessId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getPendingRetries(int $businessId, int $limit = 100)
    {
        return DB::table('eis_failed_transactions')
            ->where('business_id', $businessId)
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get();
    }
}