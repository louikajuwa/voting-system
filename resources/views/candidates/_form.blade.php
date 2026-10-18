<div class="form-grid" x-data="{ preview: @js($candidate->photoUrl()) }">
    {{-- Photo upload --}}
    <div>
        <div class="field" style="margin-bottom:6px"><label>Candidate photo <span class="hint">· shown to voters</span></label></div>
        <label class="dropzone" :class="preview ? 'has' : ''">
            <template x-if="preview">
                <img :src="preview" alt="Photo preview">
            </template>
            <template x-if="!preview">
                <div class="dz-inner">
                    <div>
                        <div class="dz-ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg></div>
                        <div class="dz-t">Click to upload</div>
                        <div class="dz-s">JPG / PNG · max 2&nbsp;MB</div>
                    </div>
                </div>
            </template>
            <input type="file" id="cand-photo-file" name="photo" accept="image/*" class="visually-hidden" aria-label="Candidate photo"
                @change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : preview">
        </label>
        @error('photo') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Fields --}}
    <div>
        <div class="row2">
            <div class="field">
                <label for="cf-name">Full name</label>
                <input type="text" id="cf-name" name="name" value="{{ old('name', $candidate->name) }}" required>
                @error('name') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="cf-position">Position</label>
                <input type="text" id="cf-position" name="position" value="{{ old('position', $candidate->position) }}">
            </div>
        </div>
        <div class="row2">
            <div class="field">
                <label for="cf-party">Party / slate <span class="hint">· optional</span></label>
                <input type="text" id="cf-party" name="party" value="{{ old('party', $candidate->party) }}">
            </div>
            <div class="field">
                <label for="cf-status">Status</label>
                <select id="cf-status" name="status">
                    @foreach (['approved' => 'Approved — on ballot', 'pending' => 'Pending review', 'withdrawn' => 'Withdrawn'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('status', $candidate->status) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field">
            <label for="cf-bio">Short bio <span class="hint">· one line under the name</span></label>
            <input type="text" id="cf-bio" name="bio" value="{{ old('bio', $candidate->bio) }}">
        </div>
        <div class="field">
            <label for="cf-manifesto">Manifesto <span class="hint">· optional</span></label>
            <textarea id="cf-manifesto" name="manifesto">{{ old('manifesto', $candidate->manifesto) }}</textarea>
        </div>
    </div>
</div>
