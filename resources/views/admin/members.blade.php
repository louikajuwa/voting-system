<x-app-layout title="Members">
    <div x-data="membersPage()" x-init="load()"
         @member-added.window="addOpen = false; tempPw = $event.detail; load()">
        <div class="page-h">
            <div><h1>Members</h1><p>People who can manage this organization.</p></div>
            <button type="button" class="btn primary" @click="addOpen = true">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                Add member
            </button>
        </div>

        <template x-if="tempPw">
            <div class="alert ok" role="alert" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="flex:1;min-width:200px">New member created. One-time password (shown only once): <b class="mono" id="temp-pw" x-text="tempPw" style="user-select:all;color:var(--ink)"></b></span>
                <button type="button" class="btn sm" @click="copyPw()">Copy</button>
                <button type="button" class="btn sm" @click="tempPw = null" aria-label="Dismiss">Dismiss</button>
            </div>
        </template>

        <div class="card">
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th style="text-align:right">Actions</th></tr></thead>
                    <tbody>
                        <template x-if="loading"><tr><td colspan="4" style="text-align:center;padding:28px"><span class="spin" role="status" aria-label="Loading members"></span></td></tr></template>
                        <template x-if="!loading && failed"><tr class="err-row"><td colspan="4" role="alert">Could not load members. <button type="button" class="link-btn" @click="load()">Retry</button></td></tr></template>
                        <template x-if="!loading && !failed && rows.length === 0"><tr><td colspan="4"><div class="empty"><div class="big" aria-hidden="true">👥</div><p>No members yet — add the first one.</p></div></td></tr></template>
                        <template x-for="m in rows" :key="m.id">
                            <tr>
                                <td><span class="el-title" x-text="m.name"></span> <template x-if="m.is_self"><span class="pill sched">You</span></template></td>
                                <td style="color:var(--muted)" x-text="m.email"></td>
                                <td>
                                    <template x-if="m.is_self"><span x-text="m.role"></span></template>
                                    <template x-if="!m.is_self">
                                        <select class="input" style="width:auto;padding:5px 9px" :aria-label="'Role for ' + m.name" @change="askRole(m, $event.target)">
                                            <option value="Manager" :selected="m.role === 'Manager'">Manager</option>
                                            <option value="Admin" :selected="m.role === 'Admin'">Admin</option>
                                        </select>
                                    </template>
                                </td>
                                <td style="text-align:right">
                                    <template x-if="!m.is_self"><button type="button" class="btn danger sm" :aria-label="'Remove ' + m.name" @click="ask(m)">Remove</button></template>
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
            <div x-show="addOpen" x-cloak x-modal="addOpen" class="modal-overlay" @click.self="addOpen=false" @keydown.escape.window="addOpen=false">
                <div class="modal-box">
                    <h3>Add a member</h3>
                    <p>They get a one-time temporary password (shown after saving).</p>
                    <form action="{{ route('admin.members.store') }}" style="margin-top:16px"
                          x-data="ajaxForm({ onOk: (data, self, form) => { self.reset(form); window.dispatchEvent(new CustomEvent('member-added', { detail: data.temp_password })); } })"
                          @submit.prevent="submit($event)">
                        <div class="field"><label for="mem-name">Name</label><input class="input" id="mem-name" name="name" required><p class="err" x-show="err('name')" x-text="err('name')"></p></div>
                        <div class="field"><label for="mem-email">Email</label><input class="input" id="mem-email" type="email" name="email" required><p class="err" x-show="err('email')" x-text="err('email')"></p></div>
                        <div class="field"><label for="mem-role">Role</label>
                            <select class="input" id="mem-role" name="role">
                                <option value="Manager">Manager — manage elections &amp; candidates</option>
                                <option value="Admin">Admin — full access incl. members &amp; settings</option>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn" @click="addOpen=false">Cancel</button>
                            <button type="submit" class="btn primary" :disabled="busy"><span x-show="!busy">Add member</span><span x-show="busy" x-cloak><span class="spin" role="status" aria-label="Adding"></span></span></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Role-change confirm --}}
        <template x-teleport="body">
            <div x-show="roleChange.open" x-cloak x-modal="roleChange.open" class="modal-overlay" @click.self="cancelRole()" @keydown.escape.window="cancelRole()">
                <div class="modal-box">
                    <h3>Change role?</h3>
                    <p>Change <b x-text="roleChange.name"></b> from <b x-text="roleChange.from"></b> to <b x-text="roleChange.role"></b>? Their access changes immediately.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn" @click="cancelRole()" :disabled="roleChange.busy">Cancel</button>
                        <button type="button" class="btn primary" @click="doRole()" :disabled="roleChange.busy">
                            <span x-show="!roleChange.busy">Change role</span><span x-show="roleChange.busy" x-cloak><span class="spin" role="status" aria-label="Saving"></span></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Remove confirm --}}
        <template x-teleport="body">
            <div x-show="del.open" x-cloak x-modal="del.open" class="modal-overlay" @click.self="del.open=false" @keydown.escape.window="del.open=false">
                <div class="modal-box">
                    <h3>Remove member?</h3>
                    <p>Remove <b x-text="del.name"></b> — they lose access to this organization.</p>
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

    <script>
        function membersPage() {
            return {
                rows: [], loading: true, failed: false, addOpen: false, tempPw: null,
                del: { open: false, url: null, name: '', busy: false },
                roleChange: { open: false, m: null, el: null, name: '', from: '', role: '', busy: false },
                async load() {
                    this.loading = true; this.failed = false;
                    try {
                        const r = await window.api('{{ route('admin.members.list') }}');
                        if (!r.ok) throw new Error('http ' + r.status);
                        this.rows = (await r.json()).data.rows;
                    } catch (e) { if (!e.session) { this.failed = true; this.rows = []; } }
                    finally { this.loading = false; }
                },
                async copyPw() {
                    try {
                        if (!navigator.clipboard) throw new Error('no clipboard');
                        await navigator.clipboard.writeText(this.tempPw);
                        window.toast('Copied');
                    } catch (e) {
                        // Fallback: select the text so the user can copy it manually.
                        const node = document.getElementById('temp-pw');
                        if (node) {
                            const range = document.createRange(); range.selectNodeContents(node);
                            const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
                            try { if (document.execCommand('copy')) { window.toast('Copied'); return; } } catch (_) {}
                        }
                        window.toast('Password selected — press Ctrl+C to copy');
                    }
                },
                askRole(m, el) {
                    if (el.value === m.role) return;
                    this.roleChange = { open: true, m, el, name: m.name, from: m.role, role: el.value, busy: false };
                },
                cancelRole() {
                    if (this.roleChange.busy) return;
                    if (this.roleChange.el && this.roleChange.m) this.roleChange.el.value = this.roleChange.m.role;
                    this.roleChange.open = false;
                },
                async doRole() {
                    const rc = this.roleChange;
                    if (rc.busy) return;
                    rc.busy = true;
                    const fd = new FormData(); fd.append('_method', 'PATCH'); fd.append('role', rc.role);
                    try {
                        const r = await window.api(rc.m.update_url, { method: 'POST', body: fd });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.result === 'success') { rc.m.role = rc.role; window.toast(j.message || 'Role updated'); }
                        else { rc.el.value = rc.m.role; window.toast(j.message || 'Could not update role', 'error'); }
                    } catch (e) { rc.el.value = rc.m.role; window.netError(e); }
                    finally { rc.busy = false; rc.open = false; }
                },
                ask(m) { this.del = { open: true, url: m.delete_url, name: m.name, busy: false }; },
                async doDelete() {
                    if (this.del.busy) return;
                    this.del.busy = true;
                    try {
                        const r = await window.api(this.del.url, { method: 'DELETE' });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok && j.result === 'success') { window.toast(j.message || 'Member removed'); this.del.open = false; this.load(); }
                        else { this.del.open = false; window.toast(j.message || 'Could not remove member', 'error'); }
                    } catch (e) { window.netError(e); }
                    finally { this.del.busy = false; }
                },
            };
        }
    </script>
</x-app-layout>
