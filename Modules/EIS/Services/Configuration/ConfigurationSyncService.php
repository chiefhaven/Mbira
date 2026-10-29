<?php

namespace Modules\EIS\Services\Configuration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Exceptions\SyncException;
use Modules\EIS\Models\EisOfflineLimit;
use Modules\EIS\Models\EisTaxRate;
use Modules\EIS\Models\EisTerminalConfiguration;
use Modules\EIS\Models\EisTerminalSite;
use Modules\EIS\Services\Configuration\EISConfigurationResponse;
use Modules\EIS\Services\Configuration\Validators\ConfigurationValidator;
use Modules\EIS\Services\EisSubscriptionAccess;

class ConfigurationSyncService
{
    private const REQUIRED_CONFIG_FIELDS = [
        'globalConfiguration',
        'terminalConfiguration',
        'taxpayerConfiguration'
    ];

    private const NON_RETRYABLE_ERRORS = [
        'invalid token',
        'authentication failed',
        'business not found',
        'validation error',
        'invalid configuration',
        'tpin not found',
        'taxpayer not found',
        'business not active',
        'account suspended',
        'invalid business id',
        'configuration validation failed',
        'permission denied',
        'access denied',
        'unauthorized'
    ];

    public function __construct(
        protected EisConfigurationClient $client,
        protected ConfigurationValidator $validator
    ) {
    }

