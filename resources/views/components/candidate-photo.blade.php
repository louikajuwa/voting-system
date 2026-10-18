@props(['candidate', 'size' => 'w-12 h-12'])
@php $url = $candidate->photoUrl(); @endphp
<div {{ $attributes->merge(['class' => trim($size).' rounded-xl overflow-hidden flex-none']) }}>
    @if ($url)
        <img src="{{ $url }}" alt="{{ $candidate->name }}" class="w-full h-full object-cover">
    @else
        <div class="w-full h-full grid place-items-center bg-brand text-white font-semibold">
            {{ \Illuminate\Support\Str::of($candidate->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
        </div>
    @endif
</div>
