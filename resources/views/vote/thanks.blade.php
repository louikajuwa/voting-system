<x-public-layout title="Vote recorded">
    <div class="voter-wrap" style="max-width:440px;text-align:center;padding-top:32px">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--good-soft);color:var(--good);display:grid;place-items:center;margin:0 auto">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <h1 style="margin-top:20px">Your vote is counted</h1>
        <p style="color:var(--muted);margin-top:8px">Thanks for voting in <b>{{ $election->title }}</b>.</p>
        <div style="margin-top:22px;display:flex;gap:8px;justify-content:center">
            <a href="{{ route('results.show', $election) }}" class="btn primary">View live results</a>
            <a href="{{ route('home') }}" class="btn">Other elections</a>
        </div>
    </div>
</x-public-layout>
