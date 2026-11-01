'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'public/js/app.js'), 'utf8');
const view = fs.readFileSync(path.join(root, 'resources/views/notification/show_template.blade.php'), 'utf8');
const start = source.indexOf('function init_notification_email_editor(');
const end = source.indexOf("$(document).on('shown.bs.modal', '.quick_add_product_modal'", start);
assert(start >= 0 && end > start, 'Notification editor lifecycle code exists');

const handlers = {};
const editors = new Map();
const initialized = [];
const removed = [];
const document = {};
let currentModal;

function wrap(modal) {
    return {
        find: () => modal.emailBody ? [modal.emailBody] : [],
        hasClass: name => name === 'in' && modal.open,
    };
}
function $(target) {
    if (target === document) {
        return { on: (event, selector, callback) => { handlers[event] = callback; } };
    }
    if (target === '#send_notification_form') {
        return { closest: () => wrap(currentModal) };
    }
    if (target === 'body') {
        return { addClass: () => {} };
    }
    if (target === '.modal.in') {
        return { length: currentModal.open ? 1 : 0 };
    }
    return target.find ? target : wrap(target);
}
const tinymce = {
    activeEditor: { id: 'unrelated_editor' },
    get: id => editors.get(id),
    init: options => {
        assert(options.target, 'Initialize the specific field, not a global selector');
        initialized.push(options.target);
        const editor = {
            getElement: () => options.target,
            remove: () => {
                removed.push(options.target);
                editors.delete(options.target.id);
            },
        };
        editors.set(options.target.id, editor);
    },
    remove: () => {
        const editor = editors.get('email_body');
        if (editor) { editor.remove(); }
    },
};
const context = vm.createContext({ $, document, tinymce });
vm.runInContext(source.slice(start, end), context);

const readyStart = view.indexOf("var modal = $('#send_notification_form')");
const readyEnd = view.indexOf('//initialize iCheck', readyStart);
assert(readyStart >= 0 && readyEnd > readyStart, 'Loaded modals initialize their email field');
const onReady = () => vm.runInContext(view.slice(readyStart, readyEnd), context);
const modal = (value, open = true) => ({ open, emailBody: { id: 'email_body', value } });

currentModal = modal('<p>Payment reminder: {due_amount}</p>', false);
onReady();
assert.equal(initialized.length, 0, 'Closed modal waits until it is shown');
currentModal.open = true;
handlers['shown.bs.modal'].call(currentModal);
assert.equal(initialized[0], currentModal.emailBody, 'Shown modal displays its current body');
onReady();
assert.equal(initialized.length, 1, 'Ready and shown events do not initialize twice');

const originalField = currentModal.emailBody;
currentModal = modal('<p>Updated reminder</p>');
onReady();
assert.equal(removed[0], originalField, 'Replacing an open modal removes the stale editor');
assert.equal(initialized[1], currentModal.emailBody, 'Replacement initializes without another shown event');
assert.equal(currentModal.emailBody.value, '<p>Updated reminder</p>', 'Current template contents are preserved');
assert.equal(tinymce.activeEditor.id, 'unrelated_editor', 'Other active editors do not block initialization');

handlers['hidden.bs.modal'].call(currentModal);
assert.equal(editors.has('email_body'), false, 'Closing removes the notification editor');
currentModal = modal('<p>Reopened reminder</p>');
handlers['shown.bs.modal'].call(currentModal);
assert.equal(initialized[2], currentModal.emailBody, 'Reopening displays the new template');

context.init_notification_email_editor({ open: true });
assert.equal(initialized.length, 3, 'Non-notification modals do not initialize an editor');
assert.doesNotMatch(view, /tinyMCE\.activeEditor/, 'Initialization must not depend on the global active editor');
const entrypoint = fs.readFileSync(path.join(root, 'public/index.php'), 'utf8');
const autoload = entrypoint.indexOf("require __DIR__.'/../vendor/autoload.php'");
assert(autoload > 0);
assert.match(entrypoint.slice(0, autoload), /ini_set\('log_errors', '1'\)/, 'Early PHP diagnostics remain logged');
assert.match(entrypoint.slice(0, autoload), /ini_set\('display_errors', '0'\)/, 'Diagnostics cannot precede the doctype and break TinyMCE');
console.log('PASS: notification email editor lifecycle checks (no dependencies or network).');
