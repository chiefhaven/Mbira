<?php

namespace Twf\Pps;

use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class Escpos
{
    public $printer;
    public $char_per_line = 42;

    public function load($printer)
    {
        if ($printer->connection_type == 'network') {
            set_time_limit(30);
            $connector = new NetworkPrintConnector(
                $printer->ip_address,
                $printer->port
            );
        } elseif ($printer->connection_type == 'linux') {
            $connector = new FilePrintConnector($printer->path);
        } else {
            $connector = new WindowsPrintConnector($printer->path);
        }

        $this->char_per_line = (int) $printer->char_per_line;

        $profile = CapabilityProfile::load(
            $printer->capability_profile
        );

        $this->printer = new Printer($connector, $profile);
    }

    public function print_invoice($data)
    {
        /* Logo */
        if (!empty($data->logo)) {

            $logo = $this->download_image($data->logo);

            if ($logo) {
                $this->printer->setJustification(
                    Printer::JUSTIFY_CENTER
                );

                $logo = EscposImage::load($logo, false);

                //$this->printer->graphics($logo);
                $this->printer->bitImage($logo);
            }
        }

        /* Header */
        $this->printer->setJustification(
            Printer::JUSTIFY_CENTER
        );

        $this->printer->setEmphasis(true);
        $this->printer->setTextSize(2, 2);

        if (!empty($data->header_text)) {
            $this->printer->text(
                strip_tags($data->header_text)
            );
            $this->printer->feed();
        }

        /* Shop name */
        if (!empty($data->display_name)) {
            $this->printer->text($data->display_name);
            $this->printer->feed();
        }

        /* Address */
        $this->printer->setTextSize(1, 1);

        if (!empty($data->address)) {
            $this->printer->text($data->address);
            $this->printer->feed(2);
        }

        /* Sub headings */
        for ($i = 1; $i <= 5; $i++) {

            $field = 'sub_heading_line' . $i;

            if (!empty($data->$field)) {
                $this->printer->text($data->$field);
                $this->printer->feed(1);
            }
        }

        /* Tax info */
        if (!empty($data->tax_info1)) {

            $this->printer->setEmphasis(true);
            $this->printer->text($data->tax_label1);
            $this->printer->setEmphasis(false);

            $this->printer->text($data->tax_info1);
            $this->printer->feed();
        }

        if (!empty($data->tax_info2)) {

            $this->printer->setEmphasis(true);
            $this->printer->text($data->tax_label2);
            $this->printer->setEmphasis(false);

            $this->printer->text($data->tax_info2);
            $this->printer->feed();
        }

        /* Invoice heading */
        if (!empty($data->invoice_heading)) {

            $this->printer->setEmphasis(true);
            $this->printer->text($data->invoice_heading);
            $this->printer->setEmphasis(false);

            $this->printer->feed(1);
        }

        $this->printer->setJustification(
            Printer::JUSTIFY_LEFT
        );

        /* Invoice info */
        $this->printer->feed(1);

        $invoice_no =
            $data->invoice_no_prefix . ' ' . $data->invoice_no;

        $date =
            $data->date_label . ' ' . $data->invoice_date;

        $this->printer->text(
            rtrim(
                $this->columnify(
                    $invoice_no,
                    $date,
                    50,
                    50,
                    0,
                    0
                )
            )
        );

        $this->printer->feed();

        if (!empty($data->eis_invoice_number)) {
            $this->printer->text(
                ($data->transaction_number_label ?? 'Transaction Number') . ': ' .
                ($data->transaction_number ?? $data->invoice_no) . "\n"
            );
            $this->printer->text(
                ($data->eis_invoice_number_label ?? 'EIS Number') . ': ' .
                $data->eis_invoice_number . "\n"
            );
        }

        /* Customer info */
        if (!empty($data->customer_info) ||
            !empty($data->client_id)) {

            $customer_info = '';

            if (!empty($data->customer_info)) {
                $customer_info =
                    $data->customer_label . ' ' .
                    $data->customer_info;
            }

            $client_id = '';

            if (!empty($data->client_id)) {
                $client_id =
                    $data->client_id_label . ' ' .
                    $data->client_id;
            }

            $this->printer->text(
                rtrim(
                    $this->columnify(
                        $customer_info,
                        $client_id,
                        50,
                        50,
                        0,
                        0
                    )
                )
            );

            $this->printer->feed();
        }

        /* Products */
        if (!empty($data->lines)) {

            $this->printer->text($this->drawLine());

            $string = $this->columnify(
                $this->columnify(
                    $this->columnify(
                        $data->table_qty_label,
                        ' ' . $data->table_product_label,
                        10,
                        40,
                        0,
                        0
                    ),
                    $data->table_unit_price_label,
                    50,
                    25,
                    0,
                    0
                ),
                ' ' . $data->table_subtotal_label,
                75,
                25,
                0,
                0
            );

            $this->printer->setEmphasis(true);
            $this->printer->text(rtrim($string));
            $this->printer->feed();
            $this->printer->setEmphasis(false);

            $this->printer->text($this->drawLine());

            foreach ($data->lines as $line) {

                $line = (array) $line;

                $product =
                    ($line['name'] ?? '') . ' ' .
                    ($line['variation'] ?? '');

                if (!empty($line['sell_line_note'])) {
                    $product .=
                        ' (' .
                        $line['sell_line_note'] .
                        ')';
                }

                if (!empty($line['sub_sku'])) {
                    $product .= ', ' . $line['sub_sku'];
                }

                if (!empty($line['brand'])) {
                    $product .= ', ' . $line['brand'];
                }

                if (!empty($line['cat_code'])) {
                    $product .= ', ' . $line['cat_code'];
                }

                $quantity = $line['quantity'] ?? '';
                $unit_price = $line['unit_price_exc_tax'] ?? '';
                $line_total = $line['line_total'] ?? '';

                $string = rtrim(
                    $this->columnify(
                        $this->columnify(
                            $this->columnify(
                                $quantity,
                                $product,
                                10,
                                40,
                                0,
                                0
                            ),
                            $unit_price,
                            50,
                            25,
                            0,
                            0
                        ),
                        $line_total,
                        75,
                        25,
                        0,
                        0
                    )
                );

                $this->printer->text($string);
                $this->printer->feed(2);
            }

            $this->printer->feed();
            $this->printer->text($this->drawLine());
        }

        /* Totals */
        $totals = [
            [
                'value' => $data->subtotal ?? null,
                'label' => $data->subtotal_label ?? '',
                'bold' => false
            ],
            [
                'value' => $data->discount ?? null,
                'label' => $data->discount_label ?? '',
                'bold' => false
            ],
            [
                'value' => $data->tax ?? null,
                'label' => $data->tax_label ?? '',
                'bold' => false
            ],
            [
                'value' => $data->total ?? null,
                'label' => $data->total_label ?? '',
                'bold' => true
            ]
        ];

        foreach ($totals as $row) {

            if (!empty($row['value']) &&
                $row['value'] != 0) {

                if ($row['bold']) {
                    $this->printer->setEmphasis(true);
                }

                $text = $this->columnify(
                    $row['label'],
                    $row['value'],
                    50,
                    50,
                    0,
                    0
                );

                $this->printer->text(rtrim($text));
                $this->printer->feed();

                if ($row['bold']) {
                    $this->printer->setEmphasis(false);
                }
            }
        }

        /* Payments */
        if (!empty($data->payments)) {

            $this->printer->setEmphasis(true);
            $this->printer->text(
                rtrim($data->total_paid_label)
            );
            $this->printer->feed();
            $this->printer->setEmphasis(false);

            foreach ($data->payments as $payment) {

                $total_paid = $this->columnify(
                    $payment->method,
                    $payment->amount,
                    50,
                    50,
                    0,
                    0
                );

                $this->printer->text(rtrim($total_paid));
                $this->printer->feed();
            }
        }

        if (!empty($data->total_due) &&
            $data->total_due != 0) {

            $total_due = $this->columnify(
                $data->total_due_label,
                $data->total_due,
                50,
                50,
                0,
                0
            );

            $this->printer->text(rtrim($total_due));
            $this->printer->feed();
        }

        $this->printer->text($this->drawLine());

        /* Taxes */
        if (!empty($data->taxes)) {

            $this->printer->setEmphasis(true);

            $this->printer->setJustification(
                Printer::JUSTIFY_CENTER
            );

            $this->printer->text(
                $data->tax_label . "\n"
            );

            $this->printer->setJustification(
                Printer::JUSTIFY_LEFT
            );

            $this->printer->setEmphasis(false);

            $this->printer->text($this->drawLine());

            foreach ($data->taxes as $key => $value) {

                $string = rtrim(
                    $this->columnify(
                        $key,
                        $value,
                        50,
                        45,
                        0,
                        0
                    )
                );

                $this->printer->text($string);
                $this->printer->feed(1);
            }
        }

        $this->printer->text($this->drawLine());

        /* Footer */
        if (!empty($data->footer_text)) {

            $this->printer->setJustification(
                Printer::JUSTIFY_CENTER
            );

            $this->printer->feed(1);

            $this->printer->text(
                strip_tags($data->footer_text) . "\n"
            );

            $this->printer->feed();
        }

        /* Finish */
        $this->printer->feed();
        $this->printer->cut();

        if (!empty($data->cash_drawer)) {
            $this->printer->pulse();
        }

        $this->printer->close();
    }

    public function open_drawer()
    {
        $this->printer->pulse();
        $this->printer->close();
    }

    function drawLine()
    {
        return str_repeat(
            '-',
            max(1, $this->char_per_line - 1)
        ) . "\n";
    }

    function printLine(
        $str,
        $size = null,
        $sep = ":",
        $space = null
    ) {

        if (!$size) {
            $size = $this->char_per_line;
        }

        $size = $space ? $space : $size;

        $length = strlen($str);

        list($first, $second) = explode(":", $str, 2);

        $line = $first . ($sep == ":" ? $sep : '');

        for ($i = 1; $i < ($size - $length); $i++) {
            $line .= ' ';
        }

        $line .=
            ($sep != ":" ? $sep : '') .
            $second;

        return $line;
    }

    function columnify(
        $leftCol,
        $rightCol,
        $leftWidthPercent,
        $rightWidthPercent,
        $space = 2,
        $remove_for_space = 0
    ) {

        $char_per_line =
            $this->char_per_line - $remove_for_space;

        $leftWidth = (int) (
            $char_per_line *
            $leftWidthPercent / 100
        );

        $rightWidth = (int) (
            $char_per_line *
            $rightWidthPercent / 100
        );

        $leftWrapped = wordwrap(
            (string) $leftCol,
            $leftWidth,
            "\n",
            true
        );

        $rightWrapped = wordwrap(
            (string) $rightCol,
            $rightWidth,
            "\n",
            true
        );

        $leftLines = explode("\n", $leftWrapped);
        $rightLines = explode("\n", $rightWrapped);

        $allLines = [];

        for (
            $i = 0;
            $i < max(count($leftLines), count($rightLines));
            $i++
        ) {

            $leftPart = str_pad(
                $leftLines[$i] ?? "",
                $leftWidth,
                " "
            );

            $rightPart = str_pad(
                $rightLines[$i] ?? "",
                $rightWidth,
                " "
            );

            $allLines[] =
                $leftPart .
                str_repeat(" ", $space) .
                $rightPart;
        }

        return implode("\n", $allLines) . "\n";
    }

    function download_image($url)
    {
        $file = basename($url);

        $logo_directory = realpath(
            dirname(__FILE__) . '/../logos/'
        );

        $logo_image =
            $logo_directory . '/' . $file;

        $success = true;

        if (!file_exists($logo_image)) {

            $image_content =
                @file_get_contents($url);

            if ($image_content !== false) {

                $success = file_put_contents(
                    $logo_image,
                    $image_content
                );
            } else {
                $success = false;
            }
        }

        return $success ? $logo_image : false;
    }
}