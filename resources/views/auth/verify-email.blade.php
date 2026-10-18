<x-guest-layout title="Verify email · BallotBox">
    <h1 class="card-title" style="margin-bottom:8px">Verify your email</h1>
    <p class="help">{{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}</p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert ok" role="status">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="auth-actions between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>{{ __('Resend Verification Email') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link-btn">{{ __('Log Out') }}</button>
        </form>
    </div>
</x-guest-layout>
