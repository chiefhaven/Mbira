@extends('layouts.app')

@section('title', 'EIS stock operations')

@section('content')
@include('eis::shared.navigation')
<section class="content-header">
    <h1>EIS stock operations</h1>
    <p>Submitted operations are immutable. Unknown outcomes are not retried because EIS does not document an idempotency key.</p>
    <p>These forms submit to EIS only. After acceptance (and approval for informal purchases), compare/reconcile each affected location to create the local stock movements. Do not also enter a separate local transfer or purchase for the same operation.</p>
</section>
<section class="content">
    @if(session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div id="eis-stock-operation-alert" aria-live="polite"></div>
    <div class="box box-primary">
        <div class="box-header"><h3 class="box-title">Create outbound stock operation</h3></div>
        <div class="box-body">
            <form method="post" action="{{ route('eis.stock.outbound.store') }}" id="eis-stock-operation-form">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="eis-stock-location">EIS site and source location</label>
                        <select class="form-control" name="location_id" id="eis-stock-location" required>
                            <option value="">Select location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" data-site="{{ $location->site_id }}">
                                    {{ $location->name }} — site {{ $location->site_id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="eis-stock-operation-type">Operation</label>
                        <select class="form-control" name="operation_type" id="eis-stock-operation-type" required>
                            <option value="">Select operation</option>
                            <option value="adjustment">Stock adjustment</option>
                            <option value="transfer">Site-to-site transfer</option>
                            <option value="informal_purchase">Informal purchase / goods receiving</option>
                        </select>
                    </div>
                </div>

                <div id="eis-stock-adjustment-fields" class="row" hidden>
                    <div class="col-md-4 form-group">
                        <label for="eis-stock-adjustment-type">Adjustment direction</label>
                        <select class="form-control" name="adjustment_type" id="eis-stock-adjustment-type">
                            <option value="Decrease">Decrease</option>
                            <option value="Increase">Increase</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="eis-stock-adjustment-reason">EIS adjustment reason</label>
                        <select class="form-control" name="adjustment_reason" id="eis-stock-adjustment-reason">
                            <option value="">Select a reason from EIS</option>
                        </select>
                    </div>
                    <div class="col-md-8 form-group">
                        <label for="eis-stock-taxpayer-remarks">Taxpayer remarks (optional)</label>
                        <input class="form-control" name="taxpayer_remarks" id="eis-stock-taxpayer-remarks" maxlength="1000">
                    </div>
                </div>

                <div id="eis-stock-transfer-fields" class="row" hidden>
                    <div class="col-md-4 form-group">
                        <label for="eis-stock-destination">Destination EIS site</label>
                        <select class="form-control" name="to_location_id" id="eis-stock-destination">
                            <option value="">Select destination</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}"> {{ $location->name }} — site {{ $location->site_id }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="eis-stock-purchase-fields" class="row" hidden>
                    <div class="col-md-3 form-group">
                        <label for="eis-stock-supplier-id">EIS supplier ID</label>
                        <input class="form-control" type="number" min="1" name="supplier_id" id="eis-stock-supplier-id">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="eis-stock-received-by">Received by</label>
                        <input class="form-control" name="received_by" id="eis-stock-received-by" maxlength="255">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="eis-stock-receiving-date">Receiving date</label>
                        <input class="form-control" type="date" name="receiving_date" id="eis-stock-receiving-date" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="eis-stock-delivery-note">Delivery note number</label>
                        <input class="form-control" name="delivery_note_number" id="eis-stock-delivery-note" maxlength="255">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="eis-stock-purchase-order">Purchase order number</label>
                        <input class="form-control" name="purchase_order_number" id="eis-stock-purchase-order" maxlength="255">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="eis-stock-notes">Notes</label>
                        <input class="form-control" name="notes" id="eis-stock-notes" maxlength="1000">
                    </div>
                    <div class="col-md-12">
                        <p class="text-warning">EIS goods receiving is subject to approval. Accepted submissions remain pending approval and block stock reconciliation.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="eis-stock-items">
                        <thead>
                            <tr>
                                <th>Mapped product</th>
                                <th>Quantity</th>
                                <th>Unit price</th>
                                <th>Unit of measure</th>
                                <th>Finished item</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <button type="button" id="eis-stock-add-item" class="btn btn-default">Add item</button>
                <button type="submit" class="btn btn-primary">Queue EIS operation</button>
            </form>
        </div>
    </div>

    <div class="box">
        <div class="box-header"><h3 class="box-title">Recent stock operations</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-striped table-bordered">
                <thead><tr><th>ID</th><th>Location</th><th>Type</th><th>Status</th><th>Created</th><th>Result</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($operations as $operation)
                        <tr>
                            <td>{{ $operation->id }}</td>
                            <td>{{ $operation->destination_name ? $operation->location_name.' → '.$operation->destination_name : $operation->location_name }}</td>
                            <td>{{ str_replace('_', ' ', $operation->operation_type) }}</td>
                            <td><span class="eis-status-label {{ in_array($operation->status, ['submitted', 'verified'], true) ? 'eis-status-success' : (in_array($operation->status, ['queued', 'submitting', 'awaiting_approval'], true) ? 'eis-status-warning' : 'eis-status-danger') }}">{{ $operation->status }}</span></td>
                            <td>{{ $operation->created_at }}</td>
                            <td>
                                {{ $operation->status === 'verified' ? 'Final EIS outcome verified; reconcile local stock.' : ($operation->error_message ?: ($operation->submitted_at ? ($operation->status === 'awaiting_approval' ? 'Accepted; awaiting EIS approval' : 'Accepted by EIS') : 'Awaiting worker/result')) }}
                                @if($operation->line_summary)
                                    <br><small>Lines: {{ $operation->line_summary }}</small>
                                @endif
                                @if($operation->verification_notes)
                                    <br><small>Verified: {{ $operation->verification_notes }}</small>
                                @endif
                            </td>
                            <td>
                                @if($operation->status === 'queued')
                                    <form method="post" action="{{ route('eis.stock.outbound.dispatch', ['operationId' => $operation->id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-default">Dispatch queued operation</button>
                                    </form>
                                @elseif(in_array($operation->status, ['unknown', 'partial', 'submitting', 'submitted', 'awaiting_approval'], true))
                                    <span class="text-muted">Immutable; no retry</span>
                                @else
                                    <span class="text-muted">Immutable</span>
                                @endif
                                @can('business_settings.access')
                                    @if(in_array($operation->status, ['unknown', 'partial', 'awaiting_approval'], true))
                                        <form method="post" action="{{ route('eis.stock.outbound.verify', ['operationId' => $operation->id]) }}">
                                            @csrf
                                            <label>Final EIS outcome / reference
                                                <textarea class="form-control" name="verification_notes" minlength="20" maxlength="2000" required></textarea>
                                            </label>
                                            <label><input type="checkbox" name="confirmed" value="1" required> I checked EIS, all item outcomes are final, and no approval/request remains pending.</label>
                                            <button type="submit" class="btn btn-xs btn-warning">Record verification; do not resend</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No EIS stock operations recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
@section('css')
@include('eis::shared.styles')
@endsection

@section('javascript')
<script>
    (function () {
        var type = document.getElementById('eis-stock-operation-type');
        var location = document.getElementById('eis-stock-location');
        var adjustment = document.getElementById('eis-stock-adjustment-fields');
        var transfer = document.getElementById('eis-stock-transfer-fields');
        var purchase = document.getElementById('eis-stock-purchase-fields');
        var reasons = document.getElementById('eis-stock-adjustment-reason');
        var reasonUrl = @json(route('eis.stock.outbound.reasons', ['locationId' => 0]));
        var products = @json($productOptions);
        var itemIndex = 0;

        function updateType() {
            adjustment.hidden = type.value !== 'adjustment';
            transfer.hidden = type.value !== 'transfer';
            purchase.hidden = type.value !== 'informal_purchase';
        }

        function addItem() {
            var row = document.createElement('tr');
            var productOptions = '<option value="">Select mapped product</option>';
            products.forEach(function (product) {
                productOptions += '<option value="' + product.id + '">' + $('<div>').text(product.name + ' — ' + product.barcode).html() + '</option>';
            });
            row.innerHTML = '<td><select class="form-control" name="items[' + itemIndex + '][product_id]" required>' + productOptions + '</select></td>'
                + '<td><input class="form-control" type="number" min="0.0001" step="0.0001" name="items[' + itemIndex + '][quantity]" required></td>'
                + '<td><input class="form-control" type="number" min="0" step="0.01" name="items[' + itemIndex + '][unit_price]" value="0" required></td>'
                + '<td><input class="form-control" name="items[' + itemIndex + '][unit_of_measure]" value="Each" maxlength="50"></td>'
                + '<td><select class="form-control" name="items[' + itemIndex + '][is_finished_product]"><option value="1">Yes</option><option value="0">No</option></select></td>'
                + '<td><button type="button" class="btn btn-danger eis-stock-remove-item">Remove</button></td>';
            document.querySelector('#eis-stock-items tbody').appendChild(row);
            itemIndex += 1;
        }

        type.addEventListener('change', updateType);
        document.getElementById('eis-stock-add-item').addEventListener('click', addItem);
        document.querySelector('#eis-stock-items tbody').addEventListener('click', function (event) {
            if (event.target.classList.contains('eis-stock-remove-item')) {
                event.target.closest('tr').remove();
            }
        });
        location.addEventListener('change', function () {
            reasons.innerHTML = '<option value="">Loading EIS reasons…</option>';
            if (!location.value) {
                reasons.innerHTML = '<option value="">Select a reason from EIS</option>';
                return;
            }
            fetch(reasonUrl.replace('/0/', '/' + location.value + '/'), {headers: {'Accept': 'application/json'}})
                .then(function (response) { return response.json().then(function (body) { return {ok: response.ok, body: body}; }); })
                .then(function (result) {
                    reasons.innerHTML = '<option value="">Select a reason from EIS</option>';
                    if (!result.ok || !result.body.success) {
                        throw new Error(result.body.message || 'EIS reasons are unavailable.');
                    }
                    result.body.data.forEach(function (reason) {
                        var option = document.createElement('option');
                        option.value = reason;
                        option.textContent = reason;
                        reasons.appendChild(option);
                    });
                })
                .catch(function (error) {
                    reasons.innerHTML = '<option value="">Reasons unavailable</option>';
                    document.getElementById('eis-stock-operation-alert').innerHTML = '<div class="alert alert-warning">' + $('<div>').text(error.message).html() + '</div>';
                });
        });
        updateType();
        addItem();
    })();
</script>
@endsection
