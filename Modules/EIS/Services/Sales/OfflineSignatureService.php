<?php

namespace Modules\EIS\Services\Sales;

use Illuminate\Support\Facades\Log;

class OfflineSignatureService
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
        // Ensure number is integer
        $number = (int) $number;
        
        if ($number == 0) {
            return 'A'; // 'A' represents 0 in standard Base64
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
     * Formula: JD = floor(365.25 * (year + 4716)) + floor(30.6001 * (month + 1)) + day + B - 1524
     * Where B = 2 - A + (A / 4) and A = year / 100
     * 
     * Example: 2026-07-13 → 2461000
     * 
     * @param \DateTime|string $date
     * @return int
     */
    public function toJulianDate($date): int
    {
        // Parse date if string
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        
        // Ensure we only work with date part
        $date = clone $date;
        $date->setTime(0, 0, 0);
        
        $year = (int) $date->format('Y');
        $month = (int) $date->format('m');
        $day = (int) $date->format('d');
        
        // Adjust for Julian calendar (same as C#)
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        
        // Calculate A and B (same as C#)
        $A = (int) ($year / 100);
        $B = 2 - $A + (int) ($A / 4);
        
        // Calculate Julian Date (same as C#)
        $JD = (int) (floor(365.25 * ($year + 4716)) 
            + floor(30.6001 * ($month + 1)) 
            + $day + $B - 1524);
        
        return $JD;
    }

    /**
     * Get Julian Date as string.
     * 
     * @param \DateTime|string $date
     * @return string
     */
    public function getJulianDateString($date): string
    {
        $julianDate = $this->toJulianDate($date);
        return (string) $julianDate;
    }

    /**
     * Generate combined string from components.
     * Format: Base64(TaxpayerID) - Base64(Position) - Base64(JulianDate) - Base64(TransactionCount)
     *
     * @param int|string $taxpayerId
     * @param int $position
     * @param int $julianDate
     * @param int $transactionCount
     * @return string
     */
    public function generateCombinedString(
        $taxpayerId,
        int $position,
        int $julianDate,
        int $transactionCount
    ): string {
        $base64TaxpayerNumber = $this->base10ToBase64($taxpayerId);
        $base64Position = $this->base10ToBase64($position);
        $julianDateBase64 = $this->base10ToBase64($julianDate);
        $serialNumberBase64 = $this->base10ToBase64($transactionCount);
        
        return $base64TaxpayerNumber . '-' . 
               $base64Position . '-' . 
               $julianDateBase64 . '-' . 
               $serialNumberBase64;
    }

    /**
     * Generate offline signature and validation URL.
     *
     * @param string $taxpayerId
     * @param int $position
     * @param array $request
     * @param string $secretKey
     * @return array
     */
    public function generateInvoiceResponse(
        string $taxpayerId,
        int $position,
        array $request,
        string $secretKey
    ): array {
        try {
            // Get transaction date
            $transactionDate = $request['transactiondate'] ?? now()->toISOString();
            
            // Convert to astronomical Julian Date (full JD)
            $julianDate = $this->toJulianDate($transactionDate);
            
            // Convert Julian Date to Base64
            $julianDateTo64 = $this->base10ToBase64($julianDate);
            
            // Generate combined string
            $combinedString = $this->generateCombinedString(
                $taxpayerId,
                $position,
                $julianDate,
                $request['transactionCount'] ?? 1
            );
            
            // Build parameters
            $param = "TI=" . $combinedString . 
                     "&N=" . ($request['NumItems'] ?? 0) . 
                     "&I=" . ($request['InvoiceTotal'] ?? 0) . 
                     "&V=" . ($request['VATAmount'] ?? 0) . 
                     "&T=" . $julianDateTo64;
            
            // Compute HMAC-SHA256 signature
            $offlineDataSignature = $this->computeHMACSHA256($param, $secretKey);
            
            // URL encode the signature
            $offlineDataSignature = urlencode($offlineDataSignature);
            
            // Build validation URL
            $offlineBaseURL = "https://dev-eis-portal.mra.mw/ReceiptValidation/Validate/";
            $validationURL = $offlineBaseURL . "?" . $param . "&S=" . $offlineDataSignature;
            
            Log::debug('Offline signature generated', [
                'taxpayer_id' => $taxpayerId,
                'position' => $position,
                'julian_date' => $julianDate,
                'julian_date_64' => $julianDateTo64,
                'combined_string' => $combinedString,
                'param' => $param,
                'signature' => $offlineDataSignature,
                'validation_url' => $validationURL
            ]);
            
            return [
                'offlineDataSignature' => $offlineDataSignature,
                'validationURL' => $validationURL,
                'param' => $param,
                'julianDate' => $julianDate,
                'julianDateTo64' => $julianDateTo64,
                'combinedString' => $combinedString,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to generate offline signature', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception('Failed to generate offline signature: ' . $e->getMessage());
        }
    }

    /**
     * Compute HMAC-SHA256 signature.
     *
     * @param string $param
     * @param string $secretKey
     * @return string
     */
    public function computeHMACSHA256(string $param, string $secretKey): string
    {
        // Compute HMAC-SHA256 hash
        $hash = hash_hmac('sha256', $param, $secretKey, true);
        return base64_encode($hash);
    }

    /**
     * Generate offline signature with URL encoding.
     *
     * @param string $param
     * @param string $secretKey
     * @return string
     */
    public function generateOfflineSignature(string $param, string $secretKey): string
    {
        $signature = $this->computeHMACSHA256($param, $secretKey);
        return urlencode($signature);
    }

    /**
     * Validate an offline signature.
     *
     * @param string $param
     * @param string $signature
     * @param string $secretKey
     * @return bool
     */
    public function validateOfflineSignature(string $param, string $signature, string $secretKey): bool
    {
        $expectedSignature = $this->generateOfflineSignature($param, $secretKey);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate full validation URL.
     *
     * @param string $taxpayerId
     * @param int $position
     * @param array $request
     * @param string $secretKey
     * @param string $baseURL
     * @return string
     */
    public function generateValidationURL(
        string $taxpayerId,
        int $position,
        array $request,
        string $secretKey,
        string $baseURL = 'https://dev-eis-portal.mra.mw/ReceiptValidation/Validate/'
    ): string {
        $result = $this->generateInvoiceResponse($taxpayerId, $position, $request, $secretKey);
        return $result['validationURL'];
    }

    /**
     * Generate receipt validation QR code data.
     *
     * @param string $validationURL
     * @return string
     */
    public function generateQRCodeData(string $validationURL): string
    {
        // Base64 encode the validation URL for QR code
        return base64_encode($validationURL);
    }

    /**
     * Parse invoice components from combined string.
     *
     * @param string $combinedString
     * @return array|null
     */
    public function parseCombinedString(string $combinedString): ?array
    {
        $parts = explode('-', $combinedString);
        
        if (count($parts) !== 4) {
            return null;
        }
        
        return [
            'taxpayerId' => $this->base64ToBase10($parts[0]),
            'position' => (int) $this->base64ToBase10($parts[1]),
            'julianDate' => (int) $this->base64ToBase10($parts[2]),
            'transactionCount' => (int) $this->base64ToBase10($parts[3]),
        ];
    }

    /**
     * Get EIS Julian Date (YYDDD format) - Simplified version.
     * Example: 2026-07-13 → 26194
     * 
     * @param \DateTime $date
     * @return int
     */
    public function getEISJulianDate(\DateTime $date): int
    {
        $year = (int) $date->format('y');
        $dayOfYear = (int) $date->format('z') + 1;
        
        return (int) ($year . str_pad($dayOfYear, 3, '0', STR_PAD_LEFT));
    }
}