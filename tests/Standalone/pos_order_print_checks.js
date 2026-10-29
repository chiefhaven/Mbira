// Executes the real POS completion callback with no browser, server, or printer.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
const path = require('path');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'public/js/pos.js'), 'utf8');
const start = source.indexOf('success: function(result)', source.indexOf("data = data + '&status=final'"));
assert(start !== -1);
const bodyStart = source.indexOf('{', start);
let depth = 1;
let end = bodyStart + 1;
for (; depth > 0; end++) {
    if (source[end] === '{') depth++;
    if (source[end] === '}') depth--;
}
const callback = source.slice(bodyStart + 1, end - 1);
let checks = 0;
for (const [result, expected] of [
    [{success: 1, msg: 'Placed', receipt: {is_enabled: true}}, ['hide', 'success', 'print', 'reset', 'enable']],
    [{success: 1, msg: 'Placed', receipt: {is_enabled: false}}, ['hide', 'success', 'reset', 'enable']],
    [{success: 1, msg: 'Placed', receipt: ''}, ['hide', 'success', 'reset', 'enable']],
    [{success: 0, msg: 'Validation failed'}, ['error', 'enable']],
]) {
    const events = [];
    const context = {
        result,
        $: () => ({modal: () => events.push('hide')}),
        toastr: {success: () => events.push('success'), error: () => events.push('error')},
        pos_print: receipt => {assert.equal(receipt, result.receipt); events.push('print');},
        reset_pos_form: () => events.push('reset'),
        enable_pos_form_actions: () => events.push('enable'),
    };
    vm.runInNewContext(callback, context);
    assert.deepEqual(events, expected);
    checks++;
}
const controller = fs.readFileSync(path.join(root, 'app/Http/Controllers/SellPosController.php'), 'utf8');
assert.equal((controller.match(/\$from_pos_screen = empty\(\$input\['is_credit_sale'\]\);/g) || []).length, 2);
checks++;
const actions = fs.readFileSync(path.join(root, 'resources/views/sale_pos/partials/pos_form_actions.blade.php'), 'utf8');
assert.match(actions, /data-pay_method="credit_sale"[\s\S]*?@lang\('lang_v1.place_order'\)/);
checks++;
console.log(`PASS: ${checks} POS order auto-print checks (no real printing or sales).`);