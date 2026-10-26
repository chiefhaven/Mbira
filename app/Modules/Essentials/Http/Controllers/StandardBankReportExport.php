<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class StandardBankReportExport implements FromCollection, WithCustomCsvSettings
{
    protected $business_id;
    protected $payroll_group_id;

    public function __construct($business_id, $payroll_group_id)
    {
        $this->business_id = $business_id;
        $this->payroll_group_id = $payroll_group_id;
    }

    public function collection()
    {
        $payrolls = Employee::where('business_id', $this->business_id)
            ->where('payroll_group_id', $this->payroll_group_id)
            ->get();

        return $payrolls->map(function ($emp) {
            return [
                $this->clean($emp->account_number),
                number_format((float) $emp->net_pay, 2, '.', ''),
                $this->clean($emp->account_name ?? $emp->name),
                $this->clean($emp->bank_code),
                $this->clean($emp->branch_code),
                $this->clean($emp->reference_number ?? $emp->id),
                $this->clean($emp->address ?? ''),
            ];
        });
    }

    /**
     * Ensure clean values (no commas, symbols, etc.)
     */
    private function clean($value)
    {
        $value = trim((string) $value);

        // Remove problematic characters
        $value = str_replace([',', '#', "\n", "\r"], '', $value);

        return $value;
    }

    /**
     * Force proper CSV format
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',      // Standard Bank usually uses comma
            'enclosure' => '',       // No quotes
            'line_ending' => "\n",
            'use_bom' => true,       // Ensures Excel compatibility
        ];
    }
}