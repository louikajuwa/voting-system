<x-app-layout>
    <div x-data="membersPage()" x-init="load()"
         @member-added.window="addOpen = false; tempPw = $event.detail; load()">
        <div class="page-h">
            <div><h1>Members</h1><p>People who can manage this organization.</p></div>
            <button class="btn primary" @click="addOpen = true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                Add member
            </button>
        </div>

        <template x-if="tempPw">
            <div class="alert ok" x-cloak>
                New member created. One-time password: <b class="mono" x-text="tempPw"></b>
                <button type="button" class="btn" style="padding:2px 8px;font-size:12px;margin-left:8px" @click="navigator.clipboard.writeText(tempPw); toast('Copied')">Copy</button>
            </div>
        </template>

        <div class="card">
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th style="text-align:right">Actions</th></tr></thead>
                    <tbody>
                        <template x-if="loading"><tr><td colspan="4" style="text-align:center;padding:28px"><span class="spin"></span></td></tr></template>
                        <template x-for="m in rows" :key="m.id">
                            <tr>
                                <td><span class="el-title" x-text="m.name"></span> <template x-if="m.is_self"><span class="pill sched">You</span></template></td>
                                <td style="color:var(--muted)" x-text="m.email"></td>
                                <td>
                                    <select class="input" style="width:auto;padding:5px 9px" @change="changeRole(m, $event.target.value)">
                                        <option value="Manager" :selected="m.role === 'Manager'">Manager</option>
                                        <option value="Admin" :selected="m.role === 'Admin'">Admin</option>
                                    </select>
                                </td>
                                <td style="text-align:right">
                                    <template x-if="!m.is_self"><button class="btn danger" style="padding:6px 12px;font-size:12px" @click="ask(m)">Remove</button></template>
                                    <template x-if="m.is_self"><span style="color:var(--faint);font-size:12px">—</span></template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Add member modal --}}
        <template x-teleport="body">
            <div x-show="addOpen" x-cloak class="modal-overlay" @click.self="addOpen=false" @keydown.escape.window="addOpen=false">
                <div class="modal-box">
                    <h3>Add a member</h3>
                    <p>They get a one-time temporary password (shown after saving).</p>
                    <form action="{{ route('admin.members.store') }}" style="margin-top:16px"
                          x-data="ajaxForm({ onOk: (data) => window.dispatchEvent(new CustomEvent('member-added', { detail: data.temp_password })) })"
                          @submit.prevent="submit($event)">
                        <div class="field"><label>Name</label><input class="input" name="name" required><p class="err" x-show="err('name')" x-text="err('name')"></p></div>
                        <div class="field"><label>Email</label><input class="input" type="email" name="email" required><p class="err" x-show="err('email')" x-text="err('email')"></p></div>
                        <div class="field"><label>Role</label>
                            <select class="input" name="role">
                                <option value="Manager">Manager — manage elections &amp; candidates</option>
                                <option value="Admin">Admin — full access incl. members &amp; settings</option>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="addOpen=false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="busy"><span x-show="!busy">Add member</span><span x-show="busy" x-cloak><span class="spin"></span></span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Remove confirm --}}
        <template x-teleport="body">
            <div x-show="del.open" x-cloak class="modal-overlay" @click.self="del.open=false" @keydown.escape.window="del.open=false">
                <div class="modal-box">
                    <h3>Remove member?</h3>
                    <p>Remove <b x-text="del.name"></b> — they lose access to this organization.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn" @click="del.open=false">Cancel</button>
                        <button type="button" class="btn danger" @click="doDelete()" :disabled="del.busy">Remove</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
        function membersPage() {
            return {
                rows: [], loading: true, addOpen: false, tempPw: null,
                del: { open: false, url: null, name: '', busy: false },
                async load() {
                    this.loading = true;
                    try {
                        const r = await fetch('{{ route('admin.members.list') }}', { headers: { Accept: 'application/json' } });
                        this.rows = (await r.json()).data.rows;
                    } finally { this.loading = false; }
                },
                async changeRole(m, role) {
                    const fd = new FormData(); fd.append('_method', 'PATCH'); fd.append('role', role);
                    const r = await fetch(m.update_url, { method: 'POST', headers: { 'X-CSRF-TOKEN': window.CSRF, Accept: 'application/json' }, body: fd });
                    const j = await r.json().catch(() => ({}));
                    if (r.ok && j.result === 'success') { m.role = role; window.toast(j.message || 'Role updated'); }
                    else window.toast(j.message || 'Could not update role', 'error');
                },
                ask(m) { this.del = { open: true, url: m.delete_url, name: m.name, busy: false }; },
                async doDelete() {
                    this.del.busy = true;
                    try {
                        const r = await fetch(this.del.url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': window.CSRF, Accept: 'application/json' } });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.result === 'success') { window.toast(j.message || 'Member removed'); this.del.open = false; this.load(); }
                        else window.toast(j.message || 'Could not remove member', 'error');
                    } finally { this.del.busy = false; }
                },
            };
        }
    </script>
</x-app-layout>
