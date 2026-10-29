<?php

namespace Modules\EIS\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisTerminalConfiguration;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Services\Terminal\EisTerminalActivationService;
use Modules\EIS\Services\Terminal\TerminalContext;
use Modules\EIS\Services\EisSubscriptionAccess;

class TerminalActivationController extends Controller
{
    public function __construct(
        protected EisTerminalActivationService $activationService
    ) {
        // No authentication middleware for activation endpoints
    }

    /**
     * Activate terminal - No authentication required.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateTerminal(Request $request)
    {
        try {
            $request->validate([
                'terminal_activation_code' => 'required|string',
            ]);

            // Get business_id from request
            $businessId = $request->input('business_id');
            
            if (!$businessId) {
                return response()->json([
                    'success' => false,
                    'msg' => 'Business ID is required'
                ]);
            }

            // Build the correct environment payload structure
            $environment = [
                'platform' => [
                    'osName' => $request->input('environment.platform.osName', $this->getOSName()),
                    'osVersion' => $request->input('environment.platform.osVersion', $this->getOSVersion()),
                    'osBuild' => $request->input('environment.platform.osBuild', ''),
                    'macAddress' => $request->input('environment.platform.macAddress', $this->getMacAddress())
                ],
                'pos' => [
                    'productID' => $request->input('environment.pos.productID', config('app.name', 'POS System')),
                    'productVersion' => $request->input('environment.pos.productVersion', config('app.version', '1.0.0'))
                ]
            ];

            // Get activation code from request
            $activationCode = $request->input('terminal_activation_code');

            // Call activation service with the correct payload
            // No token required, no authenticated user
            $result = $this->activationService->activateTerminal(
                $businessId,
                $activationCode,
                $environment,
                Auth::id(),
                $request->integer('location_id') ?: null
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'msg' => $result['message'] ?? 'Terminal activated successfully',
                    'data' => $result['data'] ?? null,
                    'activation_code' => $result['activation_code'] ?? $activationCode,
                    'terminal_credentials' => $result['terminal_credentials'] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to activate terminal',
                    'status_code' => $result['status_code'] ?? 400
                ]);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Terminal activation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate terminal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get environment data from request or browser.
     *
     * @return array
     */
    private function getEnvironmentData(): array
    {
        return [
            'platform' => [
                'osName' => request()->input('environment.platform.osName', $this->getOSName()),
                'osVersion' => request()->input('environment.platform.osVersion', $this->getOSVersion()),
                'osBuild' => request()->input('environment.platform.osBuild', ''),
                'macAddress' => request()->input('environment.platform.macAddress', $this->getMacAddress())
            ],
            'pos' => [
                'productID' => request()->input('environment.pos.productID', config('app.name', 'POS System')),
                'productVersion' => request()->input('environment.pos.productVersion', config('app.version', '1.0.0'))
            ]
        ];
    }

    /**
     * Get OS name from user agent.
     *
     * @return string
     */
    private function getOSName(): string
    {
        $userAgent = request()->header('User-Agent');
        
        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac') !== false) return 'MacOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iOS') !== false) return 'iOS';
        
        return 'Unknown';
    }

    /**
     * Get OS version from user agent.
     *
     * @return string
     */
    private function getOSVersion(): string
    {
        $userAgent = request()->header('User-Agent');
        
        if (preg_match('/Windows NT (\d+\.\d+)/', $userAgent, $matches)) {
            return $matches[1];
        }
        if (preg_match('/Mac OS X (\d+[._]\d+[._]\d+)/', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }
        if (preg_match('/Android (\d+\.\d+)/', $userAgent, $matches)) {
            return $matches[1];
        }
        if (preg_match('/OS (\d+[._]\d+)/', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }
        
        return 'Unknown';
    }

    /**
     * Get browser information.
     *
     * @return string
     */
    private function getBrowser(): string
    {
        $userAgent = request()->header('User-Agent');
        
        if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edge') === false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        if (strpos($userAgent, 'Opera') !== false) return 'Opera';
        
        return 'Unknown';
    }

    /**
     * Get browser version.
     *
     * @return string
     */
    private function getBrowserVersion(): string
    {
        $userAgent = request()->header('User-Agent');
        
        if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera)\/(\d+\.\d+)/', $userAgent, $matches)) {
            return $matches[2];
        }
        
        return 'Unknown';
    }

