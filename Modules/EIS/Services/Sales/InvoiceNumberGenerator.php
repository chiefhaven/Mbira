<?php

namespace Modules\EIS\Services\Sales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Models\EisConfiguration;
use Modules\EIS\Models\EisSetting;
use Modules\EIS\Models\EisTerminalConfiguration;

class InvoiceNumberGenerator
{
    /**
     * Base64 character set for encoding.
     */
    protected const BASE64_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    /**
     * Convert base10 number to Base64.
     * 
     * @param int|string $number
     * @return string
     */
    public function base10ToBase64($number): string
    {
        $number = (int) $number;
        
        if ($number == 0) {
            return 'A';
        }
        
        $result = '';
        $base64Chars = self::BASE64_CHARS;
        
        while ($number > 0) {
            $remainder = $number % 64;
            $result = $base64Chars[$remainder] . $result;
            $number = (int) ($number / 64);
        }
        
        return $result;
    }

    /**
     * Convert Base64 back to base10 number.
     * 
     * @param string $base64
     * @return int
     */
    public function base64ToBase10(string $base64): int
    {
        $base64Chars = self::BASE64_CHARS;
        $result = 0;
        $length = strlen($base64);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $base64[$i];
            $value = strpos($base64Chars, $char);
            if ($value === false) {
                return 0;
            }
            $result = ($result * 64) + $value;
        }
        
