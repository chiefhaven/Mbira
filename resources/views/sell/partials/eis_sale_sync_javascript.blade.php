<script>
$(function() {
    var syncBusy = false;

    $(document).on('click', '.eis-sale-sync', function(event) {
        event.preventDefault();
        if (syncBusy) return;

        var $link = $(this);
        var syncUrl = $link.attr('data-sync-url');
        if (!syncUrl) {
            alert('This sale has no EIS invoice sync action.');
            return;
        }

        syncBusy = true;
        $link.addClass('disabled').attr('aria-disabled', 'true');
        $.ajax({
            url: syncUrl,
            method: 'POST',
            dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        }).done(function(result) {
            var data = result.data || {};
            alert(result.message || 'EIS invoice/receipt sync completed.');
            if (window.sell_table) window.sell_table.ajax.reload(null, false);
            if (typeof window.reload_sell_table === 'function') window.reload_sell_table();
        }).fail(function(xhr) {
            var body = xhr.responseJSON || {};
            alert(body.message || 'Could not sync the EIS invoice/receipt.');
        }).always(function() {
            syncBusy = false;
            $link.removeClass('disabled').removeAttr('aria-disabled');
        });
    });
});
</script>
