<x-app-layout title="Overview">
    <div x-data="dashboard()" x-init="init()"
         @election-created.window="createOpen = false; page = 1; load(); refreshStats()">
        <div class="page-h">
            <div>
                <h1>Overview</h1>
                <p>Live snapshot across all elections in {{ auth()->user()->organization?->name ?? 'your organization' }}.</p>
            </div>
            <button type="button" class="btn primary" @click="openCreate()">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                New election
            </button>
        </div>

        {{-- Create-election modal (AJAX, no reload) --}}
        <template x-teleport="body">
            <div x-show="createOpen" x-cloak x-modal="createOpen" class="modal-overlay" @click.self="createOpen=false" @keydown.escape.window="createOpen=false">
                <div class="modal-box wide">
                    <h3>New election</h3>
                    <p>Create a ballot, then add candidates on the next screen.</p>
                    <form action="{{ route('admin.elections.store') }}" style="margin-top:16px"
                          x-data="ajaxForm({ onOk: (data) => { if (data.redirect) { window.location.href = data.redirect; return; } window.dispatchEvent(new CustomEvent('election-created')); } })"
                          @reset-create-form.window="reset($el)"
                          @submit.prevent="submit($event)">
                        @include('elections._modalfields')
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="createOpen=false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="busy" :aria-busy="busy.toString()">
                                <span x-show="!busy">Create election</span>
                                <span x-show="busy" x-cloak><span class="spin" role="status" aria-label="Creating"></span></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Stat tiles (live) --}}
        <div class="tiles">
            <div class="tile"><div class="k"><span class="dot pulse" aria-hidden="true"></span> Active elections</div><div class="v tnum" x-text="stats.active"></div><div class="d" style="color:var(--faint)">currently live</div></div>
            <div class="tile"><div class="k">Votes today</div><div class="v tnum" x-text="fmt(stats.votes_today)"></div><div class="d up">live</div></div>
            <div class="tile"><div class="k">Total votes</div><div class="v tnum" x-text="fmt(stats.votes)"></div><div class="d" style="color:var(--faint)">all time</div></div>
            <div class="tile"><div class="k">Elections</div><div class="v tnum" x-text="stats.elections"></div><div class="d" style="color:var(--faint)">draft, live &amp; closed</div></div>
        </div>

        {{-- Elections table (backend search + pagination, no reload) --}}
        <div class="card">
            <div class="card-h">
                <b>Elections</b>
                <div class="tbl-toolbar">
                    <label class="visually-hidden" for="el-search">Search elections</label>
                    <input id="el-search" type="search" placeholder="Search elections…" x-model="search"
                           @input.debounce.400ms="page = 1; load()">
                    <label class="visually-hidden" for="el-status">Filter by status</label>
                    <select id="el-status" x-model="status" @change="page = 1; load()">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="live">Live</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr><th>Election</th><th>Status</th><th>Candidates</th><th>Votes</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr><td colspan="5" style="text-align:center;padding:32px"><span class="spin" role="status" aria-label="Loading"></span></td></tr>
                        </template>
                        <template x-if="!loading && failed">
                            <tr class="err-row"><td colspan="5" role="alert">Could not load elections. <button type="button" class="link-btn" @click="load()">Retry</button></td></tr>
                        </template>
                        <template x-if="!loading && !failed && rows.length === 0">
                            <tr><td colspan="5"><div class="empty"><div class="big" aria-hidden="true">🗳️</div><p>No elections match.</p></div></td></tr>
                        </template>
                        <template x-for="e in rows" :key="e.manage_url">
                            <tr>
                                <td><a :href="e.manage_url"><span class="el-title" x-text="e.title"></span></a></td>
                                <td><span class="pill" :class="e.status" x-text="cap(e.status)"></span></td>
                                <td class="tnum" style="color:var(--muted)" x-text="e.candidates"></td>
                                <td class="tnum" style="color:var(--muted)" x-text="fmt(e.votes)"></td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a :href="e.manage_url" style="color:var(--muted)" :aria-label="'Manage ' + e.title">Manage</a> ·
                                    <a :href="e.results_url" style="color:var(--accent)" :aria-label="'Results for ' + e.title">Results</a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="pager">
                <span>Showing <b x-text="meta.from ?? 0"></b>–<b x-text="meta.to ?? 0"></b> of <b x-text="meta.total ?? 0"></b></span>
                <div class="btns">
                    <button type="button" @click="page = Math.max(1, page-1); load()" :disabled="page <= 1">Prev</button>
                    <button type="button" @click="page = Math.min(meta.last_page, page+1); load()" :disabled="page >= (meta.last_page || 1)">Next</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"
            integrity="sha384-2huaZvOR9iDzHqslqwpR87isEmrfxqyWOF7hr7BY6KG0+hVKLoEXMPUJw3ynWuhO" crossorigin="anonymous"></script>
    <script>
        function dashboard() {
            return {
                stats: @json($stats),
                rows: [], meta: {}, loading: true, failed: false, search: '', status: '', page: 1,
                createOpen: false, reloadTimer: null,
                fmt(n) { return (n ?? 0).toLocaleString(); },
                cap(s) { return s ? s[0].toUpperCase() + s.slice(1) : s; },
                openCreate() {
                    window.dispatchEvent(new CustomEvent('reset-create-form')); // fresh form + cleared errors every time
                    this.createOpen = true;
                },
                async load() {
                    this.loading = true; this.failed = false;
                    const q = new URLSearchParams({ search: this.search, status: this.status, page: this.page });
                    try {
                        const r = await window.api(`{{ route('dashboard.elections') }}?${q}`);
                        if (!r.ok) throw new Error('http ' + r.status);
                        const j = await r.json();
                        this.rows = j.data.rows; this.meta = j.data;
                    } catch (e) {
                        if (!e.session) { this.failed = true; this.rows = []; }
                    } finally { this.loading = false; }
                },
                async refreshStats() {
                    try {
                        const r = await window.api('{{ route('dashboard.stats') }}');
                        if (r.ok) this.stats = (await r.json()).data;
                    } catch (e) {}
                },
                init() {
                    this.load();
                    if (new URLSearchParams(location.search).get('new') === '1') this.openCreate();
                    // Live: org room pushes each vote → bump tiles, then reload the visible page (debounced).
                    try {
                        const socket = io({ path: '/socket.io/', auth: { token: @json($socketToken) } });
                        socket.on('vote', (d) => {
                            if (!d) return;
                            this.stats.votes_today = d.votes_today ?? this.stats.votes_today;
                            this.stats.votes = d.votes_total ?? this.stats.votes;
                            clearTimeout(this.reloadTimer);
                            this.reloadTimer = setTimeout(() => this.load(), 1000);
                        });
                    } catch (e) {}
                    setInterval(() => this.refreshStats(), 20000);
                },
            };
        }
    </script>
</x-app-layout>
