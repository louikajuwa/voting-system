@php
    $tz = $election->exists ? $election->timezone() : (auth()->user()?->organization?->timezone ?: 'UTC');
@endphp
<div class="field">
    <label>Title</label>
    <input type="text" name="title" value="{{ old('title', $election->title) }}" required>
    @error('title') <div class="err">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label>Description <span class="hint">· optional</span></label>
    <textarea name="description">{{ old('description', $election->description) }}</textarea>
</div>

<div class="field">
    <label>Status</label>
    <select name="status">
        @foreach (\App\Models\Election::STATUSES as $s)
            <option value="{{ $s }}" @selected(old('status', $election->status) === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label style="display:flex;align-items:center;gap:8px;font-weight:normal">
        <input type="hidden" name="hide_results" value="0">
        <input type="checkbox" name="hide_results" value="1" @checked(old('hide_results', $election->hide_results))>
        Hide results until voting closes
    </label>
</div>

<div class="row2">
    <div class="field">
        <label>Opens at <span class="hint">· {{ $tz }}</span></label>
        <input type="datetime-local" name="opens_at" value="{{ old('opens_at', $election->formatLocal('opens_at')) }}">
    </div>
    <div class="field">
        <label>Closes at <span class="hint">· {{ $tz }}</span></label>
        <input type="datetime-local" name="closes_at" value="{{ old('closes_at', $election->formatLocal('closes_at')) }}">
        @error('closes_at') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>
