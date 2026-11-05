<?php

namespace Tests\Feature;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Modules\Repair\Entities\DeviceModel;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Modules\Repair\Utils\RepairUtil;
use Mpdf\Mpdf;
use Tests\TestCase;

class RepairLabelTest extends TestCase
{
    /**
     * @dataProvider customerNames
     */
    public function testCustomerLabelNameMatchesTheContactType(
        ?string $type,
        ?string $name,
        ?string $businessName,
        string $expected
    ): void {
        $customer = new Contact([
            'contact_type' => $type,
            'name' => $name,
            'supplier_business_name' => $businessName,
        ]);

        $this->assertSame($expected, (new RepairUtil())->getCustomerLabelName($customer));
    }

    public static function customerNames(): array
    {
        return [
            'individual' => ['individual', 'Alice Customer', null, 'Alice Customer'],
            'individual with company field' => ['individual', 'Alice Customer', 'Customer Company', 'Alice Customer'],
            'business with contact person' => ['business', 'Bob Contact', 'Customer Company', 'Customer Company'],
            'business without contact person' => ['business', '', 'Customer Company', 'Customer Company'],
            'business missing company name' => ['business', 'Bob Contact', '', 'Bob Contact'],
            'individual missing name' => ['individual', '', 'Customer Company', 'Customer Company'],
            'legacy business' => [null, 'Bob Contact', 'Customer Company', 'Customer Company'],
            'legacy individual' => [null, 'Alice Customer', null, 'Alice Customer'],
            'whitespace is trimmed' => ['business', ' Alice ', ' Customer Company ', 'Customer Company'],
        ];
    }

