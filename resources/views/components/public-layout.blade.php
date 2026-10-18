@props(['title' => 'BallotBox'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · BallotBox</title>
    @include('partials.assets')
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>
    <header class="pub-top">
        <nav class="inner" aria-label="Site">
            <a href="{{ route('home') }}" class="brand" style="padding:0">
                <span class="mark" aria-hidden="true">🗳</span>
                <b>BallotBox</b>
            </a>
            <div class="spacer" style="flex:1"></div>
            <button type="button" class="iconbtn" onclick="toggleTheme()" aria-label="Toggle theme" title="Toggle theme">
                <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
            </button>
            @auth
                <a href="{{ route('dashboard') }}" class="btn">Dashboard</a>
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="btn">Log in</a>
                @endif
            @endauth
        </nav>
    </header>
    <main id="main" class="pub-main" tabindex="-1">
        {{ $slot }}
    </main>
    <x-flash />
</body>
</html>
