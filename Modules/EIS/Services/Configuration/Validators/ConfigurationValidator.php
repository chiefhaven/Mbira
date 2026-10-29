<?php

namespace Modules\EIS\Services\Configuration\Validators;

use Modules\EIS\Services\Configuration\Exceptions\ValidationException;

class ConfigurationValidator
{
    /**
     * Validate the EIS configuration data.
     *
     * @param object $data
     * @throws ValidationException
     */
    public function validate(object $data): void
    {
        $this->validateGlobalConfiguration($data->globalConfiguration);
        $this->validateTerminalConfiguration($data->terminalConfiguration);
        $this->validateTaxpayerConfiguration($data->taxpayerConfiguration);
    }

    /**
     * Validate global configuration.
     *
     * @param object $globalConfig
     * @throws ValidationException
     */
    private function validateGlobalConfiguration(object $globalConfig): void
    {
        // Validate version number
        if (!isset($globalConfig->versionNo) || !is_numeric($globalConfig->versionNo)) {
            throw new ValidationException('Invalid or missing global version number');
        }

        if ($globalConfig->versionNo < 0) {
            throw new ValidationException('Global version number cannot be negative');
        }

        // Validate tax rates
        if (!isset($globalConfig->taxrates) || !is_array($globalConfig->taxrates)) {
            throw new ValidationException('Invalid or missing tax rates');
        }

        if (empty($globalConfig->taxrates)) {
            throw new ValidationException('No tax rates found');
        }

        foreach ($globalConfig->taxrates as $index => $rate) {
            if (!isset($rate->id) || empty($rate->id)) {
                throw new ValidationException("Tax rate at index {$index} has no ID");
            }

            if (!isset($rate->rate) || !is_numeric($rate->rate)) {
                throw new ValidationException("Tax rate '{$rate->id}' has invalid rate value");
            }

            if ($rate->rate < 0) {
                throw new ValidationException("Tax rate '{$rate->id}' cannot be negative");
            }

            if (!isset($rate->name) || empty($rate->name)) {
                throw new ValidationException("Tax rate '{$rate->id}' has no name");
            }

            if (!isset($rate->chargeMode) || !in_array($rate->chargeMode, ['Item', 'Amount'])) {
                throw new ValidationException("Tax rate '{$rate->id}' has invalid charge mode");
            }

            if (!isset($rate->ordinal) || !is_numeric($rate->ordinal) || $rate->ordinal <= 0) {
                throw new ValidationException("Tax rate '{$rate->id}' has invalid ordinal");
            }
        }
    }