    /**
     * Get MAC address (simulated).
     *
     * @return string
     */
    private function getMacAddress(): string
    {
        // Generate a unique identifier for web clients
        return 'web-' . md5(request()->ip() . request()->header('User-Agent'));
    }

    /**
     * Deactivate terminal - Requires authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate(Request $request)
    {
        try {
            $request->validate([
                'business_id' => 'required|integer|exists:eis_configurations,business_id',
                'reason' => 'nullable|string|max:500'
            ]);

            $deactivatedBy = auth()->id() ?? null;

            $result = $this->activationService->deactivate(
                $request->business_id,
                $request->reason,
                $deactivatedBy,
                $request->integer('terminal_id') ?: null
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Terminal deactivation error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'msg' => 'Failed to deactivate terminal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle terminal activation - Requires authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggle(Request $request)
    {
        try {
            
            $user = auth()->user();

            $result = $this->activationService->toggle(
                $user->business_id,
                \Modules\EIS\Services\Terminal\TerminalContext::settings((int) $user->business_id, null, $request->integer('terminal_id') ?: null)->jwt_token,
                $user->id,
                $request->integer('terminal_id') ?: null
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Terminal toggle error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'msg' => 'Failed to toggle terminal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the latest terminal configuration - Requires authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchLatestConfig(Request $request)
    {
        try {
            $businessId = $request->input('business_id') or abort(400, 'business_id is required');
            
            $result = $this->activationService->getLatestConfig($businessId, $request->integer('terminal_id') ?: null);

            Log::info('Fetched latest terminal configuration', [
                'business_id' => $businessId,
                'result' => $result
            ]);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Terminal get latest config error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get latest config: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get terminal status - No authentication required.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(int $businessId)
    {
        try {
            // Validate business exists
            if (!EisConfiguration::where('business_id', $businessId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business configuration not found'
                ], 404);
            }

            $result = $this->activationService->getStatus($businessId, request()->integer('terminal_id') ?: null);

            return response()->json($result, $result['success'] ? 200 : 404);

        } catch (\Exception $e) {
            Log::error('Failed to get terminal status', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get terminal status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get terminal status for the authenticated business settings page.
     */
    public function getStatus(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $result = $this->activationService->getStatus($businessId, $request->integer('terminal_id') ?: null);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    /**
     * Check if Terminal is Active - No authentication required.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function isActive(int $businessId)
    {
        try {
            // Validate business exists
            if (!EisConfiguration::where('business_id', $businessId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business configuration not found',
                    'is_active' => false
                ], 404);
            }

            $isActive = $this->activationService->isActive($businessId, request()->integer('terminal_id') ?: null);

            return response()->json([
                'success' => true,
                'business_id' => $businessId,
                'is_active' => $isActive
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check terminal active status', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check terminal status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get terminal activation history - Requires authentication.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(int $businessId)
    {
        try {
            // Validate business exists
            if (!EisConfiguration::where('business_id', $businessId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business configuration not found'
                ], 404);
            }

            $result = $this->activationService->getActivationHistory($businessId, request()->integer('terminal_id') ?: null);

            return response()->json($result, $result['success'] ? 200 : 404);

        } catch (\Exception $e) {
            Log::error('Failed to get activation history', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get activation history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get terminal credentials - Requires authentication.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCredentials(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);
            
            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)->first();
            
            if (!$terminal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terminal configuration not found'
                ], 404);
            }

            if (!TerminalContext::isOperational((bool) $configuration->setting?->status, $terminal->is_active)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terminal is not active'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'jwt_token' => $terminal->jwt_token,
                    'secret_key' => $terminal->secret_key,
                    'has_credentials' => $terminal->hasCredentials()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get terminal credentials', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerate terminal credentials - Requires authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerateCredentials(Request $request)
    {
        try {
            $request->validate([
                'business_id' => 'required|integer|exists:eis_configurations,business_id',
                'token' => 'required|string'
            ]);

            $result = $this->activationService->regenerateCredentials(
                $request->business_id,
                $request->token,
                $request->integer('terminal_id') ?: null
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to regenerate credentials', [
                'business_id' => $request->business_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk activate terminals - No authentication required.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkActivate(Request $request)
    {
        try {
            $request->validate([
                'business_ids' => 'required|array',
                'business_ids.*' => 'integer|exists:eis_configurations,business_id',
                'terminal_activation_code' => 'required|string',
                'environment' => 'sometimes|array',
                'environment.platform' => 'sometimes|array',
                'environment.pos' => 'sometimes|array'
            ]);

            $activationCode = $request->input('terminal_activation_code');
            
            // Build environment payload
            $environment = [
                'platform' => [
                    'osName' => $request->input('environment.platform.osName', $this->getOSName()),
                    'osVersion' => $request->input('environment.platform.osVersion', $this->getOSVersion()),
                    'osBuild' => $request->input('environment.platform.osBuild', ''),
                    'macAddress' => $request->input('environment.platform.macAddress', $this->getMacAddress())
                ],
                'pos' => [
                    'productID' => $request->input('environment.pos.productID', config('app.name', 'POS System')),
                    'productVersion' => $request->input('environment.pos.productVersion', config('app.version', '1.0.0'))
                ]
            ];
            
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($request->business_ids as $businessId) {
                $result = $this->activationService->activateTerminal(
                    $businessId,
                    $activationCode,
                    $environment,
                    Auth::id(),
                    $request->integer('location_id') ?: null
                );

                $results[$businessId] = $result;
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk activation completed: {$successCount} succeeded, {$failureCount} failed",
                'data' => $results,
                'summary' => [
                    'total' => count($request->business_ids),
                    'successful' => $successCount,
                    'failed' => $failureCount
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Bulk activation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk activation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk deactivate terminals - Requires authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDeactivate(Request $request)
    {
        try {
            $request->validate([
                'business_ids' => 'required|array',
                'business_ids.*' => 'integer|exists:eis_configurations,business_id',
                'reason' => 'nullable|string|max:500'
            ]);

            $deactivatedBy = auth()->id() ?? null;
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($request->business_ids as $businessId) {
                $result = $this->activationService->deactivate(
                    $businessId,
                    $request->reason,
                    $deactivatedBy,
                    $request->integer('terminal_id') ?: null
                );

                $results[$businessId] = $result;
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk deactivation completed: {$successCount} succeeded, {$failureCount} failed",
                'data' => $results,
                'summary' => [
                    'total' => count($request->business_ids),
                    'successful' => $successCount,
                    'failed' => $failureCount
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Bulk deactivation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk deactivation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get terminal details - Requires authentication.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function details(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);
            
            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)
                ->with(['terminalSite', 'offlineLimit'])
                ->first();

            if (!$terminal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terminal configuration not found'
                ], 404);
            }

            // Get business location for branch information
            $businessLocation = \App\BusinessLocation::where('business_id', $businessId)
                ->where('eis_site_id', \Modules\EIS\Services\Terminal\TerminalContext::site(
                    \Modules\EIS\Services\Terminal\TerminalContext::settings($businessId, null, (int) $configuration->eis_setting_id)
                ))->first();
            $branch = $businessLocation?->name ?? null;

            return response()->json([
                'success' => true,
                'data' => [
                    'terminal' => [
                        'id' => $terminal->id,
                        'terminal_label' => $terminal->terminal_label,
                        'is_active' => TerminalContext::isOperational((bool) $configuration->setting?->status, $terminal->is_active),
                        'status' => TerminalContext::isOperational((bool) $configuration->setting?->status, $terminal->is_active) ? 'Active' : 'Inactive',
                        'display_name' => $terminal->display_name,
                        'trading_name' => $terminal->trading_name,
                        'email_address' => $terminal->email_address,
                        'phone_number' => $terminal->phone_number,
                        'address' => $terminal->full_address,
                        'version' => $terminal->version,
                        'terminal_id' => $terminal->terminal_id,
                        'terminal_position' => $terminal->terminal_position,
                        'taxpayer_id' => $terminal->taxpayer_id,
                        'activation_date' => $terminal->activation_date,
                        'activated_at' => $terminal->activated_at,
                        'deactivated_at' => $terminal->deactivated_at,
                        'activation_duration' => $terminal->activation_duration,
                        'has_credentials' => $terminal->hasCredentials(),
                        'last_synced_at' => $terminal->last_synced_at,
                        'branch' => $branch
                    ],
                    'site' => $terminal->terminalSite ? [
                        'site_id' => $terminal->terminalSite->site_id,
                        'site_name' => $terminal->terminalSite->site_name
                    ] : null,
                    'offline_limit' => $terminal->offlineLimit ? [
                        'max_transaction_age_hours' => $terminal->offlineLimit->max_transaction_age_hours,
                        'max_cumulative_amount' => $terminal->offlineLimit->max_cumulative_amount,
                        'formatted_max_amount' => $terminal->offlineLimit->formatted_max_amount
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get terminal details', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get terminal details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync terminal configuration - Requires authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncTerminal(Request $request)
    {
        try {
            $request->validate([
                'business_id' => 'required|integer|exists:eis_configurations,business_id',
                'token' => 'required|string'
            ]);

            $result = $this->activationService->syncTerminal(
                $request->business_id,
                $request->token,
                $request->integer('terminal_id') ?: null
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to sync terminal', [
                'business_id' => $request->business_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync terminal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get terminal health status - No authentication required.
     *
     * @param int $businessId
     * @return \Illuminate\Http\JsonResponse
     */
    public function health(int $businessId)
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, request()->integer('terminal_id') ?: null);
            
