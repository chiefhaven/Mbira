<?php

namespace Modules\EIS\Services\Products;

use App\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Models\EisProductMap;
use Modules\EIS\Models\EisTaxRate;

class ProductUpsertService {
    /**
     * Cache for unit lookups
     */
    private array $unitCache = [];
    
    /**
     * Cache for tax rate lookups
     */
    private array $taxRateCache = [];

    /**
     * Sync EIS product into UltimatePOS.
     */
    public function upsert(
        int $businessId,
        array $item,
        string $eisId,
        ?object $settings = null,
        ?int $locationId = null
    ): Product {
        if (trim($eisId) === '') {
            throw new \InvalidArgumentException('EIS product identifier is required.');
        }
        if (!$settings || !$locationId || $locationId <= 0) {
            throw new \InvalidArgumentException('EIS product upsert requires its selected terminal and location.');
        }

        \Modules\EIS\Services\Terminal\TerminalContext::assertBusiness($settings, $businessId);
        $settings = \Modules\EIS\Services\Terminal\TerminalContext::settings(
            $businessId,
            isset($settings->jwt_token) ? $settings->jwt_token : null,
            isset($settings->id) ? (int) $settings->id : null,
            $locationId
        );
        if (($item['site_id'] ?? null) !== \Modules\EIS\Services\Terminal\TerminalContext::site($settings)
            || (int) $settings->location_id !== $locationId) {
            throw new \InvalidArgumentException('EIS product does not belong to the business terminal site.');
        }
        return \App\Services\EisProductPolicy::duringSync($businessId, fn () => DB::transaction(function () use (
            $businessId,
            $item,
            $eisId,
            $settings,
            $locationId
        ) {
            $configurationId = EisConfiguration::where('business_id', $businessId)
                ->where('eis_setting_id', $settings->id)->lockForUpdate()->value('id');

            Log::info('EIS product sync started', [
                'business_id' => $businessId,
                'eis_product_id' => $eisId,
                'sku' => $item['sku'] ?? null,
                'tax_rate_short_name' => $item['tax_rate_id'] ?? null
            ]);

            /*
            |--------------------------------------------------------------------------
            | FIND EXISTING EIS MAP
            |--------------------------------------------------------------------------
            */
            $map = EisProductMap::withTrashed()
                ->where('business_id', $businessId)
                ->where('eis_product_id', $eisId)
                ->first();

            $product = null;

            /*
            |--------------------------------------------------------------------------
            | RESTORE EXISTING MAP
            |--------------------------------------------------------------------------
            */
            if ($map) {
                if ($map->trashed()) {
                    Log::info('Restoring deleted EIS map', [
                        'map_id' => $map->id,
                        'eis_product_id' => $eisId,
                    ]);
                    $map->restore();
                }

                /*
                |--------------------------------------------------------------------------
                | LOAD PRODUCT FROM MAP
                |--------------------------------------------------------------------------
                */
                $product = Product::withTrashed()
                    ->where('id', $map->product_id)
                    ->where('business_id', $businessId)
                    ->first();
                if (!$product && Product::withTrashed()->whereKey($map->product_id)->exists()) {
                    throw new \UnexpectedValueException('EIS product map points to another business.');
                }

                /*
                |--------------------------------------------------------------------------
                | PRODUCT WAS PERMANENTLY DELETED
                |--------------------------------------------------------------------------
                */
                if (!$product) {
                    throw new \UnexpectedValueException('EIS product map points to a missing product.');
                }
                if (!empty($product->eis_product_id) && (string) $product->eis_product_id !== $eisId) {
                    throw new \UnexpectedValueException('EIS product map and product identifiers conflict.');
                }

                /*
                |--------------------------------------------------------------------------
                | RESTORE SOFT DELETED PRODUCT
                |--------------------------------------------------------------------------
                */
                if ($product->exists && $product->trashed()) {
                    Log::info('Restoring deleted product', [
                        'product_id' => $product->id,
                    ]);
                    $product->restore();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | FIND PRODUCT BY SKU (PRIMARY FALLBACK)
            |--------------------------------------------------------------------------
            */
            if (!$product || !$product->exists) {
                $sku = $item['sku'] ?? null;
                
                if ($sku) {
                    $matches = Product::withTrashed()
                        ->where('business_id', $businessId)
                        ->where('sku', $sku)
                        ->limit(2)->get();
                    if ($matches->count() > 1) {
                        throw new \UnexpectedValueException('EIS SKU matches multiple business products.');
                    }
                    $product = $matches->first();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | FIND PRODUCT BY NAME (SECONDARY FALLBACK)
            |--------------------------------------------------------------------------
            */
            if (!$product || !$product->exists) {
                $name = $item['name'] ?? $item['productName'] ?? null;
                
                if ($name) {
                    $matches = Product::withTrashed()
                        ->where('business_id', $businessId)
                        ->where('name', $name)
                        ->limit(2)->get();
                    if ($matches->count() > 1) {
                        throw new \UnexpectedValueException('EIS name matches multiple business products.');
                    }
                    $product = $matches->first();
                    
                    if ($product) {
                        Log::info('Product found by name fallback', [
                            'name' => $name,
                            'product_id' => $product->id,
                            'sku' => $product->sku
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | FIND PRODUCT BY EIS ID IN PRODUCT TABLE (FINAL FALLBACK)
            |--------------------------------------------------------------------------
            */
            if (!$product || !$product->exists) {
                $matches = Product::withTrashed()
                    ->where('business_id', $businessId)
                    ->where('eis_product_id', $eisId)
                    ->limit(2)->get();
                if ($matches->count() > 1) {
                    throw new \UnexpectedValueException('EIS identifier matches multiple business products.');
                }
                $product = $matches->first();
                
                if ($product) {
                    Log::info('Product found by EIS ID fallback', [
                        'eis_id' => $eisId,
                        'product_id' => $product->id
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | CREATENEW PRODUCT
            |--------------------------------------------------------------------------
            */
            if (!$product) {
                Log::info('Creating new product', [
                    'eis_product_id' => $eisId,
                    'sku' => $item['sku'] ?? null,
                ]);
                $product = new Product();
            }

            if ($product && $product->trashed()) {
                $product->restore();
            }

            /*
            |--------------------------------------------------------------------------
            | PRODUCTDATA
            |--------------------------------------------------------------------------
            */
            $isNew = !$product->exists;
            $excludeOpeningStock = !$map && !$isNew && empty($product->eis_product_id);
            if (!$map && !$isNew && EisProductMap::withTrashed()->where('product_id', $product->id)
                ->where('business_id', '!=', $businessId)->exists()) {
                throw new \UnexpectedValueException('EIS product map points to a product owned by another business.');
            }
            $product->business_id = $businessId;

            if ($isNew) {
                $product->type = $item['type'] ?? 'single';
                $product->created_by = config('eis.system_user_id', 1);
            }
            if ($isNew) {
                $product->enable_stock = $item['manage_stock'] ?? false;
            }

            /*
            |--------------------------------------------------------------------------
            | EIS CONTROLLED FIELDS WITH DUAL FALLBACKS
            |--------------------------------------------------------------------------
            */
            $product->name = $this->getProductName($item, $eisId);
            $product->product_description = $this->getProductDescription($item);
            $product->sku = $this->getProductSku($item, $eisId);
            $taxRateId = $this->getProductTaxRateId(
                $item['tax_rate_id'] ?? null,
                $businessId,
                $configurationId ? (int) $configurationId : null,
                $product->tax
            );
            if ($taxRateId !== null) {
                $product->tax = $taxRateId;
            }
            $product->eis_product_id = $eisId;
            $product->eis_last_synced_at = now();

            /*
            |--------------------------------------------------------------------------
            | UNIT MAPPING WITH FALLBACKS
            |--------------------------------------------------------------------------
            */
            $unitId = $this->getUnitId(
                $businessId,
                $item['unit_of_measure'] ?? $item['unit'] ?? null
            );

            if ($unitId) {
                $product->unit_id = $unitId;
            } else {
                // Fallback: Try to find default unit
                $defaultUnit = DB::table('units')
                    ->where('business_id', $businessId)
                    ->whereIn('short_name', ['pcs', 'pc'])
                    ->first();
                
                if ($defaultUnit) {
                    $product->unit_id = $defaultUnit->id;
                    Log::warning('Using default unit as fallback', [
                        'unit_id' => $defaultUnit->id,
                        'unit_name' => $defaultUnit->short_name
                    ]);
                } else {
                    $anyUnit = DB::table('units')
                        ->where('business_id', $businessId)
                        ->first();
                    
                    if ($anyUnit) {
                        $product->unit_id = $anyUnit->id;
                        Log::warning('Using any available unit as final fallback', [
                            'unit_id' => $anyUnit->id
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | EXPIRY WITH FALLBACKS
            |--------------------------------------------------------------------------
            */
            $product->expiry_period = $this->getExpiryPeriod($item);

            $product->save();

            Log::info('Product saved', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'new' => $isNew,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CREATE/UPDATE EIS MAP
            |--------------------------------------------------------------------------
            */
            $this->saveProductMap(
                $businessId,
                $eisId,
                $product,
                $item['sku'] ?? null,
                $excludeOpeningStock
            );

            /*
            |--------------------------------------------------------------------------
            | CONTINUE WITH:
            | - Product variation
            | - Variation
            | - Location
            | - Stock
            |--------------------------------------------------------------------------
            */
            return $this->syncVariationAndLocation(
                $businessId,
                $product,
                $item,
                $locationId,
                $configurationId ? (int) $configurationId : null
            );
        }));
    }

    /**
     * Sync variation and stock details.
     */
    private function syncVariationAndLocation(
        int $businessId,
        Product $product,
        array $item,
        int $locationId,
        ?int $configurationId
    ): Product {

        if ($product->type !== 'single' || $product->variations()->withTrashed()->count() > 1
            || $product->product_variations()->count() > 1) {
            Log::warning('EIS stock cannot be assigned to an ambiguous product variation.', [
                'product_id' => $product->id,
                'type' => $product->type,
            ]);
            throw new \UnexpectedValueException('EIS stock cannot be assigned to an ambiguous product variation.');
        }
        /*
        |--------------------------------------------------------------------------
        | PRODUCT VARIATION
        |--------------------------------------------------------------------------
        */
        $productVariationId = DB::table('product_variations')
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->value('id');

        if (!$productVariationId) {
            $productVariationId = DB::table('product_variations')
                ->insertGetId([
                    'variation_template_id' => null,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'is_dummy' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('Product variation created', [
                'product_id' => $product->id,
                'variation_id' => $productVariationId
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | VARIATION
        |--------------------------------------------------------------------------
        */
        $variation = $product->variations()
            ->withTrashed()
            ->where('product_variation_id', $productVariationId)
            ->lockForUpdate()
            ->first();
        $restoredDeletedVariation = $variation && $variation->trashed();
        if ($variation && $variation->trashed()) {
            $variation->restore();
        }

        if (!$variation) {
            $variation = $product->variations()->create([
                'product_variation_id' => $productVariationId,
                'name' => $product->name,
                'default_sell_price' => 0,
                'default_purchase_price' => 0,
                'sell_price_inc_tax' => 0,
                'profit_percent' => 0,
            ]);

            Log::info('Variation created', [
                'variation_id' => $variation->id
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | GET TAX RATE FROM SHORT NAME
        |--------------------------------------------------------------------------
        */
        $taxRate = $this->getTaxRateByShortName($businessId, $item, $configurationId);
        $taxPercentage = $taxRate ? (float) $taxRate->rate : 0;

        /*
        |--------------------------------------------------------------------------
        | UPDATEPRICES WITH TAX CALCULATION
        |--------------------------------------------------------------------------
        */
        $sellPrice = $this->getSellPrice($item, $variation);
        $costIncTax = $this->getCostPrice($item, $variation);
        $cost = $taxPercentage > 0 ? $costIncTax / (1 + ($taxPercentage / 100)) : $costIncTax;
        
        // Calculate sell price excluding tax
        // Formula: Price Excl Tax = Price / (1 + TaxRate/100)
        $sellPriceExclTax = $taxPercentage > 0 
            ? $sellPrice / (1 + ($taxPercentage / 100))
            : $sellPrice;
        
        // Calculate tax amount
        $taxAmount = $sellPrice - $sellPriceExclTax;

        Log::debug('Price calculation with tax', [
            'tax_percentage' => $taxPercentage,
            'tax_rate_short_name' => $item['taxRateId'] ?? null,
            'tax_rate_id' => $taxRate->id ?? null,
            'sell_price_incl_tax' => $sellPrice,
            'sell_price_excl_tax' => $sellPriceExclTax,
            'tax_amount' => $taxAmount
        ]);

        $variation->update([
            'default_sell_price' => round($sellPriceExclTax, 2), // Price excluding tax
            'default_purchase_price' => $cost,
            'dpp_inc_tax' => $costIncTax,
            'sell_price_inc_tax' => round($sellPrice, 2), // Price including tax
            'sub_sku' => $item['sku'] ?? $product->sku,
            'profit_percent' => $this->profit($sellPriceExclTax, $cost),
        ]);

        /*
        |--------------------------------------------------------------------------
        | EXACT EIS SITE LOCATION
        |--------------------------------------------------------------------------
        */
        $location = DB::table('business_locations')->where('id', $locationId)
            ->where('business_id', $businessId)
            ->where('eis_site_id', $item['site_id'] ?? null)
            ->first();
        if (!$location) {
            Log::error('No location found for product', [
                'product_id' => $product->id,
                'business_id' => $businessId,
                'site_id' => $item['site_id'] ?? null
            ]);
            throw new \Exception(
                'EIS location not mapped: ' . ($item['site_id'] ?? 'NULL')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PRODUCT LOCATION
        |--------------------------------------------------------------------------
        */
        DB::table('product_locations')->updateOrInsert(
            [
                'product_id' => $product->id,
                'location_id' => $locationId,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | STOCK LOCATION
        |--------------------------------------------------------------------------
        */
        if ($restoredDeletedVariation) {
            $openingStatus = 'deleted_variation_restored_without_stock';
        } else {
            $openingStock = $item['opening_stock_source'] ?? OpeningStockInitializer::stockSource($item);
            $openingStatus = (new OpeningStockInitializer(DB::connection()))->initialize(
                $businessId,
                (int) $product->id,
                (int) $variation->id,
                $locationId,
                (string) ($product->eis_product_id ?? ''),
                (string) ($item['site_id'] ?? ''),
                $openingStock
            );
        }

        Log::info('Product inventory synced', [
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'location_id' => $locationId,
            'opening_stock_status' => $openingStatus,
            'tax_percentage' => $taxPercentage,
            'tax_rate_short_name' => $item['taxRateId'] ?? null,
            'price_excl_tax' => round($sellPriceExclTax, 2),
            'price_incl_tax' => round($sellPrice, 2)
        ]);

        return $product;
    }

    /**
     * Get tax rate by short name from EIS data.
     *
     * @param int $businessId
     * @param array $item
     * @return object|null
     */
    private function getTaxRateByShortName(int $businessId, array $item, ?int $configurationId): ?object
    {
        $shortName = $item['tax_rate_id'] ?? $item['taxRateId'] ?? null;
        if (!$configurationId) {
            if (!empty($shortName)) {
                throw new \UnexpectedValueException('EIS tax lookup requires the selected terminal configuration.');
            }
            return null;
        }
        
        Log::debug('Looking for tax rate by short name', [
            'business_id' => $businessId,
            'configuration_id' => $configurationId,
            'short_name' => $shortName
        ]);

        // Check cache first
        $cacheKey = $configurationId . ':' . ($shortName ?? 'default');
        if (isset($this->taxRateCache[$cacheKey])) {
            Log::debug('Tax rate found in cache', [
                'short_name' => $shortName,
                'rate' => $this->taxRateCache[$cacheKey]->rate ?? null
            ]);
            return $this->taxRateCache[$cacheKey];
        }

        $taxRate = null;

        // Primary: Find by short name (case insensitive)
        if (!empty($shortName)) {
            $taxRate = EisTaxRate::where('configuration_id', $configurationId)
                ->where('tax_rate_id', $shortName)
                ->first();
            
            if ($taxRate) {
                Log::debug('Tax rate found by short name', [
                    'short_name' => $shortName,
                    'tax_rate_id' => $taxRate->id,
                    'rate' => $taxRate->rate
                ]);
            }
        }

        // Secondary: Try to find by name
        if (!$taxRate && !empty($shortName)) {
            $taxRate = EisTaxRate::where('configuration_id', $configurationId)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($shortName))])
                ->first();
            
            if ($taxRate) {
                Log::debug('Tax rate found by name', [
                    'short_name' => $shortName,
                    'tax_rate_id' => $taxRate->id,
                    'rate' => $taxRate->rate
                ]);
            }
        }
        if (!$taxRate && !empty($shortName)) {
            throw new \UnexpectedValueException('EIS tax rate '.$shortName.' is not configured for the selected terminal.');
        }

        // Fallback 1: Get default tax rate (standard rate with rate > 0)
        if (!$taxRate) {
            $taxRate = EisTaxRate::where('configuration_id', $configurationId)
                ->where('is_activated', true)
                ->where('rate', '>', 0)
                ->orderBy('rate', 'desc')
                ->first();
            
            if ($taxRate) {
                Log::warning('Using default tax rate as fallback', [
                    'short_name' => $shortName,
                    'tax_rate_id' => $taxRate->id,
                    'rate' => $taxRate->rate
                ]);
            }
        }

        // Fallback 2: Use zero-rated tax if no other found
        if (!$taxRate) {
            $taxRate = EisTaxRate::where('configuration_id', $configurationId)
                ->where('is_activated', true)
                ->where('rate', 0)
                ->first();
            
            if ($taxRate) {
                Log::warning('Using zero-rated tax as final fallback', [
                    'short_name' => $shortName,
                    'tax_rate_id' => $taxRate->id
                ]);
            }
        }

        // Fallback 3: Get any tax rate
        if (!$taxRate) {
            $taxRate = EisTaxRate::where('configuration_id', $configurationId)
                ->where('is_activated', true)
                ->first();
            
            if ($taxRate) {
                Log::warning('Using any available tax rate as final fallback', [
                    'short_name' => $shortName,
                    'tax_rate_id' => $taxRate->id,
                    'rate' => $taxRate->rate
                ]);
            }
        }

        // Cache the result
        if ($taxRate) {
            $this->taxRateCache[$cacheKey] = $taxRate;
        }

        Log::debug('Tax rate lookup result', [
            'business_id' => $businessId,
            'short_name' => $shortName,
            'found' => !empty($taxRate),
            'rate' => $taxRate->rate ?? null,
            'tax_rate_id' => $taxRate->id ?? null
        ]);

        return $taxRate;
    }

    /**
     * Get sell price with dual fallbacks and tax consideration.
     */
    private function getSellPrice(array $item, $variation): float
    {
        // Prefer the terminal's current catalogue price; use local values only as a fallback.
        $price = (float) ($item['price'] ?? $item['sellingPrice'] ?? $item['unitPrice'] ?? 0);
        if ($price > 0) {
            return $price;
        }

        if ($variation->exists && $variation->sell_price_inc_tax > 0) {
            return $variation->sell_price_inc_tax;
        }
        
        // Fallback 1: Calculate from cost with margin
        $cost = (float) ($item['cost'] ?? 0);
        if ($cost > 0) {
            $margin = $item['profitMargin'] ?? 20;
            $calculatedPrice = $cost * (1 + ($margin / 100));
            Log::warning('Calculating price from cost as fallback', [
                'cost' => $cost,
                'calculated_price' => $calculatedPrice
            ]);
            return round($calculatedPrice, 2);
        }
        
        // Fallback 2: Use default 0
        Log::warning('No price available, using 0 as final fallback');
        return 0;
    }

    /**
     * Get cost price with dual fallbacks.
     */
    private function getCostPrice(array $item, $variation): float
    {
        // Prefer the terminal's current catalogue cost; use local values only as a fallback.
        $cost = (float) ($item['cost'] ?? $item['purchasePrice'] ?? $item['buyingPrice'] ?? 0);
        if ($cost > 0) {
            return $cost;
        }

        if ($variation->exists && $variation->default_purchase_price > 0) {
            return $variation->dpp_inc_tax > 0
                ? $variation->dpp_inc_tax
                : $variation->default_purchase_price;
        }
        
        // Fallback 1: Estimate from price
        $price = (float) ($item['price'] ?? $item['sellingPrice'] ?? 0);
        if ($price > 0) {
            $estimatedCost = $price * 0.7;
            Log::warning('Estimating cost from price as fallback', [
                'price' => $price,
                'estimated_cost' => $estimatedCost
            ]);
            return round($estimatedCost, 2);
        }
        
        // Fallback 2: Use default 0
        Log::warning('No cost available, using 0 as final fallback');
        return 0;
    }

    /**
     * Get product name with dual fallbacks.
     */
    private function getProductName(array $item, string $eisId): string
    {
        $name = $item['name'] ?? $item['productName'] ?? $item['description'] ?? null;
        if ($name && !empty(trim($name))) {
            return trim($name);
        }
        
        $sku = $item['sku'] ?? $item['productCode'] ?? null;
        if ($sku && !empty(trim($sku))) {
            Log::warning('Using SKU as name fallback', [
                'eis_id' => $eisId,
                'sku' => $sku
            ]);
            return 'Product ' . trim($sku);
        }
        
        Log::warning('Using generated name from EIS ID as final fallback', [
            'eis_id' => $eisId
        ]);
        return 'Product-' . substr($eisId, 0, 8);
    }

    /**
     * Get product description with dual fallbacks.
     */
    private function getProductDescription(array $item): ?string
    {
        $desc = $item['productDescription'] ?? $item['description'] ?? null;
        if ($desc && !empty(trim($desc))) {
            return trim($desc);
        }
        
        $longDesc = $item['longDescription'] ?? $item['detailedDescription'] ?? null;
        if ($longDesc && !empty(trim($longDesc))) {
            Log::warning('Using long description as fallback');
            return trim($longDesc);
        }
        
        $name = $item['name'] ?? $product->name ?? null;
        if ($name && !empty(trim($name))) {
            return 'Product: ' . trim($name);
        }
        
        return null;
    }

    /**
     * Get product taxRate with dual fallbacks.
     */
    private function getProductTaxRateId(?string $item, int $businessId, ?int $configurationId, $existingTaxId): ?int
    {
        if ($item === null || trim($item) === '') {
            return null;
        }
        if (!$configurationId) {
            throw new \UnexpectedValueException('EIS product tax requires the selected terminal configuration.');
        }
        $selected = EisTaxRate::where('configuration_id', $configurationId)->where('tax_rate_id', $item)->first();
        if (!$selected) {
            throw new \UnexpectedValueException('EIS tax rate '.$item.' is not configured for the selected terminal.');
        }
        if ($existingTaxId && (int) $existingTaxId !== (int) $selected->id) {
            $existing = EisTaxRate::whereKey($existingTaxId)
                ->whereHas('configuration', fn ($query) => $query->where('business_id', $businessId))
                ->first();
            $sameRate = $existing && number_format((float) $existing->rate, 3, '.', '')
                === number_format((float) $selected->rate, 3, '.', '');
            $sameMode = $existing && (string) ($existing->charge_mode ?? 'Item')
                === (string) ($selected->charge_mode ?? 'Item');
            if (!$sameRate || !$sameMode) {
                throw new \UnexpectedValueException(
                    'EIS terminal tax rate conflicts with the rate already assigned to this shared business product.'
                );
            }
            return (int) $existing->id;
        }
        return (int) $selected->id;
    }

    /**
     * Get product SKU with dual fallbacks.
     */
    private function getProductSku(array $item, string $eisId): string
    {
        $sku = $item['sku'] ?? $item['productCode'] ?? $item['code'] ?? null;
        if ($sku && !empty(trim($sku))) {
            return trim($sku);
        }
        
        $name = $item['name'] ?? $item['productName'] ?? null;
        if ($name && !empty(trim($name))) {
            $generatedSku = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 10));
            if ($generatedSku) {
                Log::warning('Generating SKU from name as fallback', [
                    'name' => $name,
                    'generated_sku' => $generatedSku
                ]);
                return $generatedSku;
            }
        }
        
        Log::warning('Generating SKU from EIS ID as final fallback', [
            'eis_id' => $eisId
        ]);
        return 'EIS-' . strtoupper(substr($eisId, 0, 8));
    }

    /**
     * Get expiry period with dual fallbacks.
     */
    private function getExpiryPeriod(array $item): ?string
    {
        $expiry = $item['expiry_period'] ?? $item['productExpiryDate'] ?? $item['expiryDate'] ?? null;
        if ($expiry && !empty(trim($expiry))) {
            return trim($expiry);
        }
        
        $expiryDays = $item['expiryDays'] ?? $item['shelfLife'] ?? null;
        if ($expiryDays && is_numeric($expiryDays) && $expiryDays > 0) {
            Log::warning('Using expiry days as fallback', [
                'expiry_days' => $expiryDays
            ]);
            return $expiryDays . ' days';
        }
        
        return null;
    }

    /**
     * Save EIS product mapping safely.
     */
    private function saveProductMap(
        int $businessId,
        string $eisId,
        Product $product,
        ?string $sku,
        bool $excludeOpeningStock
    ): void {

        $map = EisProductMap::withTrashed()
            ->where('business_id', $businessId)
            ->where('eis_product_id', $eisId)
            ->first();

        if ($map) {
            if ($map->trashed()) {
                $map->restore();
            }

            $map->update([
                'product_id' => $product->id,
                'sku' => $sku ?? $product->sku,
                'last_synced_at' => now(),
            ]);

            Log::debug('EIS product map updated', [
                'map_id' => $map->id,
                'product_id' => $product->id,
                'sku' => $sku
            ]);
        } else {
            $map = EisProductMap::create([
                'business_id' => $businessId,
                'eis_product_id' => $eisId,
                'product_id' => $product->id,
                'sku' => $sku ?? $product->sku,
                'last_synced_at' => now(),
            ]);
            if ($excludeOpeningStock) {
                DB::table('eis_product_maps')->where('id', $map->id)
                    ->update(['meta' => json_encode(['eis_opening_stock_excluded' => true], JSON_THROW_ON_ERROR)]);
            }

            Log::debug('EIS product map created', [
                'business_id' => $businessId,
                'eis_product_id' => $eisId,
                'product_id' => $product->id,
                'sku' => $sku
            ]);
        }
    }

    /**
     * Calculate profit margin.
     */
    private function profit(
        float $price,
        float $cost
    ): float {
        if ($cost <= 0) {
            return 0;
        }

        return round((($price - $cost) / $cost) * 100, 2);
    }

    /**
     * Find product unit with dual fallbacks.
     */
    private function getUnitId(
        int $businessId,
        ?string $unitName
    ): ?int {
        if (empty($unitName)) {
            return null;
        }

        $unitName = strtolower(trim($unitName));
        
        // Check cache first
        $cacheKey = $businessId . ':' . $unitName;
        if (isset($this->unitCache[$cacheKey])) {
            return $this->unitCache[$cacheKey];
        }

        $unitId = DB::table('units')
            ->where('business_id', $businessId)
            ->where(function ($query) use ($unitName) {
                $query->whereRaw('LOWER(short_name) = ?', [$unitName])
                    ->orWhereRaw('LOWER(actual_name) = ?', [$unitName]);
            })
            ->value('id');

        // Cache the result
        $this->unitCache[$cacheKey] = $unitId;

        Log::debug('EIS unit lookup', [
            'business_id' => $businessId,
            'unit' => $unitName,
            'unit_id' => $unitId,
        ]);

        return $unitId;
    }

    /**
     * Validate required EIS product fields.
     */
    private function validateProduct(array $item): void
    {
        if (empty($item['name'])) {
            throw new \Exception('EIS product name is required');
        }

        if (empty($item['sku'])) {
            Log::warning('EIS product has no SKU', [
                'name' => $item['name']
            ]);
        }
    }

    /**
     * Generate fallback SKU.
     */
    private function generateSku(string $eisId): string
    {
        return 'EIS-' . strtoupper($eisId);
    }
}