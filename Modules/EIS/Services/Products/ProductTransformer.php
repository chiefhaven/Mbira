<?php

namespace Modules\EIS\Services\Products;

use Illuminate\Support\Facades\Log;

class ProductTransformer
{
    /**
     * Transform EIS product data to internal format with dual fallbacks.
     *
     * @param array $item
     * @param string $eisId
     * @return array
     */
    public function fromEis(array $item, string $eisId, ?string $requestSite = null): array
    {
        Log::debug('Transforming EIS product data', [
            'eis_id' => $eisId,
            'product_code' => $item['productCode'] ?? null,
            'product_name' => $item['productName'] ?? null
        ]);
        
        $stockSource = OpeningStockInitializer::stockSource($item);

        // Initialize the transformed product array with extracted values
        $transformed = [
            'name' => $this->extractName($item, $eisId),
            'sku' => $this->extractSku($item, $eisId),
            'type' => $this->extractType($item),
            'price' => $this->extractPrice($item),
            'cost' => $this->extractCost($item),
            'stock' => $stockSource['quantity'],
            'opening_stock_source' => $stockSource,
            'opening_stock_site_id' => OpeningStockInitializer::scopedSiteId($item, $requestSite),
            'manage_stock' => $this->extractManageStock($item),
            'unit_of_measure' => $this->extractUnitOfMeasure($item),
            'expiry_period' => $this->extractExpiryPeriod($item),
            'site_id' => OpeningStockInitializer::scopedSiteId($item, $requestSite),
            'description' => $this->extractDescription($item),
            'category' => $this->extractCategory($item),
            'brand' => $this->extractBrand($item),
            'tax_rate_id' => $this->extractTaxRateId($item),
            'is_active' => $this->extractIsActive($item),
        ];

        // Validate with dual fallback
        $this->validateAndFix($transformed, $eisId);

        Log::debug('Product transformation completed', [
            'eis_id' => $eisId,
            'transformed_name' => $transformed['name'],
            'transformed_sku' => $transformed['sku'],
            'transformed_price' => $transformed['price'],
            'transformed_stock' => $transformed['stock'],
            'stock_validation_reason' => $stockSource['reason'],
            'description' => $transformed['description']
        ]);

        return $transformed;
    }

    /**
     * Transform multiple EIS products.
     *
     * @param array $items
     * @return array
     */
    public function fromEisCollection(array $items): array
    {
        Log::info('Transforming EIS product collection', [
            'count' => count($items)
        ]);

        $transformed = [];

        foreach ($items as $item) {
            $eisId = $item['id'] ?? $item['productId'] ?? null;
            if ($eisId) {
                $transformed[] = $this->fromEis($item, $eisId);
            } else {
                Log::warning('EIS product missing ID', [
                    'item' => $item
                ]);
            }
        }

        Log::debug('EIS product collection transformation completed', [
            'total' => count($items),
            'transformed' => count($transformed)
        ]);

        return $transformed;
    }

    /**
     * Extract product name with dual fallbacks.
     *
     * @param array $item
     * @param string $eisId
     * @return string
     */
    private function extractName(array $item, string $eisId): string
    {
        // Primary: Check multiple name fields
        $name = $item['name'] ?? $item['productName'] ?? $item['description'] ?? null;
        
        // Secondary: Clean and validate
        if ($name && !empty(trim($name))) {
            return trim($name);
        }
        
        // Fallback 1: Use SKU if available
        $sku = $item['productCode'] ?? $item['sku'] ?? $item['code'] ?? null;
        if ($sku && !empty(trim($sku))) {
            Log::warning('Product name missing, using SKU as fallback', [
                'eis_id' => $eisId,
                'sku' => $sku
            ]);
            return 'Product ' . trim($sku);
        }
        
        // Fallback 2: Generate from EIS ID
        Log::warning('Product name and SKU missing, using EIS ID as fallback', [
            'eis_id' => $eisId
        ]);
        return 'Product-' . substr($eisId, 0, 8);
    }

