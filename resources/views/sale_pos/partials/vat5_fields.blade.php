@php
    $vat5 = $transaction->vat5_certificate_details ?? [];
@endphp
<div id="pos_vat5_fields" class="row mt-10 @if(empty($transaction->is_relief)) hide @endif">
    <div class="col-sm-4">
        <div class="form-group">
            <label for="vat5_project_number">@lang('sale.vat5_project_number')</label>
            <input type="text" name="vat5_project_number" id="vat5_project_number" class="form-control"
                maxlength="255" value="{{ old('vat5_project_number', $vat5['projectNumber'] ?? '') }}"
                @if(!empty($only_payment)) disabled @endif>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            <label for="vat5_certificate_number">@lang('sale.vat5_certificate_number')</label>
            <input type="text" name="vat5_certificate_number" id="vat5_certificate_number" class="form-control"
                maxlength="255" value="{{ old('vat5_certificate_number', $vat5['certificateNumber'] ?? '') }}"
                @if(!empty($only_payment)) disabled @endif>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            <label for="vat5_quantity">@lang('sale.vat5_quantity')</label>
            <input type="number" name="vat5_quantity" id="vat5_quantity" class="form-control" step="any" min="0.000001"
                value="{{ old('vat5_quantity', $vat5['quantity'] ?? '') }}"
                @if(!empty($only_payment)) disabled @endif>
        </div>
    </div>
</div>