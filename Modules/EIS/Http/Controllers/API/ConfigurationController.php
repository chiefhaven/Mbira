<?php

namespace Modules\EIS\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Services\Configuration\ConfigurationSyncService;

class ConfigurationController extends Controller
{
    public function __construct(
        protected ConfigurationSyncService $syncService
    ) {
    }

    /**
     * Sync configuration.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync(Request $request)
    {
        $request->validate([
            'business_id' => 'required|integer|exists:eis_configurations,business_id',
            'token' => 'required|string'
        ]);

        try {
            $configuration = $this->syncService->sync(
                $request->business_id,
                $request->token,
                false,
                $request->integer('terminal_id') ?: null
            );

            return response()->json([
                'success' => true,
                'message' => 'Configuration synced successfully',
                'data' => $configuration
            ]);

        } catch (\Exception $e) {
            Log::error('Configuration sync failed', [
                'business_id' => $request->business_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force sync configuration.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceSync(Request $request)
    {
        $request->validate([
            'business_id' => 'required|integer|exists:eis_configurations,business_id',
            'token' => 'required|string'
        ]);

        try {
            $configuration = $this->syncService->forceSync(
                $request->business_id,
                $request->token,
                $request->integer('terminal_id') ?: null
            );

            return response()->json([
                'success' => true,
                'message' => 'Configuration force synced successfully',
                'data' => $configuration
            ]);

        } catch (\Exception $e) {
            Log::error('Configuration force sync failed', [
                'business_id' => $request->business_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to force sync configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfiguration(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $configuration
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get configuration', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration status.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus(int $businessId)
    {
        try {
            $status = $this->syncService->getSyncStatus($businessId, request()->integer('terminal_id') ?: null);

            if (!$status['synced']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get configuration status', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get configuration status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration versions.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVersions(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);

            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'global_version' => $configuration->global_version,
                    'terminal_version' => $configuration->terminal_version,
                    'taxpayer_version' => $configuration->taxpayer_version,
                    'last_synced_at' => $configuration->last_synced_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get configuration versions', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get configuration versions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook for configuration updates.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhookUpdate(Request $request)
    {
        try {
            Log::info('Configuration update webhook received', [
                'payload' => $request->all()
            ]);

            $businessId = $request->input('business_id');
            $token = $request->input('token');

            if (!$businessId || !$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }

            $configuration = $this->syncService->sync($businessId, $token, false, $request->integer('terminal_id') ?: null);

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'data' => $configuration
            ]);

        } catch (\Exception $e) {
            Log::error('Configuration webhook failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook: ' . $e->getMessage()
            ], 500);
        }
    }
}