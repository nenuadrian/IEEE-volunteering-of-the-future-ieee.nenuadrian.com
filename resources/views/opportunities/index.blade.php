@extends('layouts.public')
@section('title', 'Opportunities')
@section('meta_description', 'Search IEEE volunteering opportunities by skill, category, region, section, society and duration.')

@php
    $f = $filters;
    $chips = [];
    $q = fn (array $override) => route('opportunities.index', array_merge(request()->except('page'), $override));
    if ($f['category']) $chips[] = [$options['categories']->firstWhere('slug', $f['category'])?->name ?? $f['category'], ['category' => null]];
    foreach ($f['skills'] as $id) $chips[] = [$options['skills'][$id] ?? 'Skill', ['skills' => array_values(array_diff($f['skills'], [$id]))]];
    foreach ($f['upskills'] as $u) $chips[] = ['Builds: '.$u, ['upskills' => array_values(array_diff($f['upskills'], [$u]))]];
    if ($f['region']) $chips[] = [$options['regions'][$f['region']] ?? $f['region'], ['region' => null]];
    if ($f['section'] !== '') $chips[] = ['Section: '.$f['section'], ['section' => null]];
    if ($f['society']) $chips[] = [$f['society'], ['society' => null]];
    if ($f['size']) $chips[] = [$f['size'], ['size' => null]];
    if ($f['experience']) $chips[] = [$f['experience'], ['experience' => null]];
    if ($f['online']) $chips[] = ['Online', ['online' => null]];
    if ($f['accepting']) $chips[] = ['Accepting applicants', ['accepting' => null]];
    if ($f['my_grade']) $chips[] = ['Matches my grade', ['my_grade' => null]];
    if ($f['past']) $chips[] = ['Including past', ['past' => null]];
@endphp