            if (!$configuration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found',
                    'health_score' => 0
                ], 404);
            }

            $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)->first();
            
            if (!$terminal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terminal configuration not found',
                    'health_score' => 0
                ], 404);
            }

            // Calculate health score
            $healthScore = 100;
            $issues = [];

            // Check if terminal is active
            if (!TerminalContext::isOperational((bool) $configuration->setting?->status, $terminal->is_active)) {
                $healthScore -= 30;
                $issues[] = 'Terminal is inactive';
            }

            // Check if terminal has credentials
            if (!$terminal->hasCredentials()) {
                $healthScore -= 20;
                $issues[] = 'Missing terminal credentials';
            }

            // Check if terminal has site
            if (!$terminal->hasSite()) {
                $healthScore -= 10;
                $issues[] = 'No site configured';
            }

            // Check if terminal has offline limit
            if (!$terminal->hasOfflineLimit()) {
                $healthScore -= 10;
                $issues[] = 'No offline limit configured';
            }

            // Check if terminal was synced recently (within 24 hours)
            if ($terminal->last_synced_at && $terminal->last_synced_at->diffInHours(now()) > 24) {
                $healthScore -= 15;
                $issues[] = 'Terminal not synced in last 24 hours';
            }

            // Check activation age
            if ($terminal->activation_date && $terminal->activation_date->diffInMonths(now()) > 6) {
                $healthScore -= 5;
                $issues[] = 'Terminal activation is older than 6 months';
            }

            return response()->json([
                'success' => true,
                'business_id' => $businessId,
                'health_score' => max(0, $healthScore),
                'status' => $healthScore >= 70 ? 'healthy' : ($healthScore >= 40 ? 'warning' : 'critical'),
                'issues' => $issues,
                'data' => [
                    'is_active' => TerminalContext::isOperational((bool) $configuration->setting?->status, $terminal->is_active),
                    'has_credentials' => $terminal->hasCredentials(),
                    'has_site' => $terminal->hasSite(),
                    'has_offline_limit' => $terminal->hasOfflineLimit(),
                    'last_synced_at' => $terminal->last_synced_at,
                    'activation_days' => $terminal->activation_duration,
                    'version' => $terminal->version
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get terminal health', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get terminal health: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook for terminal activation callback - No authentication required.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activationCallback(Request $request)
    {
        try {
            Log::info('Terminal activation callback received', [
                'payload' => $request->all()
            ]);

            $businessId = $request->input('business_id');
            $activationCode = $request->input('activation_code');
            $status = $request->input('status');
            $data = $request->input('data', []);

            if (!$businessId || !$activationCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }

            app(EisSubscriptionAccess::class)->assertAllowed((int) $businessId);

            // Process the callback
            $result = $this->activationService->handleActivationCallback(
                $businessId,
                $activationCode,
                $status,
                $data
            );

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Activation callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process callback: ' . $e->getMessage()
            ], 500);
        }
    }
}