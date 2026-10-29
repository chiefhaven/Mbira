<?php

namespace Modules\EIS\Services\Stock;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Terminal\TerminalContext;

final class StockToolsOperations
{
    public const MUTATIONS = ['warehouse-transfer', 'submit-conversion', 'add-product'];

    public static function definitions(): array
    {
        return [
            'warehouse-inventory' => ['page' => 1, 'pageSize' => 50],
            'get-raw-material' => ['page' => 1, 'pageSize' => 50],
            'get-suppliers' => [], 'get-hs-codes' => [], 'get-units-of-measure' => [],
            'warehouse-transfer' => ['items' => [['barcode' => '', 'quantity' => 1, 'price' => null]]],
            'submit-conversion' => ['productionBatchId' => '', 'productionDate' => '',
                'rawMaterials' => [['productId' => '', 'productName' => '', 'availableQuantity' => 1, 'usedQuantity' => 1]],
                'finishedProducts' => [['quantity' => 1, 'unitOfMeasure' => '', 'expiryDate' => '',
                    'productDescription' => '', 'barcode' => '']]],
            'add-product' => ['barcode' => '', 'hsCode' => '', 'name' => '', 'description' => '', 'uom' => ''],
        ];
    }

    public static function payload(EisSetting $setting, string $operation, array $input): array
    {
        if (!array_key_exists($operation, self::definitions())) {
            throw ValidationException::withMessages(['operation' => 'Select a documented stock operation.']);
        }
        $text = 'required|string|min:1|max:1000';
        $rules = match ($operation) {
            'warehouse-inventory', 'get-raw-material' => ['page' => 'required|integer|between:1,2147483647',
                'pageSize' => 'required|integer|between:1,200'],
            'get-suppliers', 'get-hs-codes', 'get-units-of-measure' => [],
            'warehouse-transfer' => ['items' => 'required|array|min:1|max:100', 'items.*' => 'required|array',
                'items.*.barcode' => $text.'|distinct:strict', 'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.price' => 'nullable|numeric|min:0'],
            'submit-conversion' => ['productionBatchId' => 'required|string|min:1|max:255',
                'productionDate' => 'required|date_format:Y-m-d\TH:i:sP', 'rawMaterials' => 'required|array|min:1|max:100',
                'rawMaterials.*' => 'required|array', 'rawMaterials.*.productId' => $text.'|distinct:strict',
                'rawMaterials.*.productName' => $text, 'rawMaterials.*.availableQuantity' => 'required|numeric|min:0.01',
                'rawMaterials.*.usedQuantity' => 'required|numeric|min:0.01',
                'finishedProducts' => 'required|array|min:1|max:100', 'finishedProducts.*' => 'required|array',
                'finishedProducts.*.quantity' => 'required|numeric|min:0.01', 'finishedProducts.*.unitOfMeasure' => $text,
                'finishedProducts.*.expiryDate' => 'nullable|date_format:Y-m-d\TH:i:sP|after_or_equal:productionDate',
                'finishedProducts.*.productDescription' => $text, 'finishedProducts.*.barcode' => $text.'|distinct:strict'],
            'add-product' => ['barcode' => 'nullable|string|min:4|max:1000', 'hsCode' => $text, 'name' => $text,
                'description' => $text, 'uom' => $text],
        };
        $top = array_filter(array_keys($rules), fn ($key) => !str_contains($key, '.'));
        if (array_diff(array_keys($input), $top)) {
            throw ValidationException::withMessages(['payload' => 'Unknown fields or remote identity overrides are not allowed.']);
        }
        $lists = match ($operation) {
            'warehouse-transfer' => ['items' => ['barcode', 'quantity', 'price']],
            'submit-conversion' => ['rawMaterials' => ['productId', 'productName', 'availableQuantity', 'usedQuantity'],
                'finishedProducts' => ['quantity', 'unitOfMeasure', 'expiryDate', 'productDescription', 'barcode']],
            default => [],
        };
        foreach ($lists as $key => $fields) {
            if (isset($input[$key]) && (!is_array($input[$key]) || !array_is_list($input[$key]))) {
                throw ValidationException::withMessages([$key => 'Use a JSON list of items.']);
            }
            foreach ($input[$key] ?? [] as $item) {
                if (!is_array($item) || array_diff(array_keys($item), $fields)) {
                    throw ValidationException::withMessages([$key => 'Unknown or invalid item fields.']);
                }
                foreach (['quantity', 'price', 'availableQuantity', 'usedQuantity'] as $field) {
                    if (isset($item[$field]) && is_numeric($item[$field]) && !is_finite((float) $item[$field])) {
                        throw ValidationException::withMessages([$key => 'Use finite quantities and prices.']);
                    }
                }
            }
        }
        $payload = Validator::make($input, $rules)->validate();
        array_walk_recursive($payload, function ($value) {
            if (is_string($value) && trim($value) === '') {
                throw ValidationException::withMessages(['payload' => 'Text values must not be blank. Omit optional fields instead.']);
            }
        });
        foreach ($lists as $key => $fields) {
            foreach ($payload[$key] as &$item) {
                foreach (['quantity', 'price', 'availableQuantity', 'usedQuantity'] as $field) {
                    if (isset($item[$field])) {
                        $number = (float) $item[$field];
                        if (!is_finite($number) || $number > 1000000000) {
                            throw ValidationException::withMessages([$key => 'Use finite values no larger than 1,000,000,000.']);
                        }
                        $item[$field] = $number;
                    }
                }
                if ($key === 'rawMaterials' && $item['usedQuantity'] > $item['availableQuantity']) {
                    throw ValidationException::withMessages([$key => 'Used quantity cannot exceed available raw material.']);
                }
            }
            unset($item);
        }
        if (isset($payload['page'])) { $payload['page'] = (int) $payload['page']; $payload['pageSize'] = (int) $payload['pageSize']; }
        if ($operation === 'warehouse-transfer') {
            $payload = ['fromWarehouseToSite' => true, 'siteToWarehouse' => false,
                'fromSiteId' => null, 'toSiteId' => TerminalContext::site($setting), 'items' => $payload['items']];
        }
        return $payload;
    }
}
