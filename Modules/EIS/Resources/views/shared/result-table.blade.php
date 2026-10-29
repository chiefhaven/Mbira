<script>
window.EisResultTables = window.EisResultTables || (function() {
    function scalar(value) {
        if (value === null || typeof value === 'undefined' || value === '') return '—';
        if (typeof value === 'boolean') return value ? 'Yes' : 'No';
        return String(value);
    }

    function tableForObject(object) {
        var table = $('<table>', {'class': 'table table-bordered table-striped table-condensed eis-result-table'});
        var body = $('<tbody>').appendTo(table);
        Object.keys(object).forEach(function(key) {
            var row = $('<tr>').appendTo(body);
            $('<th>', {scope: 'row', text: key}).appendTo(row);
            var cell = $('<td>').appendTo(row);
            appendValue(cell, object[key]);
        });
        return table;
    }

    function tableForRows(rows) {
        if (!rows.length) return $('<p>', {'class': 'text-muted', text: 'No records returned.'});
        var keys = [];
        rows.forEach(function(row) {
            if (row && typeof row === 'object' && !Array.isArray(row)) {
                Object.keys(row).forEach(function(key) { if (keys.indexOf(key) === -1) keys.push(key); });
            }
        });
        if (!keys.length) {
            return $('<table>', {'class': 'table table-bordered table-striped table-condensed eis-result-table'})
                .append($('<tbody>').append(rows.map(function(value) {
                    return $('<tr>').append($('<td>').text(scalar(value)));
                })));
        }
        var table = $('<table>', {'class': 'table table-bordered table-striped table-condensed eis-result-table'});
        var head = $('<thead>').appendTo(table);
        var header = $('<tr>').appendTo(head);
        keys.forEach(function(key) { $('<th>', {scope: 'col', text: key}).appendTo(header); });
        var body = $('<tbody>').appendTo(table);
        rows.forEach(function(item) {
            var row = $('<tr>').appendTo(body);
            keys.forEach(function(key) {
                var cell = $('<td>').appendTo(row);
                appendValue(cell, item && item[key]);
            });
        });
        return table;
    }

    function appendValue(container, value) {
        if (Array.isArray(value)) {
            if (value.length && value.every(function(item) { return item && typeof item === 'object'; })) {
                container.append(tableForRows(value));
            } else if (value.length) {
                var list = $('<ul>', {'class': 'list-unstyled eis-result-list'}).appendTo(container);
                value.forEach(function(item) {
                    var entry = $('<li>').appendTo(list);
                    appendValue(entry, item);
                });
            } else {
                container.text('No records returned.');
            }
        } else if (value && typeof value === 'object') {
            container.append(tableForObject(value));
        } else {
            container.text(scalar(value));
        }
    }

    function render(target, value) {
        var container = $(target).empty();
        if (Array.isArray(value)) {
            container.append(tableForRows(value));
        } else if (value && typeof value === 'object') {
            container.append(tableForObject(value));
        } else {
            container.append($('<div>', {'class': 'alert alert-info'}).text(scalar(value)));
        }
    }

    return {render: render};
})();
</script>
