<?php

// Uses only the isolated SQLite location/terminal fixture; no application or HTTP.
require __DIR__.'\eis_location_terminal_checks.php';

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
try {
    DB::table('eis_sales')->delete();
    Carbon::setTestNow(Carbon::parse('2026-10-23 23:59:59'));
    $firstDayNumbers = [];
    foreach ([1 => 1, 2 => 2] as $terminalId => $position) {
        foreach ([1 => 'B', 2 => 'C', 3 => 'D'] as $sequence => $encodedCount) {
            $number = $generator->generateInvoiceNumber(1, $generator->getNextDailyCount(1, $terminalId), $position, 100, $terminalId);
            $parts = explode('-', $number);
            locationCheck($parts[3] === $encodedCount, 'Each terminal independently issues B/C/D on the first day');
            locationCheck($generator->parseInvoiceNumber($number)['count'] === $sequence,
                'Base64 suffix decodes to terminal daily sequence '.$sequence);
            DB::table('eis_sales')->insert([
                'business_id' => 1, 'transaction_id' => 100 + $terminalId * 10 + $sequence,
                'location_id' => $terminalId, 'eis_setting_id' => $terminalId,
                'eis_invoice_number' => $number, 'fiscal_invoice_number' => 'CONFIRMED-RECEIPT',
                'status' => $sequence === 1 ? 'submitted' : ($sequence === 2 ? 'failed' : 'pending'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $firstDayNumbers[$terminalId] = $number;
        }
    }
    DB::table('eis_sales')->insert([
        'business_id' => 1, 'transaction_id' => 199,
        'location_id' => 1, 'eis_setting_id' => 1,
        'eis_invoice_number' => $firstDayNumbers[1], 'status' => 'failed',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $payloadOnlyInvoice = implode('-', array_slice(explode('-', $firstDayNumbers[1]), 0, 3))
        .'-'.$generator->base10ToBase64(4);
    DB::table('eis_sales')->insert([
        'business_id' => 1, 'transaction_id' => 198,
        'location_id' => 1, 'eis_setting_id' => 1,
        'request_payload' => json_encode(['invoiceHeader' => ['invoiceNumber' => $payloadOnlyInvoice]]),
        'status' => 'failed', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $malformedInvoice = implode('-', array_slice(explode('-', $firstDayNumbers[1]), 0, 3)).'-bad-count';
    DB::table('eis_sales')->insert([
        'business_id' => 1, 'transaction_id' => 197,
        'location_id' => 1, 'eis_setting_id' => 1,
        'eis_invoice_number' => $malformedInvoice, 'status' => 'failed',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    locationCheck($generator->getDailyCount(1, 1) === 4
        && $generator->getNextDailyCount(1, 1) === 5,
        'Daily sequence counts unique valid terminal invoices and recovers identities from saved request payloads (count='
            .$generator->getDailyCount(1, 1).')');
    Carbon::setTestNow(Carbon::parse('2026-10-24 00:00:00'));
    foreach ([1 => 1, 2 => 2] as $terminalId => $position) {
        locationCheck($generator->getDailyCount(1, $terminalId) === 0, 'Every terminal count resets at midnight');
        $number = $generator->generateInvoiceNumber(1, $generator->getNextDailyCount(1, $terminalId), $position, 100, $terminalId);
        $parts = explode('-', $number);
        $previous = explode('-', $firstDayNumbers[$terminalId]);
        locationCheck($parts[3] === 'B' && $parts[0] === $previous[0] && $parts[1] === $previous[1]
            && $parts[2] === $generator->base10ToBase64($generator->toJulianDate(now())),
            'Next-day first invoice starts at B while taxpayer/terminal and Julian-date format are preserved');
        DB::table('eis_sales')->insert([
            'business_id' => 1, 'transaction_id' => 200 + $terminalId,
            'location_id' => $terminalId, 'eis_setting_id' => $terminalId,
            'eis_invoice_number' => $number, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        locationCheck($generator->getDailyCount(1, $terminalId) === 1, 'Next-day counter excludes all previous-day allocations');
    }
    $yesterday = Carbon::parse('2026-10-23 12:00:00');
    locationCheck($generator->getDailyCount(1, 1, $yesterday) === 4
        && $generator->getNextDailyCount(1, 1, $yesterday) === 5,
        'Invoice count is selected by the invoice transaction date, not the retry date');
    $lateSubmissionNumber = $generator->generateInvoiceNumber(
        1,
        $generator->getNextDailyCount(1, 1, $yesterday),
        1,
        100,
        1,
        $yesterday
    );
    $lateParts = explode('-', $lateSubmissionNumber);
    locationCheck($lateParts[2] === $generator->base10ToBase64($generator->toJulianDate($yesterday))
        && $lateParts[3] === $generator->base10ToBase64(5),
        'Late first submission uses invoice transaction date and that date’s terminal count plus one');
    $before = DB::table('eis_sales')->where('transaction_id', 112)->value('eis_invoice_number');
    DB::table('eis_sales')->where('transaction_id', 112)->update(['status' => 'submitted', 'updated_at' => now()]);
    locationCheck($generator->getDailyCount(1, 1) === 1
        && DB::table('eis_sales')->where('transaction_id', 112)->value('eis_invoice_number') === $before,
        'Yesterday retry keeps its invoice and does not consume today sequence');
} finally {
    Carbon::setTestNow();
    DB::rollBack();
}
echo "PASS: {$checks} cumulative daily terminal sequence checks (SQLite, no network).\n";
