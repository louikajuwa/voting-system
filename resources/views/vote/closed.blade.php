<x-public-layout :title="$election->title">
    <div class="voter-wrap" style="max-width:520px;text-align:center;padding-top:36px">
        <div style="width:60px;height:60px;border-radius:50%;background:var(--surface-2);color:var(--faint);display:grid;place-items:center;margin:0 auto">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <h1 style="margin-top:18px">{{ $election->title }}</h1>
        <p style="color:var(--muted);margin-top:8px">
            @if ($election->status === 'closed')
                Voting for this election has closed.
            @elseif ($election->opens_at && now()->lt($election->opens_at))
                Voting opens {{ $election->opens_at->diffForHumans() }}.
            @else
                Voting isn't open right now.
            @endif
        </p>
        <div style="margin-top:22px;display:flex;gap:8px;justify-content:center">
            <a href="{{ route('results.show', $election) }}" class="btn primary">View results</a>
            <a href="{{ route('home') }}" class="btn">Other elections</a>
        </div>
    </div>
</x-public-layout>
