<div class="modal-dialog no-print" role="document">
{!! Form::open(['url' => action('\Modules\Accounting\Http\Controllers\TransactionController@saveMap'), 'method' => 'POST', 'id' => 'save_accounting_map' ]) !!}
    
    <input type="hidden" name="type" value="{{$type}}" id="transaction_type">
    @if(in_array($type, ['sell', 'purchase']))
        <input type="hidden" name="id" value="{{$transaction->id}}">
    @elseif(in_array($type, ['sell_payment', 'purchase_payment']))
        <input type="hidden" name="id" value="{{$transaction_payment->id}}">
    @endif

<div class="modal-content">
    <div class="modal-header">
    <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title" id="modalTitle">
        @if($type == 'sell')
            {{$transaction->invoice_no}}
        @elseif(in_array($type, ['sell_payment', 'purchase_payment']))
            {{$transaction_payment->payment_ref_no}}
        @elseif($type == 'purchase')
            {{$transaction->ref_no}}
        @endif
    </h4>
</div>
<div class="modal-body">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                @php
                    $paymentAccountLabel = [
                        'sell' => __('accounting::lang.income'),
                        'purchase' => __('accounting::lang.inventory') . ' / ' . __('accounting::lang.expenses'),
                        'sell_payment' => __('accounting::lang.accounts_receivable'),
                        'purchase_payment' => __('accounting::lang.accounts_payable'),
                    ][$type] ?? __('accounting::lang.payment_account');
                    $depositToLabel = [
                        'sell' => __('accounting::lang.accounts_receivable'),
                        'purchase' => __('accounting::lang.accounts_payable'),
                        'sell_payment' => __('accounting::lang.bank'),
                        'purchase_payment' => __('accounting::lang.bank'),
                    ][$type] ?? __('accounting::lang.deposit_to');
                @endphp
                {!! Form::label('payment_account', $paymentAccountLabel . ':*' ) !!}
                {!! Form::select('payment_account', !is_null($default_payment_account) ? [$default_payment_account->id => $default_payment_account->name] : [], $default_payment_account->id ?? null, ['class' => 'form-control accounts-dropdown','placeholder' => $paymentAccountLabel, 'required' => 'required']); !!}
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('deposit_to', $depositToLabel . ':*' ) !!}
                {!! Form::select('deposit_to', !is_null($default_deposit_to) ? 
                    [$default_deposit_to->id => $default_deposit_to->name] : [], $default_deposit_to->id ?? null, ['class' => 'form-control accounts-dropdown','placeholder' => $depositToLabel, 'required' => 'required']); !!}
            </div>
        </div>
    </div>

</div>

<div class="modal-footer">
    <button type="submit" class="btn btn-primary">@lang('messages.update')</button>
    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
</div>

{!! Form::close() !!}
	</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->