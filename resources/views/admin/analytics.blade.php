<x-app-layout>
    <div class="page-h"><div><h1>Analytics</h1><p>Voting activity across your organization.</p></div></div>

    <div x-data="analytics()" x-init="load()">
        <div class="tiles" style="grid-template-columns:repeat(3,1fr)">
            <div class="tile"><div class="k">Total votes</div><div class="v tnum" x-text="fmt(total)"></div></div>
            <div class="tile"><div class="k">Last 30 days</div><div class="v tnum" x-text="fmt(recent)"></div></div>
            <div class="tile"><div class="k">Busiest day</div><div class="v tnum" x-text="fmt(peak)"></div></div>
        </div>

        <div class="grid2">
            <div class="card">
                <div class="card-h"><b>Votes over time</b><span class="mono">last 30 days</span></div>
                <div class="chart-box"><canvas id="overtime" height="220"></canvas></div>
            </div>
            <div class="card">
                <div class="card-h"><b>Votes by election</b></div>
                <div class="chart-box"><canvas id="perelection" height="220"></canvas></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        function analytics() {
            return {
                total: 0, recent: 0, peak: 0, line: null, bar: null,
                fmt(n) { return (n ?? 0).toLocaleString(); },
                axis() { return document.documentElement.dataset.theme === 'dark' ? '#9498A8' : '#9ca3af'; },
                async load() {
                    const r = await fetch('{{ route('admin.analytics.data') }}', { headers: { Accept: 'application/json' } });
                    const j = (await r.json()).data;
                    this.total = j.total;
                    this.recent = j.over_time.reduce((a, b) => a + b.votes, 0);
                    this.peak = Math.max(0, ...j.over_time.map(d => d.votes));
                    this.draw(j);
                },
                draw(j) {
                    const grid = 'rgba(148,163,184,.15)';
                    this.line = new Chart(document.getElementById('overtime'), {
                        type: 'line',
                        data: { labels: j.over_time.map(d => d.date), datasets: [{
                            data: j.over_time.map(d => d.votes), borderColor: '#2F6BED',
                            backgroundColor: 'rgba(47,107,237,.12)', fill: true, tension: .35, pointRadius: 0, borderWidth: 2,
                        }] },
                        options: { plugins: { legend: { display: false } }, scales: {
                            x: { ticks: { color: this.axis(), maxTicksLimit: 8 }, grid: { display: false } },
                            y: { beginAtZero: true, ticks: { color: this.axis(), precision: 0 }, grid: { color: grid } },
                        } },
                    });
                    this.bar = new Chart(document.getElementById('perelection'), {
                        type: 'bar',
                        data: { labels: j.per_election.map(e => e.title), datasets: [{
                            data: j.per_election.map(e => e.votes), backgroundColor: '#12A150', borderRadius: 6,
                        }] },
                        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: {
                            x: { beginAtZero: true, ticks: { color: this.axis(), precision: 0 }, grid: { color: grid } },
                            y: { ticks: { color: this.axis() }, grid: { display: false } },
                        } },
                    });
                },
            };
        }
    </script>
</x-app-layout>
