<?php

namespace Modules\Connector\Support;

use App\Discount;
use App\Utils\ProductUtil;

/** Request-local candidates: never retain pricing data across requests or tenants. */
class ProductDiscountLookup
{
    private $businessId;
    private $now;
    private $locations = [];
    private $productUtil;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
        $this->now = \Carbon::now()->toDateTimeString();
        $this->productUtil = new ProductUtil;
    }

    public function find($product, $locationId, $priceGroup, $variationId)
    {
        $now = \Carbon::now()->toDateTimeString();
        if ($now !== $this->now) {
            // Long catalog responses must not keep discounts past their time boundaries.
            $this->now = $now;
            $this->locations = [];
        }
        $locationKey = serialize($locationId);
        if (! array_key_exists($locationKey, $this->locations)) {
            $discounts = Discount::where('business_id', $this->businessId)
                ->where('location_id', $locationId)
                ->where('is_active', 1)
                ->where('starts_at', '<=', $this->now)
                ->where('ends_at', '>=', $this->now)
                // Keep database-native empty-string coercion from the legacy raw predicates.
                ->select('discounts.*')
                ->selectRaw("CASE WHEN brand_id = '' THEN 1 ELSE 0 END AS connector_empty_brand")
                ->selectRaw("CASE WHEN category_id = '' THEN 1 ELSE 0 END AS connector_empty_category")
                ->with('variations:id')
                ->orderBy('priority', 'desc')->latest()->get();
            $this->locations[$locationKey] = [];
            foreach ($discounts as $discount) {
                $variationIds = array_fill_keys($discount->variations->modelKeys(), true);
                $emptyBrand = (bool) $discount->connector_empty_brand;
                $emptyCategory = (bool) $discount->connector_empty_category;
                unset($discount->connector_empty_brand, $discount->connector_empty_category);
                $discount->unsetRelation('variations');
                $this->locations[$locationKey][] = [$discount, $variationIds, $emptyBrand, $emptyCategory];
            }
        }

        foreach ($this->locations[$locationKey] as [$discount, $variationIds, $emptyBrand, $emptyCategory]) {
            if ($discount->spg !== null
                && ($priceGroup === null || (string) $discount->spg !== (string) $priceGroup)) {
                continue;
            }
            // Mirror getProductDiscount's partial brand/category predicates, not a new policy.
            $hasBrand = ! empty($product->brand_id);
            $hasCategory = ! empty($product->category_id);
            $matches = ($hasBrand || $hasCategory)
                && (! $hasBrand || $discount->brand_id == $product->brand_id)
                && (! $hasCategory || $discount->category_id == $product->category_id);
            $matches = $matches
                || (((string) $product->brand_id === '' ? $emptyBrand : $discount->brand_id !== null && $discount->brand_id == (string) $product->brand_id) && $discount->category_id === null)
                || (((string) $product->category_id === '' ? $emptyCategory : $discount->category_id !== null && $discount->category_id == (string) $product->category_id) && $discount->brand_id === null)
                || (! empty($variationId) && isset($variationIds[$variationId]));
            if ($matches) {
                $result = clone $discount;
                $result->formated_starts_at = $this->productUtil->format_date($result->starts_at->toDateTimeString(), true);
                $result->formated_ends_at = $this->productUtil->format_date($result->ends_at->toDateTimeString(), true);

                return $result;
            }
        }

        return null;
    }
}