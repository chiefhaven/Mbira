<?php

namespace Modules\EIS\Services\Configuration;

class EISConfigurationResponse
{
    private int $statusCode;
    private string $remark;
    private object $data;
    private array $errors;
    private ?array $rawResponse;

    // Success status code - API uses 1 for success (left as-is)
    private const SUCCESS_STATUS_CODE = 1;

    public function __construct(object $response)
    {
        $this->statusCode = $response->statusCode ?? 0;
        $this->remark = $response->remark ?? '';
        $this->data = $response->data ?? (object) [];
        $this->errors = $response->errors ?? [];
        $this->rawResponse = json_decode(json_encode($response), true);
    }

    /**
     * Check if the response indicates success.
     * Success = statusCode is 1
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->statusCode === self::SUCCESS_STATUS_CODE;
    }

    /**
     * Get the status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the remark message.
     *
     * @return string
     */
    public function getRemark(): string
    {
        return $this->remark;
    }

    /**
     * Get the data object.
     *
     * @return object
     */
    public function getData(): object
    {
        return $this->data;
    }

    /**
     * Get the errors array.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the raw response as array.
     *
     * @return array|null
     */
    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }

    /**
     * Check if the response has any errors.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get specific field from data.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getDataField(string $key, $default = null)
    {
        return $this->data->$key ?? $default;
    }

    /**
     * Check if data has a specific field.
     *
     * @param string $key
     * @return bool
     */
    public function hasDataField(string $key): bool
    {
        return isset($this->data->$key);
    }

    /**
     * Get formatted error messages for display.
     *
     * @return array
     */
    public function getFormattedErrors(): array
    {
        $formattedErrors = [];
        
        foreach ($this->errors as $error) {
            $fieldName = $error->fieldName ?? 'general';
            $errorMessage = $error->errorMessage ?? 'Unknown error';
            $errorCode = $error->errorCode ?? null;
            
            $formattedErrors[] = [
                'field' => $fieldName,
                'message' => $errorMessage,
                'code' => $errorCode
            ];
        }
        
        return $formattedErrors;
    }

    /**
     * Get error messages as a string.
     *
     * @return string
     */
    public function getErrorMessageString(): string
    {
        if (empty($this->errors)) {
            return $this->remark ?? 'Unknown error';
        }
        
        $messages = [];
        foreach ($this->errors as $error) {
            $messages[] = $error->errorMessage ?? 'Unknown error';
        }
        
        return implode('; ', $messages);
    }

    /**
     * Check if the response has specific validation errors.
     *
     * @return bool
     */
    public function hasValidationErrors(): bool
    {
        foreach ($this->errors as $error) {
            if (isset($error->fieldName) && !empty($error->fieldName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get validation errors grouped by field.
     *
     * @return array
     */
    public function getValidationErrors(): array
    {
        $validationErrors = [];
        
        foreach ($this->errors as $error) {
            if (isset($error->fieldName) && !empty($error->fieldName)) {
                $field = $error->fieldName;
                if (!isset($validationErrors[$field])) {
                    $validationErrors[$field] = [];
                }
                $validationErrors[$field][] = $error->errorMessage ?? 'Invalid field';
            }
        }
        
        return $validationErrors;
    }

    /**
     * Get the global configuration from data.
     *
     * @return object|null
     */
    public function getGlobalConfiguration(): ?object
    {
        return $this->data->globalConfiguration ?? null;
    }

    /**
     * Get the terminal configuration from data.
     *
     * @return object|null
     */
    public function getTerminalConfiguration(): ?object
    {
        return $this->data->terminalConfiguration ?? null;
    }

    /**
     * Get the taxpayer configuration from data.
     *
     * @return object|null
     */
    public function getTaxpayerConfiguration(): ?object
    {
        return $this->data->taxpayerConfiguration ?? null;
    }

    /**
     * Get tax rates from global configuration.
     *
     * @return array
     */
    public function getTaxRates(): array
    {
        $globalConfig = $this->getGlobalConfiguration();
        return $globalConfig->taxrates ?? [];
    }

    /**
     * Get activated tax rate IDs from taxpayer configuration.
     *
     * @return array
     */
    public function getActivatedTaxRateIds(): array
    {
        $taxpayerConfig = $this->getTaxpayerConfiguration();
        return $taxpayerConfig->activatedTaxRateIds ?? [];
    }

    /**
     * Get activated tax rates from taxpayer configuration.
     *
     * @return array
     */
    public function getActivatedTaxRates(): array
    {
        $taxpayerConfig = $this->getTaxpayerConfiguration();
        return $taxpayerConfig->activatedTaxrates ?? [];
    }

    /**
     * Get activated levies from taxpayer configuration.
     *
     * @return array
     */
    public function getActivatedLevies(): array
    {
        $taxpayerConfig = $this->getTaxpayerConfiguration();
        return $taxpayerConfig->activatedLevies ?? [];
    }

    /**
     * Get tax office from taxpayer configuration.
     *
     * @return object|null
     */
    public function getTaxOffice(): ?object
    {
        $taxpayerConfig = $this->getTaxpayerConfiguration();
        return $taxpayerConfig->taxOffice ?? null;
    }

    /**
     * Get terminal site from terminal configuration.
     *
     * @return object|null
     */
    public function getTerminalSite(): ?object
    {
        $terminalConfig = $this->getTerminalConfiguration();
        return $terminalConfig->terminalSite ?? null;
    }

    /**
     * Get offline limit from terminal configuration.
     *
     * @return object|null
     */
    public function getOfflineLimit(): ?object
    {
        $terminalConfig = $this->getTerminalConfiguration();
        return $terminalConfig->offlineLimit ?? null;
    }

    /**
     * Check if the response contains all required data.
     *
     * @return bool
     */
    public function hasCompleteData(): bool
    {
        return isset($this->data->globalConfiguration) &&
               isset($this->data->terminalConfiguration) &&
               isset($this->data->taxpayerConfiguration);
    }

    /**
     * Check if terminal is active.
     *
     * @return bool
     */
    public function isTerminalActive(): bool
    {
        $terminalConfig = $this->getTerminalConfiguration();
        return $terminalConfig->isActiveTerminal ?? false;
    }

    /**
     * Check if taxpayer is VAT registered.
     *
     * @return bool
     */
    public function isVATRegistered(): bool
    {
        $taxpayerConfig = $this->getTaxpayerConfiguration();
        return $taxpayerConfig->isVATRegistered ?? false;
    }

    /**
     * Get TIN from taxpayer configuration.
     *
     * @return string|null
     */
    public function getTIN(): ?string
    {
        $taxpayerConfig = $this->getTaxpayerConfiguration();
        return $taxpayerConfig->tin ?? null;
    }

    /**
     * Get version numbers.
     *
     * @return array
     */
    public function getVersions(): array
    {
        return [
            'global' => $this->getGlobalConfiguration()->versionNo ?? null,
            'terminal' => $this->getTerminalConfiguration()->versionNo ?? null,
            'taxpayer' => $this->getTaxpayerConfiguration()->versionNo ?? null,
        ];
    }

    /**
     * Convert response to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'statusCode' => $this->statusCode,
            'remark' => $this->remark,
            'data' => $this->data,
            'errors' => $this->errors
        ];
    }

    /**
     * Convert response to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Magic method to get data properties directly.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this->data, $name)) {
            return $this->data->$name;
        }
        
        return null;
    }

    /**
     * Magic method to check if data property exists.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return property_exists($this->data, $name);
    }

    /**
     * Magic method to convert to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Get a summary of the response.
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->isSuccess(),
            'status_code' => $this->statusCode,
            'remark' => $this->remark,
            'has_data' => !empty((array)$this->data),
            'has_errors' => $this->hasErrors(),
            'error_count' => count($this->errors),
            'versions' => $this->getVersions(),
            'tin' => $this->getTIN(),
            'is_vat_registered' => $this->isVATRegistered(),
            'is_terminal_active' => $this->isTerminalActive(),
            'tax_rates_count' => count($this->getTaxRates()),
            'activated_tax_rates_count' => count($this->getActivatedTaxRateIds()),
            'activated_levies_count' => count($this->getActivatedLevies()),
        ];
    }

    /**
     * Validate specific data requirements.
     *
     * @param array $requiredFields
     * @return bool
     */
    public function validateData(array $requiredFields): bool
    {
        foreach ($requiredFields as $field) {
            if (!isset($this->data->$field)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get field from data with dot notation support.
     *
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public function getDataByPath(string $path, $default = null)
    {
        $parts = explode('.', $path);
        $current = $this->data;
        
        foreach ($parts as $part) {
            if (!is_object($current) || !property_exists($current, $part)) {
                return $default;
            }
            $current = $current->$part;
        }
        
        return $current;
    }

    /**
     * Check if data contains a specific path.
     *
     * @param string $path
     * @return bool
     */
    public function hasDataPath(string $path): bool
    {
        $parts = explode('.', $path);
        $current = $this->data;
        
        foreach ($parts as $part) {
            if (!is_object($current) || !property_exists($current, $part)) {
                return false;
            }
            $current = $current->$part;
        }
        
        return true;
    }
}