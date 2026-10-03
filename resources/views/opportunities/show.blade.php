@extends('layouts.public')
@section('title', $opportunity->title)
@section('meta_description', $opportunity->excerpt(160))
@if ($opportunity->thumbnail())@section('og_image', $opportunity->thumbnail())@endif

@php
    $user = auth()->user();
    $mySkillIds = $user?->skills()->pluck('skills.id')->all() ?? [];
    $owner = $opportunity->owners->first();
    $filledPct = $opportunity->volunteers_needed ? min(100, (int) round($confirmedCount / $opportunity->volunteers_needed * 100)) : 0;
    $unit = $opportunity->society ?: ($opportunity->section ? $opportunity->section.' Section' : $opportunity->regionLabel());
@endphp

@section('content')
    <section class="border-b border-light-gray bg-white">
        <div class="container-x py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-warm-gray">
                <a href="{{ route('opportunities.index') }}" class="hover:text-brand">Opportunities</a>
                @if ($opportunity->category)
                    <span aria-hidden="true" class="mx-1.5">/</span>
                    <a href="{{ route('opportunities.index', ['category' => $opportunity->category->slug]) }}" class="hover:text-brand">{{ $opportunity->category->name }}</a>
                @endif
            </nav>

            @if ($opportunity->isDraft())
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    This is a <strong>draft</strong> — only owners and admins can see it.
                    <a href="{{ route('opportunities.edit', $opportunity) }}" class="font-semibold underline">Finish and publish</a>
                </div>
            @endif

            <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-start">
                <x-opportunity.thumb :opportunity="$opportunity" size="h-28 w-28" class="hidden sm:grid" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$opportunity->status" />
                        @if ($opportunity->is_online)<span class="chip-blue">Online</span>@endif
                        @if ($opportunity->isImported())
                            <span class="chip-muted" title="Imported from volunteer.ieee.org">Synced from volunteer.ieee.org</span>
                        @endif
                        @if ($opportunity->clonedFrom && ! $opportunity->clonedFrom->trashed())
                            <span class="text-xs text-warm-gray">Based on <a href="{{ route('opportunities.show', $opportunity->clonedFrom) }}" class="underline hover:text-brand">an earlier opportunity</a></span>
                        @endif
                    </div>
                    <h1 class="mt-3 text-3xl font-semibold leading-tight text-ink">{{ $opportunity->title }}</h1>
                    @if ($unit)
                        <p class="mt-2 text-warm-gray">{{ $unit }}</p>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        {{-- Primary action depends on who is looking --}}
                        @if ($canManage)
                            <a href="{{ route('opportunities.manage', $opportunity) }}" class="btn-primary">Manage applicants &amp; impact</a>
                            <a href="{{ route('opportunities.edit', $opportunity) }}" class="btn-secondary">Edit</a>
                        @elseif ($application && $application->status !== \App\Models\Application::WITHDRAWN)
                            <span class="inline-flex items-center gap-2 rounded-md bg-warm-white px-3 py-2 text-sm">
                                Your application: <x-status-badge :status="$application->status" type="application" />
                            </span>
                        @elseif ($opportunity->acceptsApplications())
                            @auth
                                <button type="button" class="btn-primary px-6" x-data @click="$dispatch('open-modal', 'apply')">Apply to volunteer</button>
                            @else
                                <a href="{{ route('login') }}" class="btn-primary px-6">Sign in to apply</a>
                            @endauth
                        @else
                            <span class="rounded-md bg-warm-white px-3 py-2 text-sm text-warm-gray">Not accepting applications</span>
                        @endif

                        @auth
                            <form method="POST" action="{{ route('opportunities.save', $opportunity) }}">
                                @csrf
                                <button class="btn-ghost" aria-pressed="{{ $saved ? 'true' : 'false' }}">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $saved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                                    {{ $saved ? 'Saved' : 'Save' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('opportunities.clone', $opportunity) }}">
                                @csrf
                                <button class="btn-ghost" title="Create your own draft based on this opportunity">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg>
                                    {{ $canManage ? 'Clone' : 'Use as template' }}
                                </button>
                            </form>
                        @endauth
                        <button type="button" class="btn-ghost" x-data="{ copied: false }"
                                @click="navigator.clipboard?.writeText(window.location.href).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
                            <span x-text="copied ? 'Link copied' : 'Share'">Share</span>
                        </button>
                    </div>
                </div>

                @if ($match)
                    <div class="card w-full shrink-0 p-5 lg:w-72">
                        <p class="text-sm text-warm-gray">Your match</p>
                        <p @class(['text-4xl font-bold', 'text-accent-green' => $match['percent'] >= 80, 'text-brand' => $match['percent'] < 80])>{{ $match['percent'] }}%</p>
                        <ul class="mt-3 space-y-1.5 text-sm">
                            @foreach ($match['reasons'] as $reason)
                                <li class="flex gap-2 text-warmer-gray"><span class="text-brand" aria-hidden="true">•</span>{{ $reason }}</li>
                            @endforeach
                        </ul>
                        @if ($match['missing'])
                            <p class="mt-3 text-xs text-warm-gray">A chance to learn: {{ implode(', ', array_slice($match['missing'], 0, 4)) }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="container-x grid gap-8 py-10 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-8">
            {{-- My application --}}
            @if ($application)
                @include('opportunities.partials.my-application', ['application' => $application])
            @endif

            <section class="card p-6">
                <h2 class="text-lg font-semibold text-ink">About this opportunity</h2>
                <div class="content mt-4 whitespace-pre-line">{{ $opportunity->description }}</div>
                @if ($opportunity->details_url)
                    <a href="{{ $opportunity->details_url }}" target="_blank" rel="noopener nofollow" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-accent-blue hover:underline">More details ↗</a>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="text-lg font-semibold text-ink">Skills</h2>
                <div class="mt-4 grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold text-warmer-gray">Required</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($opportunity->skills as $skill)
                                @if (in_array($skill->id, $mySkillIds, true))
                                    <span class="chip bg-accent-green-light/60 text-accent-green-dark" title="You have this skill">✓ {{ $skill->name }}</span>
                                @else
                                    <span class="chip-blue">{{ $skill->name }}</span>
                                @endif
                            @empty
                                <span class="text-sm text-warm-gray">No specific skills required.</span>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-warmer-gray">You'll build</h3>
                        <ul class="mt-2 space-y-2">
                            @forelse ($opportunity->upskills ?? [] as $upskill)
                                <li class="text-sm"><span class="font-semibold text-ink">{{ $upskill }}</span>
                                    <span class="block text-xs text-warm-gray">{{ config('volunteering.upskills')[$upskill] ?? '' }}</span></li>
                            @empty
                                <li class="text-sm text-warm-gray">Not specified.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                @if ($opportunity->ideal_traits)
                    <div class="mt-6 border-t border-light-gray pt-4">
                        <h3 class="text-sm font-semibold text-warmer-gray">Traits of an ideal candidate</h3>
                        <p class="mt-1 text-sm text-warmer-gray">{{ $opportunity->ideal_traits }}</p>
                    </div>
                @endif
            </section>

            @if ($volunteers->isNotEmpty())
                <section class="card p-6">
                    <h2 class="text-lg font-semibold text-ink">Volunteers on this opportunity</h2>
                    <ul class="mt-4 flex flex-wrap gap-3">
                        @foreach ($volunteers as $a)
                            <li>
                                <a href="{{ route('volunteers.show', $a->user->profile) }}" class="flex items-center gap-2 rounded-full border border-light-gray py-1 pl-1 pr-3 text-sm text-warmer-gray hover:border-brand hover:text-brand">
                                    <x-avatar :user="$a->user" size="h-8 w-8" text="text-xs" />
                                    {{ $a->user->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="card p-5">
                <h2 class="text-base font-semibold text-ink">Key facts</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    @foreach (array_filter([
                        'Dates' => $opportunity->start_date ? $opportunity->start_date->format('j M Y').($opportunity->end_date ? ' – '.$opportunity->end_date->format('j M Y') : '') : null,
                        'Duration' => $opportunity->project_size ? $opportunity->project_size.' · '.(config('volunteering.project_sizes')[$opportunity->project_size] ?? '') : null,
                        'Time commitment' => $opportunity->hoursLabel(),
                        'Location' => $opportunity->locationLabel(),
                        'Experience' => $opportunity->experience_level,
                        'IEEE region' => $opportunity->regionLabel(),
                        'Section' => $opportunity->section,
                        'Organizational unit' => $opportunity->organizational_unit,
                        'Society / council' => $opportunity->society,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-light-gray pb-2 last:border-0">
                            <dt class="text-warm-gray">{{ $label }}</dt>
                            <dd class="text-right font-medium text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-warm-gray">Volunteers</span>
                        <span class="font-semibold text-ink">{{ $confirmedCount }} of {{ $opportunity->volunteers_needed }} confirmed</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-brand-50" role="progressbar" aria-valuenow="{{ $filledPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Positions filled">
                        <div class="h-2 rounded-full bg-brand" style="width: {{ $filledPct }}%"></div>
                    </div>
                </div>
            </section>

            <section class="card p-5">
                <h2 class="text-base font-semibold text-ink">Eligibility</h2>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @forelse ($opportunity->gradeLabels() as $grade)
                        <span class="chip-muted">{{ $grade }}</span>
                    @empty
                        <span class="text-sm text-warm-gray">Open to all membership grades.</span>
                    @endforelse
                </div>
            </section>

            <section class="card p-5">
                <h2 class="text-base font-semibold text-ink">Organised by</h2>
                @if ($opportunity->owners->isNotEmpty())
                    <ul class="mt-3 space-y-3">
                        @foreach ($opportunity->owners as $person)
                            <li class="flex items-center gap-3">
                                <x-avatar :user="$person" size="h-10 w-10" />
                                <div class="min-w-0 text-sm">
                                    @if ($person->profile?->is_public)
                                        <a href="{{ route('volunteers.show', $person->profile) }}" class="block truncate font-semibold text-ink hover:text-brand">{{ $person->name }}</a>
                                    @else
                                        <span class="block truncate font-semibold text-ink">{{ $person->name }}</span>
                                    @endif
                                    <span class="block text-xs text-warm-gray">{{ $person->pivot->role === 'owner' ? 'Owner' : 'Co-owner' }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-warm-gray">Posted on volunteer.ieee.org. Applications made here are reviewed by the IEEE Volunteering team until the creator links their account.</p>
                @endif
                @if ($opportunity->isImported() && $opportunity->externalUrl())
                    <a href="{{ $opportunity->externalUrl() }}" target="_blank" rel="noopener" class="mt-4 inline-block text-sm font-semibold text-accent-blue hover:underline">View on volunteer.ieee.org ↗</a>
                @endif
            </section>

            <p class="px-1 text-xs text-warm-gray">
                Posted {{ ($opportunity->published_at ?? $opportunity->created_at)->format('j M Y') }} · {{ number_format($opportunity->views_count) }} views
            </p>
        </aside>
    </div>

    @if ($similar->isNotEmpty())
        <section class="container-x pb-6">
            <h2 class="section-title">Similar opportunities</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach ($similar as $item)
                    <x-opportunity.tile :opportunity="$item" />
                @endforeach
            </div>
        </section>
    @endif

    @auth
        @if (! $canManage && $opportunity->acceptsApplications() && (! $application || $application->status === \App\Models\Application::WITHDRAWN))
            <x-modal name="apply" :show="$errors->has('motivation') || $errors->has('application')" focusable>
                <form method="POST" action="{{ route('applications.store', $opportunity) }}" class="p-6">
                    @csrf
                    <h2 class="text-lg font-semibold text-ink">Apply to “{{ $opportunity->title }}”</h2>
                    <p class="mt-1 text-sm text-warm-gray">The organisers will see your profile, skills and this message.</p>

                    <label for="motivation" class="label mt-5">Why are you interested, and what will you bring?</label>
                    <textarea id="motivation" name="motivation" rows="6" required minlength="20" maxlength="3000" class="input" placeholder="A few sentences about your experience, your availability and what you'd like to get out of it.">{{ old('motivation') }}</textarea>
                    <x-input-error :messages="$errors->get('motivation')" class="mt-1" />
                    <x-input-error :messages="$errors->get('application')" class="mt-1" />

                    @if ($user->profile->completeness()['percent'] < 60)
                        <p class="mt-3 rounded-md bg-brand-50 px-3 py-2 text-xs text-brand-800">Tip: your profile is only {{ $user->profile->completeness()['percent'] }}% complete. <a href="{{ route('profile.volunteer.edit') }}" class="font-semibold underline">Add skills and a bio</a> to stand out.</p>
                    @endif

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="btn-ghost" x-on:click="$dispatch('close')">Cancel</button>
                        <button class="btn-primary">Send application</button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endauth
@endsection