    /**
     * Synchronize EIS configuration for a business.
     *
     * @param int $businessId
     * @param string $token
     * @return EisConfiguration
     * @throws SyncException
     * @throws \InvalidArgumentException
     */
    public function sync(
        int $businessId,
        string $token,
        bool $force = false,
        ?int $terminalId = null
    ): EisConfiguration {
        app(EisSubscriptionAccess::class)->assertAllowed($businessId);
        $this->validateBusinessId($businessId);
        $this->validateToken($token);

        Log::info('Configuration sync started', [
            'business_id' => $businessId
        ]);

        try {
            return DB::transaction(function () use ($businessId, $token, $force, $terminalId) {
                $settings = \Modules\EIS\Services\Terminal\TerminalContext::settings($businessId, $token, $terminalId);
                // Fetch configuration from EIS
                $response = $this->client->latest($token, $businessId);

                // Log response summary
                Log::debug('EIS API response received', [
                    'business_id' => $businessId,
                    'status_code' => $response->getStatusCode(),
                    'remark' => $response->getRemark(),
                    'is_success' => $response->isSuccess(),
                    'has_data' => $response->hasCompleteData(),
                    'has_errors' => $response->hasErrors()
                ]);

                // Validate response status
                $this->validateResponseStatus($response);

                // Get and validate data
                $data = $response->getData();
                $this->validateResponseData($data);
                \Modules\EIS\Services\Terminal\TerminalContext::assertConfiguration($settings, $data);

                // Check if data has changed before syncing
                $existingConfig = EisConfiguration::where('eis_setting_id', $settings->id)->first();
                if (!$force && $existingConfig && !$this->hasConfigurationChanged($existingConfig, $data)) {
                    Log::info('Configuration unchanged, skipping sync', [
                        'business_id' => $businessId,
                        'global_version' => $existingConfig->global_version,
                        'terminal_version' => $existingConfig->terminal_version,
                        'taxpayer_version' => $existingConfig->taxpayer_version
                    ]);
                    
                    // Update last_synced_at even if unchanged
                    $existingConfig->update(['last_synced_at' => now()]);
                    return $existingConfig;
                }

                // Log what changed
                if ($existingConfig) {
                    $differences = $this->getConfigurationDifferences($existingConfig, $data);
                    Log::info('Configuration changes detected', [
                        'business_id' => $businessId,
                        'differences' => $differences
                    ]);
                }

                // Validate configuration values
                $this->validator->validate($data);

                // Prepare data for storage
                $configurationData = $this->prepareConfigurationData($data, $businessId);
                $configurationData['eis_setting_id'] = $settings->id;
                $configurationData['location_id'] = $settings->location_id;

                // Create or update main configuration
                $configuration = $this->saveConfiguration($businessId, $configurationData);

                // Sync tax rates
                Log::info('Starting tax rate sync...');
                $this->syncTaxRates($configuration, $data);

                // Sync terminal configuration
                Log::info('Starting terminal configuration sync...');
                $this->syncTerminalConfiguration($configuration, $data);
                $locationData = [];
                $siteName = $data->terminalConfiguration->terminalSite->siteName ?? null;
                if (is_string($siteName) && trim($siteName) !== '') {
                    $locationData['name'] = $siteName;
                }
                foreach (['phoneNumber' => 'mobile', 'emailAddress' => 'email'] as $remote => $local) {
                    if (property_exists($data->terminalConfiguration, $remote)) {
                        $locationData[$local] = $data->terminalConfiguration->$remote;
                    }
                }
                if ($locationData) {
                    \App\BusinessLocation::where('business_id', $businessId)
                        ->where('eis_site_id', \Modules\EIS\Services\Terminal\TerminalContext::site($settings))
                        ->findOrFail($settings->location_id)->update($locationData);
                }

                Log::info('Configuration sync completed successfully', [
                    'business_id' => $businessId,
                    'configuration_id' => $configuration->id,
                    'global_version' => $configuration->global_version,
                    'terminal_version' => $configuration->terminal_version,
                    'taxpayer_version' => $configuration->taxpayer_version
                ]);

                return $configuration;
            });
        } catch (SyncException $e) {
            // Re-throw SyncException as-is
            throw $e;
        } catch (\Exception $e) {
            Log::error('Configuration sync failed', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new SyncException(
                'Failed to sync EIS configuration: ' . $e->getMessage(),
                $this->determineStatusCode($e),
                $e
            );
        }
    }

    /**
     * Get latest EIS configuration.
     *
     * @param array $settings
     * @return array
     */
    public function getLatestConfig(array $settings): array
    {
        app(EisSubscriptionAccess::class)->assertAllowed((int) ($settings['business_id'] ?? 0));

        try {
            $terminalSettings = \Modules\EIS\Services\Terminal\TerminalContext::settings(
                (int) ($settings['business_id'] ?? 0), $settings['jwt_token'] ?? ''
            );
            Log::info('Requesting latest EIS configuration');

            if (empty($settings['jwt_token'])) {
                return [
                    'success' => false,
                    'message' => 'JWT token is required to fetch latest configuration'
                ];
            }


            $response = $this->client->latest($settings['jwt_token'], (int) $settings['business_id']);

            if (!$response->isSuccess()) {
                return [
                    'success' => false,
                    'message' => $response->getErrorMessageString() ?: $response->getRemark() ?: 'Failed to fetch configuration',
                    'errors' => $response->getFormattedErrors()
                ];
            }

            \Modules\EIS\Services\Terminal\TerminalContext::assertConfiguration($terminalSettings, $response->getData());
            return [
                'success' => true,
                'message' => 'Latest configuration retrieved successfully',
                'data' => $response->getData()
            ];

        } catch (\Throwable $e) {

            Log::error('EIS latest configuration failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync tax rates from EIS configuration.
     *
     * @param EisConfiguration $configuration
     * @param object $data
     * @return void
     */
    private function syncTaxRates(EisConfiguration $configuration, object $data): void
    {
        try {
            Log::debug('Entering syncTaxRates method', [
                'configuration_id' => $configuration->id,
                'business_id' => $configuration->business_id
            ]);

            // Get tax rates from global configuration
            $taxRates = $data->globalConfiguration->taxrates ?? [];
            
            Log::debug('Tax rates from response', [
                'tax_rates_count' => count($taxRates),
                'tax_rates' => $taxRates
            ]);
            
            if (empty($taxRates)) {
                Log::warning('No tax rates found in EIS configuration', [
                    'configuration_id' => $configuration->id,
                    'business_id' => $configuration->business_id,
                    'data_keys' => array_keys((array)$data->globalConfiguration ?? [])
                ]);
                return;
            }

            // Get activated tax rate IDs from taxpayer configuration
            $activatedTaxRateIds = $data->taxpayerConfiguration->activatedTaxRateIds ?? [];
            $activatedTaxRates = $data->taxpayerConfiguration->activatedTaxrates ?? [];

            Log::info('Syncing tax rates', [
                'configuration_id' => $configuration->id,
                'business_id' => $configuration->business_id,
                'total_tax_rates' => count($taxRates),
                'activated_count' => count($activatedTaxRateIds),
                'activated_tax_rates' => $activatedTaxRateIds
            ]);

            // Process each tax rate
            $syncedIds = [];
            $taxRateCount = 0;
            
            foreach ($taxRates as $taxRate) {
                $taxRateCount++;
                
                $syncedRate = $this->syncTaxRate(
                    $configuration,
                    $taxRate,
                    $activatedTaxRateIds,
                    $activatedTaxRates
                );
                $syncedIds[] = $syncedRate->id;
            }

            // Delete tax rates that are no longer in the response
            $this->cleanupTaxRates($configuration, $syncedIds);

            Log::info('Tax rates synced successfully', [
                'configuration_id' => $configuration->id,
                'business_id' => $configuration->business_id,
                'synced_count' => count($syncedIds),
                'tax_rate_ids' => $syncedIds
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to sync tax rates', [
                'configuration_id' => $configuration->id,
                'business_id' => $configuration->business_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to be handled by parent
            throw $e;
        }
    }

    /**
     * Sync a single tax rate.
     *
     * @param EisConfiguration $configuration
     * @param object $taxRate
     * @param array $activatedTaxRateIds
     * @param array $activatedTaxRates
     * @return TaxRate
     */
    private function syncTaxRate(
        EisConfiguration $configuration,
        object $taxRate,
        array $activatedTaxRateIds,
        array $activatedTaxRates
    ): EisTaxRate {
        // Check if this tax rate is activated
        $isActivated = in_array($taxRate->id, $activatedTaxRateIds);
        
        // Get activation details if available
        $activationDetails = null;
        foreach ($activatedTaxRates as $activated) {
            if (isset($activated->taxRateId) && $activated->taxRateId === $taxRate->id) {
                $activationDetails = $activated;
                break;
            }
        }

        // Prepare data for storage
        $data = [
            'business_id' => $configuration->business_id,
            'configuration_id' => $configuration->id,
            'tax_rate_id' => $taxRate->id,
            'name' => $taxRate->name ?? null,
            'charge_mode' => $taxRate->chargeMode ?? 'Item',
            'ordinal' => $taxRate->ordinal ?? 100,
            'amount' => (float) $taxRate->rate ?? 0,
            'rate' => $taxRate->rate ?? 0,
            'is_activated' => $isActivated,
            'activation_id' => $activationDetails->id ?? null,
        ];

        Log::debug('Saving tax rate data', [
            'tax_rate_id' => $taxRate->id,
            'data' => $data
        ]);

        // Update or create
        return EisTaxRate::updateOrCreate(
            [   'business_id' => $configuration->business_id,
                'configuration_id' => $configuration->id,
                'tax_rate_id' => $taxRate->id
            ],
            $data
        );
    }

    /**
     * Clean up tax rates that are no longer in the response.
     *
     * @param EisConfiguration $configuration
     * @param array $keepIds
     * @return void
     */
    private function cleanupTaxRates(EisConfiguration $configuration, array $keepIds): void
    {
        $deleted = EisTaxRate::where('configuration_id', $configuration->id)
            ->whereNotIn('id', $keepIds)
            ->delete();

        if ($deleted > 0) {
            Log::debug('Cleaned up old tax rates', [
                'configuration_id' => $configuration->id,
                'deleted_count' => $deleted
            ]);
        }
    }

    /**
     * Sync terminal configuration from EIS data.
     *
     * @param EisConfiguration $configuration
     * @param object $data
     * @return void
     */
    public function syncTerminalConfiguration(EisConfiguration $configuration, object $data): void
    {
        try {
            // Get terminal configuration from data
            $terminalData = $data->terminalConfiguration ?? null;
            
            if (!$terminalData) {
                Log::warning('No terminal configuration found in EIS data', [
                    'configuration_id' => $configuration->id,
                    'business_id' => $configuration->business_id
                ]);
                return;
            }

            // Prepare terminal configuration data
            // IMPORTANT: Convert isActiveTerminal to proper boolean
            // The API may return this as string "0"/"1", int 0/1, or boolean
            $isActiveValue = false;
            if (isset($terminalData->isActiveTerminal)) {
                $rawValue = $terminalData->isActiveTerminal;
                
                // Handle string values from API (e.g., "0", "1", "true", "false")
                if (is_string($rawValue)) {
                    $isActiveValue = in_array(strtolower($rawValue), ['1', 'true', 'yes'], true);
                } else {
                    // Handle numeric and boolean values
                    $isActiveValue = (bool) $rawValue;
                }
            }

            Log::info('Syncing terminal configuration', [
                'configuration_id' => $configuration->id,
                'business_id' => $configuration->business_id,
                'version' => $terminalData->versionNo ?? null,
                'is_active_raw' => $terminalData->isActiveTerminal ?? 'NOT PROVIDED',
                'is_active_casted' => $isActiveValue
            ]);
            
            $terminalConfigData = [
                'configuration_id' => $configuration->id,
                'version' => $terminalData->versionNo ?? null,
                'terminal_label' => $terminalData->terminalLabel ?? null,
                'email_address' => $terminalData->emailAddress ?? null,
                'phone_number' => $terminalData->phoneNumber ?? null,
                'trading_name' => $terminalData->tradingName ?? null,
                'max_transaction_age' => $terminalData->maxTransactionAgeInHours ?? null,
                'max_cummulative_amount' => $terminalData->maxCummulativeAmount ?? null,
                'address_lines' => isset($terminalData->addressLines) 
                    ? json_encode($terminalData->addressLines) 
                    : null,
                'raw_data' => json_encode($terminalData),
                'last_synced_at' => now(),
                // Terminal status comes from API response or defaults to false
                // Explicitly cast to boolean to ensure correct database storage
                'is_active' => isset($terminalData->isActiveTerminal)
                    ? $isActiveValue
                    : (EisTerminalConfiguration::where('configuration_id', $configuration->id)->value('is_active') ?? false)
            ];

            // Update or create terminal configuration
            $terminalConfig = EisTerminalConfiguration::updateOrCreate(
                ['configuration_id' => $configuration->id],
                $terminalConfigData
            );

            // Sync terminal site if present
            if (isset($terminalData->terminalSite)) {
                $this->syncTerminalSite($terminalConfig, $terminalData->terminalSite);
            }

            // Sync offline limit if present
            if (isset($terminalData->offlineLimit)) {
                $this->syncOfflineLimit($terminalConfig, $terminalData->offlineLimit);
            }

            Log::info('Terminal configuration synced successfully', [
                'configuration_id' => $configuration->id,
                'terminal_config_id' => $terminalConfig->id,
                'version' => $terminalConfig->version
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync terminal configuration', [
                'configuration_id' => $configuration->id,
                'business_id' => $configuration->business_id,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw to be handled by parent
            throw $e;
        }
    }

    /**
     * Sync terminal site.
     *
     * @param TerminalConfiguration $terminalConfig
     * @param object $siteData
     * @return void
     */
    private function syncTerminalSite(EisTerminalConfiguration $terminalConfig, object $siteData): void
    {
        $siteData = [
            'terminal_configuration_id' => $terminalConfig->id,
            'site_id' => $siteData->siteId ?? null,
            'site_name' => $siteData->siteName ?? null,
            'raw_data' => json_encode($siteData),
            'last_synced_at' => now()
        ];

        EisTerminalSite::updateOrCreate(
            ['terminal_configuration_id' => $terminalConfig->id],
            $siteData
        );

        Log::debug('Terminal site synced', [
            'terminal_config_id' => $terminalConfig->id,
            'site_id' => $siteData['site_id'],
            'site_name' => $siteData['site_name']
        ]);
    }

    /**
     * Sync offline limit.
     *
     * @param TerminalConfiguration $terminalConfig
     * @param object $offlineLimitData
     * @return void
     */
    private function syncOfflineLimit(EisTerminalConfiguration $terminalConfig, object $offlineLimitData): void
    {
        $limitData = EisOfflineLimit::normalizeLimitPayload($offlineLimitData);

        $payload = [
            'terminal_configuration_id' => $terminalConfig->id,
            'max_transaction_age_hours' => $limitData['max_transaction_age_hours'],
            'max_cumulative_amount' => $limitData['max_cumulative_amount'],
            'raw_data' => json_encode($offlineLimitData),
            'last_synced_at' => now()
        ];

        EisOfflineLimit::updateOrCreate(
            ['terminal_configuration_id' => $terminalConfig->id],
            $payload
        );

        Log::debug('Offline limit synced', [
            'terminal_config_id' => $terminalConfig->id,
            'max_hours' => $payload['max_transaction_age_hours'],
            'max_amount' => $payload['max_cumulative_amount']
        ]);
    }

    /**
     * Validate business ID.
     *
     * @param int $businessId
     * @throws \InvalidArgumentException
     */
    private function validateBusinessId(int $businessId): void
    {
        if ($businessId <= 0) {
            throw new \InvalidArgumentException('Invalid business ID: ' . $businessId);
        }
    }

    /**
     * Validate token.
     *
     * @param string $token
     * @throws \InvalidArgumentException
     */
    private function validateToken(string $token): void
    {
        if (empty($token)) {
            throw new \InvalidArgumentException('Authentication token is empty');
        }

        // Optional: Validate token format (JWT format check)
        if (strpos($token, '.') === false || substr_count($token, '.') !== 2) {
            Log::warning('Token does not appear to be a valid JWT format', [
                'token_length' => strlen($token)
            ]);
        }
    }

    /**
     * Validate response status.
     *
     * @param EISConfigurationResponse $response
     * @throws SyncException
     */
    private function validateResponseStatus(EISConfigurationResponse $response): void
    {
        // Log the full response for debugging
        Log::debug('EIS API response validation', [
            'status_code' => $response->getStatusCode(),
            'remark' => $response->getRemark(),
            'is_success' => $response->isSuccess(),
            'has_errors' => $response->hasErrors(),
            'error_count' => count($response->getErrors())
        ]);

        if (!$response->isSuccess()) {
            $errorMessage = $response->getRemark() ?? 'Unknown error';
            $errors = $response->getErrors();
            
            Log::error('EIS API returned error response', [
                'status_code' => $response->getStatusCode(),
                'remark' => $response->getRemark(),
                'errors' => $errors,
                'has_validation_errors' => $response->hasValidationErrors(),
                'error_count' => count($errors)
            ]);

            // Build detailed error message
            if (!empty($errors)) {
                $errorDetails = [];
                foreach ($errors as $error) {
                    $field = $error->fieldName ?? 'general';
                    $message = $error->errorMessage ?? 'Unknown error';
                    $code = $error->errorCode ?? null;
                    
                    if ($code) {
                        $errorDetails[] = "[{$field}] {$message} (Code: {$code})";
                    } else {
                        $errorDetails[] = "[{$field}] {$message}";
                    }
                }
                $errorMessage .= ': ' . implode('; ', $errorDetails);
            }

            // Check for specific error types
            if ($response->hasValidationErrors()) {
                $validationErrors = $response->getValidationErrors();
                Log::warning('EIS API validation errors', $validationErrors);
                
                throw new SyncException(
                    'EIS API validation failed: ' . $errorMessage,
                    $response->getStatusCode() ?: 422
                );
            }

            throw new SyncException(
                'EIS API returned error: ' . $errorMessage,
                $response->getStatusCode() ?: 400
            );
        }

        // Check if data is complete
        if (!$response->hasCompleteData()) {
            Log::error('EIS API response missing required data', [
                'has_global' => isset($response->getData()->globalConfiguration),
                'has_terminal' => isset($response->getData()->terminalConfiguration),
                'has_taxpayer' => isset($response->getData()->taxpayerConfiguration),
                'data_keys' => array_keys((array)$response->getData())
            ]);

            throw new SyncException(
                'EIS API response missing required configuration data',
                500
            );
        }

        Log::info('EIS API response validation passed', [
            'status_code' => $response->getStatusCode(),
            'remark' => $response->getRemark(),
            'global_version' => $response->getGlobalConfiguration()->versionNo ?? null,
            'terminal_version' => $response->getTerminalConfiguration()->versionNo ?? null,
            'taxpayer_version' => $response->getTaxpayerConfiguration()->versionNo ?? null
        ]);
    }

    /**
     * Validate required response data.
     *
     * @param object $data
     * @throws SyncException
     */
    private function validateResponseData(object $data): void
    {
        foreach (self::REQUIRED_CONFIG_FIELDS as $field) {
            if (!isset($data->$field) || !is_object($data->$field)) {
                throw new SyncException(
                    "Missing or invalid required configuration field: {$field}"
                );
            }
        }
    }

    /**
     * Check if configuration has changed.
     *
     * @param EisConfiguration $existing
     * @param object $newData
     * @return bool
     */
    private function hasConfigurationChanged(EisConfiguration $existing, object $newData): bool
    {
        // Check version numbers
        $globalVersionChanged = $existing->global_version !== ($newData->globalConfiguration->versionNo ?? null);
        $terminalVersionChanged = $existing->terminal_version !== ($newData->terminalConfiguration->versionNo ?? null);
        $taxpayerVersionChanged = $existing->taxpayer_version !== ($newData->taxpayerConfiguration->versionNo ?? null);
        
        // Check TIN
        $tinChanged = $existing->tpin !== ($newData->taxpayerConfiguration->tin ?? null);
        
        // Check VAT registration status
        $vatChanged = $existing->is_vat_registered !== ($newData->taxpayerConfiguration->isVATRegistered ?? false);
        
        // Check tax office code
        $taxOfficeChanged = $existing->tax_office_code !== ($newData->taxpayerConfiguration->taxOfficeCode ?? null);
        $storedResponse = $existing->raw_response;
        if (is_string($storedResponse)) {
            $storedResponse = json_decode($storedResponse, true, 512, JSON_THROW_ON_ERROR);
        }
        // EIS may change configuration values without incrementing their versions.
        $payloadChanged = data_get($storedResponse, 'data')
            !== json_decode(json_encode($newData, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        
        return $globalVersionChanged || 
               $terminalVersionChanged || 
               $taxpayerVersionChanged ||
               $tinChanged ||
               $vatChanged ||
               $taxOfficeChanged ||
               $payloadChanged;
    }

    /**
     * Prepare configuration data for storage.
     *
     * @param object $data
     * @param int $businessId
     * @return array
     */
    private function prepareConfigurationData(object $data, int $businessId): array
    {
        return [
            'business_id' => $businessId,
            'global_version' => $data->globalConfiguration->versionNo ?? null,
            'terminal_version' => $data->terminalConfiguration->versionNo ?? null,
            'taxpayer_version' => $data->taxpayerConfiguration->versionNo ?? null,
            'tpin' => $data->taxpayerConfiguration->tin ?? null,
            'is_vat_registered' => $data->taxpayerConfiguration->isVATRegistered ?? false,
            'tax_office_code' => $data->taxpayerConfiguration->taxOfficeCode ?? null,
            'tax_office_name' => $data->taxpayerConfiguration->taxOffice->name ?? null,
            'raw_response' => ['data' => $data],
            'last_synced_at' => now()
        ];
    }

    /**
     * Save configuration to database.
     *
     * @param int $businessId
     * @param array $data
     * @return EisConfiguration
     */
    private function saveConfiguration(int $businessId, array $data): EisConfiguration
    {
        return EisConfiguration::updateOrCreate(
            ['eis_setting_id' => $data['eis_setting_id']],
            $data
        );
    }

    /**
     * Determine status code from exception.
     *
     * @param \Exception $e
     * @return int
     */
    private function determineStatusCode(\Exception $e): int
    {
        // Get the code from the exception
        $code = $e->getCode();
        
        // If code is an integer and > 0, use it
        if (is_int($code) && $code > 0) {
            return $code;
        }
        
        // If code is a string, try to convert to int
        if (is_string($code) && is_numeric($code)) {
            $intCode = (int) $code;
            if ($intCode > 0) {
                return $intCode;
            }
        }

        // Check for specific error types in the message
        $message = strtolower($e->getMessage());
        
        foreach (self::NON_RETRYABLE_ERRORS as $error) {
            if (stripos($message, $error) !== false) {
                return 400; // Bad request
            }
        }

        // Check for HTTP status codes in the message
        if (preg_match('/status[:\s]+(\d{3})/i', $message, $matches)) {
            $statusCode = (int) $matches[1];
            if ($statusCode >= 100 && $statusCode <= 599) {
                return $statusCode;
            }
        }

        // Check for common HTTP status codes
        if (strpos($message, '404') !== false) {
            return 404;
        }
        if (strpos($message, '403') !== false) {
            return 403;
        }
        if (strpos($message, '401') !== false) {
            return 401;
        }
        if (strpos($message, '429') !== false) {
            return 429;
        }

        // Default to 500
        return 500;
    }

    /**
     * Check if an error is retryable.
     *
     * @param \Exception $e
     * @return bool
     */
    public function isRetryableError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        // Check for non-retryable errors
        foreach (self::NON_RETRYABLE_ERRORS as $error) {
            if (stripos($message, $error) !== false) {
                return false;
            }
        }

        // HTTP errors
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            $code = $e->getCode();
            $statusCode = is_int($code) ? $code : 0;
            
            // Server errors and rate limiting are retryable
            if ($statusCode >= 500 || $statusCode === 429) {
                return true;
            }
            
            // Client errors (4xx) are usually not retryable
            if ($statusCode >= 400 && $statusCode < 500) {
                return false;
            }
        }
        
        // Network/connection errors are retryable
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }
        
        // Timeout errors are retryable
        if ($e instanceof \Illuminate\Http\Client\TimeoutException) {
            return true;
        }
        
        // Database connection errors are retryable
        if ($e instanceof \PDOException || $e instanceof \Illuminate\Database\QueryException) {
            if (stripos($e->getMessage(), 'connection') !== false ||
                stripos($e->getMessage(), 'deadlock') !== false) {
                return true;
            }
        }
        
        // Default to retryable
        return true;
    }

    /**
     * Get configuration differences.
     *
     * @param EisConfiguration $existing
     * @param object $newData
     * @return array
     */
    public function getConfigurationDifferences(EisConfiguration $existing, object $newData): array
    {
        $differences = [];
        
        // Check global version
        if ($existing->global_version !== ($newData->globalConfiguration->versionNo ?? null)) {
            $differences['global_version'] = [
                'old' => $existing->global_version,
                'new' => $newData->globalConfiguration->versionNo ?? null
            ];
        }
        
        // Check terminal version
        if ($existing->terminal_version !== ($newData->terminalConfiguration->versionNo ?? null)) {
            $differences['terminal_version'] = [
                'old' => $existing->terminal_version,
                'new' => $newData->terminalConfiguration->versionNo ?? null
            ];
        }
        
        // Check taxpayer version
        if ($existing->taxpayer_version !== ($newData->taxpayerConfiguration->versionNo ?? null)) {
            $differences['taxpayer_version'] = [
                'old' => $existing->taxpayer_version,
                'new' => $newData->taxpayerConfiguration->versionNo ?? null
            ];
        }
        
        // Check TIN
        if ($existing->tpin !== ($newData->taxpayerConfiguration->tin ?? null)) {
            $differences['tpin'] = [
                'old' => $existing->tpin,
                'new' => $newData->taxpayerConfiguration->tin ?? null
            ];
        }
        
        // Check VAT registration
        if ($existing->is_vat_registered !== ($newData->taxpayerConfiguration->isVATRegistered ?? false)) {
            $differences['is_vat_registered'] = [
                'old' => $existing->is_vat_registered,
                'new' => $newData->taxpayerConfiguration->isVATRegistered ?? false
            ];
        }
        
        // Check tax office code
        if ($existing->tax_office_code !== ($newData->taxpayerConfiguration->taxOfficeCode ?? null)) {
            $differences['tax_office_code'] = [
                'old' => $existing->tax_office_code,
                'new' => $newData->taxpayerConfiguration->taxOfficeCode ?? null
            ];
        }
        
        return $differences;
    }

    /**
     * Force sync even if no changes detected.
     *
     * @param int $businessId
     * @param string $token
     * @return EisConfiguration
     * @throws SyncException
     */
    public function forceSync(int $businessId, string $token, ?int $terminalId = null): EisConfiguration
    {
        Log::info('Forced configuration sync started', [
            'business_id' => $businessId
        ]);

        return $this->sync($businessId, $token, true, $terminalId);
    }

    /**
     * Get sync status for a business.
     *
     * @param int $businessId
     * @return array
     */
    public function getSyncStatus(int $businessId, ?int $terminalId = null): array
    {
        $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, $terminalId);
        
        if (!$configuration) {
            return [
                'business_id' => $businessId,
                'synced' => false,
                'last_synced_at' => null,
                'message' => 'No configuration found for this business'
            ];
        }

        // Get tax rates count
        $taxRatesCount = EisTaxRate::where('configuration_id', $configuration->id)->count();
        $activatedTaxRates = EisTaxRate::where('configuration_id', $configuration->id)
            ->where('is_activated', true)
            ->count();
        
            Log::debug('Sync status retrieved', [
                'business_id' => $businessId,
                'configuration_id' => $configuration->id,
                'last_synced_at' => $configuration->last_synced_at,
                'global_version' => $configuration->global_version,
                'terminal_version' => $configuration->terminal_version,
                'taxpayer_version' => $configuration->taxpayer_version,
                'tax_rates_count' => $taxRatesCount,
                'activated_tax_rates' => $activatedTaxRates
            ]);

        // Get terminal info
        $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)->first();

        return [
            'business_id' => $businessId,
            'synced' => true,
            'configuration_id' => $configuration->id,
            'last_synced_at' => $configuration->last_synced_at,
            'global_version' => $configuration->global_version,
            'terminal_version' => $configuration->terminal_version,
            'taxpayer_version' => $configuration->taxpayer_version,
            'tpin' => $configuration->tpin,
            'is_vat_registered' => $configuration->is_vat_registered,
            'tax_rates' => [
                'total' => $taxRatesCount,
                'activated' => $activatedTaxRates
            ],
            'terminal' => $terminal ? [
                'is_active' => $terminal->is_active,
                'trading_name' => $terminal->trading_name,
                'has_site' => EisTerminalSite::where('terminal_configuration_id', $terminal->id)->exists(),
                'has_offline_limit' => EisOfflineLimit::where('terminal_configuration_id', $terminal->id)->exists()
            ] : null,
            'hours_since_sync' => $configuration->last_synced_at 
                ? $configuration->last_synced_at->diffInHours(now()) 
                : null
        ];
    }

    /**
     * Get tax rates for a configuration.
     *
     * @param EisConfiguration $configuration
     * @param bool $onlyActivated
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTaxRates(EisConfiguration $configuration, bool $onlyActivated = false)
    {
        $query = EisTaxRate::where('configuration_id', $configuration->id);
        
        if ($onlyActivated) {
            $query->where('is_activated', true);
        }
        
        return $query->orderBy('ordinal')->orderBy('rate')->get();
    }

    /**
     * Get activated tax rate IDs.
     *
     * @param EisConfiguration $configuration
     * @return array
     */
    public function getActivatedTaxRateIds(EisConfiguration $configuration): array
    {
        return EisTaxRate::where('configuration_id', $configuration->id)
            ->where('is_activated', true)
            ->pluck('tax_rate_id')
            ->toArray();
    }

    /**
     * Get tax rate by ID.
     *
     * @param int $configurationId
     * @param string $taxRateId
     * @return TaxRate|null
     */
    public function getTaxRateById(int $configurationId, string $taxRateId): ?EisTaxRate
    {
        return EisTaxRate::where('configuration_id', $configurationId)
            ->where('tax_rate_id', $taxRateId)
            ->first();
    }

    /**
     * Calculate tax for a given amount.
     *
     * @param string $taxRateId
     * @param float $amount
     * @param int $configurationId
     * @return array
     */
    public function calculateTax(string $taxRateId, float $amount, int $configurationId): array
    {
        $taxRate = $this->getTaxRateById($configurationId, $taxRateId);

        if (!$taxRate) {
            return [
                'amount' => 0,
                'rate' => 0,
                'tax_amount' => 0,
                'total' => $amount,
                'tax_rate_id' => $taxRateId,
                'error' => 'Tax rate not found'
            ];
        }

        $taxAmount = ($amount * $taxRate->rate) / 100;
        
        return [
            'amount' => (float) $taxRate->rate,
            'rate' => (float) $taxRate->rate,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($amount + $taxAmount, 2),
            'tax_rate_id' => $taxRateId,
            'name' => $taxRate->name,
            'is_activated' => $taxRate->is_activated,
            'charge_mode' => $taxRate->charge_mode
        ];
    }

    /**
     * Get terminal configuration.
     *
     * @param EisConfiguration $configuration
     * @return TerminalConfiguration|null
     */
    public function getTerminalConfiguration(EisConfiguration $configuration): ?EisTerminalConfiguration
    {
        return EisTerminalConfiguration::where('configuration_id', $configuration->id)
            ->with(['terminalSite', 'offlineLimit'])
            ->first();
    }

    /**
     * Get tax rates summary.
     *
     * @param EisConfiguration $configuration
     * @return array
     */
    public function getTaxRatesSummary(EisConfiguration $configuration): array
    {
        $total = EisTaxRate::where('configuration_id', $configuration->id)->count();
        $activated = EisTaxRate::where('configuration_id', $configuration->id)
            ->where('is_activated', true)
            ->count();
        
        $rates = EisTaxRate::where('configuration_id', $configuration->id)
            ->orderBy('rate')
            ->get(['tax_rate_id', 'name', 'rate', 'is_activated']);

        return [
            'total' => $total,
            'activated' => $activated,
            'inactive' => $total - $activated,
            'highest_rate' => EisTaxRate::where('configuration_id', $configuration->id)
                ->where('is_activated', true)
                ->max('rate'),
            'lowest_rate' => EisTaxRate::where('configuration_id', $configuration->id)
                ->where('is_activated', true)
                ->min('rate'),
            'rates' => $rates->toArray()
        ];
    }

    public function locationForActivation(int $businessId, object $data, ?int $activatedBy = null): \App\BusinessLocation
    {
        $site = $data->terminalConfiguration->terminalSite ?? null;
        $siteId = $site->siteId ?? null;
        $siteName = $site->siteName ?? null;
        if ((!is_string($siteId) && !is_int($siteId)) || trim((string) $siteId) === ''
            || !is_string($siteName) || trim($siteName) === '') {
            throw new \InvalidArgumentException('EIS activation requires a site ID and site name.');
        }
        $locations = \App\BusinessLocation::where('business_id', $businessId)
            ->where('eis_site_id', (string) $siteId)->lockForUpdate()->get()
            ->filter(fn ($location) => (string) $location->eis_site_id === (string) $siteId);
        if ($locations->count() > 1) {
            throw new \InvalidArgumentException('Multiple business locations have this EIS site ID; reconcile duplicates before activation.');
        }
        $location = $locations->first();
        if ($activatedBy !== null) {
            $user = \App\User::where('business_id', $businessId)->findOrFail($activatedBy);
            $permitted = $user->permitted_locations($businessId);
            if ($permitted !== 'all' && (!$location || !in_array((int) $location->id, array_map('intval', $permitted), true))) {
                throw new \InvalidArgumentException('You do not have access to the API-assigned location. An account with all-location access must activate a new site.');
            }
        }
        if ($location) {
            $location->update(['name' => $siteName]);
            return $location;
        }
        \App\Business::findOrFail($businessId);
        $template = \App\BusinessLocation::where('business_id', $businessId)->orderBy('id')->first();
        $scheme = \App\InvoiceScheme::getDefault($businessId);
        if (!$scheme) {
            $scheme = \App\InvoiceScheme::create([
                'business_id' => $businessId, 'name' => 'Default Invoice Scheme', 'scheme_type' => 'blank',
                'prefix' => 'INV-', 'start_number' => 1, 'total_digits' => 6, 'is_default' => true,
            ]);
        }
        $layout = \App\InvoiceLayout::where('business_id', $businessId)->where('is_default', 1)->first();
        if (!$layout) {
            $layout = \App\InvoiceLayout::create(['business_id' => $businessId, 'name' => 'Default Invoice Layout', 'is_default' => true]);
        }
        $location = \App\BusinessLocation::create([
            'business_id' => $businessId, 'eis_site_id' => (string) $siteId, 'name' => $siteName,
            'location_id' => (string) $siteId,
            'country' => $template?->country ?: 'Malawi', 'state' => $template?->state ?: 'Malawi',
            'city' => $template?->city ?: 'Lilongwe', 'zip_code' => $template?->zip_code ?: '00000',
            'invoice_scheme_id' => $scheme->id, 'sale_invoice_scheme_id' => $scheme->id,
            'invoice_layout_id' => $layout->id, 'sale_invoice_layout_id' => $layout->id,
            'default_payment_accounts' => $template?->default_payment_accounts,
            'mobile' => $data->terminalConfiguration->phoneNumber ?? null,
            'email' => $data->terminalConfiguration->emailAddress ?? null,
        ]);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'location.'.$location->id, 'guard_name' => 'web']);
        return $location;
    }

    public static function businessLocation(int $businessId, ?int $terminalId = null)
    {
        $settings = \Modules\EIS\Services\Terminal\TerminalContext::settings($businessId, null, $terminalId);
        $eisConfiguration = EisConfiguration::where('business_id', $businessId)->where('eis_setting_id', $settings->id)
            ->firstOrFail();

        // Decode the stored EIS API response
        $response = $eisConfiguration->raw_response;
        if (is_string($response)) {
            $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        }
        \Modules\EIS\Services\Terminal\TerminalContext::assertConfiguration($settings,
            json_decode(json_encode(data_get($response, 'data', []), JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR));

        // Extract EIS configurations
        $terminalConfig = data_get(
            $response,
            'data.terminalConfiguration',
            []
        );

        $taxpayerConfig = data_get(
            $response,
            'data.taxpayerConfiguration',
            []
        );

        $terminalSite = data_get(
            $terminalConfig,
            'terminalSite',
            []
        );

        $siteId = data_get($terminalSite, 'siteId');

        if (blank($siteId)) {
            throw new \InvalidArgumentException(
                'Terminal site ID is missing from the EIS configuration.'
            );
        }

        Log::info('EIS terminal site details retrieved', [
            'business_id' => $businessId,
            'site_id' => $siteId,
            'site_name' => data_get($terminalSite, 'siteName'),
        ]);

        return DB::transaction(function () use (
            $businessId,
            $terminalConfig,
            $taxpayerConfig,
            $terminalSite,
            $siteId,
            $settings
        ) {

            /*
            |--------------------------------------------------------------------------
            | Update Business
            |--------------------------------------------------------------------------
            */

            $business = \App\Business::whereKey($businessId)->lockForUpdate()->firstOrFail();
            $businessLocation = \App\BusinessLocation::where('business_id', $businessId)
                ->lockForUpdate()->findOrFail($settings->location_id);

            /*
            |--------------------------------------------------------------------------
            | Update Business Location
            |--------------------------------------------------------------------------
            */

            // Get or create the business's default invoice scheme for sales
            $defaultInvoiceScheme = \App\InvoiceScheme::getDefault($business->id);
            
            if (!$defaultInvoiceScheme) {
                // If no default scheme exists, create one
                $defaultInvoiceScheme = \App\InvoiceScheme::create([
                    'business_id' => $business->id,
                    'name' => 'Default Invoice Scheme',
                    'scheme_type' => 'blank',
                    'prefix' => 'INV-',
                    'start_number' => 1,
                    'total_digits' => 6,
                    'is_default' => true,
                ]);
                Log::info('Created default invoice scheme for business', [
                    'business_id' => $business->id,
                    'invoice_scheme_id' => $defaultInvoiceScheme->id,
                ]);
            }
            $invoiceSchemeId = $defaultInvoiceScheme->id;

            // Get or create a default invoice layout if needed
            $defaultInvoiceLayout = \App\InvoiceLayout::where('business_id', $business->id)
                ->where('is_default', 1)
                ->first();
            
            if (!$defaultInvoiceLayout) {
                // If no default layout exists, create one
                $defaultInvoiceLayout = \App\InvoiceLayout::create([
                    'business_id' => $business->id,
                    'name' => 'Default Invoice Layout',
                    'is_default' => true,
                ]);
                Log::info('Created default invoice layout for business', [
                    'business_id' => $business->id,
                    'invoice_layout_id' => $defaultInvoiceLayout->id,
                ]);
            }
            $invoiceLayoutId = $defaultInvoiceLayout->id;

            $businessLocation->update(
                [
                    'eis_site_id' => $siteId,

                    'name' => data_get($terminalSite, 'siteName') ?: $businessLocation->name,

                    'country' => $businessLocation->country ?: 'Malawi',
                    'state' => $businessLocation->state ?: 'Malawi',
                    'city' => $businessLocation->city ?: 'Lilongwe',
                    'zip_code' => $businessLocation->zip_code ?: '00000',

                    'invoice_scheme_id' => $businessLocation->invoice_scheme_id ?: $invoiceSchemeId,
                    'invoice_layout_id' => $businessLocation->invoice_layout_id ?: $invoiceLayoutId,
                    'sale_invoice_scheme_id' => $businessLocation->sale_invoice_scheme_id ?: $invoiceSchemeId,

                    'mobile' => data_get(
                        $terminalConfig,
                        'phoneNumber'
                    ),

                    'email' => data_get(
                        $terminalConfig,
                        'emailAddress'
                    ),
                ]
            );

            Log::info('EIS business location synchronized successfully', [
                'business_id' => $business->id,
                'business_location_id' => $businessLocation->id,
                'eis_site_id' => $siteId,
                'sale_invoice_scheme_id' => $invoiceSchemeId,
                'business_location_name' => $businessLocation->name,
            ]);

            return $businessLocation->fresh();
        });
    }
}