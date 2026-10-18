<section>
    <header>
        <h2 class="card-title">{{ __('Delete Account') }}</h2>
        <p class="help" style="margin-top:4px">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button x-data="" type="button" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        {{ __('Delete Account') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <h3>{{ __('Are you sure you want to delete your account?') }}</h3>

            <p>
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="field" style="margin-top:16px">
                <x-input-label for="delete_account_password" value="{{ __('Password') }}" class="visually-hidden" />
                <x-text-input id="delete_account_password" name="password" type="password" placeholder="{{ __('Password') }}" autocomplete="current-password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="modal-actions">
                <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                <x-danger-button>{{ __('Delete Account') }}</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
