@props(['status'])
@php
    $label = ucfirst($status);
    $cls = in_array($status, ['live', 'draft', 'closed']) ? $status : 'closed';
@endphp
<span {{ $attributes->merge(['class' => "pill $cls"]) }}>{{ $label }}</span>
