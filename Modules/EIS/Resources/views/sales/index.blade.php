@extends('layouts.app')
@section('title', 'EIS Sales')
@section('content')
@include('eis::shared.navigation')
<section class="content-header"><h1>EIS Sales</h1></section>
<section class="content">
    <div id="eis-sales-message" role="status" aria-live="polite"></div>
    <div class="box"><div class="box-body table-responsive">
        <p>Final POS fiscalised sales.</p>
        <table class="table table-striped table-bordered"><thead><tr><th>ID</th><th>POS invoice</th><th>EIS invoice</th><th>Location / terminal</th><th>Status</th><th>Receipt</th><th>Last error</th><th>Action</th></tr></thead><tbody>
            @forelse($sales as $sale)
            <tr data-sale-id="{{ $sale->id }}">
                <td>{{ $sale->id }}</td>
                <td>{{ $sale->invoice_number }}</td>
                <td>{{ $sale->eis_invoice_number }}</td>
                <td>{{ optional($locations->firstWhere('id', $sale->location_id))->name }} / {{ $sale->eis_setting_id }}</td>
                <td class="eis-sale-status"><span class="eis-status-label {{ in_array($sale->status, ['submitted', 'accepted', 'synced'], true) ? 'eis-status-success' : (in_array($sale->status, ['pending', 'queued', 'submitting'], true) ? 'eis-status-warning' : 'eis-status-danger') }}">{{ $sale->status }}</span></td>
                <td class="eis-sale-receipt">{{ $sale->receipt_number }}</td>
                <td>{{ $sale->error_message }}</td>
                <td><button type="button" class="btn btn-primary eis-sale-sync-print"
                    data-id="{{ $sale->id }}"
                    data-print-url="{{ route('sell.printInvoice', ['transaction_id' => $sale->transaction_id]) }}">Sync &amp; Print</button></td>
            </tr>
            @empty
            <tr><td colspan="8">No tracked sales found.</td></tr>
            @endforelse
        </tbody></table>
        <div class="box box-default" id="eis-sales-result-panel" hidden>
            <div class="box-header with-border"><h3 class="box-title">EIS invoice details</h3></div>
            <div class="box-body table-responsive" id="eis-sales-result" aria-live="polite"></div>
        </div>
    </div></div>
    <div class="box"><div class="box-body table-responsive">
        <h3>Recent linked return adjustments</h3>
        <p>Prepared requests need explicit review and confirmation. Started, rejected, partial or unknown outcomes cannot be resent and block further return edits until verified with MRA. Sending never applies local stock or accounting again. Reload to refresh statuses.</p>
        <table class="table table-striped table-bordered"><thead><tr><th>ID</th><th>Sale / return</th><th>Terminal</th><th>Type</th><th>Status</th><th>Result</th><th></th></tr></thead><tbody>
            @forelse($adjustments as $row)
            <tr><td>{{ $row->id }}</td><td>{{ $row->sale_id }} / {{ $row->return_id }}</td><td>{{ $row->eis_setting_id }}</td><td>{{ $row->note_type }}</td><td><span class="eis-status-label {{ in_array($row->status, ['accepted', 'submitted'], true) ? 'eis-status-success' : (in_array($row->status, ['prepared', 'sending'], true) ? 'eis-status-warning' : 'eis-status-danger') }}">{{ $row->status }}</span></td><td>{{ $row->result_message }}</td>
                <td><button type="button" class="btn btn-default eis-sales-review" data-id="{{ $row->id }}">Review</button></td></tr>
            @empty
            <tr><td colspan="7">No linked adjustments yet.</td></tr>
            @endforelse
        </tbody></table>
        <div id="eis-sales-confirmation" hidden>
            <label><input id="eis-sales-confirm" type="checkbox"> I reviewed this exact invoice and linked POS return. Send once to EIS.</label>
            <button id="eis-sales-submit" type="button" class="btn btn-danger">Send reviewed credit/debit note</button>
        </div>
    </div></div>
