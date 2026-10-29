<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EisSetting extends Model
{
    public function configuration()
    {
        return $this->hasOne(EisConfiguration::class, 'eis_setting_id');
    }

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    protected $table = 'eis_settings';
    
    protected $fillable = [
        'base_url',
        'location_id',
        'business_id',
        'jwt_token',
        'secret_key',
        'status',
        'last_sync_at',
        'sync_status',
        'sync_error',
        'sync_error_retry_after',
        'successful_syncs',
        'failed_syncs',
        'last_sync_attempt',
        'global_version',
        'terminal_version',
        'taxpayer_version',
        'tpin',
        'branch_id',
        'device_id'
    ];
    
    protected $casts = [
        'status' => 'boolean',
        'last_sync_at' => 'datetime',
        'sync_error_retry_after' => 'datetime',
        'last_sync_attempt' => 'datetime',
        'successful_syncs' => 'integer',
        'failed_syncs' => 'integer',
        'global_version' => 'integer',
        'terminal_version' => 'integer',
        'taxpayer_version' => 'integer',
    ];
    
    /**
     * Scope for active settings that need syncing
     */
    public function scopeNeedsSync($query)
    {
        return $query->where('status', true)
            ->whereNotNull('jwt_token')
            ->where(function ($query) {
                $query->whereNull('last_sync_at')
                    ->orWhere('last_sync_at', '<', now()->subMinutes(5));
            })
            ->where(function ($query) {
                $query->whereNull('sync_status')
                    ->orWhere('sync_status', '!=', 'failed')
                    ->orWhere('sync_error_retry_after', '<', now());
            });
    }
    
    /**
     * Scope for failed syncs that need retry
     */
    public function scopeFailedSyncs($query)
    {
        return $query->where('status', true)
            ->where('sync_status', 'failed')
            ->where('sync_error_retry_after', '<', now())
            ->where('failed_syncs', '<', 10); // Max 10 retries
    }
    
    /**
     * Calculate health score (0-100)
     */
    public function getHealthScoreAttribute(): int
    {
        if (!$this->last_sync_at) {
            return 0;
        }
        
        $hoursSinceSync = $this->last_sync_at->diffInHours(now());
        $score = 100;
        
        // Reduce score based on time since last sync
        if ($hoursSinceSync > 24) {
            $score -= 30;
        } elseif ($hoursSinceSync > 12) {
            $score -= 15;
        } elseif ($hoursSinceSync > 6) {
            $score -= 5;
        }
        
        // Reduce score based on failures
        if ($this->sync_status === 'failed') {
            $score -= 25;
        }
        
        // Reduce score based on failure rate
        $totalAttempts = ($this->successful_syncs ?? 0) + ($this->failed_syncs ?? 0);
        if ($totalAttempts > 0) {
            $failureRate = ($this->failed_syncs ?? 0) / $totalAttempts;
            $score -= min($failureRate * 50, 50);
        }
        
        return max(0, min(100, $score));
    }
}