@section('content')
    <x-page-header title="Opportunities" subtitle="Volunteer roles posted by IEEE sections, societies, councils, committees and conferences worldwide.">
        <x-slot:actions>
            @auth
                <a href="{{ route('my.opportunities', ['tab' => 'saved']) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand hover:underline">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.65 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                    Saved opportunities
                </a>
            @endauth
            <a href="{{ route('opportunities.create') }}" class="btn-primary">Create opportunity</a>
        </x-slot:actions>

        {{-- Search & filters --}}
        <form method="GET" action="{{ route('opportunities.index') }}" class="mt-8" x-data="{ more: {{ $search->activeFilterCount() > 0 ? 'true' : 'false' }} }">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <div class="flex flex-col gap-3 md:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">Search opportunities</span>
                    <input type="search" name="q" value="{{ $f['q'] }}" placeholder="Search by title, description, skill, society or place…" class="input h-12 pr-11 text-base">
                    <svg class="pointer-events-none absolute right-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                </label>
                <button type="button" @click="more = !more" class="btn-secondary h-12" :aria-expanded="more">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 12h12M10 20h4"/></svg>
                    Filters
                    @if ($search->activeFilterCount())
                        <span class="rounded-full bg-brand px-2 py-0.5 text-xs font-bold text-white">{{ $search->activeFilterCount() }}</span>
                    @endif
                </button>
                <button class="btn-primary h-12 px-6">Search</button>
            </div>

            <div x-show="more" x-transition x-cloak class="mt-4 rounded-xl border border-light-gray bg-warm-white/60 p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="label" for="f-skills">Skills required</label>
                        <x-skill-picker name="skills" :options="$options['skills']" :selected="$f['skills']" placeholder="Any skill" />
                    </div>
                    <div>
                        <label class="label" for="f-category">Category</label>
                        <select id="f-category" name="category" class="input">
                            <option value="">Any category</option>
                            @foreach ($options['categories'] as $category)
                                <option value="{{ $category->slug }}" @selected($f['category'] === $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="f-size">Duration</label>
                        <select id="f-size" name="size" class="input">
                            <option value="">Any duration</option>
                            @foreach ($options['sizes'] as $size => $hint)
                                <option value="{{ $size }}" @selected($f['size'] === $size)>{{ $size }} — {{ $hint }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="f-region">IEEE region</label>
                        <select id="f-region" name="region" class="input">
                            <option value="">Any region</option>
                            @foreach ($options['regions'] as $code => $label)
                                <option value="{{ $code }}" @selected($f['region'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="f-section">Section</label>
                        <input id="f-section" name="section" value="{{ $f['section'] }}" class="input" placeholder="e.g. Bangalore">
                    </div>
                    <div>
                        <label class="label" for="f-society">Society, council or committee</label>
                        <select id="f-society" name="society" class="input">
                            <option value="">Any</option>
                            @foreach ($options['societies'] as $society)
                                <option value="{{ $society }}" @selected($f['society'] === $society)>{{ $society }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="f-exp">Experience level</label>
                        <select id="f-exp" name="experience" class="input">
                            <option value="">Any level</option>
                            @foreach ($options['experience'] as $level => $hint)
                                <option value="{{ $level }}" @selected($f['experience'] === $level)>{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>
                    <fieldset class="sm:col-span-2">
                        <legend class="label">Skills you'll build</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($options['upskills'] as $upskill => $hint)
                                <label class="cursor-pointer" title="{{ $hint }}">
                                    <input type="checkbox" name="upskills[]" value="{{ $upskill }}" class="peer sr-only" @checked(in_array($upskill, $f['upskills'], true))>
                                    <span class="chip-muted peer-checked:border-brand peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ $upskill }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset class="flex flex-col gap-2 sm:col-span-2">
                        <legend class="sr-only">Options</legend>
                        <label class="inline-flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="online" value="1" class="checkbox" @checked($f['online'])> Online opportunity</label>
                        <label class="inline-flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="accepting" value="1" class="checkbox" @checked($f['accepting'])> Accepting applicants</label>
                        @auth
                            <label class="inline-flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="my_grade" value="1" class="checkbox" @checked($f['my_grade'])> Matching my membership grade only
                                @unless (auth()->user()->profile?->membership_grade)<a href="{{ route('profile.volunteer.edit') }}" class="text-xs text-brand underline">(set your grade)</a>@endunless
                            </label>
                        @endauth
                        <label class="inline-flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="past" value="1" class="checkbox" @checked($f['past'])> Include completed opportunities</label>
                    </fieldset>
                </div>
                <div class="mt-4 flex items-center gap-3 border-t border-light-gray pt-4">
                    <button class="btn-primary">Apply filters</button>
                    @if ($search->activeFilterCount() || $f['q'] !== '')
                        <a href="{{ route('opportunities.index', ['view' => $view]) }}" class="btn-ghost">Clear all</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($chips)
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-warm-gray">Active filters</span>
                @foreach ($chips as [$label, $override])
                    <a href="{{ $q($override) }}" class="chip hover:bg-brand-100" aria-label="Remove filter {{ $label }}">{{ $label }} <span aria-hidden="true">&times;</span></a>
                @endforeach
            </div>
        @endif
    </x-page-header>

    <section class="container-x py-8">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-warm-gray" aria-live="polite">
                <span class="font-semibold text-ink">{{ number_format($opportunities->total()) }}</span> {{ \Illuminate\Support\Str::plural('opportunity', $opportunities->total()) }}
                @if ($f['q'] !== '') for “<span class="font-semibold text-ink">{{ $f['q'] }}</span>”@endif
            </p>
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('opportunities.index') }}" class="flex items-center gap-2">
                    @foreach (request()->except(['sort', 'page']) as $key => $value)
                        @foreach ((array) $value as $v)
                            <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">
                        @endforeach
                    @endforeach
                    <label for="sort" class="text-sm text-warm-gray">Sort by</label>
                    <select id="sort" name="sort" class="input w-auto py-1.5 text-sm" onchange="this.form.submit()">
                        @foreach ($options['sorts'] as $key => $label)
                            @if ($key !== 'match' || auth()->check())
                                <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </form>
                <div class="inline-flex overflow-hidden rounded-md border border-light-gray bg-white text-sm" role="group" aria-label="Result view">
                    <a href="{{ $q(['view' => null]) }}" @class(['px-3 py-1.5', 'bg-brand text-white' => $view === 'list', 'text-warmer-gray hover:bg-brand-50' => $view !== 'list']) @if($view === 'list') aria-current="true" @endif>List</a>
                    <a href="{{ $q(['view' => 'map']) }}" @class(['px-3 py-1.5', 'bg-brand text-white' => $view === 'map', 'text-warmer-gray hover:bg-brand-50' => $view !== 'map']) @if($view === 'map') aria-current="true" @endif>Map</a>
                </div>
            </div>
        </div>
        @if ($sort === 'relevance')
            <p class="-mt-3 mb-5 text-xs text-warm-gray">“Most relevant” lists featured and open opportunities first, then title matches for your search, then the newest.</p>
        @elseif ($sort === 'match')
            <p class="-mt-3 mb-5 text-xs text-warm-gray">“Best match” ranks open opportunities by how well they fit your skills (60%), membership grade (20%) and region (20%).
                @if (auth()->user()?->skills()->count() < 3) <a href="{{ route('profile.volunteer.edit') }}#skills" class="text-brand underline">Add skills</a> for better matches.@endif
            </p>
        @endif

        @if ($view === 'map')
            <div class="card overflow-hidden">
                <div id="opportunity-map" class="h-[32rem] w-full" role="region" aria-label="Map of opportunities"></div>
            </div>
            <p class="mt-3 text-xs text-warm-gray">
                Showing {{ count($mapPoints) }} opportunities with a location. Online-only opportunities appear in the list view.
            </p>
            @push('head')
                <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
            @endpush
            @push('scripts')
                <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
                <script>
                    (function () {
                        const points = @json($mapPoints);
                        const map = L.map('opportunity-map', { scrollWheelZoom: false }).setView([20, 10], 2);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 18,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        }).addTo(map);
                        const bounds = [];
                        points.forEach((p) => {
                            const marker = L.circleMarker([p.lat, p.lng], { radius: 8, color: '#ffffff', weight: 2, fillColor: '#e87722', fillOpacity: 0.95 }).addTo(map);
                            const box = document.createElement('div');
                            const a = document.createElement('a');
                            a.href = p.url; a.textContent = p.title; a.style.fontWeight = '600'; a.style.color = '#c4601a';
                            const meta = document.createElement('div');
                            meta.textContent = p.meta; meta.style.fontSize = '12px'; meta.style.color = '#666';
                            box.append(a, meta);
                            marker.bindPopup(box);
                            bounds.push([p.lat, p.lng]);
                        });
                        if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 6 });
                    })();
                </script>
            @endpush
        @elseif ($opportunities->isEmpty())
            <x-empty-state title="No opportunities match your search" icon="🔎">
                Try removing a filter, searching for a broader term, or including completed opportunities.
                <x-slot:actions>
                    <a href="{{ route('opportunities.index') }}" class="btn-secondary">Clear search</a>
                    <a href="{{ route('opportunities.create') }}" class="btn-primary">Create one yourself</a>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="space-y-4">
                @foreach ($opportunities as $opportunity)
                    <x-opportunity.card :opportunity="$opportunity" :match="$opportunity->match ?? null" :saved="in_array($opportunity->id, $savedIds, true)" />
                @endforeach
            </div>
            <div class="mt-8">{{ $opportunities->links() }}</div>
        @endif
    </section>
@endsection
