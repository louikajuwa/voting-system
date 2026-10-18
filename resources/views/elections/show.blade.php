<x-app-layout :title="$election->title">
    <div x-data="electionPage()"
         @election-updated.window="e.title = $event.detail.title; e.status = $event.detail.status; editOpen = false"
         @candidates-changed.window="candOpen = false">

        <div class="page-h">
            <div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <a href="{{ route('dashboard') }}" class="back" style="color:var(--faint)" aria-label="Back to overview">←</a>
                    <h1 x-text="e.title"></h1>
                    <span class="pill" :class="e.status" x-text="cap(e.status)"></span>
                    @if ($winner)<span class="lead-tag" x-show="e.status === 'closed'">Winner · {{ $winner }}</span>@endif
                </div>
                <p class="mono" style="margin-top:6px">{{ $election->slug }}</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="button" class="btn" @click="exportOpen = true">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    Export
                </button>
                <a href="{{ route('results.show', $election) }}" class="btn">Live results</a>
                <button type="button" class="btn primary" @click="editOpen = true">Edit</button>
                <x-confirm-modal :action="route('admin.elections.destroy', $election)" method="DELETE"
                    title="Delete this election?"
                    message="It will be archived (soft-deleted). Vote records are preserved."
                    trigger="Delete" confirm="Delete election" triggerClass="btn danger" />
            </div>
        </div>

        {{-- Status controls (AJAX, in-place). Only transitions the server allows are offered. --}}
        <div class="card pad" style="display:flex;gap:24px;justify-content:space-between;flex-wrap:wrap;align-items:flex-start;margin-bottom:16px">
            <div>
                <div style="color:var(--muted);font-size:12.5px" id="status-lbl">Change status</div>
                <div role="group" aria-labelledby="status-lbl" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <template x-for="s in nextStatuses" :key="s">
                        <button type="button" class="btn" :class="s === 'live' ? 'primary' : ''" :disabled="statusBusy"
                                @click="askStatus(s)" x-text="actionLabel(s)"></button>
                    </template>
                    <span x-show="nextStatuses.length === 0" x-cloak style="color:var(--faint);font-size:13px">
                        This election is closed — its result is final.
                    </span>
                    <span x-show="statusBusy" x-cloak><span class="spin" role="status" aria-label="Updating status"></span></span>
                </div>
            </div>
            <div>
                <div style="color:var(--muted);font-size:12.5px">Public ballot link</div>
                <a href="{{ route('vote.show', $election) }}" target="_blank" rel="noopener" style="color:var(--accent);font-weight:600;font-size:13px;display:inline-block;margin-top:10px;word-break:break-all">{{ route('vote.show', $election) }}</a>
            </div>
        </div>

        {{-- Candidates (dynamic + AJAX modals) --}}
        <div class="card" x-data="candidateTable()" x-init="load()"
             @candidates-changed.window="load()" style="margin-bottom:16px">
            <div class="card-h">
                <b>Candidates</b>
                <div class="tbl-toolbar">
                    <label class="visually-hidden" for="cand-search">Search candidates</label>
                    <input id="cand-search" type="search" placeholder="Search candidates…" x-model="search" @input.debounce.400ms="page=1; load()">
                    <button type="button" class="btn primary sm" @click="openCreate()">Add candidate</button>
                </div>
            </div>
            <div>
                <template x-if="loading"><div style="text-align:center;padding:28px"><span class="spin" role="status" aria-label="Loading candidates"></span></div></template>
                <template x-if="!loading && failed"><div class="empty" role="alert" style="color:var(--bad)">Could not load candidates. <button type="button" class="link-btn" @click="load()">Retry</button></div></template>
                <template x-if="!loading && !failed && rows.length === 0"><div class="empty">No candidates yet — add the first one.</div></template>
                <template x-for="c in rows" :key="c.id">
                    <div class="cand">
                        <div class="thumb-mini"><template x-if="c.photo"><img :src="c.photo" :alt="c.name"></template><template x-if="!c.photo"><span aria-hidden="true" x-text="c.name.slice(0,1)"></span></template></div>
                        <div class="grow">
                            <div class="nm"><span x-text="c.name"></span> <span class="pill" :class="c.status === 'approved' ? 'live' : 'draft'" x-text="c.status"></span></div>
                            <div class="party" x-text="[c.position, c.party].filter(Boolean).join(' · ')"></div>
                        </div>
                        <button type="button" class="btn sm" :aria-label="'Edit ' + c.name" @click="openEdit(c)">Edit</button>
                        <button type="button" class="btn danger sm" x-show="!locked" :aria-label="'Remove ' + c.name" @click="ask(c)">Remove</button>
                    </div>
                </template>
                <p x-show="locked" x-cloak style="margin:0;padding:10px 18px;color:var(--faint);font-size:12.5px;border-top:1px solid var(--border)">
                    Candidates can't be removed once an election is live or closed.
                </p>
            </div>
            <div class="pager">
                <span>Showing <b x-text="meta.from ?? 0"></b>–<b x-text="meta.to ?? 0"></b> of <b x-text="meta.total ?? 0"></b></span>
                <div class="btns">
                    <button type="button" @click="page=Math.max(1,page-1); load()" :disabled="page<=1">Prev</button>
                    <button type="button" @click="page=Math.min(meta.last_page,page+1); load()" :disabled="page >= (meta.last_page||1)">Next</button>
                </div>
            </div>

            {{-- remove-confirm modal --}}
            <template x-teleport="body">
                <div x-show="del.open" x-cloak x-modal="del.open" class="modal-overlay" @click.self="del.open=false" @keydown.escape.window="del.open=false">
                    <div class="modal-box">
                        <h3>Remove candidate?</h3>
                        <p>Remove <b x-text="del.name"></b> from this election. Votes already cast stay in the record.</p>
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="del.open=false" :disabled="del.busy">Cancel</button>
                            <button type="button" class="btn danger" @click="doDelete()" :disabled="del.busy">
                                <span x-show="!del.busy">Remove</span><span x-show="del.busy" x-cloak><span class="spin" role="status" aria-label="Removing"></span></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Voter audit log (dynamic) --}}
        <div class="card" x-data="votesTable()" x-init="load()" style="margin-bottom:16px">
            <div class="card-h">
                <b>Voter audit log</b>
                <div class="tbl-toolbar">
                    <label class="visually-hidden" for="vote-search">Search by email</label>
                    <input id="vote-search" type="search" placeholder="Search email…" x-model="search" @input.debounce.400ms="page=1; load()">
                    <label class="visually-hidden" for="vote-from">Voted from date</label>
                    <input id="vote-from" type="date" x-model="from" @change="page=1; load()" title="From">
                    <label class="visually-hidden" for="vote-to">Voted to date</label>
                    <input id="vote-to" type="date" x-model="to" @change="page=1; load()" title="To">
                </div>
            </div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Email</th><th>Candidate</th><th>IP</th><th style="text-align:right">Voted at</th></tr></thead>
                    <tbody>
                        <template x-if="loading"><tr><td colspan="4" style="text-align:center;padding:28px"><span class="spin" role="status" aria-label="Loading votes"></span></td></tr></template>
                        <template x-if="!loading && failed"><tr class="err-row"><td colspan="4" role="alert">Could not load votes. <button type="button" class="link-btn" @click="load()">Retry</button></td></tr></template>
                        <template x-if="!loading && !failed && rows.length === 0"><tr><td colspan="4"><div class="empty">No votes match.</div></td></tr></template>
                        <template x-for="v in rows" :key="v.email + v.time">
                            <tr>
                                <td x-text="v.email"></td>
                                <td x-text="v.candidate"></td>
                                <td class="mono" x-text="v.ip"></td>
                                <td style="text-align:right;color:var(--muted);white-space:nowrap" x-text="v.time"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="pager">
                <span>Showing <b x-text="meta.from ?? 0"></b>–<b x-text="meta.to ?? 0"></b> of <b x-text="meta.total ?? 0"></b></span>
                <div class="btns">
                    <button type="button" @click="page=Math.max(1,page-1); load()" :disabled="page<=1">Prev</button>
                    <button type="button" @click="page=Math.min(meta.last_page,page+1); load()" :disabled="page >= (meta.last_page||1)">Next</button>
                </div>
            </div>
        </div>

        {{-- Activity (admin audit trail) — rendered only when the controller supplies $audit --}}
        @isset($audit)
            @php
                $fmtVal = fn ($v) => is_scalar($v) || $v === null ? \Illuminate\Support\Str::limit((string) ($v ?? '—'), 40) : \Illuminate\Support\Str::limit(json_encode($v), 40);
                $summary = function ($row) use ($fmtVal) {
                    $before = (array) ($row->before ?? []);
                    $after = (array) ($row->after ?? []);
                    $keys = collect(array_keys($before + $after))->take(4);
                    return $keys->map(fn ($k) => [
                        'key' => $k,
                        'from' => array_key_exists($k, $before) ? $fmtVal($before[$k]) : null,
                        'to' => array_key_exists($k, $after) ? $fmtVal($after[$k]) : null,
                    ]);
                };
            @endphp
            <div class="card" x-data="{ extra: [] }" @status-logged.window="extra.unshift($event.detail)">
                <div class="card-h"><b>Activity</b><span class="mono">latest {{ $audit->count() }} changes</span></div>
                <ul class="activity" aria-label="Election activity">
                    <template x-for="a in extra" :key="a.id">
                        <li>
                            <span class="what" x-text="a.what"></span>
                            <span class="who">by You</span>
                            <span class="when">just now</span>
                            <span class="diff"><code x-text="a.diff"></code></span>
                        </li>
                    </template>
                    @forelse ($audit as $row)
                        <li>
                            <span class="what">{{ ucfirst(str_replace(['.', '_'], ' ', $row->action)) }}</span>
                            <span class="who">by {{ $row->user?->name ?? 'System' }}</span>
                            <time class="when" datetime="{{ $row->created_at?->toIso8601String() }}" title="{{ $row->created_at?->toDayDateTimeString() }}">{{ $row->created_at?->diffForHumans() }}</time>
                            @php $parts = $summary($row); @endphp
                            @if ($parts->isNotEmpty())
                                <span class="diff">
                                    @foreach ($parts as $p)
                                        <code>{{ $p['key'] }}: {{ $p['from'] !== null && $p['to'] !== null ? $p['from'].' → '.$p['to'] : ($p['to'] ?? ($p['from'].' (removed)')) }}</code>
                                    @endforeach
                                </span>
                            @endif
                        </li>
                    @empty
                        <li x-show="extra.length === 0"><span class="who">No activity recorded yet.</span></li>
                    @endforelse
                </ul>
            </div>
        @endisset

        {{-- Edit-election modal --}}
        <template x-teleport="body">
            <div x-show="editOpen" x-cloak x-modal="editOpen" class="modal-overlay" @click.self="editOpen=false" @keydown.escape.window="editOpen=false">
                <div class="modal-box wide">
                    <h3>Edit election</h3>
                    <form action="{{ route('admin.elections.update', $election) }}" style="margin-top:16px"
                          x-data="ajaxForm({ onOk: (data) => window.dispatchEvent(new CustomEvent('election-updated', { detail: data.election })) })"
                          @submit.prevent="submit($event)">
                        @method('PUT')
                        @include('elections._modalfields', ['election' => $election])
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="editOpen=false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="busy"><span x-show="!busy">Save changes</span><span x-show="busy" x-cloak><span class="spin" role="status" aria-label="Saving"></span></span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Candidate create/edit modal --}}
        <template x-teleport="body">
            <div x-show="candOpen" x-cloak x-modal="candOpen" class="modal-overlay" @click.self="candOpen=false" @keydown.escape.window="candOpen=false">
                <div class="modal-box wide">
                    <h3 x-text="cand.id ? 'Edit candidate' : 'New candidate'"></h3>
                    <form :action="candAction()" enctype="multipart/form-data" style="margin-top:16px"
                          x-data="ajaxForm({ onOk: () => window.dispatchEvent(new CustomEvent('candidates-changed')) })"
                          @reset-cand-errors.window="errors = {}"
                          @submit.prevent="submit($event)">
                        <template x-if="cand.id"><input type="hidden" name="_method" value="PUT"></template>
                        <div class="form-grid">
                            <div>
                                <span class="label" id="cand-photo-lbl">Photo <span class="hint">· shown to voters</span></span>
                                <label class="dropzone" :class="(candPhoto || cand.photo) ? 'has' : ''">
                                    <template x-if="candPhoto || cand.photo"><img :src="candPhoto || cand.photo" :alt="cand.name ? 'Photo of ' + cand.name : 'Selected photo preview'"></template>
                                    <template x-if="!(candPhoto || cand.photo)">
                                        <div class="dz-inner"><div><div class="dz-ic"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg></div><div class="dz-t">Click to upload</div><div class="dz-s">JPG / PNG · max 2&nbsp;MB</div></div></div>
                                    </template>
                                    <input type="file" id="cand-photo" name="photo" accept="image/*" class="visually-hidden" aria-labelledby="cand-photo-lbl"
                                           @change="candPhoto = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null">
                                </label>
                                <p class="err" x-show="err('photo')" x-text="err('photo')"></p>
                            </div>
                            <div>
                                <div class="row2">
                                    <div class="field"><label for="cand-name">Full name</label><input class="input" id="cand-name" name="name" x-model="cand.name" required><p class="err" x-show="err('name')" x-text="err('name')"></p></div>
                                    <div class="field"><label for="cand-position">Position</label><input class="input" id="cand-position" name="position" x-model="cand.position"></div>
                                </div>
                                <div class="row2">
                                    <div class="field"><label for="cand-party">Party / slate</label><input class="input" id="cand-party" name="party" x-model="cand.party"></div>
                                    <div class="field"><label for="cand-status">Status</label>
                                        <select class="input" id="cand-status" name="status" x-model="cand.status">
                                            <option value="approved">Approved — on ballot</option>
                                            <option value="pending">Pending review</option>
                                            <option value="withdrawn">Withdrawn</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="field"><label for="cand-bio">Short bio</label><input class="input" id="cand-bio" name="bio" x-model="cand.bio"></div>
                                <div class="field"><label for="cand-manifesto">Manifesto <span class="hint">· optional</span></label><textarea class="input" id="cand-manifesto" name="manifesto" x-model="cand.manifesto"></textarea></div>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="candOpen=false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="busy"><span x-show="!busy">Save candidate</span><span x-show="busy" x-cloak><span class="spin" role="status" aria-label="Saving"></span></span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Status-change confirm (Go Live / back to Draft / Close) --}}
        <template x-teleport="body">
            <div x-show="confirm.open" x-cloak x-modal="confirm.open" class="modal-overlay" @click.self="confirm.open=false" @keydown.escape.window="confirm.open=false">
                <div class="modal-box">
                    <h3 x-text="confirm.title"></h3>
                    <p x-text="confirm.text"></p>
                    <div class="modal-actions">
                        <button type="button" class="btn" @click="confirm.open=false" :disabled="statusBusy">Cancel</button>
                        <button type="button" class="btn primary" @click="setStatus(confirm.status)" :disabled="statusBusy">
                            <span x-show="!statusBusy" x-text="confirm.label"></span><span x-show="statusBusy" x-cloak><span class="spin" role="status" aria-label="Updating"></span></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Export modal (date range → Excel / CSV) --}}
        <template x-teleport="body">
            <div x-show="exportOpen" x-cloak x-modal="exportOpen" class="modal-overlay" @click.self="exportOpen=false" @keydown.escape.window="exportOpen=false">
                <div class="modal-box wide" x-data="{ from: '', to: '' }">
                    <h3>Export votes</h3>
                    <p>Download individual votes for this election. Leave dates empty for all.</p>
                    <div class="row2" style="margin-top:16px">
                        <div class="field"><label for="exp-from">From date</label><input id="exp-from" type="date" class="input" x-model="from"></div>
                        <div class="field"><label for="exp-to">To date</label><input id="exp-to" type="date" class="input" x-model="to"></div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn" @click="exportOpen=false">Cancel</button>
                        <a class="btn" :href="`{{ route('admin.elections.votes.csv', $election) }}?from=${from}&to=${to}`">CSV</a>
                        <a class="btn primary" :href="`{{ route('admin.elections.votes.excel', $election) }}?from=${from}&to=${to}`">Excel (.xlsx)</a>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
        function electionPage() {
            const TRANSITIONS = @json(\App\Models\Election::TRANSITIONS);
            const COPY = {
                live: { title: 'Go live?', text: 'Voting opens immediately and the ballot link starts accepting votes. Candidates can no longer be removed.', label: 'Go live' },
                draft: { title: 'Switch back to draft?', text: 'The election stops being scheduled and voters cannot vote until you publish it again.', label: 'Switch to draft' },
                closed: { title: 'Close this election?', text: 'Voting stops immediately and the result is finalized. This cannot be undone.', label: 'Close voting' },
            };
            return {
                e: @json(['slug' => $election->slug, 'title' => $election->title, 'status' => $election->status]),
                editOpen: false, candOpen: false, exportOpen: false, statusBusy: false,
                confirm: { open: false, status: null, title: '', text: '', label: '' },
                candPhoto: null,
                cand: { id: null, name: '', position: '', party: '', bio: '', manifesto: '', status: 'approved', photo: null, update_url: '' },
                cap(s) { return s ? s[0].toUpperCase() + s.slice(1) : s; },
                get nextStatuses() { return TRANSITIONS[this.e.status] || []; },
                get locked() { return this.e.status === 'live' || this.e.status === 'closed'; },
                actionLabel(s) { return { scheduled: 'Schedule', live: 'Go live', draft: 'Back to draft', closed: 'Close voting' }[s] || this.cap(s); },
                resetPhotoInput() { const f = document.getElementById('cand-photo'); if (f) f.value = ''; },
                openCreate() {
                    this.candPhoto = null; this.resetPhotoInput();
                    this.cand = { id: null, name: '', position: 'President', party: '', bio: '', manifesto: '', status: 'approved', photo: null, update_url: '' };
                    window.dispatchEvent(new CustomEvent('reset-cand-errors'));
                    this.candOpen = true;
                },
                openEdit(c) {
                    this.candPhoto = null; this.resetPhotoInput();
                    this.cand = { id: c.id, name: c.name, position: c.position || '', party: c.party || '', bio: c.bio || '', manifesto: c.manifesto || '', status: c.status, photo: c.photo, update_url: c.update_url };
                    window.dispatchEvent(new CustomEvent('reset-cand-errors'));
                    this.candOpen = true;
                },
                candAction() { return this.cand.id ? this.cand.update_url : '{{ route('admin.elections.candidates.store', $election) }}'; },
                askStatus(s) {
                    if (this.statusBusy) return;
                    if (COPY[s]) { this.confirm = Object.assign({ open: true, status: s }, COPY[s]); }
                    else this.setStatus(s);
                },
                async setStatus(status) {
                    if (this.statusBusy) return;
                    this.statusBusy = true;
                    const fd = new FormData(); fd.append('status', status);
                    try {
                        const r = await window.api('{{ route('admin.elections.status', $election) }}', { method: 'POST', body: fd });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.result === 'success') {
                            const before = this.e.status;
                            this.e.status = (j.data && j.data.status) || status;
                            this.confirm.open = false;
                            window.toast(j.message || 'Status updated');
                            window.dispatchEvent(new CustomEvent('status-logged', { detail: { id: Date.now(), what: 'Election status changed', diff: 'status: ' + before + ' → ' + this.e.status } }));
                            window.dispatchEvent(new CustomEvent('candidates-changed'));
                        } else {
                            this.confirm.open = false;
                            window.toast(j.message || (r.status === 409 ? 'That status change is not allowed.' : 'Could not change status'), 'error');
                        }
                    } catch (err) { window.netError(err); }
                    finally { this.statusBusy = false; }
                },
            };
        }
        function candidateTable() {
            return {
                rows: [], meta: {}, loading: true, failed: false, search: '', page: 1,
                del: { open: false, url: null, name: '', busy: false },
                async load() {
                    this.loading = true; this.failed = false;
                    const q = new URLSearchParams({ search: this.search, page: this.page });
                    try {
                        const r = await window.api(`{{ route('admin.elections.candidates.list', $election) }}?${q}`);
                        if (!r.ok) throw new Error('http ' + r.status);
                        const j = await r.json(); this.rows = j.data.rows; this.meta = j.data;
                    } catch (e) { if (!e.session) { this.failed = true; this.rows = []; } }
                    finally { this.loading = false; }
                },
                ask(c) { this.del = { open: true, url: c.delete_url, name: c.name, busy: false }; },
                async doDelete() {
                    if (this.del.busy) return;
                    this.del.busy = true;
                    try {
                        const r = await window.api(this.del.url, { method: 'DELETE' });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.result === 'success') { window.toast(j.message || 'Candidate removed'); this.del.open = false; this.page = 1; this.load(); }
                        else { this.del.open = false; window.toast(j.message || 'Could not remove candidate', 'error'); }
                    } catch (e) { window.netError(e); }
                    finally { this.del.busy = false; }
                },
            };
        }
        function votesTable() {
            return {
                rows: [], meta: {}, loading: true, failed: false, search: '', from: '', to: '', page: 1,
                async load() {
                    this.loading = true; this.failed = false;
                    const q = new URLSearchParams({ search: this.search, from: this.from, to: this.to, page: this.page });
                    try {
                        const r = await window.api(`{{ route('admin.elections.votes', $election) }}?${q}`);
                        if (!r.ok) throw new Error('http ' + r.status);
                        const j = await r.json(); this.rows = j.data.rows; this.meta = j.data;
                    } catch (e) { if (!e.session) { this.failed = true; this.rows = []; } }
                    finally { this.loading = false; }
                },
            };
        }
    </script>
</x-app-layout>
