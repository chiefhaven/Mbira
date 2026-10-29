<?php

namespace Modules\EIS\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Services\Configuration\ConfigurationSyncService;

class TaxRateController extends Controller
{
    public function __construct(
        protected ConfigurationSyncService $syncService
    ) {
    }

    /**
     * Get all tax rates.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $taxRates = $this->syncService->getTaxRates($configuration);

            return response()->json([
                'success' => true,
                'data' => $taxRates
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tax rates', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get tax rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activated tax rates.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function activated(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $taxRates = $this->syncService->getTaxRates($configuration, true);

            return response()->json([
                'success' => true,
                'data' => $taxRates
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get activated tax rates', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get activated tax rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tax rate by ID.
     *
     * @param int $businessId
     * @param string $taxRateId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $businessId, string $taxRateId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $taxRate = $this->syncService->getTaxRateById($configuration->id, $taxRateId);

            if (!$taxRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tax rate not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $taxRate
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tax rate', [
                'business_id' => $businessId,
                'tax_rate_id' => $taxRateId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get tax rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate tax.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'business_id' => 'required|integer|exists:eis_configurations,business_id',
            'tax_rate_id' => 'required|string',
            'amount' => 'required|numeric|min:0'
        ]);

        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration((int) $request->business_id, $request->integer('terminal_id') ?: null);

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $result = $this->syncService->calculateTax(
                $request->tax_rate_id,
                $request->amount,
                $configuration->id
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Tax calculation failed', [
                'business_id' => $request->business_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tax calculation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tax rate summary.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $summary = $this->syncService->getTaxRatesSummary($configuration);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tax rate summary', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get tax rate summary: ' . $e->getMessage()
            ], 500);
        }
    }
}