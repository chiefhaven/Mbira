<?php

// No application boot, database, credentials, external calls or real printing.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Transaction;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Modules\Connector\Transformers\SellResource;
use Modules\EIS\Models\EisSale;

$container = new Container();
Container::setInstance($container);
$urls = Mockery::mock();
$urls->shouldReceive('route')->andReturn('https://example.invalid/invoice');
$container->instance('url', $urls);
$request = Request::create('/connector/api/sell');
$checks = 0;

function checkConnectorReceipt(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
}

foreach (['paid', 'partial', 'due'] as $paymentStatus) {
    foreach (['000123-RECEIPT', null] as $receiptNumber) {
        $tracking = new EisSale([
            'receipt_number' => $receiptNumber,
            'qr_code' => 'https://example.invalid/verify',
            'request_payload' => ['invoiceHeader' => ['invoiceNumber' => 'EIS-INVOICE-DIFFERENT']],
        ]);
        $transaction = new Transaction();
        $transaction->setRawAttributes([
            'invoice_no' => 'POS-100', 'invoice_token' => 'fixture', 'is_quotation' => 0,
            'payment_status' => $paymentStatus, 'status' => 'final',
        ]);
        $transaction->setRelation('sell_lines', collect());
        $transaction->setRelation('eisSale', $tracking);
        $data = (new SellResource($transaction))->toArray($request);
        checkConnectorReceipt($data['eis_sale']['receipt_number'] === $receiptNumber, 'Connector preserves stored receipt number, including null');
        checkConnectorReceipt($data['eis_sale']['eis_invoice_number'] === 'EIS-INVOICE-DIFFERENT', 'Receipt and submitted invoice identities remain distinct');
        checkConnectorReceipt(! array_key_exists('request_payload', $data['eis_sale']), 'Fiscal payload remains private');
        checkConnectorReceipt($data['eis_sale']['qr_code'] === 'https://example.invalid/verify', 'QR preserved');
        checkConnectorReceipt($data['invoice_no'] === 'POS-100', 'POS invoice number preserved');
    }
}
$controllerSource = file_get_contents(dirname(__DIR__, 2).'/Modules/Connector/Http/Controllers/Api/SellController.php');
checkConnectorReceipt(substr_count($controllerSource, 'eisSale:id,transaction_id,receipt_number,qr_code,request_payload') === 2,
    'Sale list and detail eager-loading both include receipt_number');
Mockery::close();
echo "PASS: {$checks} Connector EIS receipt checks (no database or printer).\n";
