<?php

namespace Modules\EIS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisProductMap;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Utilities\EisUtilitiesClient;
use Modules\EIS\Services\Utilities\UtilityOperations;

class ProductStatusController extends Controller
{
    public function show(Request $request, int $productId, EisUtilitiesClient $client)
    {
        $user = $request->user();
        abort_unless($user && $user->business_id && $user->can('product.view'), 403);
        abort_unless($user->permitted_locations((int) $user->business_id) === 'all', 403,
            'EIS product status is taxpayer-wide and requires access to all business locations.');

        $businessId = (int) $user->business_id;
        abort_unless(DB::table('products')->where('business_id', $businessId)->where('id', $productId)->exists(), 404);
        $productMap = EisProductMap::where('business_id', $businessId)->where('product_id', $productId)->firstOrFail();
        $setting = EisSetting::where('business_id', $businessId)
            ->whereNotNull('jwt_token')->where('jwt_token', '<>', '')
            ->whereNotNull('tpin')->where('tpin', '<>', '')
            ->orderBy('id')->firstOrFail();

        try {
            $payload = UtilityOperations::payload($setting, 'product-status', [
                'productId' => $productMap->eis_product_id,
            ]);
            $result = $client->send($setting, 'product-status', $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::warning('EIS product status lookup unavailable', [
                'business_id' => $businessId, 'product_id' => $productId,
            ]);
            return response()->json(['success' => false, 'message' => 'EIS could not be reached. No product status was confirmed.'], 502);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        if ($result['outcome'] !== 'accepted' || !is_array($result['response']['data'] ?? null)) {
            Log::warning('EIS product status lookup was inconclusive', [
                'business_id' => $businessId, 'product_id' => $productId, 'outcome' => $result['outcome'],
            ]);
            return response()->json(['success' => false, 'message' => 'EIS did not confirm the product status.'], 502);
        }

        return response()->json(['success' => true, 'data' => $result['response']['data']]);
    }
}
