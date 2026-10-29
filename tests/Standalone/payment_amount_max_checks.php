<?php

// Validate the actual Blade maximum expressions without application/database boot.
$precision = 2;
function session($key, $default = null)
{
    global $precision;
    return $key === 'business.currency_precision' ? $precision : $default;
}
$checks = 0;
foreach (['payment_row', 'pay_supplier_due_modal'] as $view) {
    $source = file_get_contents(dirname(__DIR__, 2).'\resources\views\transaction_payment\\'.$view.'.blade.php');
    if (!preg_match("/'data-rule-max-value' => (.*?), 'data-msg-max-value'/", $source, $match)) {
        throw new RuntimeException('Missing payment maximum: '.$view);
    }
    foreach ([0, 2, 3, 4] as $precision) {
        foreach ([0, 0.1 + 0.2, 99.999999999, 1234.56789, 1000000.005, 234.12 - 100.11] as $amount) {
            $payment_line = (object) ['amount' => $amount];
            $maximum = eval('return '.$match[1].';');
            $displayed = number_format($amount, $precision, '.', '');
            if ((float) $maximum !== (float) $displayed) {
                throw new RuntimeException('Prefilled payment rejected for '.$view.' at precision '.$precision);
            }
            if ((float) $maximum >= (float) $displayed + pow(10, -$precision)) {
                throw new RuntimeException('Maximum permits an extra currency unit for '.$view);
            }
            if (str_contains($maximum, ',')) {
                throw new RuntimeException('Maximum must remain locale-independent numeric data');
            }
            $checks += 3;
        }
    }
}
echo "PASS: {$checks} payment maximum rounding and overpayment checks; no application/database boot.\n";
