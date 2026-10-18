@if (session('status') || session('error'))
    @php $isError = (bool) session('error'); @endphp
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4500)" x-show="show" x-cloak
         class="toast" role="status">
        <div class="ic" @if($isError) style="background:var(--bad-soft);color:var(--bad)" @endif>
            @if ($isError)
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
            @else
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
            @endif
        </div>
        <div style="flex:1"><b>{{ session('error') ?? session('status') }}</b></div>
        <button type="button" @click="show = false" aria-label="Dismiss"
                style="border:none;background:transparent;color:var(--faint);cursor:pointer;font-size:16px;line-height:1">&times;</button>
    </div>
@endif
