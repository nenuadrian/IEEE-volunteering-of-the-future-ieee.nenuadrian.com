<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="bg-warm-white font-sans text-ink antialiased" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
<a href="#main-content" class="skip-link">Skip to main content</a>
@php
    // Flag the IEEE sync in the sidebar when the last refresh failed or is stale.
    $latestSync = \App\Models\SyncRun::query()->latest('started_at')->latest('id')->first();
    $syncNeedsAttention = $latestSync && ($latestSync->status === 'failed' || $latestSync->started_at?->lt(now()->subHours(48)));
    $syncNeverRun = ! $latestSync;
@endphp
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside id="admin-sidebar" :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 transform flex-col overflow-y-auto bg-charcoal-dark p-4 transition lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
           aria-label="Admin navigation">
        <div class="mb-6 flex items-center justify-between px-2">
            <a href="{{ route('admin.dashboard') }}" class="flex items-baseline gap-2">
                <x-logo-mark light size="text-lg" />
                <span class="rounded bg-white/10 px-1.5 py-0.5 font-ui text-[11px] font-semibold uppercase tracking-wider text-cream/80">Admin</span>
            </a>
            <button type="button" @click="sidebar = false" class="rounded-md p-1 text-cream/60 hover:bg-white/10 hover:text-cream lg:hidden" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1">
            <p class="px-3 pb-1 font-ui text-xs font-semibold uppercase tracking-wider text-cream/40">Insights</p>
            <x-admin.link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="◆">Analytics</x-admin.link>

            <p class="px-3 pb-1 pt-5 font-ui text-xs font-semibold uppercase tracking-wider text-cream/40">Volunteering</p>
            <x-admin.link :href="route('admin.opportunities.index')" :active="request()->routeIs('admin.opportunities.*')" icon="✦">Opportunities</x-admin.link>
            <x-admin.link :href="route('admin.sync.index')" :active="request()->routeIs('admin.sync.*')" icon="⟳">
                IEEE sync
                <x-slot:badge>
                    @if ($syncNeedsAttention || $syncNeverRun)
                        <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-charcoal-dark"
                              title="{{ $syncNeverRun ? 'Never synced' : ($latestSync->status === 'failed' ? 'Last refresh failed' : 'Last refresh is older than 48 hours') }}">
                            {{ $syncNeverRun ? 'new' : ($latestSync->status === 'failed' ? 'failed' : 'stale') }}
                        </span>
                    @endif
                </x-slot:badge>
            </x-admin.link>
            <x-admin.link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="◉">Users</x-admin.link>
            <x-admin.link :href="route('admin.skills.index')" :active="request()->routeIs('admin.skills.*')" icon="✚">Skills</x-admin.link>
            <x-admin.link :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.*')" icon="⊞">Opportunity types</x-admin.link>
            <x-admin.link :href="route('admin.activity.index')" :active="request()->routeIs('admin.activity.*')" icon="≋">Activity log</x-admin.link>

            <p class="px-3 pb-1 pt-5 font-ui text-xs font-semibold uppercase tracking-wider text-cream/40">Site</p>
            <x-admin.link :href="route('admin.pages.index')" :active="request()->routeIs('admin.pages.*')" icon="▤">Pages</x-admin.link>
            <x-admin.link :href="route('admin.menus.index')" :active="request()->routeIs('admin.menus.*')" icon="≡">Menus</x-admin.link>
            <x-admin.link :href="route('admin.media.index')" :active="request()->routeIs('admin.media.*')" icon="❏">Media &amp; files</x-admin.link>
            <x-admin.link :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')" icon="⚙">Settings</x-admin.link>
            <x-admin.link :href="route('admin.email-templates.index')" :active="request()->routeIs('admin.email-templates.*')" icon="✉">Email templates</x-admin.link>
        </nav>

        <div class="mt-6 space-y-1 border-t border-white/10 pt-4">
            <x-admin.link :href="route('home')" icon="↗">View site</x-admin.link>
            <x-admin.link :href="route('dashboard')" icon="⌂">My dashboard</x-admin.link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 font-ui text-sm font-medium text-cream/70 transition hover:bg-white/10 hover:text-cream">
                    <span class="text-base leading-none" aria-hidden="true">⏻</span> Log out
                </button>
            </form>
        </div>
    </aside>

    {{-- Backdrop (mobile) --}}
    <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden" aria-hidden="true"></div>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-light-gray bg-white px-4 sm:px-6">
            <button type="button" @click="sidebar = !sidebar" class="rounded-lg p-2 text-brand hover:bg-brand-50 lg:hidden"
                    aria-label="Open menu" aria-controls="admin-sidebar" :aria-expanded="sidebar.toString()">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="min-w-0 flex-1 truncate font-heading text-lg font-bold text-charcoal">@yield('heading', 'Admin')</h1>
            <div class="flex items-center gap-2 text-sm text-warm-gray">
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <x-avatar :user="auth()->user()" size="h-8 w-8" text="text-xs" />
            </div>
        </header>

        @include('partials.verify-notice', ['wide' => true])

        @if (session('status') && session('status') !== 'verification-link-sent')
            <div class="border-b border-accent-green/20 bg-accent-green-light/40 px-4 py-3 text-sm font-medium text-accent-green-dark sm:px-6" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 sm:px-6" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:px-6" role="alert">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <main id="main-content" tabindex="-1" class="flex-1 p-4 sm:p-6">
            @yield('content')
        </main>
    </div>
</div>

@include('partials.accessibility')
</body>
</html>
