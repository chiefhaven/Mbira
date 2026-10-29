<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EisOfflineLimit extends Model
{
    protected $table = 'eis_offline_limits';

    protected $fillable = [
        'terminal_configuration_id',
        'max_transaction_age_hours',
        'max_cumulative_amount',
        'raw_data',
        'last_synced_at'
    ];

    /**
     * Normalize legacy and canonical field names to the current EIS contract.
     */
    public static function normalizeLimitPayload($payload): array
    {
        $data = is_object($payload) ? (array) $payload : (array) ($payload ?? []);

        $maxTransactionAge = $data['maxTransactionAgeInHours']
            ?? $data['max_transaction_age_hours']
            ?? $data['max_transaction_age']
            ?? 72;

        $maxCumulativeAmount = $data['maxCummulativeAmount']
            ?? $data['max_cumulative_amount']
            ?? $data['max_cummulative_amount']
            ?? 0;

        return [
            'max_transaction_age_hours' => (int) $maxTransactionAge,
            'max_cumulative_amount' => (float) $maxCumulativeAmount,
        ];
    }

    protected $casts = [
        'max_transaction_age_hours' => 'integer',
        'max_cumulative_amount' => 'float',
        'last_synced_at' => 'datetime'
    ];

    /**
     * Get the terminal configuration that owns the offline limit.
     */
    public function terminalConfiguration(): BelongsTo
    {
        return $this->belongsTo(EisTerminalConfiguration::class, 'terminal_configuration_id');
    }

    /**
     * Get formatted max cumulative amount.
     */
    public function getFormattedMaxAmountAttribute(): string
    {
        return number_format($this->max_cumulative_amount, 2);
    }

    /**
     * Get formatted max cumulative amount with currency.
     */
    public function getFormattedMaxAmountWithCurrencyAttribute(): string
    {
        return 'MWK ' . number_format($this->max_cumulative_amount, 2);
    }

    /**
     * Check if transaction is within offline limit.
     */
    public function isWithinLimit(float $amount, int $ageInHours): bool
    {
        return $ageInHours <= $this->max_transaction_age_hours && 
               $amount <= $this->max_cumulative_amount;
    }

    /**
     * Get remaining amount before limit.
     */
    public function getRemainingAmount(float $currentAmount): float
    {
        return max(0, $this->max_cumulative_amount - $currentAmount);
    }

    /**
     * Get remaining percentage before limit.
     */
    public function getRemainingPercentage(float $currentAmount): float
    {
        if ($this->max_cumulative_amount <= 0) {
            return 0;
        }
        return min(100, ($this->getRemainingAmount($currentAmount) / $this->max_cumulative_amount) * 100);
    }

    /**
     * Check if offline limit is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->max_transaction_age_hours > 0 || $this->max_cumulative_amount > 0;
    }

    /**
     * Scope for limits with max age.
     */
    public function scopeWithMaxAge($query, int $hours)
    {
        return $query->where('max_transaction_age_hours', '>=', $hours);
    }

    /**
     * Scope for limits with max amount.
     */
    public function scopeWithMaxAmount($query, float $amount)
    {
        return $query->where('max_cumulative_amount', '>=', $amount);
    }

    /**
     * Get offline limit summary.
     */
    public function getSummary(): array
    {
        return [
            'max_transaction_age_hours' => $this->max_transaction_age_hours,
            'max_cumulative_amount' => $this->max_cumulative_amount,
            'formatted_max_amount' => $this->formatted_max_amount,
            'is_enabled' => $this->isEnabled(),
            'last_synced_at' => $this->last_synced_at
        ];
    }
}