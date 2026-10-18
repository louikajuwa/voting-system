@props([
    'action',
    'method' => 'DELETE',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirm' => 'Confirm',
    'trigger' => 'Delete',
    'triggerClass' => 'btn danger',
    'danger' => true,
])
<div x-data="{ open: false, busy: false }" @pageshow.window="busy = false" style="display:inline-flex">
    <button type="button" class="{{ $triggerClass }}" @click="open = true">{{ $trigger }}</button>

    <template x-teleport="body">
        <div x-show="open" x-cloak x-modal="open" class="modal-overlay"
             @click.self="open = false" @keydown.escape.window="open = false">
            <div class="modal-box">
                <h3>{{ $title }}</h3>
                <p>{{ $message }}</p>
                {{ $slot }}
                <div class="modal-actions">
                    <button type="button" class="btn" @click="open = false" :disabled="busy">Cancel</button>
                    <form method="POST" action="{{ $action }}" @submit="if (busy) { $event.preventDefault(); return; } busy = true">
                        @csrf
                        @if (strtoupper($method) !== 'POST') @method($method) @endif
                        {{ $fields ?? '' }}
                        <button type="submit" class="btn {{ $danger ? 'danger' : 'primary' }}" :disabled="busy" :aria-busy="busy.toString()">
                            <span x-show="!busy">{{ $confirm }}</span>
                            <span x-show="busy" x-cloak><span class="spin" role="status" aria-label="Working"></span></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
