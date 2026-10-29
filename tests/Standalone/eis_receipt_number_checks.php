<?php

// No application kernel, database, credentials, external calls or real printing.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Modules\EIS\Models\EisSale;

$root = dirname(__DIR__, 2);
$container = new Container();
Container::setInstance($container);
$loader = new Illuminate\Translation\FileLoader(new Filesystem(), $root.'/lang');
$container->instance('translator', new Illuminate\Translation\Translator($loader, 'en'));
$compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
$checks = 0;
function checkReceiptNumber(bool $condition, string $message): void {
    global $checks;
    if (! $condition) { throw new RuntimeException($message); }
    ++$checks;
}
function renderNumberPartial(BladeCompiler $compiler, object $receipt_details): string {
    global $root;
    $compiled = $compiler->compileString(file_get_contents($root.'/resources/views/sale_pos/receipts/partial/eis_invoice_number.blade.php'));
    ob_start();
    try { eval('?>'.$compiled); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
$sale = new EisSale(['invoice_number' => 'TXN-100', 'fiscal_invoice_number' => 'EIS-200',
    'receipt_number' => 'VALIDATION-CODE', 'request_payload' => ['invoiceHeader' => ['invoiceNumber' => 'EIS-200']]]);
foreach (['pending', 'failed', 'submitted'] as $status) {
    $sale->status = $status;
    checkReceiptNumber($sale->eis_invoice_number === 'EIS-200', 'Saved EIS identity available for '.$status);
}
checkReceiptNumber($sale->invoice_number === 'TXN-100', 'Original tracking number preserved');
checkReceiptNumber($sale->eis_invoice_number === $sale->fiscal_invoice_number
    && $sale->eis_invoice_number !== $sale->receipt_number,
    'Fiscal invoice identity matches the EIS number while receipt code stays separate');
$sale->setRawAttributes(['eis_invoice_number' => 'LEGACY-EIS', 'request_payload' => null]);
checkReceiptNumber($sale->eis_invoice_number === 'LEGACY-EIS', 'Support existing dedicated column when present');
foreach ([null, [], ['invoiceHeader' => []], ['invoiceHeader' => ['invoiceNumber' => '']],
    ['invoiceHeader' => ['invoiceNumber' => ['invalid']]]] as $payload) {
    $sale = new EisSale(['request_payload' => $payload]);
    checkReceiptNumber($sale->eis_invoice_number === null, 'Missing/invalid EIS number is not invented');
}
$sale = new EisSale(['request_payload' => ['invoiceHeader' => ['invoiceNumber' => ' EIS-200 ']]]);
checkReceiptNumber($sale->eis_invoice_number === 'EIS-200', 'Trim stored invoice number');
$html = renderNumberPartial($compiler, (object) ['invoice_no' => 'TXN-100', 'transaction_number' => 'TXN-100', 'eis_invoice_number' => 'EIS-200']);
checkReceiptNumber(str_contains($html, 'EIS Number') && str_contains($html, 'EIS-200'), 'Labeled EIS Number rendered');
$html = renderNumberPartial($compiler, (object) ['invoice_no' => '<script>bad</script>', 'eis_invoice_number' => '<img src=x>']);
checkReceiptNumber(! str_contains($html, '<script>') && ! str_contains($html, '<img src=x>')
    && str_contains($html, '&lt;img'), 'Both identifiers escaped');
checkReceiptNumber(trim(renderNumberPartial($compiler, (object) ['invoice_no' => 'TXN-100'])) === '', 'Non-EIS receipts unchanged');
foreach (['classic', 'columnize-taxes', 'detailed', 'elegant', 'elegant_modified', 'english-arabic', 'slim', 'slim2', 'packing_slip', 'delivery_note'] as $layout) {
    $source = file_get_contents($root.'/resources/views/sale_pos/receipts/'.$layout.'.blade.php');
    checkReceiptNumber(substr_count($source, "@include('sale_pos.receipts.partial.eis_invoice_number')") === 1, $layout.' includes number block once');
    checkReceiptNumber(str_contains($source, '$receipt_details->invoice_no'), $layout.' preserves original number');
    checkReceiptNumber($compiler->compileString($source) !== '', $layout.' compiles');
}
// Execute the actual ESC/POS number block against an in-memory printer.
$source = file_get_contents($root.'/pos_print_server/lib/Escpos.php');
$start = strpos($source, '        if (!empty($data->eis_invoice_number))');
$end = strpos($source, '        /* Customer info */', $start);
$block = substr($source, $start, $end - $start);
$harness = new class {
    public $printer;
    public function __construct() { $this->printer = new class {
        public string $output = '';
        public function text($text): void { $this->output .= $text; }
    }; }
};
$print = Closure::bind(function ($data) use ($block) { eval($block); }, $harness, get_class($harness));
$print((object) ['invoice_no' => 'TXN-100', 'eis_invoice_number' => 'EIS-200']);
checkReceiptNumber($harness->printer->output === "Transaction Number: TXN-100\nEIS Number: EIS-200\n", 'Raw thermal output prints both identities');
$harness->printer->output = '';
$print((object) ['invoice_no' => 'TXN-100']);
checkReceiptNumber($harness->printer->output === '', 'No invented raw thermal EIS identity');
$util = file_get_contents($root.'/app/Utils/TransactionUtil.php');
preg_match("/'header_text' => (.*),/", $util, $headerMatch);
foreach ([null, '', 'Configured header'] as $configuredHeader) {
    $is_eis_receipt = true;
    $il = (object) ['header_text' => $configuredHeader];
    $legalHeader = eval('return '.$headerMatch[1].';');
    checkReceiptNumber(str_starts_with(strip_tags($legalHeader), '****Start of Legal Receipt****'), 'Capitalized legal start marker present');
    checkReceiptNumber(str_contains($legalHeader, $configuredHeader ?? ''), 'Configured header preserved');
    checkReceiptNumber(str_contains(strip_tags($legalHeader), "****Start of Legal Receipt****\n"), 'Thermal marker has line break');
}
checkReceiptNumber(str_contains($util, '****End of Legal Receipt****'), 'Capitalized legal end marker retained');
$eligibilityStart = strpos($util, '        $is_eis_receipt =');
$eligibilityEnd = strpos($util, ';', $eligibilityStart);
$eligibilityBlock = substr($util, $eligibilityStart, $eligibilityEnd - $eligibilityStart + 1);
$footerStart = strpos($util, "        \$output['footer_text'] =");
$footerEnd = strpos($util, '        //Barcode related information.', $footerStart);
$footerBlock = substr($util, $footerStart, $footerEnd - $footerStart);
foreach (['due', 'partial', 'paid'] as $paymentStatus) {
    foreach ([null, new EisSale(), new EisSale(['request_payload' => ['invoiceHeader' => ['invoiceNumber' => 'EIS-200']]])] as $tracking) {
        $transaction = (object) ['payment_status' => $paymentStatus, 'eisSale' => $tracking];
        eval($eligibilityBlock);
        $expected = $paymentStatus === 'paid' && $tracking && $tracking->eis_invoice_number;
        $il = (object) ['header_text' => 'Configured header'];
        $invoice_layout = (object) ['footer_text' => 'Configured footer'];
        $legalHeader = eval('return '.$headerMatch[1].';');
        $output = [];
        eval($footerBlock);
        checkReceiptNumber(str_contains($legalHeader, '****Start of Legal Receipt****') === (bool) $expected, 'Legal start only on EIS receipts');
        checkReceiptNumber(str_contains($output['footer_text'], '****End of Legal Receipt****') === (bool) $expected, 'Legal end only on EIS receipts');
        if ($expected) {
            preg_match('/<span[^>]* style="([^"]+)">\*\*\*\*Start/', $legalHeader, $startStyle);
            preg_match('/<span[^>]* style="([^"]+)">\*\*\*\*End/', $output['footer_text'], $endStyle);
            checkReceiptNumber(isset($startStyle[1], $endStyle[1]) && $startStyle[1] === $endStyle[1]
                && str_contains($startStyle[1], 'font-size:12px;'), 'Legal markers use identical explicit font sizes');
        }
        checkReceiptNumber(str_contains($legalHeader, 'Configured header') && str_contains($output['footer_text'], 'Configured footer'), 'Custom receipt content preserved');
    }
}
checkReceiptNumber(str_contains($util, "\$output['transaction_number'] = \$transaction->invoice_no;")
    && str_contains($util, "? \$transaction->eisSale->eis_invoice_number : null;"), 'Receipt data wires distinct identities');
$start = strpos($util, "        \$output['eis_invoice_number'] =");
$end = strpos($util, ';', $start);
$numberBlock = substr($util, $start, $end - $start + 1);
foreach (['due', 'partial', 'paid'] as $paymentStatus) {
    $transaction = (object) ['payment_status' => $paymentStatus,
        'eisSale' => new EisSale(['request_payload' => ['invoiceHeader' => ['invoiceNumber' => 'EIS-200']]])];
    $output = [];
    eval($numberBlock);
    checkReceiptNumber($output['eis_invoice_number'] === ($paymentStatus === 'paid' ? 'EIS-200' : null), 'Receipt number restricted by payment status: '.$paymentStatus);
    $html = renderNumberPartial($compiler, (object) ($output + ['invoice_no' => 'TXN-100']));
    checkReceiptNumber(str_contains($html, 'EIS Number') === ($paymentStatus === 'paid'), 'EIS label hidden unless paid: '.$paymentStatus);
    $harness->printer->output = '';
    $print((object) ($output + ['invoice_no' => 'TXN-100']));
    checkReceiptNumber(str_contains($harness->printer->output, 'EIS Number') === ($paymentStatus === 'paid'), 'Thermal EIS number hidden unless paid: '.$paymentStatus);
}
$qrGuardStart = strpos($util, "        if (\$transaction->payment_status !== 'paid') {");
$qrGuardEnd = strpos($util, '        //Module related information.', $qrGuardStart);
$qrGuard = substr($util, $qrGuardStart, $qrGuardEnd - $qrGuardStart);
foreach (['due', 'partial', 'paid'] as $paymentStatus) {
    foreach ([false, true] as $qrEnabled) {
        $transaction = (object) ['payment_status' => $paymentStatus];
        $output = ['show_qr_code' => $qrEnabled, 'qr_code_text' => 'https://eis.invalid/receipt'];
        eval($qrGuard);
        checkReceiptNumber($output['show_qr_code'] === ($paymentStatus === 'paid' && $qrEnabled),
            'QR visibility respects payment status and layout setting: '.$paymentStatus);
        checkReceiptNumber($output['qr_code_text'] === ($paymentStatus === 'paid' ? 'https://eis.invalid/receipt' : null),
            'QR content omitted unless fully paid: '.$paymentStatus);
    }
}
echo "PASS: {$checks} EIS receipt number checks (no database or printer).\n";