<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $attributes->get('title') ?? config('app.name', 'BallotBox') }}</title>
    @include('partials.assets')
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>
    <main id="main" class="auth-wrap" tabindex="-1">
        <a href="{{ route('home') }}" class="brand" style="padding:0" aria-label="BallotBox home">
            <span class="mark" aria-hidden="true">🗳</span>
            <div><b>BallotBox</b><span>Voting platform</span></div>
        </a>
        <div class="card pad auth-card">
            {{ $slot }}
        </div>
    </main>
</body>
</html>
