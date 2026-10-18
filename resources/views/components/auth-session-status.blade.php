@props(['status'])

@if ($status)
    <div role="status" {{ $attributes->merge(['class' => 'alert ok']) }}>
        {{ $status }}
    </div>
@endif