    /**
     * Extract product SKU with dual fallbacks.
     *
     * @param array $item
     * @param string $eisId
     * @return string
     */
    private function extractSku(array $item, string $eisId): string
    {
        // Primary: Check multiple SKU fields
        $sku = $item['productCode'] ?? $item['sku'] ?? $item['code'] ?? null;
        
        // Secondary: Clean and validate
        if ($sku && !empty(trim($sku))) {
            return trim($sku);
        }
        
        // Fallback 1: Use product name if available
        $name = $item['name'] ?? $item['productName'] ?? null;
        if ($name && !empty(trim($name))) {
            $sku = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 10));
            Log::warning('Product SKU missing, using name as fallback', [
                'eis_id' => $eisId,
                'generated_sku' => $sku
            ]);
            return $sku ?: 'SKU-' . substr($eisId, 0, 6);
        }
        
        // Fallback 2: Generate from EIS ID
        Log::warning('Product SKU and name missing, generating from EIS ID', [
            'eis_id' => $eisId
        ]);
        return 'EIS-' . strtoupper(substr($eisId, 0, 8));
    }

    /**
     * Extract product type with dual fallbacks.
     *
     * @param array $item
     * @return string
     */
    private function extractType(array $item): string
    {
        // Primary: Check multiple type fields
        $type = $item['type'] ?? $item['productType'] ?? null;
        
        // Secondary: Validate allowed types
        $allowedTypes = ['single', 'variable', 'combo', 'service'];
        
        if ($type && in_array($type, $allowedTypes)) {
            return $type;
        }
        
        // Fallback 1: Detect from item structure
        if (isset($item['variations']) && is_array($item['variations']) && count($item['variations']) > 1) {
            Log::warning('Invalid product type, detecting as variable', [
                'type' => $type
            ]);
            return 'variable';
        }
        
        // Fallback 2: Default to single
        Log::warning('Invalid product type, defaulting to single', [
            'type' => $type
        ]);
        return 'single';
    }

    /**
     * Extract product price with dual fallbacks.
     *
     * @param array $item
     * @return float
     */
    private function extractPrice(array $item): float
    {
        // Primary: Check multiple price fields
        $price = (float) ($item['price'] ?? $item['sellingPrice'] ?? $item['unitPrice'] ?? 0);
        
        // Secondary: Validate non-negative
        if ($price >= 0) {
            return $price;
        }
        
        // Fallback 1: Try to calculate from cost + margin
        $cost = (float) ($item['cost'] ?? $item['purchasePrice'] ?? 0);
        if ($cost > 0) {
            $margin = $item['profitMargin'] ?? 20; // Default 20% margin
            $calculatedPrice = $cost * (1 + ($margin / 100));
            Log::warning('Product price negative, calculating from cost', [
                'original_price' => $price,
                'calculated_price' => $calculatedPrice
            ]);
            return round($calculatedPrice, 2);
        }
        
        // Fallback 2: Set to 0
        Log::warning('Product price negative and no cost available, setting to 0', [
            'price' => $price
        ]);
        return 0;
    }

    /**
     * Extract product cost with dual fallbacks.
     *
     * @param array $item
     * @return float
     */
    private function extractCost(array $item): float
    {
        // Primary: Check multiple cost fields
        $cost = (float) ($item['cost'] ?? $item['purchasePrice'] ?? $item['buyingPrice'] ?? 0);
        
        // Secondary: Validate non-negative
        if ($cost >= 0) {
            return $cost;
        }
        
        // Fallback 1: Estimate from price if available
        $price = (float) ($item['price'] ?? $item['sellingPrice'] ?? 0);
        if ($price > 0) {
            $estimatedCost = $price * 0.7; // Assume 30% margin
            Log::warning('Product cost negative, estimating from price', [
                'original_cost' => $cost,
                'estimated_cost' => $estimatedCost
            ]);
            return round($estimatedCost, 2);
        }
        
        // Fallback 2: Set to 0
        Log::warning('Product cost negative and no price available, setting to 0', [
            'cost' => $cost
        ]);
        return 0;
    }

    /**
     * Extract manage stock flag with dual fallbacks.
     *
     * @param array $item
     * @return bool
     */
    private function extractManageStock(array $item): bool
    {
        // Primary: Check multiple fields
        $manageStock = $item['isProduct'] ?? $item['manageStock'] ?? $item['trackStock'] ?? false;
        foreach (['isProduct', 'manageStock', 'trackStock'] as $field) {
            if (array_key_exists($field, $item)) {
                return filter_var($item[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }
        
        // Secondary: Convert to boolean
        $boolValue = filter_var($manageStock, FILTER_VALIDATE_BOOLEAN);
        
        // Fallback 1: Check if stock is set
        if (!$boolValue) {
            $stock = (float) ($item['isProduct']  ?? $item['stock'] ?? 0);
            if ($stock > 0) {
                Log::warning('Manage stock not set but stock exists, enabling', [
                    'stock' => $stock
                ]);
                return true;
            }
        }
        
        // Fallback 2: Return boolean value
        return $boolValue;
    }

    /**
     * Extract unit of measure with dual fallbacks.
     *
     * @param array $item
     * @return string|null
     */
    private function extractUnitOfMeasure(array $item): ?string
    {
        // Primary: Check multiple unit fields
        $unit = $item['unitOfMeasure'] ?? $item['unit'] ?? $item['uom'] ?? null;
        
        // Secondary: Clean
        if ($unit && !empty(trim($unit))) {
            return trim($unit);
        }
        
        // Fallback 1: Try to infer from item type
        $type = $item['type'] ?? $item['productType'] ?? null;
        if ($type === 'service') {
            Log::warning('Unit of measure missing for service, defaulting to hour', [
                'type' => $type
            ]);
            return 'hour';
        }
        
        // Fallback 2: Default null
        return null;
    }

    /**
     * Extract expiry period with dual fallbacks.
     *
     * @param array $item
     * @return string|null
     */
    private function extractExpiryPeriod(array $item): ?string
    {
        // Primary: Check multiple expiry fields
        $expiry = $item['productExpiryDate'] ?? $item['expiryDate'] ?? $item['expiry'] ?? null;
        
        // Secondary: Clean
        if ($expiry && !empty(trim($expiry))) {
            return trim($expiry);
        }
        
        // Fallback 1: Check for expiry in days/weeks/months
        $expiryDays = $item['expiryDays'] ?? $item['shelfLife'] ?? null;
        if ($expiryDays && is_numeric($expiryDays)) {
            Log::warning('Expiry date missing, using expiry days as fallback', [
                'expiry_days' => $expiryDays
            ]);
            return $expiryDays . ' days';
        }
        
        // Fallback 2: Default null
        return null;
    }

    /**
     * Extract site ID with dual fallbacks.
     *
     * @param array $item
     * @return string|null
     */
    private function extractSiteId(array $item): ?string
    {
        // Primary: Check multiple site fields
        $siteId = $item['siteId'] ?? $item['site_id'] ?? $item['locationId'] ?? null;
        
        // Secondary: Clean
        if ($siteId && !empty(trim($siteId))) {
            return trim($siteId);
        }
        
        // Fallback 1: Check in nested location object
        if (isset($item['location']) && is_array($item['location'])) {
            $siteId = $item['location']['id'] ?? $item['location']['siteId'] ?? null;
            if ($siteId && !empty(trim($siteId))) {
                Log::warning('Site ID missing in main, using location object as fallback', [
                    'site_id' => $siteId
                ]);
                return trim($siteId);
            }
        }
        
        // Fallback 2: Default null
        return null;
    }

    /**
     * Extract product description with dual fallbacks.
     *
     * @param array $item
     * @return string|null
     */
    private function extractDescription(array $item): ?string
    {
        // Primary: Check description field
        $description = $item['description'] ?? null;
        
        // Secondary: Clean
        if ($description && !empty(trim($description))) {
            return trim($description);
        }
        
        // Fallback 1: Use long description if available
        $longDesc = $item['longDescription'] ?? $item['detailedDescription'] ?? null;
        if ($longDesc && !empty(trim($longDesc))) {
            Log::warning('Description missing, using long description as fallback', [
                'long_desc_length' => strlen(trim($longDesc))
            ]);
            return trim($longDesc);
        }
        
        // Fallback 2: Use product name with prefix
        $name = $item['name'] ?? $item['productName'] ?? null;
        if ($name && !empty(trim($name))) {
            return 'Product: ' . trim($name);
        }
        
        return null;
    }

    /**
     * Extract product category with dual fallbacks.
     *
     * @param array $item
     * @return string|null
     */
    private function extractCategory(array $item): ?string
    {
        // Primary: Check multiple category fields
        $category = $item['category'] ?? $item['productCategory'] ?? $item['categoryName'] ?? null;
        
        // Secondary: Clean
        if ($category && !empty(trim($category))) {
            return trim($category);
        }
        
        // Fallback 1: Check in nested category object
        if (isset($item['categoryInfo']) && is_array($item['categoryInfo'])) {
            $category = $item['categoryInfo']['name'] ?? $item['categoryInfo']['categoryName'] ?? null;
            if ($category && !empty(trim($category))) {
                Log::warning('Category missing in main, using category info as fallback', [
                    'category' => $category
                ]);
                return trim($category);
            }
        }
        
        // Fallback 2: Use product type as category
        $type = $item['type'] ?? $item['productType'] ?? null;
        if ($type) {
            Log::warning('Category missing, using product type as fallback', [
                'type' => $type
            ]);
            return ucfirst($type) . ' Products';
        }
        
        return null;
    }

    /**
     * Extract product brand with dual fallbacks.
     *
     * @param array $item
     * @return string|null
     */
    private function extractBrand(array $item): ?string
    {
        // Primary: Check multiple brand fields
        $brand = $item['brand'] ?? $item['productBrand'] ?? $item['brandName'] ?? null;
        
        // Secondary: Clean
        if ($brand && !empty(trim($brand))) {
            return trim($brand);
        }
        
        // Fallback 1: Check in nested brand object
        if (isset($item['brandInfo']) && is_array($item['brandInfo'])) {
            $brand = $item['brandInfo']['name'] ?? $item['brandInfo']['brandName'] ?? null;
            if ($brand && !empty(trim($brand))) {
                Log::warning('Brand missing in main, using brand info as fallback', [
                    'brand' => $brand
                ]);
                return trim($brand);
            }
        }
        
        // Fallback 2: Use manufacturer if available
        $manufacturer = $item['manufacturer'] ?? $item['supplier'] ?? null;
        if ($manufacturer && !empty(trim($manufacturer))) {
            Log::warning('Brand missing, using manufacturer as fallback', [
                'manufacturer' => $manufacturer
            ]);
            return trim($manufacturer);
        }
        
        return null;
    }

    /**
     * Extract tax rate ID with dual fallbacks.
     *
     * @param array $item
     * @return string|null
     */
    private function extractTaxRateId(array $item): ?string
    {
        // Primary: Check multiple tax fields
        $taxRateId = $item['taxRateId'] ?? $item['tax_id'] ?? $item['taxCode'] ?? null;
        
        // Secondary: Clean
        if ($taxRateId && !empty(trim($taxRateId))) {
            return trim($taxRateId);
        }
        
        // Fallback 1: Check if tax rate is explicitly set
        $taxRate = $item['taxRate'] ?? $item['tax'] ?? null;
        if ($taxRate && is_numeric($taxRate) && $taxRate > 0) {
            Log::warning('Tax rate ID missing, using tax rate value as fallback', [
                'tax_rate' => $taxRate
            ]);
            return 'TAX-' . $taxRate;
        }
        
        // Fallback 2: Check if tax included
        $taxIncluded = $item['taxIncluded'] ?? $item['includesTax'] ?? false;
        if (filter_var($taxIncluded, FILTER_VALIDATE_BOOLEAN)) {
            Log::warning('Tax rate ID missing, using default tax included ID', [
                'tax_included' => $taxIncluded
            ]);
            return 'TAX-INCLUDED';
        }
        
        return null;
    }

    /**
     * Extract is active flag with dual fallbacks.
     *
     * @param array $item
     * @return bool
     */
    private function extractIsActive(array $item): bool
    {
        // Primary: Check multiple status fields
        $isActive = $item['isActive'] ?? $item['status'] ?? $item['active'] ?? true;
        
        // Secondary: Convert to boolean
        $boolValue = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
        
        // Fallback 1: Check if stock is available
        if (!$boolValue) {
            $stock = (float) ($item['qty_available'] ?? $item['stock'] ?? 0);
            if ($stock > 0) {
                Log::warning('Product marked inactive but has stock, activating', [
                    'stock' => $stock
                ]);
                return true;
            }
        }
        
        // Fallback 2: Return boolean value
        return $boolValue;
    }

    /**
     * Validate and fix transformed data with dual fallbacks.
     *
     * @param array $data
     * @param string $eisId
     * @return void
     */
    private function validateAndFix(array &$data, string $eisId): void
    {
        $issues = [];
        
        // Ensure name is not empty
        if (empty($data['name']) || trim($data['name']) === '') {
            $data['name'] = 'Product-' . substr($eisId, 0, 8);
            $issues[] = 'name';
        }
        
        // Ensure SKU is not empty
        if (empty($data['sku']) || trim($data['sku']) === '') {
            $data['sku'] = 'EIS-' . strtoupper(substr($eisId, 0, 8));
            $issues[] = 'sku';
        }
        
        // Ensure price is not negative
        if ($data['price'] < 0) {
            $data['price'] = 0;
            $issues[] = 'price';
        }
        
        // Ensure cost is not negative
        if ($data['cost'] < 0) {
            $data['cost'] = 0;
            $issues[] = 'cost';
        }
        
        if (!empty($issues)) {
            Log::warning('Product data fixed with fallbacks', [
                'eis_id' => $eisId,
                'fixed_fields' => $issues,
                'fixed_data' => $data
            ]);
        }
    }

    /**
     * Validate transformed data.
     *
     * @param array $data
     * @return bool
     */
    public function validate(array $data): bool
    {
        if (empty($data['name'])) {
            Log::warning('Validation failed: Product name is required', [
                'data' => $data
            ]);
            return false;
        }

        if (empty($data['sku'])) {
            Log::warning('Validation failed: Product SKU is required', [
                'data' => $data
            ]);
            return false;
        }

        if ($data['price'] < 0) {
            Log::warning('Validation failed: Product price cannot be negative', [
                'price' => $data['price']
            ]);
            return false;
        }

        if ($data['cost'] < 0) {
            Log::warning('Validation failed: Product cost cannot be negative', [
                'cost' => $data['cost']
            ]);
            return false;
        }

        if (OpeningStockInitializer::decimal($data['stock'] ?? null) === null) {
            Log::warning('Validation failed: Product stock is missing or invalid', [
                'stock' => $data['stock'] ?? null,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get field mapping for documentation.
     *
     * @return array
     */
    public function getFieldMapping(): array
    {
        return [
            'EIS Field' => 'Internal Field',
            'productName' => 'name',
            'productCode' => 'sku',
            'price' => 'price',
            'cost' => 'cost',
            'quantity' => 'stock',
            'isProduct' => 'manage_stock',
            'unitOfMeasure' => 'unit_of_measure',
            'productExpiryDate' => 'expiry_period',
            'siteId' => 'site_id',
            'description' => 'description',
            'category' => 'category',
            'brand' => 'brand',
            'taxRateId' => 'tax_rate_id',
            'isActive' => 'is_active',
        ];
    }
}