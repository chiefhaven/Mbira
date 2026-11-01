'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor(type) {
        this.type = type;
        this.disabled = false;
        this.attributes = new Map();
        this.style = { opacity: '', cursor: '' };
    }
    getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
    setAttribute(name, value) { this.attributes.set(name, value); }
    removeAttribute(name) { this.attributes.delete(name); }
    closest() { return this.form || null; }
}

class Form extends Element {
    constructor() {
        super();
        this.elements = [new Element('submit'), new Element('submit')];
        this.elements.forEach(element => { element.form = this; });
        this.elements[1].disabled = true;
        this.submissions = 0;
        this.payloadButtonEnabled = null;
    }
    submit() {
        this.submissions++;
        this.payloadButtonEnabled = !this.elements[0].disabled;
    }
    querySelectorAll() { return []; }
}

const forms = [];
const listeners = {};
const windowListeners = {};
const timers = new Map();
let timerId = 0;
function setTimeout(callback, delay) {
    timerId++;
    timers.set(timerId, { callback, delay });
    return timerId;
}
function clearTimeout(id) {
    timers.delete(id);
}
function fireTimers() {
    const pending = [...timers.values()];
    timers.clear();
    pending.forEach(timer => timer.callback());
}
let prefilter;
let dispatchHandler = () => {};
let fetchResolve;
let fetchReject;
const jquery = {
    event: { dispatch: function (event) { return dispatchHandler(event); } },
    ajaxPrefilter: callback => { prefilter = callback; }
};
const window = {
    jQuery: jquery,
    addEventListener: (name, callback) => { windowListeners[name] = callback; },
    fetch: () => new Promise((resolve, reject) => {
        fetchResolve = resolve;
        fetchReject = reject;
    })
};
const document = {
    addEventListener: (name, callback) => { listeners[name] = callback; },
    querySelectorAll: () => forms
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/js/form-submission-guard.js'), 'utf8'), {
    window, document, HTMLFormElement: Form, WeakMap, WeakSet, Promise, setTimeout, clearTimeout
});

function form() {
    const result = new Form();
    forms.push(result);
    return result;
}

function event(form, prevented = false) {
    return {
        target: form,
        type: 'submit',
        submitter: form.elements[0],
        defaultPrevented: prevented,
        preventDefault() { this.defaultPrevented = true; },
        stopImmediatePropagation() { this.stopped = true; }
    };
}

function request(options = {}) {
    let done;
    prefilter(options, options, { always: callback => { done = callback; } });
    return () => {
        if (done) { done(); }
        if (options.success) { options.success({}); }
        if (options.complete) { options.complete(); }
    };
}

