<!-- Header Section -->
<div class="row">
    <div class="row">
        <div class="col-xs-6">
            @if(!empty($receipt_details->letter_head))
                <img style="max-height: 80px; width: auto;" src="{{$receipt_details->letter_head}}" class="img img-responsive center-block">
            @elseif(!empty($receipt_details->logo))
                <img style="max-height: 80px; width: auto;" src="{{$receipt_details->logo}}" class="img img-responsive center-block">
            @endif
        </div>
        <div class="col-xs-6 text-center">
            @if(!empty($receipt_details->header_text))
                <div>{!! $receipt_details->header_text !!}</div>
            @endif
            @if(!empty($receipt_details->address))
                <div><b>{{$receipt_details->display_name}}</b>
				{!! $receipt_details->customer_info_address !!}
			</div>
            @endif
            @if(!empty($receipt_details->contact))
                <div >{{$receipt_details->contact}}</div>
            @endif
        </div>
    </div>
</div>
<!-- Invoice Title and Number -->
<div class="row" style="text-align: center;">
    <div class="col-xs-6">
        <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 10px;">
            <div style="font-size: 18px; font-weight: bold;">
                    SIMPLIFIED TAX INVOICE<br>
                    <span style="direction: rtl;">فاتورة ضريبية مبسطة</span>
            </div>
        </div>
    </div>
    <div class="col-xs-6">
        <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 19px;">
            <div class="print-red" style="font-size: 18px; font-weight: bold; ">
                {{$receipt_details->invoice_no}}
                @include('sale_pos.receipts.partial.eis_invoice_number')
            </div>
        </div>
    </div>
</div>

<!-- Customer Information Section -->
<div class="row">
    <div class="col-xs-12">
        <table class="table customer-info-table" style="width: 98%; font-size: 12px;">
            <tbody>
                <tr style="background: #2d2360;">
                    <td style="padding: 5px; font-weight: bold; width: 15%; text-align: right !important;">CUS ID:</td>
                    <td class="print-red" style="padding: 5px; width: 15%;">
                        @if(!empty($receipt_details->contact_id))
                            {{$receipt_details->contact_id}}
                        @endif
                    </td>
                    <td style="padding: 5px; font-weight: bold; width: 15%;  direction: rtl; text-align: left !important;">رقم العميل:</td>
                    <td style="padding: 5px; font-weight: bold; width: 20%;text-align: right !important;">INVOICE TIMESTAMP:</td>
                    <td class="print-red" style="padding: 5px; width: 18%;">{{$receipt_details->invoice_date}}</td>
                    <td style="padding: 5px; font-weight: bold; width: 20%;  direction: rtl; text-align: left !important;">وقت الفاتورة:</td>
                </tr>
                <tr style="background: #2d2360;">
                    <td style="padding: 5px; font-weight: bold;text-align: right !important;">CUS NAME:</td>
                    <td class="print-red" style="padding: 5px;">
                        @if(!empty($receipt_details->contact_name))
                            {!! strip_tags($receipt_details->contact_name) !!}
                        @endif
                    </td>
                    <td style="padding: 5px; font-weight: bold;  direction: rtl; text-align: left !important;">اسم العميل:</td>
                    <td style="padding: 5px; font-weight: bold;text-align: right !important;">DUE DATE:</td>
                    <td class="print-red" style="padding: 5px;">
                        @if(!empty($receipt_details->due_date))
                            {{$receipt_details->due_date}}
                        @endif
                    </td>
                    <td style="padding: 5px; font-weight: bold;  direction: rtl; text-align: left !important;">تاريخ الاستحقاق:</td>
                </tr>
                <tr style="background: #2d2360;">
                    <td style="padding: 5px; font-weight: bold;text-align: right !important;">CUS CR:</td>
                    <td class="print-red" style="padding: 5px;">
                        @if(!empty($receipt_details->sell_custom_field_1_value))
                            {{$receipt_details->sell_custom_field_1_value}}
                        @endif
                    </td>
                    <td style="padding: 5px; font-weight: bold;  direction: rtl; text-align: right; text-align: left !important;">سجل العميل:</td>
                    <td style="padding: 5px; font-weight: bold;text-align: right !important;">SALES ORDER ID:</td>
                    <td style="padding: 5px;">
                        <!-- now empty -->
                    </td>
                    <td style="padding: 5px; font-weight: bold;  direction: rtl; text-align: left !important;">رقم طلب البيع:</td>
                </tr>
                <tr style="background: #2d2360;">
                    <td style="padding: 5px; font-weight: bold;text-align: right !important;">VAT ID:</td>
                    <td class="print-red" style="padding: 5px;">
                        @if(!empty($receipt_details->tax_info1))
                            {{$receipt_details->tax_info1}}
                        @endif
                    </td>
                    <td style="padding: 5px; font-weight: bold;  direction: rtl; text-align: left !important;">الرقم الضريبي:</td>
                    <td style="padding: 5px;" colspan="3"></td>
                </tr>
                <tr style="background: #2d2360;">
                    <td style="padding: 5px; font-weight: bold;text-align: right !important;">CUS ADRS:</td>
                    <td class="print-red" style="padding: 5px;" colspan="4">
                        @if(!empty($receipt_details->customer_info))
                            {!! str_replace('<br>', ', ', $receipt_details->customer_info) !!}
                        @endif
                    </td>
                    <td style="padding: 5px; font-weight: bold;  direction: rtl; text-align: left !important;">عنوان العميل:</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div style=" margin-bottom: -15px;">
    <span>User :</span>
    <span class="print-red" style="color: #c00 !important;">{{ $receipt_details->added_by }}</span>
    <span style="direction: rtl; text-align: right;">معرف المستخدم:</span>
