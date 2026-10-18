@if (session('status') || session('error'))
    {{-- Rendered through the shared client toast() so server + client toasts stack together. --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.toast(@js(session('error') ?? session('status')), @js(session('error') ? 'error' : 'ok'));
        });
    </script>
@endif