    /**
     * Validate terminal configuration.
     *
     * @param object $terminalConfig
     * @throws ValidationException
     */
    private function validateTerminalConfiguration(object $terminalConfig): void
    {
        // Validate version
        if (!isset($terminalConfig->versionNo) || !is_numeric($terminalConfig->versionNo)) {
            throw new ValidationException('Invalid or missing terminal version number');
        }

        // Validate terminal label if present
        if (isset($terminalConfig->terminalLabel) && 
            !empty($terminalConfig->terminalLabel) && 
            !is_string($terminalConfig->terminalLabel)) {
            throw new ValidationException('Terminal label must be a string');
        }

        // Validate contact information
        if (isset($terminalConfig->emailAddress) && 
            !empty($terminalConfig->emailAddress) && 
            !filter_var($terminalConfig->emailAddress, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email address: ' . $terminalConfig->emailAddress);
        }

        // Validate phone number
        if (isset($terminalConfig->phoneNumber) && !empty($terminalConfig->phoneNumber)) {
            // Adjust regex as needed for your phone number format
            if (!preg_match('/^[0-9+\-\s()]{10,15}$/', $terminalConfig->phoneNumber)) {
                throw new ValidationException('Invalid phone number format');
            }
        }

        // Validate trading name
        if (isset($terminalConfig->tradingName) && 
            !empty($terminalConfig->tradingName) && 
            strlen($terminalConfig->tradingName) > 255) {
            throw new ValidationException('Trading name exceeds maximum length of 255 characters');
        }

        // Validate address lines
        if (isset($terminalConfig->addressLines) && is_array($terminalConfig->addressLines)) {
            foreach ($terminalConfig->addressLines as $line) {
                if (!is_string($line) || empty($line)) {
                    throw new ValidationException('Invalid address line format');
                }
            }
        }

        // Validate offline limit if present
        if (isset($terminalConfig->offlineLimit) && is_object($terminalConfig->offlineLimit)) {
            $this->validateOfflineLimit($terminalConfig->offlineLimit);
        }
    }

    /**
     * Validate offline limit.
     *
     * @param object $offlineLimit
     * @throws ValidationException
     */
    private function validateOfflineLimit(object $offlineLimit): void
    {
        $maxTransactionAge = $offlineLimit->maxTransactionAgeInHours
            ?? $offlineLimit->max_transaction_age_hours
            ?? $offlineLimit->max_transaction_age
            ?? null;

        if (!is_numeric($maxTransactionAge)) {
            throw new ValidationException('Invalid max transaction age in hours');
        }

        if ((float) $maxTransactionAge < 0) {
            throw new ValidationException('Max transaction age cannot be negative');
        }

        $maxCumulativeAmount = $offlineLimit->maxCummulativeAmount
            ?? $offlineLimit->max_cumulative_amount
            ?? $offlineLimit->max_cummulative_amount
            ?? null;

        if (!is_numeric($maxCumulativeAmount)) {
            throw new ValidationException('Invalid max cumulative amount');
        }

        if ((float) $maxCumulativeAmount < 0) {
            throw new ValidationException('Max cumulative amount cannot be negative');
        }
    }

    /**
     * Validate taxpayer configuration.
     *
     * @param object $taxpayerConfig
     * @throws ValidationException
     */
    private function validateTaxpayerConfiguration(object $taxpayerConfig): void
    {
        // Validate TIN
        if (isset($taxpayerConfig->tin) && !empty($taxpayerConfig->tin)) {
            // Adjust regex for your TIN format
            if (!preg_match('/^[0-9]{6,10}$/', $taxpayerConfig->tin)) {
                throw new ValidationException('Invalid TIN format: ' . $taxpayerConfig->tin);
            }
        } else {
            // TIN might be required, adjust as needed
            \Log::warning('Taxpayer TIN is missing or empty');
        }

        // Validate VAT registration status
        if (isset($taxpayerConfig->isVATRegistered) && 
            !is_bool($taxpayerConfig->isVATRegistered) && 
            !in_array($taxpayerConfig->isVATRegistered, [0, 1], true)) {
            throw new ValidationException('Invalid VAT registration status');
        }

        // Validate activated tax rate IDs if present
        if (isset($taxpayerConfig->activatedTaxRateIds) && is_array($taxpayerConfig->activatedTaxRateIds)) {
            foreach ($taxpayerConfig->activatedTaxRateIds as $rateId) {
                if (!is_string($rateId) || empty($rateId)) {
                    throw new ValidationException('Invalid activated tax rate ID');
                }
            }
        }
    }

    /**
     * Check if two configurations are identical.
     *
     * @param object $config1
     * @param object $config2
     * @return bool
     */
    public function isConfigurationIdentical(object $config1, object $config2): bool
    {
        $json1 = json_encode($config1);
        $json2 = json_encode($config2);
        
        return $json1 === $json2;
    }

    /**
     * Get configuration changes between two versions.
     *
     * @param object $oldConfig
     * @param object $newConfig
     * @return array
     */
    public function getConfigurationChanges(object $oldConfig, object $newConfig): array
    {
        $oldArray = json_decode(json_encode($oldConfig), true);
        $newArray = json_decode(json_encode($newConfig), true);
        
        $changes = [];
        $this->arrayDiff($oldArray, $newArray, '', $changes);
        
        return $changes;
    }

    /**
     * Recursive array diff to find changes.
     *
     * @param array $old
     * @param array $new
     * @param string $path
     * @param array $changes
     */
    private function arrayDiff(array $old, array $new, string $path, array &$changes): void
    {
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        
        foreach ($allKeys as $key) {
            $currentPath = $path ? $path . '.' . $key : $key;
            
            if (!array_key_exists($key, $old)) {
                $changes[$currentPath] = ['action' => 'added', 'value' => $new[$key]];
            } elseif (!array_key_exists($key, $new)) {
                $changes[$currentPath] = ['action' => 'removed', 'value' => $old[$key]];
            } elseif (is_array($old[$key]) && is_array($new[$key])) {
                $this->arrayDiff($old[$key], $new[$key], $currentPath, $changes);
            } elseif ($old[$key] !== $new[$key]) {
                $changes[$currentPath] = [
                    'action' => 'changed',
                    'old' => $old[$key],
                    'new' => $new[$key]
                ];
            }
        }
    }
}