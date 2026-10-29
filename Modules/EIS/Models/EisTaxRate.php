<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EisTaxRate extends Model
{
    protected $table = 'tax_rates';

    protected $fillable = [
        'business_id',
        'configuration_id',
        'tax_rate_id',
        'name',
        'charge_mode',
        'ordinal',
        'rate',
        'amount',
        'is_activated',
        'activation_id',
    ];

    protected $casts = [
        'rate' => 'float',
        'ordinal' => 'integer',
        'is_activated' => 'boolean',
    ];

    /**
     * Get the configuration that owns the tax rate.
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(EisConfiguration::class, 'configuration_id');
    }

    /**
     * Scope for activated tax rates.
     */
    public function scopeActivated($query)
    {
        return $query->where('is_activated', true);
    }

    /**
     * Scope for tax rates by charge mode.
     */
    public function scopeByChargeMode($query, string $mode)
    {
        return $query->where('charge_mode', $mode);
    }

    /**
     * Scope for tax rates with rate greater than 0.
     */
    public function scopeWithRate($query)
    {
        return $query->where('rate', '>', 0);
    }

    /**
     * Scope for zero-rated tax rates.
     */
    public function scopeZeroRated($query)
    {
        return $query->where('rate', 0)->where('is_activated', true);
    }

    /**
     * Scope for exempt tax rates.
     */
    public function scopeExempt($query)
    {
        return $query->where('rate', 0)->where('is_activated', false);
    }

    /**
     * Get the tax rate as a formatted string.
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate, 2) . '%';
    }

    /**
     * Check if the tax rate is a standard rate.
     */
    public function isStandardRate(): bool
    {
        return $this->rate > 0 && $this->is_activated;
    }

    /**
     * Check if the tax rate is zero rated.
     */
    public function isZeroRated(): bool
    {
        return $this->rate == 0 && $this->is_activated;
    }

    /**
     * Check if the tax rate is exempt.
     */
    public function isExempt(): bool
    {
        return $this->rate == 0 && !$this->is_activated;
    }

    /**
     * Calculate tax for a given amount.
     */
    public function calculateTax(float $amount): array
    {
        $taxAmount = ($amount * $this->rate) / 100;
        
        return [
            'rate' => $this->rate,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($amount + $taxAmount, 2),
            'tax_rate_id' => $this->tax_rate_id,
            'name' => $this->name,
            'charge_mode' => $this->charge_mode,
            'is_activated' => $this->is_activated
        ];
    }

    /**
     * Get the tax rate details as an object.
     */
    public function toTaxRateObject(): object
    {
        return (object) [
            'id' => $this->tax_rate_id,
            'name' => $this->name,
            'chargeMode' => $this->charge_mode,
            'ordinal' => $this->ordinal,
            'rate' => (float) $this->rate,
            'isActivated' => (bool) $this->is_activated
        ];
    }

    /**
     * Get tax rate for API response.
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->tax_rate_id,
            'name' => $this->name,
            'chargeMode' => $this->charge_mode,
            'ordinal' => $this->ordinal,
            'rate' => (float) $this->rate,
            'isActivated' => (bool) $this->is_activated,
            'formattedRate' => $this->formatted_rate
        ];
    }
}