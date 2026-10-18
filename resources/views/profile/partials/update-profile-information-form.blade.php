<section>
    <header>
        <h2 class="card-title">{{ __('Profile Information') }}</h2>
        <p class="help" style="margin-top:4px">{{ __("Update your account's profile information and email address.") }}</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="field">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="alert warn" style="margin:10px 0 0">
                    {{ __('Your email address is unverified.') }}
                    <button form="send-verification" class="link-btn">{{ __('Click here to re-send the verification email.') }}</button>

                    @if (session('status') === 'verification-link-sent')
                        <p role="status" style="margin:8px 0 0;font-weight:600">{{ __('A new verification link has been sent to your email address.') }}</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="form-actions">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="form-saved" role="status">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
