@extends('layouts.app')

@php
    $title = __('lang_v1.split_bill');
@endphp

@section('title', $title)

@section('content')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $title }}</h1>
</section>

<!-- Main content -->
<section class="content no-print">
    <div class="row">
        <div class="col-md-12">
            <!-- Split Bill Modal - Compact for POS -->
            <div class="modal fade" id="splitBillModal" tabindex="-1" role="dialog" aria-labelledby="splitBillModalLabel">
                <div class="modal-dialog modal-lg" role="document" style="max-width: 800px;">
                    <div class="modal-content">
                        <div class="modal-header" style="padding: 8px 15px;">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <h4 class="modal-title" id="splitBillModalLabel" style="font-size: 16px;">
                                <i class="fa fa-cut"></i> @lang('lang_v1.split_bill')
                            </h4>
                        </div>
                        
                        <div class="modal-body" style="padding: 10px 15px;">
                            <!-- Transaction Info - Compact -->
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-12">
                                    <div class="box box-solid" style="margin-bottom: 0;">
                                        <div class="box-body" style="padding: 8px 12px;">
                                            <div class="row" style="font-size: 13px;">
                                                <div class="col-md-3">
                                                    <strong>@lang('sale.invoice_no'):</strong> {{ $sell->invoice_no }}
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>@lang('sale.date'):</strong> {{ @format_datetime($sell->transaction_date) }}
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>@lang('contact.customer'):</strong> {{ $sell->contact->name }}
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>@lang('business.location'):</strong> {{ $sell->location->name }}
                                                </div>
                                            </div>
                                            <div class="row" style="font-size: 13px;">
                                                <div class="col-md-3">
                                                    <strong>@lang('sale.final_total'):</strong> @format_currency($sell->final_total)
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>@lang('sale.payment_status'):</strong> 
                                                    <span class="label {{ $sell->payment_status == 'paid' ? 'bg-green' : ($sell->payment_status == 'partial' ? 'bg-yellow' : 'bg-red') }}" style="font-size: 11px;">
                                                        {{ __('lang_v1.' . $sell->payment_status) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Products Table - Compact -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="box box-solid" style="margin-bottom: 8px;">
                                        <div class="box-header with-border" style="padding: 6px 12px;">
                                            <h3 class="box-title" style="font-size: 14px;">@lang('sale.select_products_to_split')</h3>
                                        </div>
                                        <div class="box-body" style="padding: 5px 10px;">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped" id="split-bill-table" style="font-size: 12px; margin-bottom: 0;">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" style="width: 30px; padding: 4px;">#</th>
                                                            <th style="padding: 4px;">@lang('product.product_name')</th>
                                                            <th style="padding: 4px;">@lang('lang_v1.variation')</th>
                                                            <th class="text-center" style="width: 110px; padding: 4px;">@lang('sale.qty')</th>
                                                            <th class="text-right" style="width: 70px; padding: 4px;">@lang('sale.price')</th>
                                                            <th class="text-right" style="width: 80px; padding: 4px;">@lang('sale.subtotal')</th>
                                                            <th class="text-center" style="width: 30px; padding: 4px;">
                                                                <input type="checkbox" id="select-all-lines" style="margin: 0;">
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($sell->sell_lines as $index => $line)
                                                        @php
                                                            $allow_decimal_split = !empty($line->product->unit) && $line->product->unit->allow_decimal;
                                                        @endphp
                                                        <tr>
                                                            <td class="text-center" style="padding: 3px;">{{ $index + 1 }}</td>
                                                            <td style="padding: 3px;">{{ $line->product->name }}</td>
                                                            <td style="padding: 3px;">
                                                                @if(!empty($line->variations->name) && $line->variations->name != 'DUMMY')
                                                                    {{ $line->variations->name }}
                                                                @endif
                                                            </td>
                                                            <td class="text-center" style="padding: 3px;">
                                                                <div class="input-group input-group-sm" style="width: 100px; margin: 0 auto;">
                                                                    <input type="number" class="form-control input-sm split-quantity" 
                                                                        value="{{ $line->quantity }}" 
                                                                        min="{{ $allow_decimal_split ? '0.0001' : '1' }}"
                                                                        step="{{ $allow_decimal_split ? '0.0001' : '1' }}"
                                                                        max="{{ $line->quantity }}"
                                                                        data-line-id="{{ $line->id }}"
                                                                        data-max-qty="{{ $line->quantity }}"
                                                                        data-allow-decimal="{{ $allow_decimal_split ? '1' : '0' }}"
                                                                        style="padding: 2px 4px; font-size: 12px; height: 24px;">
                                                                    <span class="input-group-addon" style="padding: 2px 6px; font-size: 11px;">/ {{ @format_quantity($line->quantity) }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="text-right" style="padding: 3px;">@format_currency($line->unit_price_inc_tax)</td>
                                                            <td class="text-right" style="padding: 3px;">@format_currency($line->unit_price_inc_tax * $line->quantity)</td>
                                                            <td class="text-center" style="padding: 3px;">
                                                                <input type="checkbox" class="split-line-checkbox" 
                                                                       value="{{ $line->id }}" 
                                                                       data-price="{{ $line->unit_price_inc_tax }}"
                                                                       data-quantity="{{ $line->quantity }}"
                                                                       data-total="{{ $line->unit_price_inc_tax * $line->quantity }}"
                                                                       style="margin: 0;">
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="5" class="text-right" style="padding: 4px; font-size: 12px;">
                                                                <strong>@lang('sale.selected_total')</strong>
                                                            </th>
                                                            <th id="selected-total" class="text-right" style="padding: 4px; font-size: 12px;">@format_currency(0)</th>
                                                            <th style="padding: 4px;"></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Split Form - Compact -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="box box-solid" style="margin-bottom: 0;">
                                        <div class="box-body" style="padding: 8px 12px;">
                                            <input type="hidden" id="split_expected_version" value="{{ $split_preview['expected_version'] }}">
                                            <input type="hidden" id="split_operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <p class="text-muted" style="margin-bottom: 8px;">@lang('lang_v1.split_bill_new_invoice_note')</p>
                                            
                                            <!-- Split Amount Display - Compact -->
                                            <div class="row">
                                                <div class="col-md-12 text-center">
                                                    <div class="form-group" style="margin-bottom: 5px;">
                                                        <label class="text-primary" style="font-size: 14px; margin-bottom: 2px;">@lang('sale.split_amount')</label>
                                                        <h3 id="split-amount-display" class="text-primary" style="font-size: 24px; font-weight: bold; margin: 2px 0;">@format_currency(0)</h3>
                                                        <input type="hidden" id="split-amount" value="0">
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer" style="padding: 8px 15px;">
                            <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">
                                <i class="fa fa-times"></i> @lang('messages.cancel')
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="confirm-split-btn">
                                <i class="fa fa-cut"></i> @lang('lang_v1.split_bill')
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    // Initialize modal
    $('#splitBillModal').modal({
        backdrop: 'static',
        keyboard: false
    });

    // Store translation strings
    var langQuantityExceedsAvailable = '{{ __("lang_v1.quantity_exceeds_available", ["available" => ""]) }}';
    var langQuantityMustBeGreaterThanZero = '{{ __("lang_v1.quantity_must_be_greater_than_zero") }}';
    var langSelectAtLeastOneProduct = '{{ __("lang_v1.select_at_least_one_product") }}';
    var langProcessing = '{{ __("messages.processing") }}';
    var langSomethingWentWrong = '{{ __("messages.something_went_wrong") }}';
    var langCannotSplitAllItems = '{{ __("lang_v1.cannot_split_all_items") }}';
    var langSplitSuccess = '{{ __("lang_v1.split_success") }}';

    // Select all checkbox
    $('#select-all-lines').change(function() {
        $('.split-line-checkbox').prop('checked', $(this).prop('checked'));
        calculateTotal();
    });

    // Calculate total on checkbox change
    $('.split-line-checkbox').change(function() {
        calculateTotal();
    });

    // Calculate total on quantity change
    $(document).on('change', '.split-quantity', function() {
        var maxQty = parseFloat($(this).data('max-qty'));
        var val = parseFloat($(this).val()) || 0;
        var minQty = parseFloat($(this).attr('min')) || 1;
        
        if (val < minQty) {
            $(this).val(minQty);
        } else if (val > maxQty) {
            $(this).val(maxQty);
            toastr.warning(langQuantityExceedsAvailable.replace(':available', maxQty));
        }
        
        calculateTotal();
    });

    // Calculate selected total with quantities using price_inc_tax
    function calculateTotal() {
        var total = 0;
        $('.split-line-checkbox:checked').each(function() {
            var row = $(this).closest('tr');
            var quantity = parseFloat(row.find('.split-quantity').val()) || 0;
            var price = parseFloat($(this).data('price'));
            total += price * quantity;
        });
        var currencySymbol = '{{ $sell->location->currency->symbol ?? 'K' }}';
        $('#selected-total').text(currencySymbol + ' ' + total.toFixed(2));
        $('#split-amount-display').text(currencySymbol + ' ' + total.toFixed(2));
        $('#split-amount').val(total);
    }

    // Confirm split
    $('#confirm-split-btn').click(function() {
        var selectedLines = [];
        var hasInvalidQuantity = false;
        var totalRemainingItems = 0;
        
        // Check all lines for remaining items
        $('.split-line-checkbox').each(function() {
            var row = $(this).closest('tr');
            var maxQty = parseFloat(row.find('.split-quantity').data('max-qty'));
            
            if ($(this).is(':checked')) {
                var quantityInput = row.find('.split-quantity');
                var quantity = String(quantityInput.val()).trim();
                var quantityValue = parseFloat(quantity) || 0;
                var lineId = $(this).val();
                
                if (/^\.\d+$/.test(quantity)) {
                    quantity = '0' + quantity;
                    quantityInput.val(quantity);
                } else if (/^\d+\.$/.test(quantity)) {
                    quantity += '0';
                    quantityInput.val(quantity);
                }

                if (!/^\d+(?:\.\d{1,4})?$/.test(quantity) ||
                    (quantityInput.data('allow-decimal') != 1 && quantityValue % 1 !== 0)) {
                    toastr.error(langQuantityMustBeGreaterThanZero);
                    hasInvalidQuantity = true;
                    return false;
                }

                if (quantityValue <= 0) {
                    toastr.error(langQuantityMustBeGreaterThanZero);
                    hasInvalidQuantity = true;
                    return false;
                }
                
                if (quantityValue > maxQty) {
                    toastr.error(langQuantityExceedsAvailable.replace(':available', maxQty));
                    hasInvalidQuantity = true;
                    return false;
                }
                
                selectedLines.push({
                    id: lineId,
                    quantity: quantity
                });
                
                // Calculate remaining items for selected lines
                totalRemainingItems += (maxQty - quantityValue);
            } else {
                // Items not selected remain
                totalRemainingItems += maxQty;
            }
        });

        if (hasInvalidQuantity) {
            return false;
        }

        // Check if all items are being moved (no items remaining)
        if (totalRemainingItems === 0) {
            toastr.error(langCannotSplitAllItems);
            return false;
        }

        if (selectedLines.length === 0) {
            toastr.error(langSelectAtLeastOneProduct);
            return false;
        }

        var data = {
            transaction_id: {{ $sell->id }},
            operation_id: $('#split_operation_id').val(),
            expected_version: $('#split_expected_version').val(),
            lines: selectedLines,
            _token: '{{ csrf_token() }}'
        };

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + langProcessing);

        $.ajax({
            url: '{{ action([\App\Http\Controllers\SellController::class, "processSplitBill"]) }}',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.msg || langSplitSuccess);
                    setTimeout(function() {
                        $('#splitBillModal').modal('hide');
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    }, 1500);
                } else {
                    toastr.error(response.msg || langSomethingWentWrong);
                    $btn.prop('disabled', false).html('<i class="fa fa-cut"></i> @lang("lang_v1.split_bill")');
                }
            },
            error: function(xhr) {
                var errorMsg = langSomethingWentWrong;
                if (xhr.responseJSON && xhr.responseJSON.msg) {
                    errorMsg = xhr.responseJSON.msg;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                toastr.error(errorMsg);
                console.error('Split Bill Error:', xhr.responseText);
                $btn.prop('disabled', false).html('<i class="fa fa-cut"></i> @lang("lang_v1.split_bill")');
            }
        });
    });

    // Initialize calculateTotal on page load
    calculateTotal();

    // Reset modal when hidden
    $('#splitBillModal').on('hidden.bs.modal', function() {
        var currencySymbol = '{{ $sell->location->currency->symbol ?? 'K' }}';
        $('#selected-total').text(currencySymbol + ' 0.00');
        $('#split-amount-display').text(currencySymbol + ' 0.00');
        $('#split-amount').val(0);
        $('.split-line-checkbox').prop('checked', false);
        $('#select-all-lines').prop('checked', false);
        $('#confirm-split-btn').prop('disabled', false).html('<i class="fa fa-cut"></i> @lang("lang_v1.split_bill")');
    });

    // Keyboard shortcuts for POS
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#splitBillModal').is(':visible')) {
            $('#splitBillModal').modal('hide');
        }
        if ((e.key === 'Enter' || e.key === 'F2') && $('#splitBillModal').is(':visible') && !$('#confirm-split-btn').prop('disabled')) {
            e.preventDefault();
            $('#confirm-split-btn').click();
        }
    });
});
</script>
@endsection