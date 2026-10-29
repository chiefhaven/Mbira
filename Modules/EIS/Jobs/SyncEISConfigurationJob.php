<?php

namespace Modules\EIS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Services\Configuration\ConfigurationSyncService;
use Modules\EIS\Services\EisSubscriptionAccess;

class SyncEISConfigurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    
    private const MIN_SYNC_INTERVAL_MINUTES = 1;
    private const RETRY_BACKOFF_MINUTES = 1;
    private const CHUNK_SIZE = 50;
    private const MAX_PROCESS_LIMIT = 500;

    public function handle(ConfigurationSyncService $syncService, EisSubscriptionAccess $subscriptionAccess): array
    {
        $startTime = microtime(true);
        $processedCount = 0;
        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;
        
        Log::info('EIS Configuration Sync Job - Started');
        
        try {
            $settingsQuery = EisSetting::where('status', true)
                ->whereNotNull('jwt_token')
                ->where(function ($query) {
                    $query->whereNull('last_sync_at')
                        ->orWhere('last_sync_at', '<', now()->subMinutes(self::MIN_SYNC_INTERVAL_MINUTES));
                })
                ->where(function ($query) {
                    $query->whereNull('sync_status')
                        ->orWhere('sync_status', '!=', 'failed')
                        ->orWhere('sync_error_retry_after', '<', now());
                });
            $businessIds = (clone $settingsQuery)->distinct()->pluck('business_id');
            $eligibleBusinessIds = $businessIds
                ->filter(fn ($businessId) => $subscriptionAccess->allows((int) $businessId))
                ->values()
                ->all();

            $settingsQuery = $settingsQuery
                ->whereIn('business_id', $eligibleBusinessIds)
                ->orderBy('last_sync_at', 'asc')
                ->limit(self::MAX_PROCESS_LIMIT);
            
            $settingsQuery->chunkById(self::CHUNK_SIZE, function ($chunk) use ($syncService, &$processedCount, &$successCount, &$failureCount, &$skippedCount) {
                foreach ($chunk as $setting) {
                    $processedCount++;
                    
                    if ($this->shouldSkipSync($setting)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    $this->processSetting($setting, $syncService, $successCount, $failureCount);
                }
                
                if ($chunk->count() > 0) {
                    usleep(100000); // 100ms delay between chunk
                }
            });
            
        } catch (\Throwable $e) {
            Log::error('EIS Configuration Sync Job - Critical Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        $executionTime = microtime(true) - $startTime;
        
        Log::info('EIS Configuration Sync Job - Completed', [
            'processed' => $processedCount,
            'successful' => $successCount,
            'failed' => $failureCount,
            'skipped' => $skippedCount,
            'execution_time' => round($executionTime, 2),
            'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2)
        ]);

        return [
            'success' => true,
            'processed' => $processedCount,
            'successful' => $successCount,
            'failed' => $failureCount,
            'skipped' => $skippedCount,
            'execution_time' => round($executionTime, 2),
            'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2)
        ];
    }

    protected function shouldSkipSync($setting): bool
    {
        // Skip if synced recently
        if ($setting->last_sync_at && $setting->last_sync_at->diffInMinutes(now()) < self::MIN_SYNC_INTERVAL_MINUTES) {
            return true;
        }
        
        // Skip if failed recently (backoff period)
        if ($setting->sync_status === 'failed' && 
            // $setting->updated_at && Skipping EIS configuration sync for business
            $setting->last_sync_at->diffInMinutes(now()) < self::RETRY_BACKOFF_MINUTES) {
            return true;
        }
        
        return false;
    }

    protected function processSetting($setting, $syncService, &$successCount, &$failureCount): void
    {
        try {
            DB::transaction(function () use ($setting, $syncService, &$successCount) {
                $result = $syncService->sync(
                    $setting->business_id,
                    $setting->jwt_token,
                    false,
                    (int) $setting->id
                );
                
                $setting->update([
                    'last_sync_at' => now(),
                    'sync_status' => 'success',
                    'sync_error' => null,
                    'sync_error_retry_after' => null,
                    'successful_syncs' => ($setting->successful_syncs ?? 0) + 1,
                    'global_version' => $result->global_version ?? null,
                    'terminal_version' => $result->terminal_version ?? null,
                    'taxpayer_version' => $result->taxpayer_version ?? null,
                ]);
                
                $successCount++;
            });

            \Modules\EIS\Services\Configuration\ConfigurationSyncService::businessLocation($setting->business_id, (int) $setting->id);
            
        } catch (\Throwable $e) {
            $failureCount++;
            
            // Determine if error is retryable
            $isRetryable = $this->isRetryableError($e);
            $retryAfter = $isRetryable ? now()->addMinutes(5) : null;
            
            $setting->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'sync_error_retry_after' => $retryAfter,
                'failed_syncs' => ($setting->failed_syncs ?? 0) + 1,
                'last_sync_attempt' => now(),
            ]);
            
            Log::error('EIS Sync - Individual Failure', [
                'business_id' => $setting->business_id,
                'error' => $e->getMessage(),
                'is_retryable' => $isRetryable
            ]);
        }
    }

    protected function isRetryableError(\Throwable $e): bool
    {
        $nonRetryableErrors = [
            'Invalid token',
            'Authentication failed',
            'Business not found',
            'Validation error',
            'Invalid configuration',
            'TIN not found',
            'Taxpayer not found'
        ];
        
        $message = strtolower($e->getMessage());
        
        foreach ($nonRetryableErrors as $error) {
            if (stripos($message, strtolower($error)) !== false) {
                return false;
            }
        }
        
        return true;
    }
}