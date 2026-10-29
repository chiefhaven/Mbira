<?php

namespace App\Services;

use App\Product;
use Illuminate\Support\Facades\DB;
use Modules\EIS\Models\EisProductMap;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\EisSubscriptionAccess;

class EisProductPolicy
{
    private static array $sync = [];

    public static function configured($businessId): bool
    {
        return $businessId
            && app(EisSubscriptionAccess::class)->allows((int) $businessId)
            && DB::connection()->getSchemaBuilder()->hasTable('eis_settings')
            && EisSetting::where('business_id', $businessId)->exists();
    }

    public static function localFields(): array
    {
        return array_merge(['brand_id', 'category_id', 'sub_category_id', 'alert_quantity', 'image'],
            array_map(fn ($number) => 'product_custom_field'.$number, range(1, 20)));
    }

    public static function syncing($businessId): bool
    {
        return !empty(self::$sync[$businessId]);
    }

    public static function duringSync(int $businessId, callable $work)
    {
        self::$sync[$businessId] = (self::$sync[$businessId] ?? 0) + 1;
        try {
            return $work();
        } finally {
            if (--self::$sync[$businessId] === 0) {
                unset(self::$sync[$businessId]);
            }
        }
    }

    public static function guard(Product $product, bool $deleting = false): void
    {
        $businessId = $product->getRawOriginal('business_id') ?? $product->business_id;
        if (self::syncing($businessId) && (string) $businessId === (string) $product->business_id) {
            return;
        }
        if (!self::configured($businessId)
            && ((string) $businessId === (string) $product->business_id || !self::configured($product->business_id))) {
            return;
        }
        $allowed = array_merge(self::localFields(), ['updated_at', 'woocommerce_media_id', 'woocommerce_product_id']);
        if ($deleting || !$product->exists || array_diff(array_keys($product->getDirty()), $allowed)
            || !EisProductMap::where('business_id', $businessId)->where('product_id', $product->id)->exists()) {
            throw new \DomainException(__('product.eis_catalog_locked'));
        }
    }

    public static function guardVariation(\App\Variation $variation, bool $deleting = false): void
    {
        foreach (array_unique([$variation->getRawOriginal('product_id'), $variation->product_id]) as $productId) {
            $product = Product::withTrashed()->find($productId);
            if ($product && !self::syncing($product->business_id) && self::configured($product->business_id)
                && ($deleting || !$variation->exists || array_diff(array_keys($variation->getDirty()), ['updated_at', 'woocommerce_variation_id']))) {
                throw new \DomainException(__('product.eis_catalog_locked'));
            }
        }
    }
}
