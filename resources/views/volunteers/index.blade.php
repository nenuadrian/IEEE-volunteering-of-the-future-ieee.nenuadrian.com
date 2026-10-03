@extends('layouts.public')
@section('title', 'Volunteers')
@section('meta_description', 'Find IEEE volunteers by skill, region, section, society and membership grade.')

@php
    $f = $filters;
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
@endphp

@section('content')
    <x-page-header title="Volunteers" :subtitle="number_format($volunteers->total()).' IEEE members ready to share their skills. Find people for your next opportunity, or get inspired by their journeys.'">
        <form method="GET" action="{{ route('volunteers.index') }}" class="mt-8" x-data="{ more: {{ $activeFilters ? 'true' : 'false' }} }">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <div class="flex flex-col gap-3 md:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">Search volunteers</span>
                    <input type="search" name="q" value="{{ $f['q'] }}" placeholder="Search by name, skill, headline, section or city…" class="input h-12 pr-11 text-base">
                    <svg class="pointer-events-none absolute right-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                </label>
                <button type="button" @click="more = !more" class="btn-secondary h-12" :aria-expanded="more">
                    Filters @if ($activeFilters)<span class="rounded-full bg-brand px-2 py-0.5 text-xs font-bold text-white">{{ $activeFilters }}</span>@endif
                </button>
                <button class="btn-primary h-12 px-6">Search</button>
            </div>
            <div x-show="more" x-transition x-cloak class="mt-4 rounded-xl border border-light-gray bg-warm-white/60 p-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="label">Has all of these skills</label>
                        <x-skill-picker name="skills" :options="$skillOptions" :selected="$f['skills']" placeholder="Any skill" />
                    </div>
                    <div>
                        <label class="label" for="v-region">IEEE region</label>
                        <select id="v-region" name="region" class="input">
                            <option value="">Any region</option>
                            @foreach (config('volunteering.regions') as $code => $label)
                                <option value="{{ $code }}" @selected($f['region'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="v-section">Section</label>
                        <input id="v-section" name="section" value="{{ $f['section'] }}" class="input" placeholder="e.g. Benelux">
                    </div>
                    <div>
                        <label class="label" for="v-society">Society, council or committee</label>
                        <select id="v-society" name="society" class="input">
                            <option value="">Any</option>
                            @foreach (config('volunteering.societies') as $society)
                                <option value="{{ $society }}" @selected($f['society'] === $society)>{{ $society }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="v-grade">Membership grade</label>
                        <select id="v-grade" name="grade" class="input">
                            <option value="">Any grade</option>
                            @foreach (config('volunteering.membership_grades') as $code => $label)
                                <option value="{{ $code }}" @selected($f['grade'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="v-country">Country</label>
                        <select id="v-country" name="country" class="input">
                            <option value="">Any country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country }}" @selected($f['country'] === $country)>{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col justify-end gap-2">
                        <label class="inline-flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="available" value="1" class="checkbox" @checked($f['available'])> Available for new opportunities</label>
                        <label class="inline-flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="endorsed" value="1" class="checkbox" @checked($f['endorsed'])> Has endorsements</label>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3 border-t border-light-gray pt-4">
                    <button class="btn-primary">Apply filters</button>
                    @if ($activeFilters || $f['q'] !== '')<a href="{{ route('volunteers.index') }}" class="btn-ghost">Clear all</a>@endif
                </div>
            </div>
        </form>
    </x-page-header>

    <section class="container-x py-8">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-warm-gray" aria-live="polite"><span class="font-semibold text-ink">{{ number_format($volunteers->total()) }}</span> {{ \Illuminate\Support\Str::plural('volunteer', $volunteers->total()) }}</p>
            <form method="GET" action="{{ route('volunteers.index') }}" class="flex items-center gap-2">
                @foreach (request()->except(['sort', 'page']) as $key => $value)
                    @foreach ((array) $value as $v)<input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">@endforeach
                @endforeach
                <label for="v-sort" class="text-sm text-warm-gray">Sort by</label>
                <select id="v-sort" name="sort" class="input w-auto py-1.5 text-sm" onchange="this.form.submit()">
                    @foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>@endforeach
                </select>
            </form>
        </div>

        @if ($volunteers->isEmpty())
            <x-empty-state title="No volunteers match those filters" icon="🔎">Try fewer skills or a wider region.</x-empty-state>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($volunteers as $profile)
                    <article class="card group relative flex flex-col p-5 transition hover:border-brand/40 hover:shadow-dropdown">
                        <div class="flex items-start gap-4">
                            <x-avatar :user="$profile->user" size="h-14 w-14" text="text-lg" />
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-base font-semibold text-ink">
                                    <a href="{{ route('volunteers.show', $profile) }}" class="after:absolute after:inset-0 group-hover:text-brand">{{ $profile->user->name }}</a>
                                </h2>
                                @if ($profile->headline)<p class="truncate text-sm text-warmer-gray">{{ $profile->headline }}</p>@endif
                                <p class="truncate text-xs text-warm-gray">{{ collect([$profile->section ? $profile->section.' Section' : null, $profile->country])->filter()->implode(' · ') ?: $profile->regionLabel() }}</p>
                            </div>
                            <span @class(['mt-1 h-2.5 w-2.5 shrink-0 rounded-full',
                                'bg-accent-green' => $profile->availability === 'available',
                                'bg-amber-400' => $profile->availability === 'limited',
                                'bg-light-gray' => $profile->availability === 'unavailable']) title="{{ $profile->availabilityLabel() }}"><span class="sr-only">{{ $profile->availabilityLabel() }}</span></span>
                        </div>
                        <div class="mt-4 flex flex-1 flex-wrap content-start gap-1.5">
                            @forelse ($profile->user->skills->take(5) as $skill)
                                <span class="chip-muted">{{ $skill->name }}</span>
                            @empty
                                <span class="text-xs text-warm-gray">No skills listed yet</span>
                            @endforelse
                            @if ($profile->user->skills->count() > 5)<span class="text-xs text-warm-gray">+{{ $profile->user->skills->count() - 5 }}</span>@endif
                        </div>
                        <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-light-gray pt-3 text-center">
                            <div><dt class="text-[11px] text-warm-gray">Hours</dt><dd class="text-base font-bold text-brand">{{ $fmt($profile->approved_hours) }}</dd></div>
                            <div><dt class="text-[11px] text-warm-gray">Completed</dt><dd class="text-base font-bold text-ink">{{ $profile->completed_count }}</dd></div>
                            <div><dt class="text-[11px] text-warm-gray">Endorsements</dt><dd class="text-base font-bold text-ink">{{ $profile->endorsement_count }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>
            <div class="mt-8">{{ $volunteers->links() }}</div>
        @endif
    </section>
@endsection
