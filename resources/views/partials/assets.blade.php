{{-- No build step. BallotBox design system (ballotbox.css) drives every page. --}}
<link rel="stylesheet" href="{{ asset('css/ballotbox.css') }}?v={{ @filemtime(public_path('css/ballotbox.css')) ?: 1 }}">

<script>
    // Apply theme before paint (no flash). Sets data-theme (ballotbox tokens).
    // toggleTheme() flips + persists.
    (function () {
        try {
            var d = localStorage.theme === 'dark'
                || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = d ? 'dark' : 'light';
        } catch (e) {}
    })();
    function toggleTheme() {
        var d = document.documentElement.dataset.theme !== 'dark';
        document.documentElement.dataset.theme = d ? 'dark' : 'light';
        try { localStorage.theme = d ? 'dark' : 'light'; } catch (e) {}
    }
</script>

{{-- Shared AJAX + toast helpers (defined before Alpine so x-data="ajaxForm()" resolves). --}}
@verbatim
<script>
    window.CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

    // Client-side toast — stacks in a fixed flex-column container; auto-dismisses.
    // Errors use role="alert" (assertive), everything else role="status".
    window.toast = function (message, type) {
        if (!message) return;
        var isErr = type === 'error';
        var box = document.querySelector('.toasts');
        if (!box) {
            box = document.createElement('div');
            box.className = 'toasts';
            document.body.appendChild(box);
        }
        var el = document.createElement('div');
        el.className = 'toast' + (isErr ? ' error' : '');
        el.setAttribute('role', isErr ? 'alert' : 'status');
        el.innerHTML =
            '<div class="ic">' +
            (isErr
                ? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>'
                : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>') +
            '</div><div style="flex:1"><b></b></div>' +
            '<button type="button" class="x" aria-label="Dismiss">&times;</button>';
        el.querySelector('b').textContent = message;
        var gone = false;
        function dismiss() {
            if (gone) return; gone = true;
            el.style.transition = 'opacity .3s'; el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }
        el.querySelector('.x').addEventListener('click', dismiss);
        box.appendChild(el);
        setTimeout(dismiss, isErr ? 6500 : 4200);
    };

    // Session handling shared by every fetch helper.
    // 401 → toast + redirect to /login; 419 → toast + reload. Returns true when handled.
    window.handleSession = function (res) {
        if (res.status === 401) {
            window.toast('Session expired — redirecting to log in…', 'error');
            setTimeout(function () { location.href = '/login'; }, 1500);
            return true;
        }
        if (res.status === 419) {
            window.toast('This page expired — reloading…', 'error');
            setTimeout(function () { location.reload(); }, 1500);
            return true;
        }
        return false;
    };

    // fetch() wrapper: JSON headers, CSRF on writes, session-expiry handling.
    // Throws an Error with .session = true when the session handler took over.
    window.api = async function (url, opts) {
        opts = opts || {};
        var method = (opts.method || 'GET').toUpperCase();
        opts.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
        if (method !== 'GET') opts.headers['X-CSRF-TOKEN'] = window.CSRF;
        var res = await fetch(url, opts);
        if (window.handleSession(res)) { var e = new Error('session'); e.session = true; throw e; }
        return res;
    };

    // Standard "request failed before we got a response" toast (silent for session errors).
    window.netError = function (e, msg) {
        if (e && e.session) return;
        window.toast(msg || 'Network error — check your connection.', 'error');
    };

    // Alpine factory for AJAX form submission (no page reload).
    // Usage: <form x-data="ajaxForm({ onOk: (data, self, form) => {...} })" @submit.prevent="submit($event)">
    // Field errors: <p class="err" x-text="err('title')" x-show="err('title')"></p>
    window.ajaxForm = function (opts) {
        opts = opts || {};
        return {
            busy: false,
            errors: {},
            err: function (field) { return (this.errors[field] || [])[0]; },
            reset: function (form) { this.errors = {}; this.busy = false; if (form && form.reset) form.reset(); },
            async submit($event) {
                var form = $event.target;
                if (this.busy) return;
                this.busy = true;
                this.errors = {};
                try {
                    var res = await window.api(form.getAttribute('action'), { method: 'POST', body: new FormData(form) });
                    if (res.status === 422) {
                        this.errors = (await res.json().catch(function () { return {}; })).errors || {};
                        return;
                    }
                    var json = await res.json().catch(function () { return {}; });
                    if (!res.ok || json.result === 'error') {
                        var fallback = res.status === 429 ? 'Too many attempts — wait a minute and try again.'
                            : 'Something went wrong. Please try again.';
                        window.toast(json.message || fallback, 'error');
                        return;
                    }
                    if (json.message) window.toast(json.message, 'ok');
                    if (opts.onOk) opts.onOk(json.data || {}, this, form);
                } catch (e) {
                    window.netError(e);
                } finally {
                    this.busy = false;
                }
            },
        };
    };

    // x-modal="openFlag": dialog semantics, focus into modal on open, focus trap, focus back to the
    // trigger on close, background scroll lock. Put it on the .modal-overlay element.
    document.addEventListener('alpine:init', function () {
        var uid = 0, locks = 0;
        var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
        window.Alpine.directive('modal', function (el, d, u) {
            var get = u.evaluateLater(d.expression), was = false, prev = null;
            var box = el.querySelector('.modal-box') || el;
            var title = box.querySelector('h3, h2');
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            if (title) { title.id = title.id || ('modal-title-' + (++uid)); box.setAttribute('aria-labelledby', title.id); }
            box.setAttribute('tabindex', '-1');
            function visible(n) { return n.offsetParent !== null; }
            el.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab') return;
                var items = Array.prototype.filter.call(box.querySelectorAll(FOCUSABLE), visible);
                if (!items.length) { e.preventDefault(); box.focus(); return; }
                var first = items[0], last = items[items.length - 1];
                if (e.shiftKey && (document.activeElement === first || document.activeElement === box)) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            });
            u.effect(function () {
                get(function (open) {
                    open = !!open;
                    if (open && !was) {
                        prev = document.activeElement;
                        locks++; document.documentElement.style.overflow = 'hidden';
                        setTimeout(function () {
                            var f = box.querySelector('[autofocus]') ||
                                Array.prototype.filter.call(box.querySelectorAll('input:not([type=hidden]):not([type=file]):not([disabled]),select,textarea'), visible)[0] ||
                                Array.prototype.filter.call(box.querySelectorAll(FOCUSABLE), visible)[0] || box;
                            f.focus();
                        }, 40);
                    } else if (!open && was) {
                        locks = Math.max(0, locks - 1);
                        if (!locks) document.documentElement.style.overflow = '';
                        if (prev && prev.focus && document.contains(prev)) prev.focus();
                        prev = null;
                    }
                    was = open;
                });
            });
        });
    });
</script>
@endverbatim

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"
        integrity="sha384-l8f0VcPi/M1iHPv8egOnY/15TDwqgbOR1anMIJWvU6nLRgZVLTLSaNqi/TOoT5Fh" crossorigin="anonymous"></script>
