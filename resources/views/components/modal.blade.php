@props([
    'name',
    'show' => false,
    'maxWidth' => 'md',
])
{{-- Named modal driven by window events: $dispatch('open-modal', 'name') / $dispatch('close-modal', 'name'). --}}
<div x-data="{ show: @js($show) }"
     x-modal="show"
     x-show="show" x-cloak
     x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
     x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
     x-on:close.stop="show = false"
     x-on:keydown.escape.window="show = false"
     x-on:click.self="show = false"
     class="modal-overlay">
    <div class="modal-box {{ in_array($maxWidth, ['lg', 'xl', '2xl']) ? 'wide' : '' }}">
        {{ $slot }}
    </div>
</div>
