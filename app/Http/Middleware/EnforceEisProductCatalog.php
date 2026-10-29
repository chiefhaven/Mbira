<?php

namespace App\Http\Middleware;

use App\Services\EisProductPolicy;
use Closure;

class EnforceEisProductCatalog
{
    public function handle($request, Closure $next)
    {
        $businessId = $request->hasSession() ? $request->session()->get('user.business_id') : null;
        $businessId = $businessId ?? $request->user()?->business_id;
        $action = $request->route()?->getActionName() ?? '';
        [$controller, $method] = array_pad(explode('@', $action), 2, '');
        $controller = class_basename($controller);
        $blocked = match ($controller) {
            'ProductController' => in_array($method, [
                'create', 'store', 'quickAdd', 'saveQuickProduct', 'destroy', 'massDestroy',
                'bulkEdit', 'bulkUpdate', 'getProductToEdit', 'addSellingPrices', 'saveSellingPrices',
                'massDeactivate', 'activate', 'toggleWooCommerceSync',
            ], true),
            'ImportProductsController', 'ImportOpeningStockController', 'OpeningStockController' => true,
            'SellingPriceGroupController' => in_array($method, ['updateProductPrice', 'import'], true),
            'ModifierSetsController' => in_array($method, ['create', 'store', 'edit', 'update', 'destroy'], true),
            'StockAdjustmentController' => in_array($method, ['create', 'store', 'destroy', 'removeExpiredStock'], true),
            default => false,
        };
        if ($blocked && EisProductPolicy::configured($businessId)) {
            abort(403, __('product.eis_catalog_locked'));
        }

        return $next($request);
    }
}
