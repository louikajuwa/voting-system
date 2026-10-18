<x-public-layout :title="$election->title.' — Results'">
    @php
        $palette = ['#2F6BED', '#12A150', '#B7791F', '#8257E6', '#E0518A', '#0EA5E9', '#F59E0B', '#6366F1'];
        $candidates = $election->approvedCandidates->values();
        $total = array_sum($tallies);
        $isClosed = $election->status === 'closed';
        $leaderId = $isClosed ? $winnerId : ($total ? array_key_first(array_filter($tallies, fn ($v) => $v === max($tallies))) : null);
        $leadLabel = $isClosed ? 'Winner' : 'Leading';
    @endphp

    <div class="el-head">
        <a href="{{ route('home') }}" class="back">←</a>
        <div style="flex:1;min-width:200px">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <h1>{{ $election->title }}</h1>
                <x-status-pill :status="$election->status" />
            </div>
            @if ($election->description)
                <p style="color:var(--muted);margin:6px 0 0">{{ $election->description }}</p>
            @endif
        </div>
        @if ($election->isOpen())
            <a href="{{ route('vote.show', $election) }}" class="btn primary">Cast your vote</a>
        @endif
    </div>

    @if (! $visible)
        <div class="card pad">
            <div class="empty">
                <div class="big">🔒</div>
                <p>Results are hidden until voting closes.</p>
            </div>
        </div>
        <script>
            // Embargoed: poll the lightweight status and reload once results become visible.
            setInterval(async () => {
                try {
                    const r = await fetch('{{ route('results.tally', $election) }}', { headers: { Accept: 'application/json' } });
                    if (r.ok && (await r.json()).visible) location.reload();
                } catch (e) {}
            }, 30000);
        </script>
    @else
    <div class="results">
        {{-- Standings --}}
        <div class="card">
            <div class="card-h"><b>Standings</b><span class="mono"><span id="total">{{ number_format($total) }}</span> votes cast</span></div>
            @forelse ($candidates as $i => $c)
                @php $v = $tallies[$c->id] ?? 0; $pct = $total ? round($v / $total * 100) : 0; $url = $c->photoUrl(); @endphp
                <div class="cand" data-cand="{{ $c->id }}">
                    <div class="face">
                        @if ($url)<img src="{{ $url }}" alt="{{ $c->name }}">@else{{ \Illuminate\Support\Str::of($c->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}@endif
                    </div>
                    <div class="grow">
                        <div class="nm">{{ $c->name }} <span class="lead-tag" data-lead style="{{ $c->id === $leaderId ? '' : 'display:none' }}">{{ $leadLabel }}</span></div>
                        <div class="party">{{ $c->position }}@if ($c->party) · {{ $c->party }}@endif</div>
                        <div class="track"><i data-bar style="width:{{ $pct }}%;background:{{ $palette[$i % count($palette)] }}"></i></div>
                    </div>
                    <div>
                        <div class="pct tnum"><span data-pct>{{ $pct }}</span>%</div>
                        <div class="votes tnum" data-votes>{{ number_format($v) }}</div>
                    </div>
                </div>
            @empty
                <div class="empty">No candidates.</div>
            @endforelse
        </div>

        {{-- Live panel + chart --}}
        <div style="display:flex;flex-direction:column;gap:16px">
            <div class="card">
                <div class="livebox">
                    <div class="lbl">Total votes</div>
                    <div class="count tnum" id="bigtotal">{{ number_format($total) }}</div>
                    <div class="sub">{{ $isClosed ? 'Final result' : 'Updated live as votes arrive' }}</div>
                    @unless ($isClosed)
                        <div class="live-chip off" id="livechip"><i></i> Connecting…</div>
                    @endunless
                </div>
                <div class="chart-box"><canvas id="chart" height="200"></canvas></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" integrity="sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF" crossorigin="anonymous"></script>
    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js" integrity="sha384-2huaZvOR9iDzHqslqwpR87isEmrfxqyWOF7hr7BY6KG0+hVKLoEXMPUJw3ynWuhO" crossorigin="anonymous"></script>
    <script>
        const IDS = @json($candidates->pluck('id'));
        const LABELS = @json($candidates->pluck('name'));
        const COLORS = @json(collect($candidates)->keys()->map(fn ($i) => $palette[$i % count($palette)]));
        let TALLIES = @json((object) $tallies);
        const LIVE = @json(! $isClosed);
        const SOCKET_TOKEN = @json($socketToken);

        const axis = () => document.documentElement.dataset.theme === 'dark' ? '#9498A8' : '#9ca3af';
        const chart = new Chart(document.getElementById('chart'), {
            type: 'bar',
            data: { labels: LABELS, datasets: [{ data: IDS.map(id => TALLIES[id] ?? 0), backgroundColor: COLORS, borderRadius: 6 }] },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, color: axis() }, grid: { color: 'rgba(148,163,184,.15)' } },
                    y: { ticks: { color: axis() }, grid: { display: false } },
                },
                animation: { duration: 600 },
            },
        });

        function render(tallies) {
            const total = Object.values(tallies).reduce((a, b) => a + b, 0);
            const max = Math.max(0, ...Object.values(tallies));
            document.getElementById('total').textContent = total.toLocaleString();
            document.getElementById('bigtotal').textContent = total.toLocaleString();
            IDS.forEach(id => {
                const v = tallies[id] ?? 0;
                const pct = total ? Math.round(v / total * 100) : 0;
                const row = document.querySelector(`[data-cand="${id}"]`);
                if (!row) return;
                row.querySelector('[data-pct]').textContent = pct;
                row.querySelector('[data-votes]').textContent = v.toLocaleString();
                row.querySelector('[data-bar]').style.width = pct + '%';
                const lead = row.querySelector('[data-lead]');
                if (lead) lead.style.display = (max > 0 && v === max) ? '' : 'none';
            });
            chart.data.datasets[0].data = IDS.map(id => tallies[id] ?? 0);
            chart.update();
        }

        if (LIVE) {
            const chip = document.getElementById('livechip');
            const setChip = (text, on) => { chip.className = 'live-chip' + (on ? '' : ' off'); chip.innerHTML = '<i></i> ' + text; };
            if (!SOCKET_TOKEN) {
                setChip('Live unavailable', false);
            } else try {
                const socket = io({ path: '/socket.io/', auth: { token: SOCKET_TOKEN } });
                socket.on('connect', () => setChip('Live · connected', true));
                socket.on('disconnect', () => setChip('Reconnecting…', false));
                socket.on('connect_error', () => setChip('Live unavailable', false));
                socket.on('vote', (data) => { if (data && data.tallies) render(data.tallies); });
            } catch (e) { setChip('Live unavailable', false); }

            setInterval(async () => {
                try {
                    const r = await fetch('{{ route('results.tally', $election) }}', { headers: { Accept: 'application/json' } });
                    if (r.ok) render((await r.json()).tallies);
                } catch (e) {}
            }, 15000);
        }
    </script>
    @endif
</x-public-layout>
