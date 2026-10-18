@php
    $tz = $election->exists ? $election->timezone() : (auth()->user()?->organization?->timezone ?: 'UTC');
    // Mirror the server-side state machine so the form only offers accepted statuses.
    $statuses = $election->exists
        ? array_values(array_unique([$election->status, ...(\App\Models\Election::TRANSITIONS[$election->status] ?? [])]))
        : ['draft', 'scheduled'];
@endphp
<div class="field">
    <label for="f-title">Title</label>
    <input type="text" id="f-title" name="title" value="{{ old('title', $election->title) }}" required maxlength="255">
    @error('title') <div class="err">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="f-description">Description <span class="hint">· optional</span></label>
    <textarea id="f-description" name="description">{{ old('description', $election->description) }}</textarea>
    @error('description') <div class="err">{{ $message }}</div> @enderror
</div>

<div class="field">
    <label for="f-status">Status</label>
    <select id="f-status" name="status">
        @foreach ($statuses as $s)
            <option value="{{ $s }}" @selected(old('status', $election->status ?? 'draft') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    @error('status') <div class="err">{{ $message }}</div> @enderror
</div>

<div class="field">
    <input type="hidden" name="hide_results" value="0">
    <label class="check">
        <input type="checkbox" name="hide_results" value="1" @checked(old('hide_results', $election->hide_results))>
        Hide results until voting closes
    </label>
</div>

<div class="row2">
    <div class="field">
        <label for="f-opens">Opens at <span class="hint">· {{ $tz }}</span></label>
        <input type="datetime-local" id="f-opens" name="opens_at" value="{{ old('opens_at', $election->formatLocal('opens_at')) }}">
        @error('opens_at') <div class="err">{{ $message }}</div> @enderror
    </div>
    <div class="field">
        <label for="f-closes">Closes at <span class="hint">· {{ $tz }}</span></label>
        <input type="datetime-local" id="f-closes" name="closes_at" value="{{ old('closes_at', $election->formatLocal('closes_at')) }}">
        @error('closes_at') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>
