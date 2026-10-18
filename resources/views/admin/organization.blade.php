<x-app-layout title="Organization settings">
    <div class="page-h"><div><h1>Organization settings</h1><p>Branding and defaults for your organization.</p></div></div>

    <div class="card pad" style="max-width:640px"
         x-data="{ logo: @js($org->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($org->logo) : null) }">
        <form action="{{ route('admin.org.update') }}" enctype="multipart/form-data"
              x-data="ajaxForm({ onOk: (data) => window.dispatchEvent(new CustomEvent('org-updated', { detail: data })) })"
              @submit.prevent="submit($event)">
            @method('PATCH')

            <div class="form-grid">
                <div>
                    <span class="label">Logo <span class="hint">· shown in the sidebar</span></span>
                    <label class="dropzone" :class="logo ? 'has' : ''">
                        <template x-if="logo"><img :src="logo" alt="Logo"></template>
                        <template x-if="!logo">
                            <div class="dz-inner"><div><div class="dz-ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg></div><div class="dz-t">Upload logo</div><div class="dz-s">PNG / SVG · max 1&nbsp;MB</div></div></div>
                        </template>
                        <input type="file" id="org-logo" name="logo" accept="image/*" class="visually-hidden" aria-label="Organization logo"
                               @change="logo = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : logo">
                    </label>
                    <p class="err" x-show="err('logo')" x-text="err('logo')"></p>
                </div>

                <div>
                    <div class="field">
                        <label for="org-name">Organization name</label>
                        <input class="input" id="org-name" type="text" name="name" value="{{ $org->name }}" required>
                        <p class="err" x-show="err('name')" x-text="err('name')"></p>
                    </div>
                    <div class="field">
                        <label for="org-tz">Timezone</label>
                        <select class="input" id="org-tz" name="timezone">
                            @foreach (['UTC', 'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Asia/Manila', 'Asia/Singapore', 'Asia/Tokyo', 'Australia/Sydney'] as $tz)
                                <option value="{{ $tz }}" @selected($org->timezone === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary" :disabled="busy">
                    <span x-show="!busy">Save settings</span><span x-show="busy" x-cloak><span class="spin"></span></span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
