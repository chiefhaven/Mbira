<script>
$(function() {
    var executeUrl = @json(route('eis.sales.operations.execute'));
    var syncBusy = false;

    $(document).on('click', '.eis-void-sync', function(event) {
        event.preventDefault();
        if (syncBusy) return;

        var $link = $(this);
        var invoiceNumber = $link.attr('data-invoice-number');
        var terminalId = $link.attr('data-terminal-id');
        if (!invoiceNumber || !terminalId) {
            alert('This fiscal invoice is missing its EIS invoice or terminal identity.');
            return;
        }

        syncBusy = true;
        $link.addClass('disabled').attr('aria-disabled', 'true');
        $.ajax({
            url: executeUrl,
            method: 'POST',
            dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {
                terminal_id: terminalId,
                operation: 'get-void-receipts',
                payload: JSON.stringify({invoiceNumber: invoiceNumber, page: 1, pageSize: 200})
            }
        }).done(function(result) {
            var sync = result.data && result.data.sync;
            if (!sync) {
                alert(result.message || 'EIS returned no synchronized void receipt data.');
                return;
            }

            var message = sync.voided
                ? 'EIS approved this void request. The invoice is marked Void locally.'
                : (sync.synced
                    ? 'Void receipt status synced: ' + sync.synced + ' receipt(s); none are approved.'
                    : 'No matching EIS void receipt was found. The local invoice was not changed.');
            alert(message);
            if (window.sell_table) window.sell_table.ajax.reload(null, false);
        }).fail(function(xhr) {
            var body = xhr.responseJSON || {};
            alert(body.message || 'Could not retrieve and sync this EIS void receipt.');
        }).always(function() {
            syncBusy = false;
            $link.removeClass('disabled').removeAttr('aria-disabled');
        });
    });
});
</script>
