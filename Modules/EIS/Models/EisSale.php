<?php

namespace Modules\EIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EisSale extends Model
{
    protected $table = 'eis_sales';

    protected static function booted()
    {
        static::saving(function ($model) {
            // Reject draft/split tracking before writing, including direct service callers.
            $header = \App\Services\Sales\SavedSaleWriteGuard::fiscal($model->getConnection(), $model->transaction_id);
            if ($header && $header->type === 'sell' && $header->status === 'draft') {
                throw new \App\Services\Sales\SavedSplitException('draft_fiscal_state_blocked', 'Draft sales must not create fiscal tracking.');
            }
        });
    }

    protected $fillable = [
        'location_id',
        'eis_setting_id',
        'eis_invoice_number',
        'business_id',
        'transaction_id',
        'invoice_number',
        'fiscal_invoice_number',
        'receipt_number',
        'receipt_signature',
        'qr_code',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'submitted_at',
        'is_fiscalized',
        'fiscalized_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'void_response_payload' => 'array',
        'submitted_at' => 'datetime',
        'fiscalized_at' => 'datetime',
        'void_synced_at' => 'datetime',
        'is_fiscalized' => 'boolean',
    ];

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class);
    }

    /** The submitted invoice identity, not the validation URL's receipt code. */
    public function getEisInvoiceNumberAttribute($value): ?string
    {
        // The generated number is persisted in the request on existing schemas.
        $number = $this->request_payload['invoiceHeader']['invoiceNumber'] ?? $value;

        return is_string($number) && trim($number) !== '' ? trim($number) : null;
    }

    /**
     * Check if offline sale limits are within allowed thresholds
     * 
     * @param int $business_id
     * @param float $sale_amount
     * @return array Returns ['success' => bool, 'message' => string, 'code' => string|null, 'details' => array|null]
     */
    public static function checkOfflineLimits($business_id, $sale_amount, int $location_id, ?int $till_id = null)
    {
        try {
            // Get business settings with terminal configuration using DB
            $settings = $till_id
                ? \Modules\Connector\Support\TillAssignment::terminal((int) $business_id, $location_id, $till_id)
                : \Modules\EIS\Services\Terminal\TerminalContext::forLocation((int) $business_id, $location_id, true);
            $eisConfigurations = DB::table('eis_configurations')
                ->where('business_id', $business_id)->where('eis_setting_id', $settings->id)
                ->first();
                
            if (!$eisConfigurations) {
                \Log::warning('Business settings not found for offline limit check', [
                    'business_id' => $business_id,
                    'location_id' => $location_id
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Business settings not found',
                    'code' => 'BUSINESS_NOT_FOUND',
                    'details' => null
                ];
            }
            
            // Get terminal configuration
            $terminalConfig = DB::table('eis_terminal_configurations')
                ->where('configuration_id', $eisConfigurations->id)
                ->first();
                
            if (!$terminalConfig) {
                \Log::warning('Terminal configuration not found for offline limit check', [
                    'business_id' => $business_id,
                    'location_id' => $location_id
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Terminal configuration not found',
                    'code' => 'TERMINAL_CONFIG_NOT_FOUND',
                    'details' => null
                ];
            }
            
            // Prefer the canonical offline-limit record, but fall back to the legacy terminal fields.
            $offlineLimitRow = DB::table('eis_offline_limits')
                ->where('terminal_configuration_id', $terminalConfig->id)
                ->first();

            $offlineLimit = (float) (
                $offlineLimitRow->max_cumulative_amount ??
                $terminalConfig->max_cummulative_amount ??
                $terminalConfig->max_cumulative_amount ??
                0
            );

            $maxTransactionAge = (int) (
                $offlineLimitRow->max_transaction_age_hours ??
                $terminalConfig->max_transaction_age ??
                $terminalConfig->max_transaction_age_hours ??
                0
            );
            
            // If no limits set, allow sale
            if ($offlineLimit == 0 && $maxTransactionAge == 0) {
                return [
                    'success' => true,
                    'message' => 'No limits configured',
                    'code' => 'NO_LIMITS',
                    'details' => [
                        'limit_type' => 'unlimited',
                        'sale_amount' => $sale_amount
                    ]
                ];
            }
            
            // ✅ Get total unsubmitted sales with transaction amounts using DB
            $totalUnsubmitted = DB::table('eis_sales')
                ->join('transactions', 'eis_sales.transaction_id', '=', 'transactions.id')
                ->where('eis_sales.business_id', $business_id)
                ->where('eis_sales.eis_setting_id', $settings->id)
                ->where('eis_sales.status', '!=', 'submitted')
                ->sum('transactions.final_total');
            
            // Get the oldest unsubmitted sale using DB
            $oldestUnsubmittedSale = DB::table('eis_sales')
                ->where('business_id', $business_id)
                ->where('eis_setting_id', $settings->id)
                ->where('status', '!=', 'submitted')
                ->orderBy('created_at', 'asc')
                ->first();
            
            // Calculate age difference in hours
            $ageDifferenceInHours = 0;
            if ($oldestUnsubmittedSale) {
                $createdAt = \Carbon\Carbon::parse($oldestUnsubmittedSale->created_at);
                $ageDifferenceInHours = now()->diffInHours($createdAt);
            }
            
            // Check age limit (max_transaction_age)
            if ($maxTransactionAge > 0 && $ageDifferenceInHours > $maxTransactionAge) {
                \Log::warning('Transaction age limit exceeded', [
                    'business_id' => $business_id,
                    'location_id' => $location_id,
                    'max_age_limit' => $maxTransactionAge,
                    'age_difference' => $ageDifferenceInHours,
                    'oldest_sale_id' => $oldestUnsubmittedSale->id ?? null,
                    'oldest_sale_date' => $oldestUnsubmittedSale->created_at ?? null
                ]);
                
                return [
                    'success' => false,
                    'message' => "Unsubmitted sale exceeds maximum transaction age of {$maxTransactionAge} hours. Please submit pending sales.",
                    'code' => 'TRANSACTION_AGE_EXCEEDED',
                    'details' => [
                        'max_age_limit' => $maxTransactionAge,
                        'age_difference' => $ageDifferenceInHours,
                        'oldest_sale_date' => $oldestUnsubmittedSale->created_at ?? null,
                        'total_unsubmitted' => $totalUnsubmitted,
                        'limit_type' => 'age_limit'
                    ]
                ];
            }
            
            // Check cumulative amount limit (max_cummulative_amount)
            if ($offlineLimit > 0) {
                $totalAfterSale = $totalUnsubmitted + $sale_amount;
                
                if ($totalAfterSale > $offlineLimit) {
                    $remaining = $offlineLimit - $totalUnsubmitted;
                    
                    \Log::warning('Cumulative amount limit exceeded', [
                        'business_id' => $business_id,
                        'location_id' => $location_id,
                        'cumulative_limit' => $offlineLimit,
                        'current_total' => $totalUnsubmitted,
                        'sale_amount' => $sale_amount,
                        'total_after_sale' => $totalAfterSale,
                        'remaining' => $remaining
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => "Cumulative offline amount limit exceeded. Remaining: " . number_format($remaining, 2),
                        'code' => 'CUMULATIVE_LIMIT_EXCEEDED',
                        'details' => [
                            'cumulative_limit' => $offlineLimit,
                            'current_total' => $totalUnsubmitted,
                            'sale_amount' => $sale_amount,
                            'total_after_sale' => $totalAfterSale,
                            'remaining' => $remaining,
                            'limit_type' => 'cumulative_amount'
                        ]
                    ];
                }
            }
            
            // All checks passed
            return [
                'success' => true,
                'message' => 'Offline limits check passed',
                'code' => 'LIMITS_PASSED',
                'details' => [
                    'cumulative_limit' => $offlineLimit,
                    'current_total' => $totalUnsubmitted,
                    'sale_amount' => $sale_amount,
                    'total_after_sale' => $totalUnsubmitted + $sale_amount,
                    'remaining' => $offlineLimit - ($totalUnsubmitted + $sale_amount),
                    'max_age_limit' => $maxTransactionAge,
                    'age_difference' => $ageDifferenceInHours,
                    'oldest_sale_date' => $oldestUnsubmittedSale->created_at ?? null,
                    'is_within_limits' => true
                ]
            ];
            
        } catch (\Exception $e) {
            // Log error but allow sale to proceed (fail-open strategy)
            \Log::error('Error checking offline limits - allowing sale', [
                'business_id' => $business_id,
                'location_id' => $location_id,
                'sale_amount' => $sale_amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return success to not block sales if there's an error checking limits
            return [
                'success' => true,
                'message' => 'Error checking limits, allowing sale to proceed',
                'code' => 'CHECK_ERROR',
                'details' => [
                    'error' => $e->getMessage(),
                    'fallback' => 'fail-open'
                ]
            ];
        }
    }

    /**
     * Mark sale as fiscalized.
     * 
     * @return bool
     */
    public function markAsFiscalized(): bool
    {
        return $this->update([
            'is_fiscalized' => true,
            'fiscalized_at' => now(),
        ]);
    }

    /**
     * Check if sale has been fiscalized.
     * 
     * @return bool
     */
    public function isFiscalized(): bool
    {
        return $this->is_fiscalized === true;
    }

    /**
     * Get sales that are pending fiscalization.
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendingFiscalization($query)
    {
        return $query->where('is_fiscalized', false)
            ->where('status', '!=', 'failed')
            ->where('status', '!=', 'cancelled');
    }

    /**
     * Get sales that have been fiscalized.
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFiscalized($query)
    {
        return $query->where('is_fiscalized', true);
    }
}
