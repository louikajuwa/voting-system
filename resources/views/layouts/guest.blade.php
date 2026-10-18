<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'BallotBox') }}</title>
    @include('partials.assets')
</head>
<body>
    <div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;gap:20px">
        <a href="{{ route('home') }}" class="brand" style="padding:0">
            <span class="mark">🗳</span>
            <div><b>BallotBox</b><span>Voting platform</span></div>
        </a>
        <div class="card pad" style="width:100%;max-width:400px">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