async function main() {
    const invalid = form();
    listeners.submit(event(invalid, true));
    await Promise.resolve();
    assert.equal(invalid.elements[0].disabled, false, 'Cancelled/invalid submission remains enabled');

    const ajax = form();
    const submit = event(ajax, true);
    listeners.submit(submit);
    // Simulate existing submit handlers that disable their own button.
    ajax.elements[0].disabled = true;
    const finish = request();
    assert.equal(window.FormSubmissionGuard.isBusy(ajax), true);
    assert.equal(ajax.getAttribute('aria-busy'), 'true');
    const duplicate = event(ajax);
    listeners.submit(duplicate);
    assert.equal(duplicate.defaultPrevented, true);
    assert.equal(duplicate.stopped, true);
    finish();
    await Promise.resolve();
    assert.equal(ajax.elements[0].disabled, false, 'AJAX completion restores pre-handler state');
    assert.equal(ajax.elements[1].disabled, true, 'Previously disabled controls stay disabled');
    assert.equal(ajax.getAttribute('aria-busy'), null);

    const native = form();
    native.submit();
    native.submit();
    assert.equal(native.submissions, 1, 'Programmatic native submissions are deduplicated');
    assert.equal(native.payloadButtonEnabled, true, 'Controls are disabled after native serialization');
    assert.equal(native.elements[0].disabled, true);
    windowListeners.pageshow();
    assert.equal(native.elements[0].disabled, false, 'Back navigation restores buttons');

    const navigation = form();
    listeners.submit(event(navigation));
    await Promise.resolve();
    assert.equal(navigation.elements[0].disabled, true, 'Ordinary native navigation locks controls');
    windowListeners.pageshow();

    const validation = form();
    listeners.submit(event(validation, true));
    request({ mode: 'abort', port: 'validateemail' });
    assert.equal(validation.elements[0].disabled, false, 'Remote validation is not a pending submission');
    await Promise.resolve();

    const delegated = form();
    const synthetic = event(delegated, true);
    let finishDelegated;
    dispatchHandler = () => { finishDelegated = request(); };
    jquery.event.dispatch(synthetic);
    assert.equal(delegated.elements[0].disabled, true, 'Synthetic jQuery submissions are tracked');
    dispatchHandler = () => { throw new Error('Duplicate handler executed'); };
    jquery.event.dispatch(event(delegated, true));
    finishDelegated();
    await Promise.resolve();
    assert.equal(delegated.elements[0].disabled, false);

    const chained = form();
    listeners.submit(event(chained, true));
    let finishSecond;
    const finishFirst = request({ success: () => { finishSecond = request(); } });
    await Promise.resolve();
    finishFirst();
    await Promise.resolve();
    assert.equal(chained.elements[0].disabled, true, 'Chained AJAX requests keep the form locked');
    finishSecond();
    await Promise.resolve();
    assert.equal(chained.elements[0].disabled, false);

    const parallel = form();
    listeners.submit(event(parallel, true));
    const finishA = request();
    const finishB = request();
    finishA();
    await Promise.resolve();
    assert.equal(parallel.elements[0].disabled, true, 'Other pending requests keep controls locked');
    finishB();
    await Promise.resolve();
    assert.equal(parallel.elements[0].disabled, false);

    const fetching = form();
    listeners.submit(event(fetching, true));
    const pendingFetch = window.fetch('/test');
    assert.equal(fetching.elements[0].disabled, true);
    fetchResolve({ ok: true });
    await pendingFetch;
    await Promise.resolve();
    assert.equal(fetching.elements[0].disabled, false, 'Fetch completion restores controls');
    const failing = form();
    listeners.submit(event(failing, true));
    const failedFetch = window.fetch('/failure');
    fetchReject(new Error('Network failed'));
    await assert.rejects(failedFetch, /Network failed/);
    await Promise.resolve();
    assert.equal(failing.elements[0].disabled, false, 'Fetch failures restore controls without hiding errors');

    const stalled = form();
    listeners.submit(event(stalled));
    await Promise.resolve();
    assert.equal(stalled.elements[0].disabled, true);
    assert.equal(timers.size, 1, 'Navigation locks schedule a release');
    assert.equal([...timers.values()][0].delay, 15000, 'Same-window navigations use the long release');
    fireTimers();
    assert.equal(stalled.elements[0].disabled, false, 'Failed/stalled navigation re-enables controls');
    const retry = event(stalled);
    listeners.submit(retry);
    assert.notEqual(retry.defaultPrevented, true, 'Released forms accept a retry');
    await Promise.resolve();
    windowListeners.pageshow();

    const download = form();
    download.setAttribute('target', '_blank');
    listeners.submit(event(download));
    await Promise.resolve();
    assert.equal(download.elements[0].disabled, true);
    assert.equal([...timers.values()][0].delay, 2000, 'Foreign-target submissions release quickly');
    fireTimers();
    assert.equal(download.elements[0].disabled, false, 'Download/new-tab submissions re-enable controls');

    const mixed = form();
    listeners.submit(event(mixed));
    const finishMixed = request();
    await Promise.resolve();
    fireTimers();
    assert.equal(mixed.elements[0].disabled, true, 'Pending requests keep controls locked after release');
    finishMixed();
    await Promise.resolve();
    assert.equal(mixed.elements[0].disabled, false, 'AJAX completion restores after navigation release');

    const unloading = form();
    listeners.submit(event(unloading));
    await Promise.resolve();
    windowListeners.pagehide();
    assert.equal(timers.size, 0, 'Actual unloads cancel the release timer');
    windowListeners.pageshow();
    assert.equal(unloading.elements[0].disabled, false, 'Returning via Back still restores controls');

    console.log('PASS: form submission guard checks (no dependencies or network).');
}

main().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