</section>
@endsection
@section('css')
@include('eis::shared.styles')
@endsection
@section('javascript')
@include('eis::shared.result-table')
<script>
$(function() {
    var showUrl = @json(route('eis.sales.show', ['id' => 0]));
    var submitUrl = @json(route('eis.sales.submit', ['id' => 0]));
    var syncUrl = @json(route('eis.sales.sync', ['id' => 0]));
    var pending = null;
    function reset() {
        pending = null;
        $('#eis-sales-confirmation').prop('hidden', true);
        $('#eis-sales-confirm').prop('checked', false);
        $('#eis-sales-result-panel').prop('hidden', true);
        $('#eis-sales-result').empty();
    }
    function statusClass(status) {
        if (['submitted', 'accepted', 'synced'].indexOf(status) !== -1) return 'eis-status-success';
        if (['pending', 'prepared', 'queued', 'submitting', 'sending'].indexOf(status) !== -1) return 'eis-status-warning';
        return 'eis-status-danger';
    }
    function request(url, method, data) {
        reset();
        $('#eis-sales-submit, .eis-sales-review, .eis-sale-sync-print').prop('disabled', true);
        $.ajax({url: url, method: method, data: data, dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}})
            .done(function(result) {
                $('#eis-sales-result-panel').prop('hidden', false);
                window.EisResultTables.render('#eis-sales-result', result.data);
                $('#eis-sales-message').attr('class', 'alert alert-info').text(result.message || 'Response received.');
                if (result.data && result.data.id && result.data.status) {
                    var row = $('tr[data-sale-id="' + result.data.id + '"]');
                    row.find('.eis-sale-status').empty().append($('<span>', {
                        'class': 'eis-status-label ' + statusClass(result.data.status),
                        text: result.data.status
                    }));
                    row.find('.eis-sale-receipt').text(result.data.receipt_number || '');
                }
                if (result.data && result.data.status === 'prepared') {
                    pending = result.data;
                    $('#eis-sales-confirmation').prop('hidden', false);
                }
            }).fail(function(xhr) {
                var result = xhr.responseJSON || {}, errors = result.errors || {}, first = Object.keys(errors)[0];
                if (result.data) {
                    $('#eis-sales-result-panel').prop('hidden', false);
                    window.EisResultTables.render('#eis-sales-result', result.data);
                }
                $('#eis-sales-message').attr('class', 'alert alert-danger').text(first ? errors[first][0] :
                    (result.message || 'No successful result confirmed. Review the saved status before further action.'));
            }).always(function() { $('#eis-sales-submit, .eis-sales-review, .eis-sale-sync-print').prop('disabled', false); });
    }
    $('.eis-sales-review').on('click', function() { request(showUrl.replace(/\/0$/, '/' + $(this).attr('data-id')), 'GET', {}); });
    $('.eis-sale-sync-print').on('click', function() {
        var button = $(this);
        var saleId = button.attr('data-id');
        button.prop('disabled', true);
        $('#eis-sales-message').attr('class', 'alert alert-info').text('Verifying the saved invoice with EIS before printing…');
        $.ajax({
            url: syncUrl.replace(/\/0\/sync$/, '/' + saleId + '/sync'),
            method: 'POST',
            data: {},
            dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        }).done(function(result) {
            var row = $('tr[data-sale-id="' + saleId + '"]');
            row.find('.eis-sale-status').empty().append($('<span>', {
                'class': 'eis-status-label ' + statusClass(result.data.status),
                text: result.data.status
            }));
            row.find('.eis-sale-receipt').text(result.data.receipt_number || '');
            $('#eis-sales-message').attr('class', 'alert alert-success').text(
                (result.message || 'Invoice synchronized with EIS.') + ' Preparing the POS receipt to print.'
            );
            $('<a>', {
                'class': 'print-invoice',
                'data-href': button.attr('data-print-url'),
                'aria-hidden': 'true',
                tabindex: -1
            }).hide().appendTo('body').trigger('click').remove();
        }).fail(function(xhr) {
            var result = xhr.responseJSON || {};
            $('#eis-sales-message').attr('class', 'alert alert-danger').text(
                result.message || 'EIS verification failed. The POS receipt was not opened for printing.'
            );
        }).always(function() {
            button.prop('disabled', false);
        });
    });
    $('#eis-sales-submit').on('click', function() {
        if (!pending || !$('#eis-sales-confirm').prop('checked')) {
            $('#eis-sales-message').attr('class', 'alert alert-danger').text('Review and confirm the saved request first.'); return;
        }
        request(submitUrl.replace('/0/', '/' + pending.id + '/'), 'POST', {confirmed: 1});
    });
});
</script>
@endsection
