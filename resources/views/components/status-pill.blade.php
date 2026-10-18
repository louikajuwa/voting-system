@props(['status'])
@php
    $label = ucfirst($status);
    $cls = in_array($status, ['live', 'draft', 'closed', 'scheduled', 'approved', 'pending', 'withdrawn']) ? $status : 'closed';
@endphp
<span {{ $attributes->merge(['class' => "pill $cls"]) }}>{{ $label }}</span>
