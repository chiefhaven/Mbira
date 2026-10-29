<?php

namespace Modules\EIS\Services\Utilities;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;

final class UtilityOperations
{
    public const MUTATIONS = ['cancel-receipt', 'taxpayer-initial-inventory-upload'];

    public static function definitions(): array
    {
        return [
            'validate-vat5-certificate' => ['projectNumber' => '', 'certificateNumber' => '', 'quantity' => 1],
            'get-terminal-blocking-message' => [],
            'check-terminal-unblock-status' => [],
            'check-tin-authorization-requirement' => ['tin' => ''],
            'validate-authorization-code' => ['authorizationCode' => ''],
            'ping' => [],
            'product-status' => ['productId' => ''],
            'cancel-receipt' => ['receiptNumber' => '', 'reason' => '', 'supportingDocuments' => ''],
            'get-void-receipts' => [
                'status' => null, 'invoiceNumber' => '', 'startDate' => '', 'endDate' => '', 'page' => 1, 'pageSize' => 20,
            ],
            'get-terminal-site-products' => [],
            'taxpayer-initial-inventory-upload' => ['isLastBatch' => false, 'products' => [[
                'barCode' => '', 'productName' => '', 'productDescription' => '',
                'unitPrice' => 0, 'quantityInStock' => 0, 'costPrice' => 0, 'sellingPrice' => 0,
                'reorderLevel' => null, 'overQuantityStockLevel' => null,
            ]]],
        ];
    }

    public static function path(string $operation): string
    {
        if (!array_key_exists($operation, self::definitions())) {
            throw ValidationException::withMessages(['operation' => 'Select a documented EIS utility.']);
        }
        return (in_array($operation, ['cancel-receipt', 'get-void-receipts'], true) ? 'sales/' : 'utilities/').$operation;
    }

    public static function payload(EisSetting $setting, string $operation, array $input): array
    {
        self::path($operation);
        $text = 'required|string|min:1|max:1000';
        $rules = match ($operation) {
            'validate-vat5-certificate' => ['projectNumber' => $text, 'certificateNumber' => $text, 'quantity' => 'required|numeric|min:0'],
            'get-terminal-blocking-message', 'check-terminal-unblock-status' => ['terminalId' => 'sometimes|string'],
            'check-tin-authorization-requirement' => ['tin' => $text],
            'validate-authorization-code' => ['authorizationCode' => $text],
            'ping' => [],
            'product-status' => ['productId' => $text, 'tin' => 'sometimes|string'],
            'cancel-receipt' => [
                'receiptNumber' => $text, 'reason' => $text, 'supportingDocuments' => 'nullable|string|max:6990508',
            ],
            'get-void-receipts' => [
                'status' => 'nullable|integer|between:-2147483648,2147483647', 'invoiceNumber' => 'nullable|string|max:1000',
                'startDate' => 'nullable|date_format:Y-m-d\TH:i:sP', 'endDate' => 'nullable|date_format:Y-m-d\TH:i:sP|after_or_equal:startDate',
                'page' => 'required|integer|min:1|max:2147483647', 'pageSize' => 'required|integer|between:1,200',
            ],
            'get-terminal-site-products' => ['tin' => 'sometimes|string', 'siteId' => 'sometimes|string'],
            'taxpayer-initial-inventory-upload' => [
                'tin' => 'sometimes|string', 'isLastBatch' => 'required|boolean',
                'products' => 'required|array|min:1|max:50', 'products.*' => 'required|array',
                'products.*.barCode' => $text.'|distinct:strict', 'products.*.productName' => $text,
                'products.*.productDescription' => $text, 'products.*.unitPrice' => 'required|numeric|min:0',
                'products.*.quantityInStock' => 'required|numeric|min:0', 'products.*.costPrice' => 'required|numeric|min:0',
                'products.*.sellingPrice' => 'required|numeric|min:0', 'products.*.reorderLevel' => 'nullable|numeric',
                'products.*.overQuantityStockLevel' => 'nullable|numeric',
            ],
        };
        $allowed = array_filter(array_keys($rules), fn ($key) => !str_contains($key, '.'));
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['payload' => 'Unknown fields are not allowed by the EIS utility contract.']);
        }
        if ($operation === 'get-void-receipts') {
            $input += ['page' => 1, 'pageSize' => 20];
            if (!isset($input['startDate'])) {
                $rules['endDate'] = 'nullable|date_format:Y-m-d\TH:i:sP';
            }
        }
        if ($operation === 'taxpayer-initial-inventory-upload' && isset($input['products'])
            && (!is_array($input['products']) || !array_is_list($input['products']))) {
            throw ValidationException::withMessages(['products' => 'Inventory products must be a JSON list.']);
        }
        $payload = Validator::make($input, $rules)->validate();
        if (in_array($operation, ['get-terminal-blocking-message', 'check-terminal-unblock-status'], true)) {
            self::owned($payload, 'terminalId', (string) $setting->device_id);
        }
        if (in_array($operation, ['product-status', 'get-terminal-site-products', 'taxpayer-initial-inventory-upload'], true)) {
            self::owned($payload, 'tin', (string) $setting->tpin);
        }
        if ($operation === 'get-terminal-site-products') {
            self::owned($payload, 'siteId', TerminalContext::site($setting));
        }
        if ($operation === 'cancel-receipt' && isset($payload['supportingDocuments'])) {
            $encoded = $payload['supportingDocuments'];
            $bytes = base64_decode($encoded, true);
            if ($bytes === false || base64_encode($bytes) !== $encoded || strlen($bytes) > 5 * 1024 * 1024) {
                throw ValidationException::withMessages(['supportingDocuments' => 'Use canonical base64 supporting documents, at most 5 MiB decoded.']);
            }
        }
        if ($operation === 'taxpayer-initial-inventory-upload') {
            $itemKeys = ['barCode', 'productName', 'productDescription', 'unitPrice', 'quantityInStock',
                'costPrice', 'sellingPrice', 'reorderLevel', 'overQuantityStockLevel'];
            foreach ($input['products'] as $index => $item) {
                if (array_diff(array_keys($item), $itemKeys)) {
                    throw ValidationException::withMessages(['products' => 'Unknown inventory item fields are not allowed.']);
                }
                foreach (['unitPrice', 'quantityInStock', 'costPrice', 'sellingPrice', 'reorderLevel', 'overQuantityStockLevel'] as $field) {
                    if (isset($payload['products'][$index][$field])) {
                        $value = (float) $payload['products'][$index][$field];
                        if (!is_finite($value) || abs($value) > 1000000000) {
                            throw ValidationException::withMessages(['products' => 'Use finite inventory values no larger than 1,000,000,000.']);
                        }
                        $payload['products'][$index][$field] = $value;
                    }
                }
            }
            $payload['products'] = array_values($payload['products']);
            $payload['isLastBatch'] = (bool) $payload['isLastBatch'];
        }
        if ($operation === 'validate-vat5-certificate') {
            $payload['quantity'] = (float) $payload['quantity'];
            if (!is_finite($payload['quantity'])) {
                throw ValidationException::withMessages(['quantity' => 'Use a finite certificate quantity.']);
            }
        }
        foreach (['page', 'pageSize', 'status'] as $key) {
            if (isset($payload[$key])) { $payload[$key] = (int) $payload[$key]; }
        }
        return $payload;
    }

    private static function owned(array &$payload, string $field, string $value): void
    {
        if (trim($value) === '' || (isset($payload[$field]) && $payload[$field] !== $value)) {
            throw ValidationException::withMessages([$field => 'This identity must match the selected business terminal.']);
        }
        $payload[$field] = $value;
    }
}
