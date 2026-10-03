@php
    $user = auth()->user();
    $nav = [
        ['Opportunities', route('opportunities.index'), request()->routeIs('opportunities.index', 'opportunities.show')],
        ['My Opportunities', route('my.opportunities'), request()->routeIs('my.opportunities', 'opportunities.manage')],
        ['Volunteers', route('volunteers.index'), request()->routeIs('volunteers.*')],
        ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
    ];
@endphp
<header x-data="{ mobile: false }" class="sticky top-0 z-40 print:hidden">
    {{-- IEEE network bar --}}
    <div class="bg-gradient-to-r from-charcoal-light to-charcoal text-white">
        <div class="container-x flex h-9 items-center justify-between gap-4 text-[13px]">
            <nav aria-label="IEEE sites" class="hidden items-center divide-x divide-white/40 sm:flex">
                <a href="https://www.ieee.org" class="pr-3 hover:underline" rel="noopener">IEEE.org</a>
                <a href="https://ieeexplore.ieee.org" class="px-3 hover:underline" rel="noopener">IEEE <em>Xplore</em> Digital Library</a>
                <a href="https://standards.ieee.org" class="hidden px-3 hover:underline md:inline" rel="noopener">IEEE Standards</a>
                <a href="https://spectrum.ieee.org" class="hidden px-3 hover:underline lg:inline" rel="noopener">IEEE Spectrum</a>
                <a href="https://www.ieee.org/sitemap.html" class="hidden pl-3 hover:underline lg:inline" rel="noopener">More Sites</a>
            </nav>
            <div class="ml-auto flex items-center gap-4">
                @auth
                    @can('admin')
                        <a href="{{ route('admin.dashboard') }}" class="hover:underline">Admin panel</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="hover:underline">Sign Out</button></form>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">Sign In</a>
                    <a href="{{ route('register') }}" class="hover:underline">Join</a>
                @endauth
                <span class="hidden font-extrabold tracking-wider sm:inline" aria-hidden="true">IEEE</span>
            </div>
        </div>
    </div>

    {{-- Main navigation --}}
    <div class="border-b border-light-gray bg-white/95 backdrop-blur">
        <nav class="container-x flex h-16 items-center justify-between gap-4" aria-label="Main">
            <a href="{{ route('home') }}" class="shrink-0" aria-label="IEEE Volunteering home">
                <x-logo-mark size="text-[22px]" />
            </a>

            <div class="hidden h-full items-stretch lg:flex">
                @foreach ($nav as [$label, $href, $active])
                    <a href="{{ $href }}" @class([
                        'relative flex items-center border-l border-light-gray px-4 text-[15px] transition first:border-l-0',
                        'text-brand after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:bg-brand' => $active,
                        'text-warmer-gray hover:text-brand' => ! $active,
                    ])>
                        {{ $label }}
                        @if ($label === 'My Opportunities' && ($needsActionCount ?? 0) > 0)
                            <span class="ml-1.5 rounded-full bg-brand px-1.5 py-0.5 text-[11px] font-bold leading-none text-white" title="Items need your action">{{ $needsActionCount }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="hidden items-center gap-3 lg:flex">
                @auth
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 text-[15px] text-warmer-gray hover:text-brand" :aria-expanded="open">
                            <x-avatar :user="$user" size="h-8 w-8" text="text-xs" />
                            <span>My Profile</span>
                            <svg class="h-3 w-3 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 7l5 6 5-6H5z"/></svg>
                        </button>
                        <div x-show="open" x-transition x-cloak class="absolute right-0 top-full mt-2 w-60 rounded-xl border border-light-gray bg-white p-2 shadow-dropdown">
                            <div class="border-b border-light-gray px-3 pb-2 pt-1">
                                <p class="truncate text-sm font-semibold text-ink">{{ $user->name }}</p>
                                <p class="truncate text-xs text-warm-gray">{{ $user->email }}</p>
                            </div>
                            <a href="{{ route('volunteers.show', $user->profile) }}" class="mt-1 block rounded-lg px-3 py-2 text-sm text-warmer-gray hover:bg-brand-50 hover:text-brand">View my profile &amp; CV</a>
                            <a href="{{ route('profile.volunteer.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-warmer-gray hover:bg-brand-50 hover:text-brand">Edit volunteer profile</a>
                            <a href="{{ route('my.opportunities', ['tab' => 'saved']) }}" class="block rounded-lg px-3 py-2 text-sm text-warmer-gray hover:bg-brand-50 hover:text-brand">Saved opportunities</a>
                            <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-warmer-gray hover:bg-brand-50 hover:text-brand">Account settings</a>
                            @can('admin')
                                <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm text-warmer-gray hover:bg-brand-50 hover:text-brand">Admin panel</a>
                            @endcan
                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-light-gray pt-1">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">Sign out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-[15px] text-warmer-gray hover:text-brand">Sign in</a>
                @endauth
                <a href="{{ route('opportunities.create') }}" class="btn-primary">Create Opportunity</a>
            </div>

            <button @click="mobile = !mobile" class="rounded-md p-2 text-brand hover:bg-brand-50 lg:hidden" aria-label="Menu" :aria-expanded="mobile">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </nav>

        {{-- Mobile menu --}}
        <div x-show="mobile" x-transition x-cloak class="border-t border-light-gray bg-white lg:hidden">
            <div class="container-x space-y-1 py-4">
                @foreach ($nav as [$label, $href, $active])
                    <a href="{{ $href }}" @class(['flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium', 'bg-brand-50 text-brand' => $active, 'text-warmer-gray hover:bg-brand-50' => ! $active])>
                        {{ $label }}
                        @if ($label === 'My Opportunities' && ($needsActionCount ?? 0) > 0)
                            <span class="rounded-full bg-brand px-2 py-0.5 text-xs font-bold text-white">{{ $needsActionCount }}</span>
                        @endif
                    </a>
                @endforeach
                <div class="mt-3 flex flex-col gap-2 border-t border-light-gray pt-3">
                    <a href="{{ route('opportunities.create') }}" class="btn-primary w-full">Create Opportunity</a>
                    @auth
                        <a href="{{ route('volunteers.show', $user->profile) }}" class="btn-secondary w-full">My profile &amp; CV</a>
                        <a href="{{ route('profile.volunteer.edit') }}" class="btn-ghost w-full">Edit volunteer profile</a>
                        @can('admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn-ghost w-full">Admin panel</a>
                        @endcan
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary w-full">Sign in</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</header>

@include('partials.verify-notice')