    /**
     * @dataProvider pdfCustomers
     */
    public function testJobSheetPdfContainsTheCustomerOnTheFirstLabel(
        array $attributes,
        string $expected,
        string $excluded
    ): void {
        $customer = new Contact($attributes);
        $html = $this->jobSheetLabelHtml($customer);

        $this->assertStringContainsString('Workshop Sample', $html);
        $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString($excluded, $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('SN-123456', $html);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertNotFalse($css);
        $pdfHtml = preg_replace_callback('/<link\b[^>]*>\s*/i', fn () => '<style>'.$css.'</style>', $html, 1, $replacedLinks);
        $this->assertSame(1, $replacedLinks);

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mbira-repair-label-'.bin2hex(random_bytes(8));
        try {
            $pdf = new Mpdf([
                'tempDir' => $tempDir,
                'format' => [75, 50],
                'mode' => 'utf-8',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'autoVietnamese' => true,
                'autoArabic' => true,
                'margin_top' => 4,
                'margin_left' => 4,
                'margin_right' => 4,
                'margin_bottom' => 4,
            ]);
            $pdf->useSubstitutions = true;
            $pdf->WriteHTML($pdfHtml);
            $pdfBytes = $pdf->Output('', 'S');

            $this->assertSame(1, $pdf->page);
            $this->assertStringStartsWith('%PDF-', $pdfBytes);
            $this->assertSame(1, preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $pdfBytes, $match));
            $firstPage = gzuncompress($match[1]);
            $this->assertNotFalse($firstPage);
            foreach (['Workshop Sample', 'JOB-123', $expected] as $text) {
                $this->assertStringContainsString(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8'), $firstPage);
            }
            $this->assertStringNotContainsString(mb_convert_encoding($excluded, 'UTF-16BE', 'UTF-8'), $firstPage);
        } finally {
            if (is_dir($tempDir)) {
                (new Filesystem())->deleteDirectory($tempDir);
            }
        }
    }

    public static function pdfCustomers(): array
    {
        return [
            'individual' => [
                ['contact_type' => 'individual', 'name' => 'Alice Customer', 'supplier_business_name' => 'Customer Company'],
                'Alice Customer',
                'Customer Company',
            ],
            'business' => [
                ['contact_type' => 'business', 'name' => 'Bob Contact', 'supplier_business_name' => 'Customer Company'],
                'Customer Company',
                'Bob Contact',
            ],
        ];
    }

    public function testJobSheetLabelEscapesCustomerNames(): void
    {
        $customer = new Contact([
            'contact_type' => 'individual',
            'name' => 'Alice & <Guest>',
        ]);

        $html = $this->jobSheetLabelHtml($customer);

        $this->assertStringContainsString('Alice &amp; &lt;Guest&gt;', $html);
        $this->assertStringNotContainsString('Alice & <Guest>', $html);
    }

    public function testPrintableRepairLabelUsesTheSameCustomerBusinessName(): void
    {
        session()->start();
        $customer = new Contact([
            'contact_type' => 'business',
            'name' => 'Bob Contact',
            'supplier_business_name' => 'Customer Company',
        ]);
        $transaction = new Transaction(['invoice_no' => 'INV-123', 'repair_model' => 'Model-100']);
        $transaction->id = 123;
        $transaction->setRelation('contact', $customer);
        $product_details = [['details' => $transaction, 'qty' => 1]];
        $business_name = 'Workshop Sample';
        $customer_label_name = (new RepairUtil())->getCustomerLabelName($customer);
        $barcode_details = (object) [
            'is_continuous' => true,
            'height' => 1,
            'width' => 2,
            'paper_width' => 2,
            'stickers_in_one_row' => 1,
        ];
        $barcode_type = 'C128';

        $path = view()->getFinder()->find('repair::repair.partials.preview_label');
        $source = file_get_contents($path);
        $this->assertStringContainsString("@extends('layouts.app')", $source);
        $source = str_replace("@extends('layouts.app')", '', $source);
        $html = Blade::render(
            $source."\n@yield('content')",
            compact('product_details', 'business_name', 'customer_label_name', 'barcode_details', 'barcode_type'),
            true
        );

        $this->assertStringContainsString('Workshop Sample', $html);
        $this->assertStringContainsString('Customer Company', $html);
        $this->assertStringNotContainsString('Bob Contact', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function testSettingsShowCustomerNameIsRequiredWithoutAHideCheckbox(): void
    {
        session()->start();
        session(['business.custom_labels' => '{}']);
        $html = view('repair::settings.partials.jobsheet_settings_tab', ['jobsheet_pdf_settings' => []])->render();

        $this->assertStringNotContainsString('name="show_customer_name_in_label"', $html);
        $this->assertStringContainsString(__('sale.customer_name').' / '.__('business.business_name'), $html);
        $this->assertStringContainsString(__('lang_v1.required'), $html);
        $this->assertStringContainsString('name="show_customer_address_in_label"', $html);
    }

    private function jobSheetLabelHtml(Contact $customer): string
    {
        session()->start();
        session(['business.custom_labels' => '{}']);
        $customer->setRelation('business', new Business(['name' => 'Workshop Sample']));

        $job_sheet = new JobSheet(['job_sheet_no' => 'JOB-123', 'serial_no' => 'SN-123456']);
        $job_sheet->setRelation('customer', $customer);
        $job_sheet->setRelation('businessLocation', new BusinessLocation(['name' => 'Workshop Sample']));
        $job_sheet->setRelation('brand', new Brands(['name' => 'DeviceBrand']));
        $job_sheet->setRelation('deviceModel', new DeviceModel(['name' => 'Model-100']));
        $job_sheet->setRelation('status', new RepairStatus(['name' => 'Pending']));
        $jobsheet_settings = [
            'show_customer_name_in_label' => 0,
            'show_barcode_in_label' => 1,
            'show_brand_in_label' => 1,
            'show_status_in_label' => 1,
            'show_sr_no_in_label' => 1,
        ];
        $customer_label_name = (new RepairUtil())->getCustomerLabelName($customer);

        return view('repair::job_sheet.print_label', compact('job_sheet', 'jobsheet_settings', 'customer_label_name'))->render();
    }
}
