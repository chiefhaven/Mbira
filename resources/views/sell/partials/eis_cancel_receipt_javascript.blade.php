<script>
$(function() {
    var executeUrl = @json(route('eis.sales.operations.execute'));
    var submitUrl = @json(route('eis.sales.operations.submit', ['requestId' => 0]));
    var terminalId = null, receiptNumber = null, pendingRequest = null, busy = false;
    var modal = $('#eis_cancel_receipt_modal');

    function setBusy(value) {
        busy = value;
        $('#eis-cancel-prepare, #eis-cancel-submit').prop('disabled', value);
    }

    function showMessage(text, error) {
        $('#eis-cancel-message').attr('class', 'alert ' + (error ? 'alert-danger' : 'alert-info')).text(text);
    }

    $(document).on('click', '.eis-cancel-receipt', function(event) {
        event.preventDefault();
        if (busy) return;
        var link = $(this);
        terminalId = link.attr('data-terminal-id');
        receiptNumber = link.attr('data-invoice-number');
        pendingRequest = null;
        $('#eis-cancel-invoice').text(receiptNumber || '');
        $('#eis-cancel-reason').val('');
        $('#eis-cancel-confirm').prop('checked', false);
        $('#eis-cancel-review').prop('hidden', true);
        $('#eis-cancel-form').prop('hidden', false);
        $('#eis-cancel-request-details').empty();
        showMessage('', false);
        modal.modal('show');
    });

    $('#eis-cancel-prepare').on('click', function() {
        var reason = $.trim($('#eis-cancel-reason').val());
        if (busy) return;
        if (!terminalId || !receiptNumber || !reason) {
            showMessage('A fiscal invoice and cancellation reason are required.', true);
            return;
        }
        setBusy(true);
        $.ajax({
            url: executeUrl,
            method: 'POST',
            dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {
                terminal_id: terminalId,
                operation: 'cancel-receipt',
                payload: JSON.stringify({receiptNumber: receiptNumber, reason: reason})
            }
        }).done(function(result) {
            var data = result.data || {};
            if (data.status !== 'prepared' || !data.id) {
                showMessage(result.message || 'The request is not ready to submit. Review its saved status.', true);
                return;
            }
            pendingRequest = data;
            window.EisResultTables.render('#eis-cancel-request-details', data);
            $('#eis-cancel-form').prop('hidden', true);
            $('#eis-cancel-review').prop('hidden', false);
            showMessage('Review the saved request before sending it to EIS.', false);
        }).fail(function(xhr) {
            var body = xhr.responseJSON || {};
            showMessage(body.message || 'Could not prepare the cancellation request.', true);
        }).always(function() {
            setBusy(false);
        });
    });

    $('#eis-cancel-submit').on('click', function() {
        if (busy) return;
        if (!pendingRequest || !$('#eis-cancel-confirm').prop('checked')) {
            showMessage('Review and confirm the saved request before sending.', true);
            return;
        }
        var requestId = pendingRequest.id;
        pendingRequest = null;
        setBusy(true);
        $.ajax({
            url: submitUrl.replace(/\/0\/submit$/, '/' + requestId + '/submit'),
            method: 'POST',
            dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {confirmed: 1}
        }).done(function(result) {
            showMessage(result.message || 'EIS accepted the void request. MRA approval is separate.', false);
            if (window.sell_table) window.sell_table.ajax.reload(null, false);
        }).fail(function(xhr) {
            var body = xhr.responseJSON || {};
            showMessage(body.message || 'The request did not complete successfully. Check its saved status; do not resend it blindly.', true);
        }).always(function() {
            setBusy(false);
        });
    });
});
</script>
