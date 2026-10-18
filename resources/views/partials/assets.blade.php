{{-- No build step. BallotBox design system (ballotbox.css) drives all custom pages;
     Tailwind Play CDN remains only for the Breeze auth/profile forms. --}}
<link rel="stylesheet" href="{{ asset('css/ballotbox.css') }}">

<script>
    // Apply theme before paint (no flash). Sets data-theme (ballotbox tokens)
    // and the .dark class (Tailwind/Breeze). toggleTheme() flips + persists.
    (function () {
        try {
            var d = localStorage.theme === 'dark'
                || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = d ? 'dark' : 'light';
            document.documentElement.classList.toggle('dark', d);
        } catch (e) {}
    })();
    function toggleTheme() {
        var d = document.documentElement.dataset.theme !== 'dark';
        document.documentElement.dataset.theme = d ? 'dark' : 'light';
        document.documentElement.classList.toggle('dark', d);
        localStorage.theme = d ? 'dark' : 'light';
    }
</script>

<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: { extend: { colors: { brand: { DEFAULT: '#2F6BED', 600: '#2F6BED', 700: '#2556c9' } } } },
    };
</script>

{{-- Shared AJAX + toast helpers (defined before Alpine so x-data="ajaxForm()" resolves). --}}
@verbatim
<script>
    window.CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

    // Client-side toast — mirrors the server <x-flash> look, auto-dismisses.
    window.toast = function (message, type) {
        if (!message) return;
        var isErr = type === 'error';
        var el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'status');
        el.innerHTML =
            '<div class="ic"' + (isErr ? ' style="background:var(--bad-soft);color:var(--bad)"' : '') + '>' +
            (isErr
                ? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>'
                : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>') +
            '</div><div style="flex:1"><b></b></div>';
        el.querySelector('b').textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 300); }, 4200);
    };

    // Alpine factory for AJAX form submission (no page reload).
    // Usage: <form x-data="ajaxForm({ onOk: (json, self) => {...} })" @submit.prevent="submit($event)">
    // Field errors: <p class="err" x-text="err('title')" x-show="err('title')"></p>
    window.ajaxForm = function (opts) {
        opts = opts || {};
        return {
            busy: false,
            errors: {},
            err: function (field) { return (this.errors[field] || [])[0]; },
            async submit($event) {
                var form = $event.target;
                if (this.busy) return;
                this.busy = true;
                this.errors = {};
                try {
                    var res = await fetch(form.getAttribute('action'), {
                        method: 'POST', // PUT/PATCH/DELETE are spoofed via the _method field
                        headers: { 'X-CSRF-TOKEN': window.CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(form),
                    });
                    if (res.status === 422) { this.errors = (await res.json()).errors || {}; return; }
                    var json = await res.json().catch(function () { return {}; });
                    if (!res.ok || json.result === 'error') {
                        window.toast(json.message || 'Something went wrong. Please try again.', 'error');
                        return;
                    }
                    if (json.message) window.toast(json.message, 'ok');
                    if (opts.onOk) opts.onOk(json.data || {}, this);
                } catch (e) {
                    window.toast('Network error — check your connection.', 'error');
                } finally {
                    this.busy = false;
                }
            },
        };
    };
</script>
@endverbatim

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
