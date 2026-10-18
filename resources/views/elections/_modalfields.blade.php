@php $e = $election ?? null; $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d\TH:i') : ''; @endphp
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
<div class="row2">
    <div class="field">
        <label>Opens at</label>
        <input class="input" type="datetime-local" name="opens_at" value="{{ $fmt($e->opens_at ?? null) }}">
    </div>
    <div class="field">
        <label>Closes at</label>
        <input class="input" type="datetime-local" name="closes_at" value="{{ $fmt($e->closes_at ?? null) }}">
        <p class="err" x-show="err('closes_at')" x-text="err('closes_at')"></p>
    </div>
</div>
