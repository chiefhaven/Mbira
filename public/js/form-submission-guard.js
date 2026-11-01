(function () {
    'use strict';

    if (window.FormSubmissionGuard) {
        return;
    }

    var NAVIGATION_RELEASE_MS = 15000;
    var FOREIGN_TARGET_RELEASE_MS = 2000;
    var states = new WeakMap();
    var activeForm = null;
    var activeControl = null;
    var acceptedEvents = new WeakSet();
    var snapshots = new WeakMap();
    var nativeSubmit = HTMLFormElement.prototype.submit;

    function state(form) {
        if (!states.has(form)) {
            states.set(form, { pending: 0, navigating: false, controls: [], busy: null, timer: null });
        }
        return states.get(form);
    }

    function busy(form) {
        var current = state(form);
        return current.pending > 0 || current.navigating;
    }

    function lock(form, control) {
        var current = state(form);
        if (current.controls.length) {
            return;
        }
        current.busy = form.getAttribute('aria-busy');
        form.setAttribute('aria-busy', 'true');
        var controls = Array.prototype.filter.call(form.elements, function (element) {
            return element.type === 'submit' || element.type === 'image';
        });
        form.querySelectorAll('.actions a[href="#finish"]').forEach(function (element) {
            controls.push(element);
        });
        if (control && controls.indexOf(control) === -1) {
            controls.push(control);
        }
        current.controls = controls.map(function (element) {
            var original = {
                element: element,
                disabled: snapshots.has(element) ? snapshots.get(element) : element.disabled,
                aria: element.getAttribute('aria-disabled'),
                opacity: element.style.opacity,
                cursor: element.style.cursor
            };
            if ('disabled' in element) {
                element.disabled = true;
            }
            element.setAttribute('aria-disabled', 'true');
            element.style.opacity = '0.6';
            element.style.cursor = 'wait';
            return original;
        });
    }

    function restore(form) {
        var current = state(form);
        if (busy(form)) {
            return;
        }
        current.controls.forEach(function (original) {
            var element = original.element;
            if ('disabled' in element) {
                element.disabled = original.disabled;
            }
            if (original.aria === null) {
                element.removeAttribute('aria-disabled');
            } else {
                element.setAttribute('aria-disabled', original.aria);
            }
            element.style.opacity = original.opacity;
            element.style.cursor = original.cursor;
        });
        current.controls = [];
        if (current.busy === null) {
            form.removeAttribute('aria-busy');
        } else {
            form.setAttribute('aria-busy', current.busy);
        }
    }

    function releaseDelay(form, control) {
        // getAttribute avoids DOM clobbering by fields named "target".
        var target = (control && control.getAttribute && control.getAttribute('formtarget')) ||
            form.getAttribute('target') || '';
        target = String(target).toLowerCase();
        if (target === '' || target === '_self' || target === '_top' || target === '_parent') {
            return NAVIGATION_RELEASE_MS;
        }
        // Submissions into another tab/window never unload this page.
        return FOREIGN_TARGET_RELEASE_MS;
    }

    function cancelRelease(form) {
        var current = state(form);
        if (current.timer !== null) {
            clearTimeout(current.timer);
            current.timer = null;
        }
    }

    function navigate(form, control) {
        var current = state(form);
        current.navigating = true;
        lock(form, control);
        cancelRelease(form);
        // Downloads, new tabs, cancelled unloads and unreachable servers leave
        // the page alive; release the lock so failed processes can be retried.
        current.timer = setTimeout(function () {
            current.timer = null;
            current.navigating = false;
            restore(form);
        }, releaseDelay(form, control));
    }

    function context(form, control, callback) {
        var previousForm = activeForm;
        var previousControl = activeControl;
        activeForm = form;
        activeControl = control;
        try {
            return callback();
        } finally {
            activeForm = previousForm;
            activeControl = previousControl;
        }
    }

    function formFor(control) {
        return control && (control.form || control.closest('form'));
    }

    function prevent(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }

    function accept(event, form, control) {
        acceptedEvents.add(event);
        Array.prototype.forEach.call(form.elements, function (element) {
            snapshots.set(element, element.disabled);
        });
        activeForm = form;
        activeControl = control;
        Promise.resolve().then(function () {
            activeForm = null;
            activeControl = null;
        });
    }

    document.addEventListener('click', function (event) {
        var control = event.target.closest('button, input[type="submit"], input[type="image"], .actions a');
        var form = formFor(control);
        if (form && busy(form)) {
            prevent(event);
        } else if (form) {
            accept(event, form, control);
        }
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (busy(form)) {
            prevent(event);
            return;
        }
        accept(event, form, event.submitter);
        // Wait until validation and submit handlers decide whether navigation occurs.
        Promise.resolve().then(function () {
            if (!event.defaultPrevented && !state(form).navigating) {
                navigate(form, event.submitter);
            }
        });
    }, true);

    HTMLFormElement.prototype.submit = function () {
        if (busy(this)) {
            return;
        }
        // Serialize the native submitter/fields before disabling controls.
        nativeSubmit.call(this);
        navigate(this);
    };

    var $ = window.jQuery;
    if ($) {
        var dispatch = $.event.dispatch;
        $.event.dispatch = function (event) {
            var receiver = this;
            var args = arguments;
            var control = event.target.closest
                ? event.target.closest('button, input[type="submit"], input[type="image"], .actions a')
                : null;
            var form = event.type === 'submit' ? event.target : formFor(control);
            if (form && (event.type === 'submit' || event.type === 'click')) {
                var originalEvent = event.originalEvent || event;
                if (busy(form) && !acceptedEvents.has(originalEvent)) {
                    prevent(event);
                    return;
                }
                if (!acceptedEvents.has(originalEvent)) {
                    accept(originalEvent, form, control);
                }
                return context(form, control, function () {
                    return dispatch.apply(receiver, args);
                });
            }
            return dispatch.apply(receiver, args);
        };

        $.ajaxPrefilter(function (options, originalOptions, xhr) {
            var form = activeForm;
            // Remote validation must be able to resume the validated submission.
            if (!form || (options.mode === 'abort' && String(options.port).indexOf('validate') === 0)) {
                return;
            }
            var current = state(form);
            var control = activeControl;
            current.pending++;
            lock(form, activeControl);
            ['success', 'error', 'complete'].forEach(function (name) {
                var callbacks = options[name];
                if (!callbacks) {
                    return;
                }
                options[name] = function () {
                    var receiver = this;
                    var args = arguments;
                    return context(form, control, function () {
                        [].concat(callbacks).forEach(function (callback) {
                            callback.apply(receiver, args);
                        });
                    });
                };
            });
            xhr.always(function () {
                current.pending--;
                // Existing complete handlers may also update the buttons.
                Promise.resolve().then(function () { restore(form); });
            });
        });
    }

    if (window.fetch) {
        var fetch = window.fetch;
        window.fetch = function () {
            var form = activeForm;
            var request = fetch.apply(this, arguments);
            if (!form) {
                return request;
            }
            state(form).pending++;
            lock(form, activeControl);
            function complete() {
                state(form).pending--;
                Promise.resolve().then(function () { restore(form); });
            }
            return request.then(function (response) {
                complete();
                return response;
            }, function (error) {
                complete();
                throw error;
            });
        };
    }

    window.addEventListener('pagehide', function () {
        // The page is actually unloading; stop release timers so a bfcache
        // resume does not fire them before pageshow restores the forms.
        document.querySelectorAll('form').forEach(function (form) {
            if (states.has(form)) {
                cancelRelease(form);
            }
        });
    });

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form').forEach(function (form) {
            if (states.has(form)) {
                cancelRelease(form);
                state(form).navigating = false;
                restore(form);
            }
        });
    });

    window.FormSubmissionGuard = { isBusy: busy };
}());
