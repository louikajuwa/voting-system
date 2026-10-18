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
<div x-data="{ open: false }" style="display:inline-flex">
    <button type="button" class="{{ $triggerClass }}" @click="open = true">{{ $trigger }}</button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="modal-overlay"
             @click.self="open = false" @keydown.escape.window="open = false">
            <div class="modal-box" role="dialog" aria-modal="true">
                <h3>{{ $title }}</h3>
                <p>{{ $message }}</p>
                {{ $slot }}
                <div class="modal-actions">
                    <button type="button" class="btn" @click="open = false">Cancel</button>
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @if (strtoupper($method) !== 'POST') @method($method) @endif
                        {{ $fields ?? '' }}
                        <button type="submit" class="btn {{ $danger ? 'danger' : 'primary' }}">{{ $confirm }}</button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
