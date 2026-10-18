<x-app-layout title="Analytics">
    <div class="page-h"><div><h1>Analytics</h1><p>Voting activity across your organization.</p></div></div>

    <div x-data="analytics()" x-init="load()">
        <div class="alert err" role="alert" x-show="failed" x-cloak>Could not load analytics. <button type="button" class="link-btn" @click="load()">Retry</button></div>
        <div class="tiles t3">
            <div class="tile"><div class="k">Total votes</div><div class="v tnum" x-text="fmt(total)"></div></div>
            <div class="tile"><div class="k">Last 30 days</div><div class="v tnum" x-text="fmt(recent)"></div></div>
            <div class="tile"><div class="k">Busiest day</div><div class="v tnum" x-text="fmt(peak)"></div></div>
        </div>

        <div class="grid2">
            <div class="card">
                <div class="card-h"><b>Votes over time</b><span class="mono">last 30 days</span></div>
                <div class="chart-box"><canvas id="overtime" height="220" role="img" aria-label="Line chart of votes per day over the last 30 days"></canvas></div>
            </div>
            <div class="card">
                <div class="card-h"><b>Votes by election</b></div>
                <div class="chart-box"><canvas id="perelection" height="220" role="img" aria-label="Bar chart of total votes by election"></canvas></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"
            integrity="sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF" crossorigin="anonymous"></script>
    <script>
        function analytics() {
            return {
                total: 0, recent: 0, peak: 0, line: null, bar: null, failed: false,
                fmt(n) { return (n ?? 0).toLocaleString(); },
                axis() { return document.documentElement.dataset.theme === 'dark' ? '#A3A7B7' : '#5A5D6E'; },
                async load() {
                    this.failed = false;
                    try {
                        if (typeof Chart === 'undefined') throw new Error('chart.js unavailable');
                        const r = await window.api('{{ route('admin.analytics.data') }}');
                        if (!r.ok) throw new Error('http ' + r.status);
                        const j = (await r.json()).data;
                        this.total = j.total;
                        this.recent = j.over_time.reduce((a, b) => a + b.votes, 0);
                        this.peak = Math.max(0, ...j.over_time.map(d => d.votes));
                        [this.line, this.bar].forEach(c => c && c.destroy());
                        this.draw(j);
                    } catch (e) { if (!e.session) this.failed = true; }
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
