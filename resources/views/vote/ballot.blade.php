@php
    $initialsOf = fn ($name) => \Illuminate\Support\Str::of($name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $cands = $election->candidates->mapWithKeys(fn ($c) => [$c->id => [
        'name' => $c->name,
        'role' => trim($c->position.($c->party ? ' · '.$c->party : '')),
    ]]);
    $oldCandidate = old('candidate_id');
    $oldCandidate = $oldCandidate !== null && $cands->has((int) $oldCandidate) ? (int) $oldCandidate : null;
@endphp
<x-public-layout :title="$election->title">
    <div class="voter-wrap"
         x-data="{
            candidate: @js($oldCandidate),
            email: @js(old('email', '')),
            candidates: @js($cands),
            step: 'select',
            busy: false, errors: @js($errors->all()),
            get validEmail() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim()); },
            get ready() { return this.candidate !== null && this.validEmail; },
            get chosen() { return this.candidate !== null ? this.candidates[this.candidate] : null; },
            review() { if (!this.ready) return; this.errors = []; this.step = 'confirm'; this.$nextTick(() => this.$refs.confirmPanel.focus()); },
            change() { this.step = 'select'; this.errors = []; },
            async cast($event) {
                if (this.step !== 'confirm') { this.review(); return; }
                if (!this.ready || this.busy) return;
                this.busy = true; this.errors = [];
                try {
                    const res = await fetch($event.target.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': window.CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData($event.target),
                    });
                    if (res.status === 419) {
                        this.errors = ['Your page expired before the vote was cast. Reloading — nothing was submitted.'];
                        this.step = 'select';
                        setTimeout(() => location.reload(), 1800);
                        return;
                    }
                    if (res.status === 429) {
                        this.errors = ['Too many attempts, wait a minute and try again.'];
                        this.step = 'select';
                        return;
                    }
                    if (res.status === 422) {
                        const j = await res.json().catch(() => ({}));
                        this.errors = Object.values(j.errors || {}).flat();
                        if (!this.errors.length) this.errors = [j.message || 'Please check your details and try again.'];
                        this.step = 'select';
                        return;
                    }
                    const j = await res.json().catch(() => ({}));
                    if (!res.ok || j.result === 'error') {
                        this.errors = [j.message || 'Something went wrong. Please try again.'];
                        this.step = 'select';
                        return;
                    }
                    this.step = 'done';
                    this.$nextTick(() => this.$refs.donePanel.focus());
                } catch (e) { this.errors = ['Network error — check your connection and try again.']; this.step = 'select'; }
                finally { this.busy = false; }
            }
         }">

        {{-- Step 3: done (no navigation) --}}
        <div x-show="step === 'done'" x-cloak x-ref="donePanel" tabindex="-1" role="status" style="max-width:440px;margin:0 auto;text-align:center;padding-top:32px;outline:none">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--good-soft);color:var(--good);display:grid;place-items:center;margin:0 auto">
                <svg aria-hidden="true" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
            </div>
            <h1 style="margin-top:20px">Your vote is counted</h1>
            <p style="color:var(--muted);margin-top:8px">Thanks for voting in <b>{{ $election->title }}</b>.</p>
            <div style="margin-top:22px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
                <a href="{{ route('results.show', $election) }}" class="btn primary">View live results</a>
                <a href="{{ route('home') }}" class="btn">Other elections</a>
            </div>
        </div>

        <div x-show="step !== 'done'">
            <div class="voter-hero">
                <x-status-pill status="live" />
                <h1 style="margin-top:12px">{{ $election->title }}</h1>
                @if ($election->description)
                    <p>{{ $election->description }}</p>
                @endif
                @if ($cands->isNotEmpty())
                    <p style="color:var(--faint)">Choose one candidate. You can vote once, and your ballot is final.</p>
                    <ol class="steps" aria-label="Voting steps" style="list-style:none;padding:0;margin:14px 0 0">
                        <li style="display:inline"><b :class="step === 'select' ? 'on' : ''" :aria-current="step === 'select' ? 'step' : null">1 · Select</b></li>
                        <li style="display:inline"> → <b :class="step === 'confirm' ? 'on' : ''" :aria-current="step === 'confirm' ? 'step' : null">2 · Confirm</b></li>
                        <li style="display:inline"> → 3 · Done</li>
                    </ol>
                @endif
            </div>

            <div class="alert err" role="alert" x-show="errors.length" x-cloak>
                <template x-if="errors.length === 1"><span x-text="errors[0]"></span></template>
                <template x-if="errors.length > 1"><ul style="margin:0;padding-left:18px"><template x-for="m in errors" :key="m"><li x-text="m"></li></template></ul></template>
            </div>

            @if ($cands->isEmpty())
                <div class="card pad">
                    <div class="empty"><div class="big" aria-hidden="true">🗳️</div>
                        <p>This ballot has no approved candidates yet. Please check back soon.</p>
                        <div style="margin-top:14px"><a href="{{ route('home') }}" class="btn">Other elections</a></div>
                    </div>
                </div>
            @else
            <form method="POST" action="{{ route('vote.store', $election) }}" @submit.prevent="cast($event)" novalidate>
                @csrf

                {{-- Step 2: confirm --}}
                <div x-show="step === 'confirm'" x-cloak x-ref="confirmPanel" tabindex="-1" class="confirm-panel" style="outline:none">
                    <h2 style="margin:0;font-size:17px">Confirm your vote</h2>
                    <p style="color:var(--muted);margin:4px 0 0">Your ballot is final once cast and can't be changed.</p>
                    <dl>
                        <dt>Election</dt><dd>{{ $election->title }}</dd>
                        <dt>Candidate</dt><dd><span x-text="chosen?.name"></span><span x-show="chosen?.role" style="color:var(--muted);font-weight:400"> — <span x-text="chosen?.role"></span></span></dd>
                        <dt>Email</dt><dd x-text="email.trim()"></dd>
                    </dl>
                    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
                        <button type="submit" class="btn primary" :disabled="busy" :aria-busy="busy.toString()">
                            <span x-show="!busy">Cast my vote</span>
                            <span x-show="busy" x-cloak><span class="spin" role="status" aria-label="Submitting your vote"></span></span>
                        </button>
                        <button type="button" class="btn" @click="change()" :disabled="busy">Change</button>
                    </div>
                </div>

                {{-- Step 1: select --}}
                <div x-show="step === 'select'">
                    <div class="field" style="margin-top:6px">
                        <label for="voter-email">Your email <span class="hint">· confirms eligibility &amp; blocks duplicate votes</span></label>
                        <input id="voter-email" type="email" name="email" x-model="email" placeholder="you@acme.edu" required autocomplete="email"
                               :class="email && !validEmail ? 'bad' : ''" :aria-invalid="(email && !validEmail) ? 'true' : 'false'">
                    </div>

                    <fieldset style="border:0;padding:0;margin:0;min-width:0">
                        <legend class="label" style="padding:0;margin-top:14px">Choose your candidate</legend>
                        @foreach ($election->candidates as $c)
                            @php $url = $c->photoUrl(); @endphp
                            <label class="vcard" :class="candidate === {{ $c->id }} ? 'sel' : ''">
                                <input type="radio" class="visually-hidden" name="candidate_id" value="{{ $c->id }}" x-model.number="candidate">
                                <div class="pic">
                                    @if ($url)
                                        <img src="{{ $url }}" alt="">
                                    @else
                                        <span aria-hidden="true">{{ $initialsOf($c->name) }}</span>
                                    @endif
                                </div>
                                <div class="info">
                                    <h4>{{ $c->name }}</h4>
                                    <div class="role">{{ $c->position }}@if ($c->party) · {{ $c->party }}@endif</div>
                                    @if ($c->bio)<div class="bio">{{ $c->bio }}</div>@endif
                                    @if ($c->manifesto)
                                        <div x-data="{ m: false }" style="margin-top:6px">
                                            <button type="button" class="link-btn" style="font-size:12px"
                                                    :aria-expanded="m.toString()"
                                                    @click.prevent.stop="m = !m" x-text="m ? 'Hide manifesto' : 'Read manifesto'"></button>
                                            <div x-show="m" x-cloak style="margin-top:6px;font-size:13px;color:var(--muted);white-space:pre-line">{{ $c->manifesto }}</div>
                                        </div>
                                    @endif
                                </div>
                                <span class="vradio" aria-hidden="true"></span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="vote-bar">
                        <span class="sel-name" x-show="!ready"><span>Select a candidate and enter a valid email</span></span>
                        <span class="sel-name" x-show="ready" x-cloak>Ready to review: <span x-text="chosen?.name" style="color:var(--ink)"></span></span>
                        <div style="flex:1"></div>
                        <button type="button" class="btn primary" :disabled="!ready" @click="review()">Review vote</button>
                    </div>
                </div>

                <div class="secure-foot">
                    <svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    One vote per email · duplicates blocked at the database
                </div>
            </form>
            @endif
        </div>
    </div>
</x-public-layout>
