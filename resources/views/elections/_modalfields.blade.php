@php $e = $election ?? null; $tz = $e?->timezone() ?? (auth()->user()?->organization?->timezone ?: 'UTC'); @endphp
<div class="field">
    <label>Title</label>
    <input class="input" name="title" value="{{ $e->title ?? '' }}" required>
    <p class="err" x-show="err('title')" x-text="err('title')"></p>
</div>
<div class="field">
    <label>Description <span class="hint">· optional</span></label>
    <textarea class="input" name="description">{{ $e->description ?? '' }}</textarea>
</div>
<div class="field">
    <label>Status</label>
    <select class="input" name="status">
        @foreach (\App\Models\Election::STATUSES as $s)
            <option value="{{ $s }}" @selected(($e->status ?? 'draft') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label style="display:flex;align-items:center;gap:8px;font-weight:normal">
        <input type="hidden" name="hide_results" value="0">
        <input type="checkbox" name="hide_results" value="1" @checked($e->hide_results ?? false)>
        Hide results until voting closes
    </label>
</div>
<div class="row2">
    <div class="field">
        <label>Opens at <span class="hint">· {{ $tz }}</span></label>
        <input class="input" type="datetime-local" name="opens_at" value="{{ $e?->formatLocal('opens_at') }}">
    </div>
    <div class="field">
        <label>Closes at <span class="hint">· {{ $tz }}</span></label>
        <input class="input" type="datetime-local" name="closes_at" value="{{ $e?->formatLocal('closes_at') }}">
        <p class="err" x-show="err('closes_at')" x-text="err('closes_at')"></p>
    </div>
</div>
