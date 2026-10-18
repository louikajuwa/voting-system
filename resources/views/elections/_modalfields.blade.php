@php
    $e = $election ?? null;
    $tz = $e?->timezone() ?? (auth()->user()?->organization?->timezone ?: 'UTC');
    // Only offer states the server will accept: new elections start as draft/scheduled,
    // existing ones may stay put or take an allowed transition.
    $statuses = $e
        ? array_values(array_unique([$e->status, ...(\App\Models\Election::TRANSITIONS[$e->status] ?? [])]))
        : ['draft', 'scheduled'];
@endphp
<div class="field">
    <label for="ef-title">Title</label>
    <input class="input" id="ef-title" name="title" value="{{ $e->title ?? '' }}" required maxlength="255">
    <p class="err" x-show="err('title')" x-text="err('title')"></p>
</div>
<div class="field">
    <label for="ef-description">Description <span class="hint">· optional</span></label>
    <textarea class="input" id="ef-description" name="description">{{ $e->description ?? '' }}</textarea>
</div>
<div class="field">
    <label for="ef-status">Status</label>
    <select class="input" id="ef-status" name="status">
        @foreach ($statuses as $s)
            <option value="{{ $s }}" @selected(($e->status ?? 'draft') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <p class="err" x-show="err('status')" x-text="err('status')"></p>
</div>
<div class="field">
    <input type="hidden" name="hide_results" value="0">
    <label class="check">
        <input type="checkbox" name="hide_results" value="1" @checked($e->hide_results ?? false)>
        Hide results until voting closes
    </label>
</div>
<div class="row2">
    <div class="field">
        <label for="ef-opens">Opens at <span class="hint">· {{ $tz }}</span></label>
        <input class="input" id="ef-opens" type="datetime-local" name="opens_at" value="{{ $e?->formatLocal('opens_at') }}">
        <p class="err" x-show="err('opens_at')" x-text="err('opens_at')"></p>
    </div>
    <div class="field">
        <label for="ef-closes">Closes at <span class="hint">· {{ $tz }}</span></label>
        <input class="input" id="ef-closes" type="datetime-local" name="closes_at" value="{{ $e?->formatLocal('closes_at') }}">
        <p class="err" x-show="err('closes_at')" x-text="err('closes_at')"></p>
    </div>
</div>
