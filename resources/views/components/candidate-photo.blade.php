@props(['candidate', 'size' => 42])
@php $url = $candidate->photoUrl(); @endphp
<div {{ $attributes->merge(['class' => 'thumb-mini', 'style' => "width:{$size}px;height:{$size}px"]) }}>
    @if ($url)
        <img src="{{ $url }}" alt="{{ $candidate->name }}">
    @else
        <span aria-hidden="true">{{ \Illuminate\Support\Str::of($candidate->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
    @endif
</div>
