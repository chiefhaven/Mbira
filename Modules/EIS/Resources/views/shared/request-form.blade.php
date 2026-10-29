<script>
window.EisRequestForms = window.EisRequestForms || (function() {
    function labelFor(key) {
        return key.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/[_-]/g, ' ')
            .replace(/\b\w/g, function(character) { return character.toUpperCase(); });
    }

    function inputFor(key, value, optional, choices) {
        if (Array.isArray(choices)) {
            var select = $('<select>', {'class': 'form-control eis-request-value', 'data-key': key,
                'data-value-type': 'string', 'data-optional': optional ? 'true' : 'false'})
                .append($('<option>', {value: '', text: choices.length ? 'Select ' + labelFor(key).toLowerCase() : 'Loading EIS options…'}));
            choices.forEach(function(choice) {
                select.append($('<option>', {value: choice.value, text: choice.label}));
            });
            if (!choices.length) select.prop('disabled', true);
            return select.val(value || '');
        }
        if (typeof value === 'boolean') {
            return $('<select>', {'class': 'form-control eis-request-value', 'data-key': key, 'data-value-type': 'boolean',
                'data-optional': optional ? 'true' : 'false'})
                .append($('<option>', {value: 'true', text: 'Yes'}))
                .append($('<option>', {value: 'false', text: 'No'}))
                .val(value ? 'true' : 'false');
        }
        var numeric = typeof value === 'number' || /^(status|page|pageSize|quantity|price|.*Quantity|.*Price|.*Level)$/i.test(key);
        var input = $('<input>', {
            type: numeric ? 'number' : 'text',
            'class': 'form-control eis-request-value',
            'data-key': key,
            'data-value-type': numeric ? 'number' : 'string',
            'data-optional': optional ? 'true' : 'false',
            value: value === null ? '' : value,
            autocomplete: 'off'
        });
        if (numeric) input.attr({step: /^(status|page|pageSize)$/i.test(key) ? '1' : 'any'});
        if (key === 'page') input.attr({min: 1});
        if (key === 'pageSize') input.attr({min: 1, max: 200});
        if (/Date$/.test(key)) input.attr({placeholder: '2026-10-28T00:00:00+02:00'});
        return input;
    }

    function addArrayRow(body, keys, values, optionalKeys, choices) {
        var row = $('<tr>').appendTo(body);
        keys.forEach(function(key) {
            var cell = $('<td>').appendTo(row);
            cell.append(inputFor(key, values[key], optionalKeys.indexOf(key) !== -1,
                choices && choices[key]));
        });
        $('<td>').append($('<button>', {type: 'button', 'class': 'btn btn-danger btn-xs eis-request-remove', text: 'Remove'})).appendTo(row);
    }

    function render(target, template, optional) {
        var root = $(target).empty();
        var fields = $('<div>', {'class': 'row'}).appendTo(root);
        Object.keys(template).forEach(function(key) {
            var value = template[key];
            if (Array.isArray(value)) {
                var rows = value.length ? value : [{}];
                var first = rows[0];
                if (!first || typeof first !== 'object' || Array.isArray(first)) return;
                var keys = Object.keys(first);
                var section = $('<div>', {'class': 'form-group eis-request-array'}).attr('data-array-key', key).appendTo(root);
                $('<label>', {text: labelFor(key)}).appendTo(section);
                var table = $('<div>', {'class': 'table-responsive'}).appendTo(section);
                var grid = $('<table>', {'class': 'table table-bordered table-striped'}).appendTo(table);
                var head = $('<thead>').appendTo(grid);
                var header = $('<tr>').appendTo(head);
                keys.forEach(function(field) { $('<th>', {scope: 'col', text: labelFor(field)}).appendTo(header); });
                $('<th>', {scope: 'col', text: 'Action'}).appendTo(header);
                var body = $('<tbody>').appendTo(grid);
                rows.forEach(function(row) { addArrayRow(body, keys, row,
                    (optional.arrays && optional.arrays[key]) || [], optional.choices); });
                var add = $('<button>', {type: 'button', 'class': 'btn btn-default btn-sm eis-request-add', text: 'Add ' + labelFor(key).replace(/s$/, '')}).appendTo(section);
                add.on('click', function() {
                    if (body.children().length >= (optional.limits && optional.limits[key] || 100)) return;
                    var empty = {};
                    keys.forEach(function(field) {
                        var sample = first[field];
                        empty[field] = typeof sample === 'number' ? 0 : (typeof sample === 'boolean' ? false : '');
                    });
                    addArrayRow(body, keys, empty, (optional.arrays && optional.arrays[key]) || [], optional.choices);
                });
                body.on('click', '.eis-request-remove', function() {
                    if (body.children().length > 1) $(this).closest('tr').remove();
                });
                $('<p>', {'class': 'help-block', text: 'Add each item as a separate row. At least one row is required.'}).appendTo(section);
                return;
            }
            var field = $('<div>', {'class': 'col-md-4 form-group'}).appendTo(fields);
            var input = inputFor(key, value, optional.fields && optional.fields.indexOf(key) !== -1,
                optional.choices && optional.choices[key]);
            $('<label>', {text: labelFor(key)}).attr('for', 'eis-field-' + key).appendTo(field);
            input.attr('id', 'eis-field-' + key).appendTo(field);
        });
    }

    function collect(target) {
        var result = {};
        $(target).find('> .row .eis-request-value').each(function() {
            var input = $(this), value = input.val(), key = input.attr('data-key');
            if (input.attr('data-optional') === 'true' && (value === '' || value === null)) return;
            if (input.attr('data-value-type') === 'boolean') value = value === 'true';
            if (input.attr('data-value-type') === 'number') value = value === '' ? '' : Number(value);
            result[key] = value;
        });
        $(target).find('.eis-request-array').each(function() {
            var section = $(this), key = section.attr('data-array-key'), rows = [];
            section.find('tbody tr').each(function() {
                var item = {};
                $(this).find('.eis-request-value').each(function() {
                    var input = $(this), value = input.val(), field = input.attr('data-key');
                    if (input.attr('data-optional') === 'true' && (value === '' || value === null)) return;
                    if (input.attr('data-value-type') === 'boolean') value = value === 'true';
                    if (input.attr('data-value-type') === 'number') value = value === '' ? '' : Number(value);
                    item[field] = value;
                });
                rows.push(item);
            });
            result[key] = rows;
        });
        return result;
    }

    return {render: render, collect: collect};
})();
</script>
