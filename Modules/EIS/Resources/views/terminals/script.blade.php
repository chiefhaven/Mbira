@include('eis::shared.result-table')
<script>
$(function() {
    var eisBusy = false;
    var eisUrls = {
        index: @json(route('eis.terminals.index')),
        activate: @json(route('eis.terminals.activate')),
        'latest-config': @json(route('eis.terminals.latest-config', ['terminalId' => 0])),
        configuration: @json(route('eis.terminals.configuration', ['terminalId' => 0])),
        'request-new-token': @json(route('eis.terminals.request-new-token', ['terminalId' => 0])),
        deactivate: @json(route('eis.terminals.deactivate', ['terminalId' => 0])),
        'renew-tokens': @json(route('eis.terminals.renew-tokens', ['terminalId' => 0])),
        'default': @json(route('eis.terminals.default', ['terminalId' => 0]))
    };

    function eisMessage(message, error) {
        $('#eis-management-message')
            .attr('class', 'alert ' + (error ? 'alert-danger' : 'alert-success'))
            .text(message);
    }

    function eisError(xhr) {
        var result = xhr.responseJSON || {};
        var errors = result.errors || {};
        var field = Object.keys(errors)[0];
        eisMessage(field ? errors[field][0] : (result.msg || result.message || 'Unable to complete terminal action.'), true);
    }

    function eisControls() {
        $('#eis-terminal-management button').prop('disabled', eisBusy);
        $('#eis-terminal-rows button[data-is-default="true"]').prop('disabled', true);
    }

    function loadEisTerminals() {
        eisBusy = true;
        eisControls();
        return $.ajax({
            url: eisUrls.index,
            dataType: 'json'
        }).done(function(result) {
            if (!result.success) {
                eisMessage(result.msg || result.message || 'Unable to load terminals.', true);
                return;
            }
            var rows = $('#eis-terminal-rows').empty();
            $.each(result.data.terminals, function(_, terminal) {
                var row = $('<tr>').appendTo(rows);
                $('<td>').text((terminal.terminal_label || terminal.device_id || 'Terminal') + ' (#' + terminal.id + ')').appendTo(row);
                $('<td>').text(terminal.location_name).appendTo(row);
                $('<td>').text(terminal.is_default ? 'Default' : '—').appendTo(row);
                $('<td>').append($('<span>', {
                    'class': 'eis-status-label ' + (terminal.is_active ? 'eis-status-success' : 'eis-status-danger'),
                    text: terminal.is_active ? 'Active' : 'Inactive'
                })).appendTo(row);
                var actions = $('<td>').appendTo(row);
                $.each({configuration: 'View configurations', 'latest-config': 'Latest config', 'request-new-token': 'Request new token', 'deactivate': 'Deactivate', 'renew-tokens': 'Renew tokens', 'default': 'Set default'}, function(action, label) {
                    if ((action === 'deactivate' || action === 'default') && !terminal.is_active) return;
                    $('<button>', {type: 'button', 'class': 'btn btn-default btn-xs eis-terminal-action'})
                        .attr('data-terminal-id', terminal.id)
                        .attr('data-action', action)
                        .attr('data-is-default', action === 'default' && terminal.is_default ? 'true' : 'false')
                        .text(label).appendTo(actions);
                    actions.append(' ');
                });
            });
            if (!result.data.terminals.length) {
                $('<tr>').append($('<td>', {colspan: 5}).text('No terminals in your permitted locations.')).appendTo(rows);
            }
        }).fail(function(xhr) {
            $('#eis-terminal-rows').empty().append($('<tr>').append($('<td>', {colspan: 5}).text('Unable to load terminals. Use Refresh to try again.')));
            eisError(xhr);
        }).always(function() {
            eisBusy = false;
            eisControls();
        });
    }

    function showEisConfiguration(data) {
        window.EisResultTables.render('#eis-configuration-data', data);
        $('#eis-configuration-panel').prop('hidden', false);
    }

    function eisAction(url, data, activated) {
        var reload = false;
        eisBusy = true;
        eisControls();
        $.ajax({
            method: 'post',
            url: url,
            data: data,
            dataType: 'json',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        }).done(function(result) {
            eisMessage(result.msg || result.message || 'Terminal updated.', !result.success);
            if (result.success) {
                if (result.data && result.data.globalConfiguration) showEisConfiguration(result.data);
                if (activated) $('#eis-activation-code').val('');
                reload = true;
            }
        }).fail(eisError).always(function() {
            if (reload) {
                loadEisTerminals();
            } else {
                eisBusy = false;
                eisControls();
            }
        });
    }

    $('#eis-refresh-terminals').click(function() {
        if (!eisBusy) loadEisTerminals();
    });
    $('#eis-activate-terminal').click(function() {
        if (eisBusy) return;
        var code = $('#eis-activation-code').val().trim();
        if (!code) {
            eisMessage('Enter an activation code.', true);
            return;
        }
        eisAction(eisUrls.activate, {
            terminal_activation_code: code,
            environment: {
                platform: {osVersion: 'Unknown', osBuild: ''}
            }
        }, true);
    });
    $('#eis-terminal-rows').on('click', '.eis-terminal-action', function() {
        if (eisBusy) return;
        var action = $(this).attr('data-action');
        var terminalId = $(this).attr('data-terminal-id');
        var data = {};
        if (action === 'configuration') {
            eisBusy = true; eisControls();
            $('#eis-configuration-panel').prop('hidden', true);
            $.getJSON(eisUrls.configuration.replace('/0/', '/' + terminalId + '/'))
                .done(function(result) { showEisConfiguration(result.data); })
                .fail(eisError).always(function() { eisBusy = false; eisControls(); });
            return;
        }
        if (action === 'latest-config') $('#eis-configuration-panel').prop('hidden', true);
        if (action === 'request-new-token') {
            if (!window.confirm('Request a new token for terminal #' + terminalId + '? This may invalidate the existing token. An uncertain response must be verified with MRA; do not repeat it.')) return;
            data.confirmed = 1;
        }
        if (action === 'deactivate') {
            var reason = window.prompt('Deactivate terminal #' + terminalId + '? Reason (optional):', '');
            if (reason === null) return;
            data.reason = reason;
        } else if (action === 'renew-tokens' && !window.confirm('Renew tokens for terminal #' + terminalId + '? Existing credentials will become invalid.')) {
            return;
        } else if (action === 'default' && !window.confirm('Use terminal #' + terminalId + ' as the default for its location?')) {
            return;
        }
        eisAction(eisUrls[action].replace('/0/', '/' + terminalId + '/'), data, false);
    });
    loadEisTerminals();
});
</script>
