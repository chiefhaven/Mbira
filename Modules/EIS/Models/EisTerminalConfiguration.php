<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EisTerminalConfiguration extends Model
{
    protected $table = 'eis_terminal_configurations';

    protected $fillable = [
        'configuration_id',
        'version',
        'terminal_label',
        'is_active',
        'email_address',
        'phone_number',
        'trading_name',
        'address_lines',
        'raw_data',
        'last_synced_at',
        // Legacy / compatibility fields for EIS offline limits.
        'max_transaction_age',
        'max_cummulative_amount',
        'max_transaction_age_hours',
        'max_cumulative_amount',
        // Activation fields
        'activated_at',
        'activated_by',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason',
        'toggled_at',
        'toggled_by',
        'activation_code',
        'activation_environment',
        // Terminal details from API response
        'terminal_id',
        'terminal_position',
        'taxpayer_id',
        'activation_date',
        'jwt_token',
        'secret_key',
        'confirmed_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'terminal_position' => 'integer',
        'taxpayer_id' => 'integer',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'toggled_at' => 'datetime',
        'activation_date' => 'datetime',
        'last_synced_at' => 'datetime'
    ];

    /**
     * Get the configuration that owns the terminal.
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(EisConfiguration::class, 'configuration_id');
    }

    /**
     * Get the terminal site.
     */
    public function terminalSite(): HasOne
    {
        return $this->hasOne(EisTerminalSite::class, 'terminal_configuration_id');
    }

    /**
     * Get the offline limit.
     */
    public function offlineLimit(): HasOne
    {
        return $this->hasOne(EisOfflineLimit::class, 'terminal_configuration_id');
    }

    /**
     * Get address lines as array.
     */
    public function getAddressLinesAttribute($value): array
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * Get full address as string.
     */
    public function getFullAddressAttribute(): string
    {
        return implode(', ', $this->address_lines);
    }

    /**
     * Get activation environment as array.
     */
    public function getActivationEnvironmentAttribute($value): ?array
    {
        return $value ? json_decode($value, true) : null;
    }

    /**
     * Check if terminal has valid credentials.
     */
    public function hasCredentials(): bool
    {
        return !empty($this->jwt_token) && !empty($this->secret_key);
    }

    /**
     * Check if terminal has site.
     */
    public function hasSite(): bool
    {
        return $this->terminalSite !== null;
    }

    /**
     * Check if terminal has offline limit.
     */
    public function hasOfflineLimit(): bool
    {
        return $this->offlineLimit !== null;
    }

    /**
     * Check if terminal is activated.
     */
    public function isActivated(): bool
    {
        return $this->is_active && $this->activated_at !== null;
    }

    /**
     * Scope for active terminals.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive terminals.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope for terminals with activation code.
     */
    public function scopeWithActivationCode($query)
    {
        return $query->whereNotNull('activation_code');
    }

    /**
     * Scope for terminals with credentials.
     */
    public function scopeWithCredentials($query)
    {
        return $query->whereNotNull('jwt_token')->whereNotNull('secret_key');
    }

    /**
     * Get terminal label or trading name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->terminal_label ?? $this->trading_name ?? 'Terminal';
    }

    /**
     * Get terminal status as string.
     */
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    /**
     * Get activation duration in days.
     */
    public function getActivationDurationAttribute(): ?int
    {
        if (!$this->activation_date) {
            return null;
        }

        $endDate = $this->deactivated_at ?? now();
        return $this->activation_date->diffInDays($endDate);
    }

    /**
     * Check if terminal was recently activated.
     */
    public function wasRecentlyActivated(int $hours = 24): bool
    {
        return $this->activation_date && $this->activation_date->diffInHours(now()) <= $hours;
    }
}