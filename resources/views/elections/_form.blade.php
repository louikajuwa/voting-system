@php
    $fmt = fn ($dt) => $dt ? \Illuminate\Support\Carbon::parse($dt)->format('Y-m-d\TH:i') : '';
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

<div class="row2">
    <div class="field">
        <label>Opens at</label>
        <input type="datetime-local" name="opens_at" value="{{ old('opens_at', $fmt($election->opens_at)) }}">
    </div>
    <div class="field">
        <label>Closes at</label>
        <input type="datetime-local" name="closes_at" value="{{ old('closes_at', $fmt($election->closes_at)) }}">
        @error('closes_at') <div class="err">{{ $message }}</div> @enderror
    </div>
</div>
