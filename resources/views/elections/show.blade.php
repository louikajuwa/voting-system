<x-app-layout>
    <div x-data="electionPage()"
         @election-updated.window="e.title = $event.detail.title; e.status = $event.detail.status; editOpen = false"
         @candidates-changed.window="candOpen = false">

        <div class="page-h">
            <div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <a href="{{ route('dashboard') }}" class="back" style="color:var(--faint)">←</a>
                    <h1 x-text="e.title"></h1>
                    <span class="pill" :class="e.status" x-text="cap(e.status)"></span>
                    @if ($winner)<span class="lead-tag" x-show="e.status === 'closed'">Winner · {{ $winner }}</span>@endif
                </div>
                <p class="mono" style="margin-top:6px">{{ $election->slug }}</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn" @click="exportOpen = true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    Export
                </button>
                <a href="{{ route('results.show', $election) }}" class="btn">Live results</a>
                <button class="btn primary" @click="editOpen = true">Edit</button>
                <x-confirm-modal :action="route('admin.elections.destroy', $election)" method="DELETE"
                    title="Delete this election?"
                    message="It will be archived (soft-deleted). Vote records are preserved."
                    trigger="Delete" confirm="Delete election" triggerClass="btn danger" />
            </div>
        </div>

        {{-- Status controls (AJAX, in-place) --}}
        <div class="card pad" style="display:flex;gap:24px;justify-content:space-between;flex-wrap:wrap;align-items:flex-start;margin-bottom:16px">
            <div>
                <div style="color:var(--muted);font-size:12.5px">Change status</div>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    @foreach (['draft', 'scheduled', 'live'] as $s)
                        <button class="btn" :class="e.status === '{{ $s }}' ? 'primary' : ''" :disabled="e.status === '{{ $s }}'"
                                @click="setStatus('{{ $s }}')">{{ ucfirst($s) }}</button>
                    @endforeach
                    <button class="btn" :class="e.status === 'closed' ? 'primary' : ''" :disabled="e.status === 'closed'"
                            @click="closeConfirm = true">Closed</button>
                </div>
            </div>
            <div>
                <div style="color:var(--muted);font-size:12.5px">Public ballot link</div>
                <a href="{{ route('vote.show', $election) }}" target="_blank" style="color:var(--accent);font-weight:600;font-size:13px;display:inline-block;margin-top:10px;word-break:break-all">{{ route('vote.show', $election) }}</a>
            </div>
        </div>

        {{-- Candidates (dynamic + AJAX modals) --}}
        <div class="card" x-data="candidateTable()" x-init="load()"
             @candidates-changed.window="load()" style="margin-bottom:16px">
            <div class="card-h">
                <b>Candidates</b>
                <div class="tbl-toolbar">
                    <input type="search" placeholder="Search candidates…" x-model="search" @input.debounce.400ms="page=1; load()">
                    <button class="btn primary" style="font-size:12px;padding:6px 12px" @click="openCreate()">Add candidate</button>
                </div>
            </div>
            <div>
                <template x-if="loading"><div style="text-align:center;padding:28px"><span class="spin"></span></div></template>
                <template x-if="!loading && rows.length === 0"><div class="empty">No candidates yet — add the first one.</div></template>
                <template x-for="c in rows" :key="c.id">
                    <div class="cand">
                        <div class="thumb-mini"><template x-if="c.photo"><img :src="c.photo"></template><template x-if="!c.photo"><span x-text="c.name.slice(0,1)"></span></template></div>
                        <div class="grow">
                            <div class="nm"><span x-text="c.name"></span> <span class="pill" :class="c.status === 'approved' ? 'live' : 'draft'" x-text="c.status"></span></div>
                            <div class="party" x-text="[c.position, c.party].filter(Boolean).join(' · ')"></div>
                        </div>
                        <button class="btn" style="padding:6px 12px;font-size:12px" @click="openEdit(c)">Edit</button>
                        <button class="btn danger" style="padding:6px 12px;font-size:12px" @click="ask(c)">Remove</button>
                    </div>
                </template>
            </div>
            <div class="pager">
                <span>Showing <b x-text="meta.from ?? 0"></b>–<b x-text="meta.to ?? 0"></b> of <b x-text="meta.total ?? 0"></b></span>
                <div class="btns">
                    <button @click="page=Math.max(1,page-1); load()" :disabled="page<=1">Prev</button>
                    <button @click="page=Math.min(meta.last_page,page+1); load()" :disabled="page >= (meta.last_page||1)">Next</button>
                </div>
            </div>

            {{-- remove-confirm modal --}}
            <template x-teleport="body">
                <div x-show="del.open" x-cloak class="modal-overlay" @click.self="del.open=false" @keydown.escape.window="del.open=false">
                    <div class="modal-box">
                        <h3>Remove candidate?</h3>
                        <p>Remove <b x-text="del.name"></b> from this election. Votes already cast stay in the record.</p>
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="del.open=false">Cancel</button>
                            <button type="button" class="btn danger" @click="doDelete()" :disabled="del.busy">Remove</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Voter audit log (dynamic) --}}
        <div class="card" x-data="votesTable()" x-init="load()">
            <div class="card-h">
                <b>Voter audit log</b>
                <div class="tbl-toolbar">
                    <input type="search" placeholder="Search email…" x-model="search" @input.debounce.400ms="page=1; load()">
                    <input type="date" x-model="from" @change="page=1; load()" title="From">
                    <input type="date" x-model="to" @change="page=1; load()" title="To">
                </div>
            </div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Email</th><th>Candidate</th><th>IP</th><th style="text-align:right">Voted at</th></tr></thead>
                    <tbody>
                        <template x-if="loading"><tr><td colspan="4" style="text-align:center;padding:28px"><span class="spin"></span></td></tr></template>
                        <template x-if="!loading && rows.length === 0"><tr><td colspan="4"><div class="empty">No votes match.</div></td></tr></template>
                        <template x-for="v in rows" :key="v.email + v.time">
                            <tr>
                                <td x-text="v.email"></td>
                                <td x-text="v.candidate"></td>
                                <td class="mono" x-text="v.ip"></td>
                                <td style="text-align:right;color:var(--muted)" x-text="v.time"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="pager">
                <span>Showing <b x-text="meta.from ?? 0"></b>–<b x-text="meta.to ?? 0"></b> of <b x-text="meta.total ?? 0"></b></span>
                <div class="btns">
                    <button @click="page=Math.max(1,page-1); load()" :disabled="page<=1">Prev</button>
                    <button @click="page=Math.min(meta.last_page,page+1); load()" :disabled="page >= (meta.last_page||1)">Next</button>
                </div>
            </div>
        </div>

        {{-- Edit-election modal --}}
        <template x-teleport="body">
            <div x-show="editOpen" x-cloak class="modal-overlay" @click.self="editOpen=false" @keydown.escape.window="editOpen=false">
                <div class="modal-box wide">
                    <h3>Edit election</h3>
                    <form action="{{ route('admin.elections.update', $election) }}" style="margin-top:16px"
                          x-data="ajaxForm({ onOk: (data) => window.dispatchEvent(new CustomEvent('election-updated', { detail: data.election })) })"
                          @submit.prevent="submit($event)">
                        @method('PUT')
                        @include('elections._modalfields', ['election' => $election])
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="editOpen=false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="busy"><span x-show="!busy">Save changes</span><span x-show="busy" x-cloak><span class="spin"></span></span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Candidate create/edit modal --}}
        <template x-teleport="body">
            <div x-show="candOpen" x-cloak class="modal-overlay" @click.self="candOpen=false" @keydown.escape.window="candOpen=false">
                <div class="modal-box wide">
                    <h3 x-text="cand.id ? 'Edit candidate' : 'New candidate'"></h3>
                    <form :action="candAction()" enctype="multipart/form-data" style="margin-top:16px"
                          x-data="ajaxForm({ onOk: () => window.dispatchEvent(new CustomEvent('candidates-changed')) })"
                          @submit.prevent="submit($event)">
                        <template x-if="cand.id"><input type="hidden" name="_method" value="PUT"></template>
                        <div class="form-grid">
                            <div>
                                <label class="label">Photo <span class="hint">· shown to voters</span></label>
                                <label class="dropzone" :class="(candPhoto || cand.photo) ? 'has' : ''">
                                    <template x-if="candPhoto || cand.photo"><img :src="candPhoto || cand.photo"></template>
                                    <template x-if="!(candPhoto || cand.photo)">
                                        <div class="dz-inner"><div><div class="dz-ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg></div><div class="dz-t">Click to upload</div><div class="dz-s">JPG / PNG · max 2&nbsp;MB</div></div></div>
                                    </template>
                                    <input type="file" name="photo" accept="image/*" style="display:none"
                                           @change="candPhoto = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null">
                                </label>
                                <p class="err" x-show="err('photo')" x-text="err('photo')"></p>
                            </div>
                            <div>
                                <div class="row2">
                                    <div class="field"><label>Full name</label><input class="input" name="name" x-model="cand.name" required><p class="err" x-show="err('name')" x-text="err('name')"></p></div>
                                    <div class="field"><label>Position</label><input class="input" name="position" x-model="cand.position"></div>
                                </div>
                                <div class="row2">
                                    <div class="field"><label>Party / slate</label><input class="input" name="party" x-model="cand.party"></div>
                                    <div class="field"><label>Status</label>
                                        <select class="input" name="status" x-model="cand.status">
                                            <option value="approved">Approved — on ballot</option>
                                            <option value="pending">Pending review</option>
                                            <option value="withdrawn">Withdrawn</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="field"><label>Short bio</label><input class="input" name="bio" x-model="cand.bio"></div>
                                <div class="field"><label>Manifesto <span class="hint">· optional</span></label><textarea class="input" name="manifesto" x-model="cand.manifesto"></textarea></div>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="candOpen=false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="busy"><span x-show="!busy">Save candidate</span><span x-show="busy" x-cloak><span class="spin"></span></span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Close-election confirm --}}
        <template x-teleport="body">
            <div x-show="closeConfirm" x-cloak class="modal-overlay" @click.self="closeConfirm=false" @keydown.escape.window="closeConfirm=false">
                <div class="modal-box">
                    <h3>Close this election?</h3>
                    <p>Voting stops immediately and the result is finalized.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn" @click="closeConfirm=false">Cancel</button>
                        <button type="button" class="btn primary" @click="setStatus('closed')">Close voting</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Export modal (date range → Excel / CSV) --}}
        <template x-teleport="body">
            <div x-show="exportOpen" x-cloak class="modal-overlay" @click.self="exportOpen=false" @keydown.escape.window="exportOpen=false">
                <div class="modal-box wide" x-data="{ from: '', to: '' }">
                    <h3>Export votes</h3>
                    <p>Download individual votes for this election. Leave dates empty for all.</p>
                    <div class="row2" style="margin-top:16px">
                        <div class="field"><label>From date</label><input type="date" class="input" x-model="from"></div>
                        <div class="field"><label>To date</label><input type="date" class="input" x-model="to"></div>
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
            return {
                e: @json(['slug' => $election->slug, 'title' => $election->title, 'status' => $election->status]),
                editOpen: false, candOpen: false, closeConfirm: false, exportOpen: false,
                candPhoto: null,
                cand: { id: null, name: '', position: '', party: '', bio: '', manifesto: '', status: 'approved', photo: null, update_url: '' },
                cap(s) { return s ? s[0].toUpperCase() + s.slice(1) : s; },
                openCreate() { this.candPhoto = null; this.cand = { id: null, name: '', position: 'President', party: '', bio: '', manifesto: '', status: 'approved', photo: null, update_url: '' }; this.candOpen = true; },
                openEdit(c) { this.candPhoto = null; this.cand = { id: c.id, name: c.name, position: c.position || '', party: c.party || '', bio: c.bio || '', manifesto: c.manifesto || '', status: c.status, photo: c.photo, update_url: c.update_url }; this.candOpen = true; },
                candAction() { return this.cand.id ? this.cand.update_url : '{{ route('admin.elections.candidates.store', $election) }}'; },
                async setStatus(status) {
                    const fd = new FormData(); fd.append('status', status);
                    try {
                        const r = await fetch('{{ route('admin.elections.status', $election) }}', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': window.CSRF, Accept: 'application/json' }, body: fd,
                        });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.result === 'success') { this.e.status = (j.data && j.data.status) || status; this.closeConfirm = false; window.toast(j.message || 'Status updated'); }
                        else window.toast(j.message || 'Could not change status', 'error');
                    } catch (e) { window.toast('Network error', 'error'); }
                },
            };
        }
        function candidateTable() {
            return {
                rows: [], meta: {}, loading: true, search: '', page: 1,
                del: { open: false, url: null, name: '', busy: false },
                async load() {
                    this.loading = true;
                    const q = new URLSearchParams({ search: this.search, page: this.page });
                    try {
                        const r = await fetch(`{{ route('admin.elections.candidates.list', $election) }}?${q}`, { headers: { Accept: 'application/json' } });
                        const j = await r.json(); this.rows = j.data.rows; this.meta = j.data;
                    } finally { this.loading = false; }
                },
                ask(c) { this.del = { open: true, url: c.delete_url, name: c.name, busy: false }; },
                async doDelete() {
                    this.del.busy = true;
                    try {
                        const r = await fetch(this.del.url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': window.CSRF, Accept: 'application/json' } });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.result === 'success') { window.toast(j.message || 'Candidate removed'); this.del.open = false; this.page = 1; this.load(); }
                        else window.toast(j.message || 'Could not remove candidate', 'error');
                    } finally { this.del.busy = false; }
                },
            };
        }
        function votesTable() {
            return {
                rows: [], meta: {}, loading: true, search: '', from: '', to: '', page: 1,
                async load() {
                    this.loading = true;
                    const q = new URLSearchParams({ search: this.search, from: this.from, to: this.to, page: this.page });
                    try {
                        const r = await fetch(`{{ route('admin.elections.votes', $election) }}?${q}`, { headers: { Accept: 'application/json' } });
                        const j = await r.json(); this.rows = j.data.rows; this.meta = j.data;
                    } finally { this.loading = false; }
                },
            };
        }
    </script>
</x-app-layout>