</div>
<!-- Products Table -->
<div class="row">
    <div class="col-xs-12">
        <br/>
        <table class="table table-sm product-table" style="width: 98%; font-size: 12px;">
            <thead class="product-table-header">
                <tr>
                    <th style=" text-align: center; font-weight: bold;">
                        PROD ID<br><span style="direction: rtl;">معرف المنتج</span>
                    </th>
                    <th style=" text-align: center; font-weight: bold;">
                        PRODUCT NAME<br><span style="direction: rtl;">اسم المنتج</span>
                    </th>
                    <th style=" text-align: center; font-weight: bold;">
                        QUANTITY<br><span style="direction: rtl;">الكمية</span>
                    </th>
                    <th style=" text-align: center; font-weight: bold;">
                        U.P<br><span style="direction: rtl;">سعر الوحدة</span>
                    </th>
                    <th style=" text-align: center; font-weight: bold;">
                        TOTAL<br><span style="direction: rtl;">الإجمالي</span>
                    </th>
                    <th style=" text-align: center; font-weight: bold;">
                        TAX<br><span style="direction: rtl;">الضريبة</span>
                    </th>
                    <th style="text-align: center; font-weight: bold;">
                        SUBTOTAL<br><span style="direction: rtl;">المجموع الفرعي</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt_details->lines as $line)
                <tr>
                    <td class="print-red" style="text-align: center;">
                        @if(!empty($line['sub_sku']))
                            {{$line['sub_sku']}}
                        @endif
                    </td>
                    <td class="print-red" style=" text-align: left;">
                        {{$line['name']}} {{$line['product_variation']}} {{$line['variation']}}
                        @if(!empty($line['sub_sku'])), {{$line['sub_sku']}} @endif 
                        @if(!empty($line['brand'])), {{$line['brand']}} @endif 
                        @if(!empty($line['cat_code'])), {{$line['cat_code']}}@endif
                        @if(!empty($line['product_custom_fields'])), {{$line['product_custom_fields']}} @endif
                        @if(!empty($line['product_description']))
                            <br><small>{!!$line['product_description']!!}</small>
                        @endif
                        @if(!empty($line['sell_line_note']))
                            <br><small>{!!$line['sell_line_note']!!}</small>
                        @endif
                    </td>
                    <td class="print-red" style="text-align: center;">{{$line['quantity']}} {{$line['units']}}</td>
                    <td class="print-red" style=" text-align: center;">{{$line['unit_price_before_discount']}}</td>
                    <td class="print-red" style=" text-align: center;">{{$line['line_total_exc_tax']}}</td>
                    <td class="print-red" style=" text-align: center;">
                        @if(!empty($line['tax']))
                            {{$line['tax'] * $line['quantity']}}
                        @endif
                    </td>
                    <td class="print-red" style="text-align: center;">{{$line['line_total']}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- TOTAL QUANTITY & TOTAL ITEMS Row -->
<div class="row">
    <div class="col-xs-12" style="font-size: 12px;">
        <strong>TOTAL QUANTITY:</strong> 
        <span class="print-red" >
            @if(!empty($receipt_details->total_quantity))
                {{$receipt_details->total_quantity}}
            @endif
        </span>
        &nbsp;&nbsp;&nbsp;
        <strong>TOTAL ITEMS:</strong> 
        <span class="print-red" >
				{{ count($receipt_details->lines) }}
        </span>
    </div>
</div>

<!-- Totals Section -->
<div class="row">
    <div class="col-xs-12">
        <table style="width:98%; border-collapse: collapse;">
            <tr>
                <td style="width:40%; vertical-align:top;">
                    <!-- Left side: notes, payment reference, etc. -->
                    <div style="font-size: 12px;">
                        <div>
                            <strong >Sell Note</strong><br>
							@if(!empty($receipt_details->additional_notes))
								<div class="print-red">
									{!! $receipt_details->additional_notes !!}
								</div>
							@endif
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <strong>PAYMENT REFERENCE:</strong>
                            <span >
                                @if(!empty($receipt_details->payments))
                                    @foreach($receipt_details->payments as $payment)
                                        {{$payment['method']}}: {{$payment['amount']}}
                                    @endforeach
                                @endif
                            </span>
                        </div>
                        
                        <div>
                            <strong>AMOUNT IN WORDS</strong>
							<br>
                        <span>
                            @if(!empty($receipt_details->total_unformatted))
                                {{ app(\App\Utils\TransactionUtil::class)->numberToCurrencyWords($receipt_details->total_unformatted, 'riyal', 'halala', 'en') }}
                            @endif
                        </span>
                        <br>
                        <span>
                            @if(!empty($receipt_details->total_unformatted))
							{{ app(\App\Utils\TransactionUtil::class)->numberToCurrencyWords($receipt_details->total_unformatted, 'ريالًا و', ' هللة فقط', 'ar'); }}
                            @endif
                        </span>
                        </div>
                    </div>
                </td>
                <td>
                    <!-- Right side: totals, tax, etc. -->
                    <div class="table-responsive totals-section">
                        <table class="table table-bordered" style="width: 98%; font-size: 11px; margin-bottom: 2px;">
                            <tbody>
                                <tr>
                                    <td  style="font-weight: bold; text-align: right; width: 30%;">Gross:</td>
                                    <td class="print-red" style="text-align: center; width: 25%;">
                                        @if(!empty($receipt_details->subtotal))
                                            {{$receipt_details->subtotal}}
                                        @endif
                                    </td>
                                    <td style="font-weight: bold; direction: rtl; text-align: right; width: 45%;">المبلغ الكلي:</td>
                                </tr>
                                
                                <tr>
                                    <td style="font-weight: bold; text-align: right;">Discount:</td>
                                    <td class="print-red" style="text-align: center;">
                                        @if(!empty($receipt_details->discount))
                                            {{$receipt_details->discount}}
										@else
											@format_currency(0)
                                        @endif
                                    </td>
                                    <td style="font-weight: bold; direction: rtl; text-align: right;">الخصم:</td>
                                </tr>
                                
                                <tr>
                                    <td style="font-weight: bold; text-align: right;">After Discount:</td>
                                    <td class="print-red" style="text-align: center;">
										@format_currency($receipt_details->subtotal_unformatted - $receipt_details->discount_amount_unformatted)
                                    </td>
                                    <td style="font-weight: bold; direction: rtl; text-align: right;">المبلغ بعد الخصم:</td>
                                </tr>
                            
								@php
                                    // Calculate total VAT (sum of all line taxes)
                                    $total_vat = 0;
                                    if (!empty($receipt_details->lines)) {
                                        foreach ($receipt_details->lines as $line) {
                                            if (!empty($line['tax']) && !empty($line['quantity'])) {
                                                $total_vat += $line['tax'] * $line['quantity'];
                                            }
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td style="font-weight: bold; text-align: right;">Total VAT:</td>
                                    <td class="print-red" style="text-align: center;">
                                        @format_currency($total_vat ?? 0)
                                    </td>
                                    <td style="font-weight: bold; direction: rtl; text-align: right;">إجمالي ضريبة القيمة المضافة:</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; text-align: right;">Order Tax:</td>
                                    <td class="print-red" style=" text-align: center;">
										@if(!empty($receipt_details->tax))
                                            {{ $receipt_details->tax }} 
										@else
											@format_currency(0)
										@endif
                                    </td>
                                    <td style="font-weight: bold; direction: rtl; text-align: right;">ضريبة الطلب:</td>
                                </tr>
								<tr class="bg-net-amount">
                                    <td style=" font-weight: bold; text-align: right;">
                                        NET AMOUNT
                                    </td>
                                    <td style=" text-align: center;">
                                        {{ $receipt_details->total }}
                                    </td>
                                    <td style=" font-weight: bold; direction: rtl; text-align: right;">
                                        المبلغ الصافي (مجموع كل شيء):
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td style=" font-weight: bold; text-align: right;">Paid Amount:</td>
                                    <td class="print-red" style=" text-align: center;">
                                        @if(!empty($receipt_details->total_paid))
                                            {{$receipt_details->total_paid}}
                                        @endif
                                    </td>
                                    <td style=" font-weight: bold; direction: rtl; text-align: right;">المبلغ المدفوع:</td>
                                </tr>
                                
                                <tr>
                                    <td style=" font-weight: bold; text-align: right;">Due Amount:</td>
                                    <td class="print-red" style="text-align: center;">
                                        @if(!empty($receipt_details->total_due))
                                            {{$receipt_details->total_due}}
										@else
											@format_currency(0)
                                        @endif
                                    </td>
                                    <td style="font-weight: bold; direction: rtl; text-align: right;">المبلغ المستحق:</td>
                                </tr>
                               
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
<div class="row avoid-page-break">
<hr>
    <div class="col-xs-9">
        <div >
            EXPLANATION VAT NOT CHARGED.<br>
            EXPLANATION VAT % CHARGED.<br>
            GOODS RETURN IN 100%, WITHIN 03DAYS FROM INVOICE DATE.<br>
            *** COMPUTER GENERATED INVOICE. NOSIGNATURE OR STAMP REQUIRED. ***
        </div>
        <div style="text-align: right;">
            شرح لماذا لم يتم تحصيل ضريبة القيمة المضافة.<br>
            شرح نسبة ضريبة القيمة المضافة المحصلة.<br>
            استرجاع البضائع بنسبة 100% خلال 3 أيام من تاريخ الفاتورة.<br>
            *** فاتورة مولدة إلكترونياً لا حاجة لتوقيع أو ختم ***
        </div>
    </div>
    <div class="col-xs-3 text-center">
        @if($receipt_details->show_qr_code && !empty($receipt_details->qr_code_text))
            <img class="center-block" style="margin: 0 auto; width: 180px; height: 180px;" src="data:image/png;base64,{{DNS2D::getBarcodePNG($receipt_details->qr_code_text, 'QRCODE', 4, 4)}}" alt="QR Code">
        @endif
    </div>
</div>

<style>
@media print {
    .avoid-page-break {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .totals-section .table,
    .totals-section .table-bordered,
    .totals-section .table > tbody > tr > td,
    .totals-section .table > tbody > tr > th {
        border: none !important;
    }
    .totals-section .table > tbody > tr > td {
        padding: 2px 4px !important;
        font-size: 11px !important;
    }
    .totals-section .table {
        margin-bottom: 2px !important;
    }
    .customer-info-table,
    .customer-info-table td,
    .customer-info-table th,
    .customer-info-table tr {
        border: none !important;
    }
    .customer-info-table td {
        background: #EAE7F2 !important;
    }
    .customer-info-table tr:first-child td {
        font-weight: bold;
    }
    .product-table-header th {
        background: #2d2360 !important;
        color: #fff !important;
        border-bottom: none !important;
    }
    .product-table-header th:first-child {
        border-top-left-radius: 12px !important;
    }
	.product-table-header th:last-child {
        border-top-right-radius: 12px !important;
    }
    .product-table-header th span[style*="direction: rtl"] {
        color: #fff !important;
    }
    .print-red {
        color: #c00 !important;
    }

   
    /* .bg-net-amount:first-child {
        border-top-left-radius: 12px !important;
        border-bottom-left-radius: 12px !important;
    }
    .bg-net-amount:last-child {
        border-top-right-radius: 12px !important;
        border-bottom-right-radius: 12px !important;
    }
     */
   
    .customer-info-table {
        border-radius: 12px !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        overflow: hidden;
        border: 1px solid #EAE7F2 !important;
    }
    .product-table td {
        border: 1px solid #2d2360 !important;
    }
    .product-table tbody tr:not(:last-child) td {
        border-bottom: none !important;
    }
    .product-table tbody tr:not(:first-child) td {
        border-top: none !important;
    }
    .bg-net-amount td {
        background: #2d2360 !important;
        color: #fff !important;
        border: none !important;
        /* Remove all inner borders */
    }
    tr.bg-net-amount td:first-child {
        border-top-left-radius: 12px !important;
        border-bottom-left-radius: 12px !important;
    }
    tr.bg-net-amount td:last-child {
        border-top-right-radius: 12px !important;
        border-bottom-right-radius: 12px !important;
    }
    /* Add a border to the whole row using a box-shadow trick */
    tr.bg-net-amount td {
        box-shadow: 0 0 0 1.5px #2d2360;
    }
}
</style>
