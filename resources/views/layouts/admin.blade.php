<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="bg-warm-white font-sans text-ink antialiased" x-data="{ sidebar: false }">
<a href="#main-content" class="skip-link">Skip to main content</a>
@php($pendingCount = \App\Models\Post::pending()->whereIn('type', ['job', 'resource'])->count())
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 w-64 transform overflow-y-auto bg-charcoal p-4 transition lg:static lg:translate-x-0">
        <a href="{{ route('admin.dashboard') }}" class="mb-6 flex items-center gap-3 px-2">
            <x-logo-mark chip size="h-10" />
            <span class="font-heading text-sm font-extrabold text-cream">Admin Panel</span>
        </a>

        <nav class="space-y-1">
            <x-admin.link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="◆">Dashboard</x-admin.link>

            @can('moderate submissions')
                <x-admin.link :href="route('admin.submissions.index')" :active="request()->routeIs('admin.submissions.*')" icon="✓">
                    Submissions
                    <x-slot:badge>
                        @if ($pendingCount)<span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-charcoal">{{ $pendingCount }}</span>@endif
                    </x-slot:badge>
                </x-admin.link>
            @endcan

            <p class="px-3 pb-1 pt-4 font-ui text-xs uppercase tracking-wider text-cream/40">Content</p>
            @can('manage posts')<x-admin.link :href="route('admin.posts.index')" :active="request()->routeIs('admin.posts.*')" icon="✎">Blog posts</x-admin.link>@endcan
            @can('manage pages')<x-admin.link :href="route('admin.pages.index')" :active="request()->routeIs('admin.pages.*')" icon="▤">Pages</x-admin.link>@endcan
            @can('manage media')<x-admin.link :href="route('admin.media.index')" :active="request()->routeIs('admin.media.*')" icon="❏">Media &amp; files</x-admin.link>@endcan
            @can('manage categories')<x-admin.link :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.*')" icon="⊞">Categories</x-admin.link>@endcan
            @can('manage menus')<x-admin.link :href="route('admin.menus.index')" :active="request()->routeIs('admin.menus.*')" icon="≡">Menus</x-admin.link>@endcan
            @can('manage slideshows')<x-admin.link :href="route('admin.slideshows.index')" :active="request()->routeIs('admin.slideshows.*')" icon="▦">Slideshows</x-admin.link>@endcan

            <p class="px-3 pb-1 pt-4 font-ui text-xs uppercase tracking-wider text-cream/40">People</p>
            @can('manage team')<x-admin.link :href="route('admin.people.index')" :active="request()->routeIs('admin.people.*')" icon="★">Team profiles</x-admin.link>@endcan
            @can('manage team')<x-admin.link :href="route('admin.positions.index')" :active="request()->routeIs('admin.positions.*')" icon="⌗">Roles &amp; structure</x-admin.link>@endcan
            @can('manage users')<x-admin.link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="◉">Users</x-admin.link>@endcan
            @can('manage roles')<x-admin.link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')" icon="⚿">Usergroups</x-admin.link>@endcan

            @can('manage settings')
                <p class="px-3 pb-1 pt-4 font-ui text-xs uppercase tracking-wider text-cream/40">System</p>
                <x-admin.link :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')" icon="⚙">Settings</x-admin.link>
                <x-admin.link :href="route('admin.email-templates.index')" :active="request()->routeIs('admin.email-templates.*')" icon="✉">Email templates</x-admin.link>
            @endcan
        </nav>

        <div class="mt-6 space-y-1 border-t border-white/10 pt-4">
            <x-admin.link :href="route('home')" icon="↗">View site</x-admin.link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 font-ui text-sm font-medium text-cream/70 hover:bg-white/10 hover:text-cream">
                    <span>⏻</span> Log out
                </button>
            </form>
        </div>
    </aside>

    {{-- Backdrop (mobile) --}}
    <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-light-gray bg-white px-6">
            <button @click="sidebar = !sidebar" class="rounded-lg p-2 text-brand hover:bg-brand-50 lg:hidden" aria-label="Menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="font-heading text-lg font-bold text-charcoal">@yield('heading', 'Admin')</h1>
            <div class="flex items-center gap-2 text-sm text-warm-gray">
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <span class="grid h-8 w-8 place-items-center rounded-full bg-brand text-xs font-bold text-cream">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            </div>
        </header>

        @include('partials.verify-notice', ['wide' => true])

        @if (session('status') && session('status') !== 'verification-link-sent')
            <div class="border-b border-accent-green/20 bg-accent-green-light/40 px-6 py-3 text-sm font-medium text-accent-green-dark">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="border-b border-red-200 bg-red-50 px-6 py-3 text-sm text-red-700">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <main id="main-content" tabindex="-1" class="flex-1 p-6">
            @yield('content')
        </main>
    </div>
</div>

@include('partials.accessibility')
</body>
</html>
