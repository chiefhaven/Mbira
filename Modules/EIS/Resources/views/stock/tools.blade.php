@extends('layouts.app')
@section('title', 'EIS warehouse and production tools')
@section('content')
@include('eis::shared.navigation')
<section class="content-header"><h1>EIS warehouse, production and catalog tools</h1></section>
<section class="content">
    <div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Warehouse, production and catalog</h3></div>
    <div class="box-body">
        <p>Taxpayer-wide EIS operations require all-location access. These tools never change local stock, purchase, manufacturing or product records. Import the catalog separately; compare/reconcile site stock after confirmed movements.</p>
        <p>Site transfers, stock adjustments, adjustment reasons and informal receiving are available on the Stock outbound tab. Use get-suppliers here to obtain the EIS supplier ID for informal receiving.</p>
        <p class="text-warning">Warehouse transfer destination is the selected terminal's site, not a source site. Site-to-warehouse transfers are deprecated and not offered. Conversion consumes taxpayer warehouse raw materials, not local site stock. Use a unique productionBatchId for each genuine batch. Add-product creates zero-quantity EIS warehouse stock; do not use it as a stock receipt.</p>
        <div id="eis-tools-message" role="status" aria-live="polite"></div>
        <div class="row">
        <div class="col-md-6 form-group"><label for="eis-tools-terminal">Location / terminal (warehouse-transfer destination)</label>
        <select id="eis-tools-terminal" class="form-control">
            <option value="">Select terminal</option>
            @foreach($terminals as $terminal)
            <option value="{{ $terminal->id }}">{{ optional($locations->firstWhere('id', $terminal->location_id))->name }} / {{ $terminal->device_id }} (#{{ $terminal->id }})</option>
            @endforeach
        </select>
        </div><div class="col-md-6 form-group"><label for="eis-tools-operation">Operation</label>
        <select id="eis-tools-operation" class="form-control">
            @foreach($operations as $operation => $template)
            <option value="{{ $operation }}">{{ \Illuminate\Support\Str::headline($operation) }}</option>
            @endforeach
        </select>
        </div></div>
        <div id="eis-tools-fields" class="row"></div>
        <p class="help-block">Pagination: page minimum 1; page size 1-200 (default 50). Dates require ISO 8601 with timezone, e.g. 2026-10-24T02:00:00+02:00. Stock batches: 1-100 unique items, finite quantities/prices up to 1 billion. Conversion quantities minimum 0.01. Product classifications and units are selected from current EIS lookup lists. Omit barcode in add-product to let EIS assign one.</p>
        <button type="button" id="eis-tools-execute" class="btn btn-primary">Run lookup / prepare request</button>
        <button type="button" id="eis-tools-new" class="btn btn-default">Start a different request</button>
        <p class="text-muted">If a browser response is lost, reuse the current request or review history; do not start another transfer for the same goods.</p>
        <div id="eis-tools-confirmation" hidden>
            <label><input id="eis-tools-confirm" type="checkbox"> I reviewed the saved taxpayer, terminal/site and payload. This is a genuine EIS-only operation and will not update local ledgers.</label>
            <button type="button" id="eis-tools-submit" class="btn btn-danger">Send reviewed request once</button>
        </div>
        <div id="eis-tools-verification" hidden>
            <label for="eis-tools-notes">External verification reference and confirmed outcome (minimum 20 characters)</label>
            <textarea id="eis-tools-notes" class="form-control" maxlength="2000"></textarea>
            <label><input id="eis-tools-verified" type="checkbox"> I independently verified the final result with MRA. This records verification, not a retry or approval.</label>
            <button type="button" id="eis-tools-verify" class="btn btn-warning">Record verified outcome</button>
        </div>
        <div class="box box-default" id="eis-tools-result-panel" hidden>
            <div class="box-header with-border"><h3 class="box-title">EIS response / saved request</h3></div>
            <div class="box-body table-responsive" id="eis-tools-result" aria-live="polite"></div>
        </div>
    </div></div>
    <div class="box"><div class="box-body table-responsive">
        <h3>Recent reviewed stock requests</h3>
        <p>Requests are immutable and never resent after sending starts. Unknown results require MRA verification. Sending requests require investigation, not resetting. Reload to refresh this list.</p>
        <table class="table table-striped table-bordered"><thead><tr><th>ID</th><th>Terminal</th><th>Operation</th><th>Status</th><th>Result</th><th></th></tr></thead><tbody>
        @forelse($history as $row)
            <tr><td>{{ $row->id }}</td><td>{{ $row->eis_setting_id }}</td><td>{{ \Illuminate\Support\Str::headline($row->operation) }}</td><td><span class="eis-status-label {{ in_array($row->status, ['submitted', 'verified'], true) ? 'eis-status-success' : (in_array($row->status, ['prepared', 'sending'], true) ? 'eis-status-warning' : 'eis-status-danger') }}">{{ $row->status }}</span></td><td>{{ $row->result_message }}</td>
                <td><button type="button" class="btn btn-default eis-tools-review" data-id="{{ $row->id }}">Review</button></td></tr>
        @empty
            <tr><td colspan="6">No requests yet.</td></tr>
        @endforelse
        </tbody></table>
    </div></div>
</section>
@endsection
@section('css')
@include('eis::shared.styles')
@endsection
@section('javascript')
@include('eis::shared.request-form')
@include('eis::shared.result-table')
<script>
$(function() {
    var templates = @json($operations);
    var executeUrl = @json(route('eis.stock.tools.execute'));
    var showUrl = @json(route('eis.stock.tools.show', ['id' => 0]));
    var submitUrl = @json(route('eis.stock.tools.submit', ['id' => 0]));
    var verifyUrl = @json(route('eis.stock.tools.verify', ['id' => 0]));
    var key = @json($requestKey);
    var pending = null;
    var choiceCache = {};
    function reset() {
        pending = null;
        $('#eis-tools-confirmation, #eis-tools-verification').prop('hidden', true);
        $('#eis-tools-confirm, #eis-tools-verified').prop('checked', false);
        $('#eis-tools-notes').val('');
        $('#eis-tools-result-panel').prop('hidden', true);
        $('#eis-tools-result').empty();
    }
    function message(text, error) { $('#eis-tools-message').attr('class', 'alert ' + (error ? 'alert-danger' : 'alert-info')).text(text); }
    function display(data) {
        reset();
        $('#eis-tools-result-panel').prop('hidden', false);
        window.EisResultTables.render('#eis-tools-result', data);
        if (data && data.status === 'prepared') {
            pending = data;
            $('#eis-tools-confirmation').prop('hidden', false);
        } else if (data && (data.status === 'unknown' || data.status === 'needs_review')) {
            pending = data;
            $('#eis-tools-verification').prop('hidden', false);
        }
    }
    function send(url, method, data) {
        reset();
        $('#eis-tools-execute, #eis-tools-submit, #eis-tools-new, #eis-tools-verify, .eis-tools-review, #eis-tools-terminal, #eis-tools-operation, #eis-tools-fields :input').prop('disabled', true);
        $.ajax({url: url, method: method, data: data, dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}})
            .done(function(result) {
                display(result.data);
                message(result.message || (result.prepared ? 'Saved for review; nothing sent yet.' : 'EIS response received.'), false);
            }).fail(function(xhr) {
                var result = xhr.responseJSON || {}, errors = result.errors || {}, field = Object.keys(errors)[0];
                if (result.data) display(result.data);
                message(field ? errors[field][0] : (result.message || 'No successful result confirmed. Check saved history; do not duplicate a movement.'), true);
            }).always(function() {
                $('#eis-tools-execute, #eis-tools-submit, #eis-tools-new, #eis-tools-verify, .eis-tools-review, #eis-tools-terminal, #eis-tools-operation, #eis-tools-fields :input').prop('disabled', false);
            });
    }
    function requestTemplate() {
        reset();
        var value = templates[$('#eis-tools-operation').val()];
        var operation = $('#eis-tools-operation').val();
        var optional = {fields: [], arrays: {}, limits: {}};
        if (operation === 'warehouse-transfer') {
            optional.arrays.items = ['price'];
            optional.limits.items = 100;
        } else if (operation === 'submit-conversion') {
            optional.arrays.finishedProducts = ['expiryDate'];
            optional.limits.rawMaterials = 100;
            optional.limits.finishedProducts = 100;
        }
        var choices = choiceCache[$('#eis-tools-terminal').val()] || {};
        if (operation === 'add-product') {
            optional.fields = ['barcode'];
            optional.choices = {hsCode: choices.hsCode || [], uom: choices.uom || []};
        } else if (operation === 'submit-conversion') {
            optional.choices = {unitOfMeasure: choices.unitOfMeasure || []};
        }
        window.EisRequestForms.render('#eis-tools-fields', value, optional);
        $('#eis-tools-fields :input').on('change input', reset);
    }

    function lookup(operation) {
        return $.ajax({
            url: executeUrl,
            method: 'POST',
            data: {terminal_id: $('#eis-tools-terminal').val(), operation: operation, payload: '{}', request_key: key},
            dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });
    }

    function lookupChoices() {
        var operation = $('#eis-tools-operation').val();
        if (!['add-product', 'submit-conversion'].includes(operation)) return;
        var terminalId = $('#eis-tools-terminal').val();
        if (!terminalId) {
            message('Select a terminal to load current EIS product classifications and units.', true);
            return;
        }
        if (choiceCache[terminalId]) return;

        $('#eis-tools-terminal, #eis-tools-operation, #eis-tools-execute').prop('disabled', true);
        message('Loading current product classifications and units from EIS…', false);
        var requests = operation === 'add-product'
            ? [lookup('get-hs-codes'), lookup('get-units-of-measure')]
            : [lookup('get-units-of-measure')];
        Promise.all(requests.map(function(request) {
            return request.then(function(result) { return result.data.data; });
        })).then(function(results) {
                var hsData = operation === 'add-product' ? results[0] : [];
                var unitData = results[operation === 'add-product' ? 1 : 0];
                choiceCache[terminalId] = {
                    hsCode: hsData.map(function(item) {
                        return {value: item.code, label: item.description ? item.code + ' — ' + item.description : item.code};
                    }),
                    uom: unitData.map(function(item) {
                        var value = item.unitOfMeasure;
                        return {value: value, label: item.description ? value + ' — ' + item.description : value};
                    }),
                    unitOfMeasure: unitData.map(function(item) {
                        var value = item.unitOfMeasure;
                        return {value: value, label: item.description ? value + ' — ' + item.description : value};
                    })
                };
                requestTemplate();
                message('EIS lookup values loaded. Select the applicable classification and unit.', false);
            }).catch(function(xhr) {
                var body = xhr.responseJSON || {};
                message(body.message || 'Unable to load current EIS classifications and units. Retry by selecting the operation again.', true);
            }).then(function() {
                $('#eis-tools-terminal, #eis-tools-operation, #eis-tools-execute').prop('disabled', false);
            });
    }

    $('#eis-tools-operation').on('change', function() {
        requestTemplate();
        lookupChoices();
    });
    $('#eis-tools-terminal').on('change', function() {
        reset();
        requestTemplate();
        lookupChoices();
    });
    $('#eis-tools-new').on('click', function() { window.location.reload(); });
    $('#eis-tools-execute').on('click', function() {
        var operation = $('#eis-tools-operation').val();
        if (['add-product', 'submit-conversion'].includes(operation)
            && !choiceCache[$('#eis-tools-terminal').val()]) {
            message('Load current EIS classifications and units before preparing this request.', true);
            return;
        }
        send(executeUrl, 'POST', {terminal_id: $('#eis-tools-terminal').val(), operation: $('#eis-tools-operation').val(),
            payload: JSON.stringify(window.EisRequestForms.collect('#eis-tools-fields')), request_key: key});
    });
    $('.eis-tools-review').on('click', function() { send(showUrl.replace(/\/0$/, '/' + $(this).attr('data-id')), 'GET', {}); });
    $('#eis-tools-submit').on('click', function() {
        if (!pending || !$('#eis-tools-confirm').prop('checked')) { message('Review and confirm the saved payload first.', true); return; }
        send(submitUrl.replace('/0/', '/' + pending.id + '/'), 'POST', {confirmed: 1});
    });
    $('#eis-tools-verify').on('click', function() {
        if (!pending || !$('#eis-tools-verified').prop('checked')) { message('Independently verify the final outcome with MRA first.', true); return; }
        send(verifyUrl.replace('/0/', '/' + pending.id + '/'), 'POST', {confirmed: 1, notes: $('#eis-tools-notes').val()});
    });
    requestTemplate();
});
</script>
@endsection
