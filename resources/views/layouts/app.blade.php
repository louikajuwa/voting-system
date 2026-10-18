@php
    $user = auth()->user();
    $initials = \Illuminate\Support\Str::of($user?->name ?? 'U')->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $overview = request()->routeIs('dashboard') || request()->routeIs('admin.elections.*') || request()->routeIs('admin.*candidates*');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $attributes->get('title') ?? 'Admin' }} · BallotBox</title>
    @include('partials.assets')
</head>
<body>
    <a href="#main" class="skip-link">Skip to main content</a>
    <div class="app"
         x-data="{ nav: false, mobile: matchMedia('(max-width: 900px)').matches, orgName: @js($user?->organization?->name ?? 'BallotBox'), orgLogo: @js($user?->organization?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->organization->logo) : null),
                   openNav() { this.nav = true; this.$nextTick(() => setTimeout(() => this.$refs.sidebar.querySelector('a')?.focus(), 260)); },
                   closeNav() { if (!this.nav) return; this.nav = false; this.$refs.burger.focus(); } }"
         x-init="const mq = matchMedia('(max-width: 900px)'); mq.addEventListener('change', e => { mobile = e.matches; if (!e.matches) nav = false; })"
         @keydown.escape.window="closeNav()"
         @org-updated.window="orgName = $event.detail.name; if ($event.detail.logo) orgLogo = $event.detail.logo">
        <div class="nav-backdrop" x-show="nav" x-cloak @click="closeNav()" aria-hidden="true"></div>
        <aside class="sidebar" id="sidebar" x-ref="sidebar" :class="{ open: nav }" @click="nav = false"
               x-effect="$el.toggleAttribute('inert', mobile && !nav)" aria-label="Sidebar">
            <a href="{{ route('dashboard') }}" class="brand" aria-label="BallotBox — Overview">
                <div class="mark" aria-hidden="true">🗳</div>
                <div><b>BallotBox</b><span>Voting platform</span></div>
            </a>

            <nav aria-label="Main" style="display:flex;flex-direction:column;gap:4px">
            <div class="navlabel">Workspace</div>
            <a href="{{ route('dashboard') }}" class="nav {{ $overview ? 'active' : '' }}" @if ($overview) aria-current="page" @endif>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                Overview
            </a>
            <a href="{{ route('admin.elections.create') }}" class="nav {{ request()->routeIs('admin.elections.create') ? 'active' : '' }}" @if (request()->routeIs('admin.elections.create')) aria-current="page" @endif>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                New election
            </a>

            <div class="navlabel">Public</div>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="nav">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                Voter site
            </a>

            <div class="navlabel">Insights</div>
            @if (Route::has('admin.analytics'))
                <a href="{{ route('admin.analytics') }}" class="nav {{ request()->routeIs('admin.analytics') ? 'active' : '' }}" @if (request()->routeIs('admin.analytics')) aria-current="page" @endif>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                    Analytics
                </a>
            @endif
            @if (Route::has('admin.members.index'))
                <a href="{{ route('admin.members.index') }}" class="nav {{ request()->routeIs('admin.members.*') ? 'active' : '' }}" @if (request()->routeIs('admin.members.*')) aria-current="page" @endif>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0113 0M16 6a3 3 0 010 6M22 20a6.5 6.5 0 00-4-6"/></svg>
                    Members
                </a>
            @endif
            @if (Route::has('admin.org.edit'))
                <a href="{{ route('admin.org.edit') }}" class="nav {{ request()->routeIs('admin.org.*') ? 'active' : '' }}" @if (request()->routeIs('admin.org.*')) aria-current="page" @endif>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.6 1.6 0 00-2.7 1.1V21a2 2 0 11-4 0v-.1A1.6 1.6 0 004.6 19l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.6 1.6 0 00-1.1-2.7H.6a2 2 0 110-4h.1A1.6 1.6 0 002.4 6"/></svg>
                    Settings
                </a>
            @endif

            </nav>

            <div class="sidefoot">
                <div class="avatar" aria-hidden="true">{{ $initials }}</div>
                <div style="min-width:0;flex:1">
                    <div style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $user?->name }}</div>
                    <div style="color:var(--faint);font-size:11px">{{ $user?->getRoleNames()->first() ?? 'Member' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="iconbtn" title="Log out" aria-label="Log out" style="width:30px;height:30px">
                        <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main">
            <div class="topbar">
                <button class="iconbtn hamburger" x-ref="burger" @click.stop="openNav()" aria-label="Open menu" title="Menu" aria-controls="sidebar" :aria-expanded="nav.toString()">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
                <div class="orgswitch">
                    <template x-if="orgLogo"><img :src="orgLogo" alt="" style="width:20px;height:20px;border-radius:6px;object-fit:cover"></template>
                    <template x-if="!orgLogo"><div class="logo" x-text="orgName.charAt(0)"></div></template>
                    <span x-text="orgName"></span>
                </div>
                <div class="spacer"></div>
                <button class="iconbtn" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">
                    <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
                </button>
                <a href="{{ route('dashboard') }}?new=1" class="btn primary" aria-label="New election">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                    <span class="hide-sm">New election</span>
                </a>
            </div>

            <main id="main" class="content" tabindex="-1">
                {{ $slot }}
            </main>
            <x-flash />
        </div>
    </div>
</body>
</html>
