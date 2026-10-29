<?php

namespace Modules\EIS\Services\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Exceptions\EisSaleException;
use Modules\EIS\Services\EisSubscriptionAccess;

class EisSaleClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $connectTimeout;
    protected int $retries;

    public function __construct(
        protected EisHealthService $health
    )
    {
        $this->baseUrl = \Modules\EIS\Services\Utilities\EisUtilitiesClient::baseUrl();
        $this->timeout = config('eis.http_timeout', 60);
        $this->connectTimeout = config('eis.connect_timeout', 10);
        $this->retries = config('eis.http_retries', 3);
    }

    /**
     * Submit sale transaction to EIS.
     * 
     * Returns array with 'success' flag instead of throwing exceptions
     * 
     * @param array $payload
     * @param object $settings
     * @return array {
     *     success: bool,
     *     data?: array,
     *     error?: string,
     *     error_code?: string,
     *     status?: int,
     *     retryable?: bool
     * }
     */
    public function submit(array $payload, object $settings): array
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) ($settings->business_id ?? 0));

        try {
            $site = \Modules\EIS\Services\Terminal\TerminalContext::site($settings);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResult($e->getMessage(), 'INVALID_SETTINGS', null, false);
        }
        if (($payload['invoiceHeader']['siteId'] ?? null) !== $site
            || (string) ($payload['invoiceHeader']['sellerTIN'] ?? '') !== (string) ($settings->tpin ?? '')) {
            Log::error('EIS sale payload does not match its terminal credentials.', ['business_id' => $settings->business_id ?? null]);
            return $this->errorResult('Sale payload does not match its business terminal.', 'TERMINAL_MISMATCH', null, false);
        }

        // Check if EIS server is online
        if (! $this->health->isOnline($settings->business_id, $settings->jwt_token)) {
            Log::error('EIS server is currently offline.', [
                'business_id' => $settings->business_id
            ]);

            return $this->errorResult(
                'EIS server is currently offline',
                'SERVER_OFFLINE',
                null,
                true // Retryable
            );
        }

        // Validate settings
        $validationResult = $this->validateSettings($settings);
        if (!$validationResult['valid']) {
            return $this->errorResult(
                $validationResult['error'],
                'INVALID_SETTINGS',
                null,
                false // Not retryable - fix settings first
            );
        }

        $url = $this->baseUrl . '/sales/submit-sales-transaction';

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withOptions(['allow_redirects' => false])
                // Retry via persisted sale tracking, not by silently replaying
                // a first-attempt payload without its offline signature.
                ->withToken($settings->jwt_token)
                ->withHeaders([
                    'X-Secret-Key' => $settings->secret_key,
                ])
                ->post($url, $payload);

            // Handle HTTP errors
            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->body();
                
                Log::error('EIS request failed', [
                    'url' => $url,
                    'status' => $status,
                    'response' => $body,
                ]);

                $errorMessage = $this->parseErrorMessage($response);
                $isRetryable = $this->isRetryableHttpStatus($status);
                $responseBody = $response->json() ?? $body;
                $duplicateRemark = is_array($responseBody) ? ($responseBody['remark'] ?? null) : null;
                $isDuplicateInvoice = $status === 400
                    && is_array($responseBody)
                    && is_numeric($responseBody['statusCode'] ?? null)
                    && (int) $responseBody['statusCode'] === -2
                    && is_string($duplicateRemark)
                    && strcasecmp(trim($duplicateRemark), 'Invoice Number already exists') === 0;

                return $this->errorResult(
                    $isDuplicateInvoice
                        ? 'EIS reports this invoice number already exists; verify it with a read-only invoice lookup before retrying.'
                        : ($errorMessage ?? sprintf('EIS returned HTTP %s', $status)),
                    $isDuplicateInvoice ? 'DUPLICATE_INVOICE' : 'HTTP_ERROR_' . $status,
                    $status,
                    $isDuplicateInvoice ? false : $isRetryable,
                    $responseBody
                );
            }

            $responseData = $response->json();
            $invoiceNumber = $payload['invoiceHeader']['invoiceNumber'] ?? null;
            
            Log::info('EIS invoice submitted successfully.', [
                'url' => $url,
                'invoice' => $invoiceNumber,
                'response' => $responseData
            ]);

            return [
                'success' => true,
                'data' => $responseData,
                'reference' => $responseData['reference'] ?? null,
                'invoice_number' => $invoiceNumber,
                'status' => $response->status(),
            ];

        } catch (ConnectionException $e) {
            Log::error('Unable to connect to EIS server.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResult(
                'Unable to connect to EIS server: ' . $e->getMessage(),
                'CONNECTION_ERROR',
                null,
                true // Retryable - network issue
            );

        } catch (RequestException $e) {
            Log::error('EIS request exception.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResult(
                'EIS request failed: ' . $e->getMessage(),
                'REQUEST_ERROR',
                $e->getCode(),
                true // Retryable
            );

        } catch (\Throwable $e) {
            Log::error('Unexpected EIS client error.', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResult(
                'Unexpected error: ' . $e->getMessage(),
                'UNEXPECTED_ERROR',
                null,
                false // Not sure if retryable - investigate first
            );
        }
    }

    /**
     * Validate EIS settings
     */
    private function validateSettings(object $settings): array
    {
        if (empty($settings->jwt_token)) {
            return ['valid' => false, 'error' => 'Missing EIS JWT token.'];
        }

        if (empty($settings->secret_key)) {
            return ['valid' => false, 'error' => 'Missing EIS Secret Key.'];
        }

        if (empty($settings->site_id ?? $settings->branch_id ?? null)) {
            return ['valid' => false, 'error' => 'Missing EIS Site ID.'];
        }

        if (empty($settings->tpin)) {
            return ['valid' => false, 'error' => 'Missing EIS TIN.'];
        }

        return ['valid' => true];
    }

    /**
     * Parse error message from response
     */
    private function parseErrorMessage($response): ?string
    {
        try {
            $data = $response->json();
            
            if (isset($data['message'])) {
                return $data['message'];
            }
            
            if (isset($data['error'])) {
                return is_string($data['error']) ? $data['error'] : json_encode($data['error']);
            }
            
            if (isset($data['errors'])) {
                $errors = $data['errors'];
                if (is_array($errors) && isset($errors[0])) {
                    return is_string($errors[0]) ? $errors[0] : json_encode($errors[0]);
                }
            }
            
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Determine if HTTP status code is retryable
     */
    private function isRetryableHttpStatus(int $status): bool
    {
        // Retry on server errors (5xx)
        if ($status >= 500 && $status < 600) {
            return true;
        }

        // Retry on rate limiting
        if ($status === 429) {
            return true;
        }

        // Don't retry on client errors (4xx) except 429
        if ($status >= 400 && $status < 500) {
            return false;
        }

        // Default - retry if unsure
        return true;
    }

    /**
     * Create error result array
     */
    private function errorResult(
        string $message,
        string $code,
        ?int $status = null,
        bool $retryable = false,
        $response = null
    ): array {
        return [
            'success' => false,
            'error' => $message,
            'error_code' => $code,
            'status' => $status,
            'retryable' => $retryable,
            'response' => $response,
        ];
    }

    /**
     * Sanitize payload for logging (remove sensitive data)
     */
    private function sanitizePayloadForLog(array $payload): array
    {
        // Remove sensitive fields if needed
        $sanitized = $payload;
        
        // Example: Mask or remove sensitive data
        if (isset($sanitized['customer']['email'])) {
            // Keep for logging but could mask
        }
        
        return $sanitized;
    }
}