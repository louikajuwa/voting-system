<x-public-layout title="Live elections">
    <div class="page-h"><div><h1>Live elections</h1><p>Cast your ballot in an open election below.</p></div></div>

    <div class="grid2">
        @forelse ($elections as $election)
            <div class="card pad">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <x-status-pill status="live" />
                    <span class="mono">{{ number_format($election->votes_count) }} votes</span>
                </div>
                <h3 style="margin:14px 0 2px;font-size:17px;letter-spacing:-.01em">{{ $election->title }}</h3>
                <div style="color:var(--faint);font-size:13px">{{ $election->organization?->name }}</div>
                <div style="margin-top:16px;display:flex;gap:8px">
                    <a href="{{ route('vote.show', $election) }}" class="btn primary">Vote</a>
                    <a href="{{ route('results.show', $election) }}" class="btn">Results</a>
                </div>
            </div>
        @empty
            <div class="card pad" style="grid-column:1/-1">
                <div class="empty"><div class="big">🗳️</div><p>No live elections right now. Check back when voting opens.</p></div>
            </div>
        @endforelse
    </div>
</x-public-layout>
