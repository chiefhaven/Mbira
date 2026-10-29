@if(empty($only_payment))
    <input type="hidden" name="is_relief" value="0">
@endif
<label for="isRelief" class="checkbox-inline tw-font-semibold">
    <input type="checkbox" name="is_relief" id="isRelief" value="1"
        @if(!empty($transaction->is_relief)) checked @endif
        @if(!empty($only_payment)) disabled @endif>
    @lang('sale.is_relief')
</label>