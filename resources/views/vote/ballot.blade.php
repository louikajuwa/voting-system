<x-public-layout :title="$election->title">
    <div class="voter-wrap"
         x-data="{
            candidate: {{ old('candidate_id', 'null') }},
            email: @js(old('email', '')),
            busy: false, done: false, errorMsg: '',
            get validEmail() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim()); },
            get ready() { return this.candidate !== null && this.validEmail; },
            async cast($event) {
                if (!this.ready || this.busy) return;
                this.busy = true; this.errorMsg = '';
                try {
                    const res = await fetch($event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': window.CSRF, 'Accept': 'application/json' },
                        body: new FormData($event.target),
                    });
                    if (res.status === 422) { const j = await res.json(); this.errorMsg = Object.values(j.errors)[0][0]; return; }
                    const j = await res.json().catch(() => ({}));
                    if (!res.ok || j.result === 'error') { this.errorMsg = j.message || 'Something went wrong. Please try again.'; return; }
                    this.done = true;
                } catch (e) { this.errorMsg = 'Network error — check your connection.'; }
                finally { this.busy = false; }
            }
         }">

        {{-- Success state (no navigation) --}}
        <div x-show="done" x-cloak style="max-width:440px;margin:0 auto;text-align:center;padding-top:32px">
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

        <div x-show="!done">
        <div class="voter-hero">
            <x-status-pill status="live" />
            <h1 style="margin-top:12px">{{ $election->title }}</h1>
            @if ($election->description)
                <p>{{ $election->description }}</p>
            @endif
            <p style="color:var(--faint)">Choose one candidate. You can vote once, and your ballot is final.</p>
            <div class="steps"><b style="color:var(--accent)">1 · Select</b> → 2 · Confirm → 3 · Done</div>
        </div>

        <div class="alert err" x-show="errorMsg" x-cloak x-text="errorMsg"></div>

        <form method="POST" action="{{ route('vote.store', $election) }}" @submit.prevent="cast($event)">
            @csrf

            <div class="field" style="margin-top:6px">
                <label>Your email <span class="hint">· confirms eligibility &amp; blocks duplicate votes</span></label>
                <input type="email" name="email" x-model="email" placeholder="you@acme.edu" required
                       :class="email && !validEmail ? 'bad' : ''">
            </div>

            @foreach ($election->candidates as $c)
                @php $url = $c->photoUrl(); @endphp
                <label class="vcard" :class="candidate === {{ $c->id }} ? 'sel' : ''">
                    <input type="radio" name="candidate_id" value="{{ $c->id }}" x-model.number="candidate" style="display:none">
                    <div class="pic">
                        @if ($url)
                            <img src="{{ $url }}" alt="{{ $c->name }}">
                        @else
                            {{ \Illuminate\Support\Str::of($c->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                        @endif
                    </div>
                    <div class="info">
                        <h4>{{ $c->name }}</h4>
                        <div class="role">{{ $c->position }}@if ($c->party) · {{ $c->party }}@endif</div>
                        @if ($c->bio)<div class="bio">{{ $c->bio }}</div>@endif
                        @if ($c->manifesto)
                            <div x-data="{ m: false }" style="margin-top:6px">
                                <button type="button" class="link" style="font-size:12px;background:none;border:none;cursor:pointer;padding:0"
                                        @click.prevent.stop="m = !m" x-text="m ? 'Hide manifesto' : 'Read manifesto'"></button>
                                <div x-show="m" x-cloak style="margin-top:6px;font-size:13px;color:var(--muted);white-space:pre-line">{{ $c->manifesto }}</div>
                            </div>
                        @endif
                    </div>
                    <span class="vradio"></span>
                </label>
            @endforeach

            <div class="vote-bar">
                <span class="sel-name" x-show="!ready"><span>Select a candidate and enter your email</span></span>
                <span class="sel-name" x-show="ready" x-cloak>Ready to submit</span>
                <div style="flex:1"></div>
                <button type="submit" class="btn primary" :disabled="!ready || busy">
                    <span x-show="!busy">Submit vote</span>
                    <span x-show="busy" x-cloak><span class="spin"></span></span>
                </button>
            </div>

            <div class="secure-foot">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                One vote per email · duplicates blocked at the database
            </div>
        </form>
        </div>
    </div>
</x-public-layout>