        return $result;
    }

    /**
     * Convert date to astronomical Julian Date (JD).
     * This matches the C# implementation exactly.
     * 
     * @param \DateTime $date
     * @return int
     */
    public function toJulianDate(\DateTimeInterface $date): int
    {
        $date = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        
        $year = (int) $date->format('Y');
        $month = (int) $date->format('m');
        $day = (int) $date->format('d');
        
        // Adjust for Julian calendar
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        
        $A = (int) ($year / 100);
        $B = 2 - $A + (int) ($A / 4);
        
        // Calculate Julian Date
        $JD = (int) (floor(365.25 * ($year + 4716)) 
            + floor(30.6001 * ($month + 1)) 
            + $day + $B - 1524);
        
        return $JD;
    }

    /**
     * Generate EIS compliant invoice number.
     * Format: Base64(TaxpayerID) - Base64(TerminalPosition) - Base64(JulianDate) - Base64(Count)
     *
     * @param int $businessId
     * @param int|null $terminalPosition
     * @return string
     */
    public function generateInvoiceNumber(
        int $businessId,
        int $transactionCount,
        ?int $terminalPosition,
        ?int $taxpayerId,
        ?int $terminalId = null,
        ?\DateTimeInterface $transactionDate = null
    ): string
    {
        try {

            return DB::transaction(function () use ($businessId, $transactionCount, $terminalPosition, $taxpayerId, $terminalId, $transactionDate) {
                // Get EIS settings
                $settings = EisSetting::where('business_id', $businessId)->when($terminalId !== null, fn ($query) => $query->whereKey($terminalId))->whereHas('configuration', function ($query) use ($terminalPosition, $taxpayerId) {
                    $query->whereHas('terminalConfiguration', fn ($terminal) => $terminal
                        ->where('terminal_position', $terminalPosition)->where('taxpayer_id', $taxpayerId));
                })->limit(2)->get();
                if ($settings->count() !== 1 || $transactionCount < 1) {
                    throw new \InvalidArgumentException('Select exactly one EIS terminal and a positive invoice sequence.');
                }
                $setting = $settings->first();

                // Get configuration with terminal relationship
                $configuration = EisConfiguration::with('terminalConfiguration')
                    ->where('eis_setting_id', $setting->id)
                    ->first();
                    
                if (!$configuration) {
                    throw new \Exception('EIS configuration not found for business: ' . $businessId);
                }

                // Get terminal position
                $terminalPos = $terminalPosition ?? 1;

                // Use full astronomical Julian Date
                $julianDate = $this->toJulianDate($transactionDate ?? now());
                
                // Encode each component to Base64
                $encodedTaxpayerId = $this->base10ToBase64($taxpayerId);
                $encodedTerminalPos = $this->base10ToBase64($terminalPos);
                $encodedJulianDate = $this->base10ToBase64($julianDate);
                $encodedCount = $this->base10ToBase64($transactionCount);

                // Build invoice number
                $invoiceNumber = $encodedTaxpayerId . '-' . 
                                $encodedTerminalPos . '-' . 
                                $encodedJulianDate . '-' . 
                                $encodedCount;

                Log::info('Invoice number generated successfully', [
                    'business_id' => $businessId,
                    'invoice_number' => $invoiceNumber,
                    'components' => [
                        'taxpayer_id' => $taxpayerId,
                        'terminal_position' => $terminalPos,
                        'julian_date' => $julianDate,
                        'encoded_taxpayer_id' => $encodedTaxpayerId,
                        'encoded_terminal_pos' => $encodedTerminalPos,
                        'encoded_julian_date' => $encodedJulianDate,
                        'encoded_count' => $encodedCount,
                    ]
                ]);

                return $invoiceNumber;
            });

        } catch (\Exception $e) {
            Log::error('Failed to generate invoice number', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Create default terminal configuration.
     *
     * @param EisConfiguration $configuration
     * @param int $terminalPosition
     * @return EisTerminalConfiguration
     */
    protected function createDefaultTerminal(EisConfiguration $configuration, int $terminalPosition): EisTerminalConfiguration
    {
        try {
            $terminal = EisTerminalConfiguration::create([
                'configuration_id' => $configuration->id,
                'terminal_position' => $terminalPosition,
                'terminal_label' => 'Default Terminal',
                'is_active' => true,
                'is_confirmed' => true,
                'version' => 1,
                'last_synced_at' => now(),
            ]);

            Log::info('Default terminal created', [
                'configuration_id' => $configuration->id,
                'terminal_position' => $terminalPosition,
                'terminal_id' => $terminal->id
            ]);

            return $terminal;

        } catch (\Exception $e) {
            Log::error('Failed to create default terminal', [
                'configuration_id' => $configuration->id,
                'error' => $e->getMessage()
            ]);

            return new EisTerminalConfiguration([
                'configuration_id' => $configuration->id,
                'terminal_position' => $terminalPosition,
                'terminal_label' => 'Default Terminal',
                'is_active' => true,
            ]);
        }
    }

    /**
     * Generate fallback invoice number.
     *
     * @param int $businessId
     * @return string
     */
    private function generateFallbackInvoiceNumber(int $businessId): string
    {
        $setting = \Modules\EIS\Services\Terminal\TerminalContext::settings($businessId);
        $tpin = $setting->tpin ?? '000000';
        $date = now()->format('Ymd');
        $timestamp = now()->format('His');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return 'FALLBACK-' . $tpin . '-' . $date . '-' . $timestamp . '-' . $random;
    }

    /**
     * Get terminal position from terminal configuration.
     *
     * @param int $businessId
     * @param int|null $terminalPosition
     * @return int
     */
    public function getTerminalPosition(int $businessId, ?int $terminalPosition = null, ?int $terminalId = null): int
    {
        return (int) $this->getOrCreateTerminal($businessId, $terminalPosition, $terminalId)->terminal_position;
    }

    /**
     * Get or create terminal configuration.
     *
     * @param int $businessId
     * @param int|null $terminalPosition
     * @return EisTerminalConfiguration
     */
    public function getOrCreateTerminal(int $businessId, ?int $terminalPosition = null, ?int $terminalId = null): EisTerminalConfiguration
    {
        try {
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, $terminalId);

            $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)->firstOrFail();

            if (!$terminal->terminal_position || !$terminal->taxpayer_id
                || ($terminalPosition !== null && (int) $terminal->terminal_position !== $terminalPosition)) {
                throw new \InvalidArgumentException('EIS requires the selected terminal taxpayer and terminal position.');
            }

            return $terminal;

        } catch (\Exception $e) {
            Log::error('Failed to get or create terminal', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generate multiple invoice numbers.
     *
     * @param int $businessId
     * @param int $count
     * @param int|null $terminalPosition
     * @return array
     */
    public function generateInvoiceNumbers(int $businessId, int $count, ?int $terminalPosition = null, ?int $terminalId = null): array
    {
        try {
            Log::info('Generating multiple invoice numbers', [
                'business_id' => $businessId,
                'count' => $count,
                'terminal_position' => $terminalPosition
            ]);

            $invoiceNumbers = [];
            $configuration = \Modules\EIS\Services\Terminal\TerminalContext::configuration($businessId, $terminalId);
            $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)->firstOrFail();
            $sequence = $this->getNextDailyCount($businessId, (int) $configuration->eis_setting_id);
            
            for ($i = 0; $i < $count; $i++) {
                $invoiceNumbers[] = $this->generateInvoiceNumber($businessId, $sequence + $i,
                    $terminalPosition ?? (int) $terminal->terminal_position, (int) $terminal->taxpayer_id,
                    (int) $configuration->eis_setting_id);
            }
            
            return $invoiceNumbers;

        } catch (\Exception $e) {
            Log::error('Failed to generate multiple invoice numbers', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /** Count unique EIS invoices issued today by the selected terminal. */
    public function getDailyCount(
        int $businessId,
        ?int $terminalId = null,
        ?\DateTimeInterface $transactionDate = null
    ): int
    {
        $settings = \Modules\EIS\Services\Terminal\TerminalContext::settings($businessId, null, $terminalId);
        $configuration = EisConfiguration::where('business_id', $businessId)->where('eis_setting_id', $settings->id)->firstOrFail();
        $terminal = EisTerminalConfiguration::where('configuration_id', $configuration->id)->first();
        if (!$terminal || !$terminal->taxpayer_id || !$terminal->terminal_position) {
            throw new \InvalidArgumentException('EIS invoice counting requires a configured taxpayer and terminal position.');
        }
        $prefix = $this->base10ToBase64($terminal->taxpayer_id).'-'
            .$this->base10ToBase64($terminal->terminal_position).'-'
            .$this->base10ToBase64($this->toJulianDate($transactionDate ?? now())).'-';

        $invoices = DB::table('eis_sales')
            ->where('business_id', $businessId)
            ->where('eis_setting_id', $settings->id)
            ->where(function ($query) use ($prefix) {
                $query->where('eis_invoice_number', 'like', $prefix.'%')
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery->where(function ($numberQuery) {
                            $numberQuery->whereNull('eis_invoice_number')->orWhere('eis_invoice_number', '');
                        })->whereNotNull('request_payload');
                    });
            })
            ->get(['eis_invoice_number', 'request_payload']);
        $countedInvoices = [];
        foreach ($invoices as $invoice) {
            $number = $invoice->eis_invoice_number;
            if (!is_string($number) || $number === '') {
                $payload = is_string($invoice->request_payload)
                    ? json_decode($invoice->request_payload, true)
                    : null;
                $number = is_array($payload) ? ($payload['invoiceHeader']['invoiceNumber'] ?? null) : null;
            }
            if (!is_string($number) || !str_starts_with($number, $prefix)) {
                continue;
            }

            $encodedCount = substr($number, strlen($prefix));
            if ($encodedCount === '' || str_contains($encodedCount, '-')
                || $this->base64ToBase10($encodedCount) < 1) {
                continue;
            }
            $countedInvoices[$number] = true;
        }

        return count($countedInvoices);
    }

    /** Return the next sequence for today's invoices on the selected terminal. */
    public function getNextDailyCount(
        int $businessId,
        ?int $terminalId = null,
        ?\DateTimeInterface $transactionDate = null
    ): int
    {
        return $this->getDailyCount($businessId, $terminalId, $transactionDate) + 1;
    }

    /**
     * Parse invoice number to get components.
     *
     * @param string $invoiceNumber
     * @return array|null
     */
    public function parseInvoiceNumber(string $invoiceNumber): ?array
    {
        try {
            $parts = explode('-', $invoiceNumber);
            
            if (count($parts) !== 4) {
                return null;
            }

            return [
                'taxpayer_id' => $this->base64ToBase10($parts[0]),
                'terminal_position' => (int) $this->base64ToBase10($parts[1]),
                'julian_date' => (int) $this->base64ToBase10($parts[2]),
                'count' => (int) $this->base64ToBase10($parts[3]),
                'encoded_taxpayer_id' => $parts[0],
                'encoded_terminal_position' => $parts[1],
                'encoded_julian_date' => $parts[2],
                'encoded_count' => $parts[3],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to parse invoice number', [
                'invoice_number' => $invoiceNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate invoice number format.
     *
     * @param string $invoiceNumber
     * @return bool
     */
    public function validateInvoiceNumber(string $invoiceNumber): bool
    {
        $parsed = $this->parseInvoiceNumber($invoiceNumber);
        
        if (!$parsed) {
            return false;
        }

        if (!is_numeric($parsed['count'])) {
            return false;
        }

        if (empty($parsed['taxpayer_id']) || empty($parsed['terminal_position'])) {
            return false;
        }

        return true;
    }
}