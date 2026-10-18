{{-- Self-contained error shell: no DB, auth, session, route() or component calls, so it renders
     even when the database or cache is down. Only ballotbox.css + a tiny inline theme script. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title') · BallotBox</title>
    <link rel="stylesheet" href="/css/ballotbox.css">
    <script>
        try {
            var d = localStorage.theme === 'dark' || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = d ? 'dark' : 'light';
        } catch (e) {}
    </script>
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>
    <header class="pub-top">
        <nav class="inner" aria-label="Site">
            <a href="/" class="brand" style="padding:0">
                <span class="mark" aria-hidden="true">🗳</span>
                <b>BallotBox</b>
            </a>
        </nav>
    </header>
    <main id="main" class="pub-main">
        <div class="err-page">
            <div class="code" aria-hidden="true">@yield('code')</div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div class="actions">
                @hasSection('actions')
                    @yield('actions')
                @else
                    <a href="/" class="btn primary">Back to home</a>
                @endif
            </div>
        </div>
    </main>
</body>
</html>
