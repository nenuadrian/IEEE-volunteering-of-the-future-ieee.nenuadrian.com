@extends('layouts.public')
@section('title', $profile->user->name)
@section('meta_description', $profile->headline ?: 'IEEE volunteer profile and CV')
@unless ($profile->is_public)@section('robots_noindex', '1')@endunless

@php
    $user = $profile->user;
    $stats = $cv['stats'];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $sections = \App\Services\VolunteerCv::SECTIONS;
@endphp

@section('content')
    <section class="border-b border-light-gray bg-white">
        <div class="container-x py-8">
            @unless ($profile->is_public)
                <p class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">Your profile is hidden from the directory. Only you and admins can see this page.</p>
            @endunless
            <div class="flex flex-col gap-6 md:flex-row md:items-start">
                <x-avatar :user="$user" size="h-28 w-28" text="text-3xl" />
                <div class="min-w-0 flex-1">
                    <h1 class="text-3xl font-semibold text-ink">{{ $user->name }}</h1>
                    @if ($profile->headline)<p class="mt-1 text-lg text-warmer-gray">{{ $profile->headline }}</p>@endif
                    <p class="mt-2 text-sm text-warm-gray">
                        {{ collect([$profile->gradeLabel(), $profile->regionLabel(), $profile->section ? $profile->section.' Section' : null, $profile->locationLine()])->filter()->implode(' · ') }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                        <span @class(['chip', 'bg-accent-green-light/60 text-accent-green-dark' => $profile->availability === 'available', 'bg-amber-100 text-amber-800' => $profile->availability === 'limited', 'bg-light-gray text-warmer-gray' => $profile->availability === 'unavailable'])>
                            {{ $profile->availabilityLabel() }}@if ($profile->hours_per_month && $profile->availability !== 'unavailable') · ~{{ $profile->hours_per_month }} h/month @endif
                        </span>
                        @if ($profile->society)<span class="chip-muted">{{ $profile->society }}</span>@endif
                        @if ($profile->linkedin_url)<a href="{{ $profile->linkedin_url }}" rel="noopener nofollow" target="_blank" class="chip-blue hover:underline">LinkedIn ↗</a>@endif
                        @if ($profile->github_url)<a href="{{ $profile->github_url }}" rel="noopener nofollow" target="_blank" class="chip-blue hover:underline">GitHub ↗</a>@endif
                        @if ($profile->website_url)<a href="{{ $profile->website_url }}" rel="noopener nofollow" target="_blank" class="chip-blue hover:underline">Website ↗</a>@endif
                        @if ($profile->show_email || $isOwn)<a href="mailto:{{ $user->email }}" class="chip-muted hover:text-brand">{{ $user->email }}</a>@endif
                    </div>
                </div>

                <div class="flex shrink-0 flex-col gap-2">
                    <div x-data="{ open: false }" class="relative">
                        <div class="flex">
                            <a href="{{ route('volunteers.cv', [$profile, 'download' => 1]) }}" class="btn-primary rounded-r-none">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                Download PDF CV
                            </a>
                            <button type="button" @click="open = !open" class="btn-primary rounded-l-none border-l border-white/30 px-2.5" aria-label="Choose CV sections" :aria-expanded="open">▾</button>
                        </div>
                        <form x-show="open" x-cloak @click.outside="open = false" method="GET" action="{{ route('volunteers.cv', $profile) }}" target="_blank"
                              class="absolute right-0 top-full z-30 mt-2 w-64 rounded-xl border border-light-gray bg-white p-4 shadow-dropdown">
                            <p class="text-sm font-semibold text-ink">Sections to include</p>
                            <div class="mt-2 space-y-1.5">
                                @foreach ($sections as $key => $label)
                                    <label class="flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="sections[]" value="{{ $key }}" class="checkbox" checked> {{ $label }}</label>
                                @endforeach
                            </div>
                            <button class="btn-primary btn-sm mt-3 w-full">Preview PDF</button>
                        </form>
                    </div>
                    @if ($isOwn)
                        <a href="{{ route('profile.volunteer.edit') }}" class="btn-secondary">Edit profile</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="container-x py-8">
        <dl class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach ([
                [$fmt($stats['hours']), 'Volunteer hours'],
                [$stats['completed'], 'Opportunities completed'],
                [$stats['active'], 'In progress'],
                [$stats['endorsements'], 'Endorsements'],
                [$stats['created'], 'Opportunities created'],
                [$stats['volunteers_led'], 'Volunteers engaged'],
            ] as [$value, $label])
                <div class="card p-4 text-center">
                    <dd class="stat-value text-2xl">{{ $value }}</dd>
                    <dt class="stat-label text-xs">{{ $label }}</dt>
                </div>
            @endforeach
        </dl>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div class="min-w-0 space-y-8">
                @if ($profile->bio || $profile->cv_statement)
                    <section class="card p-6">
                        <h2 class="text-lg font-semibold text-ink">About</h2>
                        @if ($profile->cv_statement)
                            <p class="mt-3 border-l-4 border-brand pl-4 text-warmer-gray">{{ $profile->cv_statement }}</p>
                        @endif
                        @if ($profile->bio)
                            <div class="content mt-4 whitespace-pre-line">{{ $profile->bio }}</div>
                        @endif
                    </section>
                @elseif ($isOwn)
                    <x-empty-state title="Tell people about yourself" icon="✍️">A short bio and personal statement make your profile and CV stand out.
                        <x-slot:actions><a href="{{ route('profile.volunteer.edit') }}" class="btn-primary">Add a bio</a></x-slot:actions>
                    </x-empty-state>
                @endif

                <section class="card p-6">
                    <h2 class="text-lg font-semibold text-ink">Volunteering experience</h2>
                    @if ($cv['experiences']->isEmpty())
                        <p class="mt-3 text-sm text-warm-gray">{{ $isOwn ? 'Opportunities you are accepted onto will appear here.' : 'No volunteering experience recorded yet.' }}</p>
                    @else
                        <ol class="relative mt-5 space-y-6 border-l-2 border-brand-100 pl-6">
                            @foreach ($cv['experiences'] as $e)
                                <li class="relative">
                                    <span @class(['absolute -left-[31px] top-1.5 h-3.5 w-3.5 rounded-full ring-4 ring-white', 'bg-brand' => $e->in_progress, 'bg-accent-blue' => ! $e->in_progress]) aria-hidden="true"></span>
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                                        <h3 class="font-semibold text-ink">
                                            @if (! $e->opportunity->trashed() && ! $e->opportunity->isDraft())
                                                <a href="{{ route('opportunities.show', $e->opportunity) }}" class="hover:text-brand">{{ $e->title }}</a>
                                            @else
                                                {{ $e->title }}
                                            @endif
                                        </h3>
                                        <span class="text-xs text-warm-gray">{{ $e->start?->format('M Y') }} – {{ $e->in_progress ? 'present' : ($e->end?->format('M Y') ?? '') }}</span>
                                    </div>
                                    <p class="text-sm text-warm-gray">{{ $e->organisation }} · {{ $e->location }}@if ($e->hours) · <span class="font-semibold text-warmer-gray">{{ $fmt($e->hours) }} h</span>@endif</p>
                                    <p class="mt-2 line-clamp-3 text-sm text-warmer-gray">{{ $e->description }}</p>
                                    @if ($e->upskills)
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($e->upskills as $u)<span class="chip">{{ $u }}</span>@endforeach
                                        </div>
                                    @endif
                                    @if ($e->endorsement)
                                        <blockquote class="mt-3 rounded-lg bg-brand-50 p-3 text-sm italic text-warmer-gray">“{{ $e->endorsement->message }}” <span class="not-italic text-warm-gray">— {{ $e->endorsement->endorser?->name }}</span></blockquote>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                @if ($cv['created']->isNotEmpty())
                    <section class="card p-6">
                        <h2 class="text-lg font-semibold text-ink">Opportunities created</h2>
                        <ul class="mt-4 divide-y divide-light-gray">
                            @foreach ($cv['created'] as $o)
                                <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="min-w-0">
                                        <a href="{{ route('opportunities.show', $o) }}" class="font-medium text-ink hover:text-brand">{{ $o->title }}</a>
                                        <span class="block text-xs text-warm-gray">{{ $o->category?->name }} · {{ $o->start_date?->format('M Y') }}</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-3 text-xs text-warm-gray">
                                        <span><strong class="text-ink">{{ $o->confirmed_count }}</strong> volunteers</span>
                                        <span><strong class="text-ink">{{ $fmt($o->approved_hours) }}</strong> h</span>
                                        <x-status-badge :status="$o->status" />
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="card p-6" id="endorsements">
                    <h2 class="text-lg font-semibold text-ink">Endorsements <span class="text-sm font-normal text-warm-gray">({{ $cv['endorsements']->count() }})</span></h2>
                    @forelse ($cv['endorsements'] as $endorsement)
                        <figure class="mt-4 border-t border-light-gray pt-4 first-of-type:border-0 first-of-type:pt-0">
                            <blockquote class="text-sm text-warmer-gray">“{{ $endorsement->message }}”</blockquote>
                            <figcaption class="mt-2 flex flex-wrap items-center gap-2 text-xs text-warm-gray">
                                <x-avatar :user="$endorsement->endorser" size="h-6 w-6" text="text-[10px]" />
                                <span class="font-semibold text-warmer-gray">{{ $endorsement->endorser?->name }}</span>
                                · {{ \Illuminate\Support\Str::limit($endorsement->opportunity?->title, 50) }} · {{ $endorsement->created_at->format('M Y') }}
                                @foreach ($endorsement->skills as $skill)<span class="chip">{{ $skill->name }}</span>@endforeach
                            </figcaption>
                        </figure>
                    @empty
                        <p class="mt-3 text-sm text-warm-gray">Organisers can endorse volunteers when they complete an opportunity.</p>
                    @endforelse
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card p-5" id="skills">
                    <h2 class="text-base font-semibold text-ink">Skills</h2>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @forelse ($cv['skills'] as $skill)
                            <li class="chip-muted">{{ $skill->name }}@if ($skill->endorsements)<span class="ml-1 rounded-full bg-brand px-1.5 text-[10px] font-bold text-white" title="Endorsed {{ $skill->endorsements }} times">{{ $skill->endorsements }}</span>@endif</li>
                        @empty
                            <li class="text-sm text-warm-gray">No skills listed yet.</li>
                        @endforelse
                    </ul>
                </section>

                @if ($cv['upskills']->isNotEmpty())
                    <section class="card p-5">
                        <h2 class="text-base font-semibold text-ink">Developed through volunteering</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($cv['upskills'] as $name => $count)
                                <li class="flex justify-between"><span class="text-warmer-gray">{{ $name }}</span><span class="text-xs text-warm-gray">{{ $count }} {{ \Illuminate\Support\Str::plural('opportunity', $count) }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="card p-5 text-sm">
                    <h2 class="text-base font-semibold text-ink">IEEE membership</h2>
                    <dl class="mt-3 space-y-2">
                        @if ($profile->gradeLabel())<div class="flex justify-between gap-3"><dt class="text-warm-gray">Grade</dt><dd class="text-right text-ink">{{ $profile->gradeLabel() }}</dd></div>@endif
                        @if ($profile->yearsAsMember() !== null)<div class="flex justify-between gap-3"><dt class="text-warm-gray">Member for</dt><dd class="text-right text-ink">{{ $profile->yearsAsMember() }} {{ \Illuminate\Support\Str::plural('year', $profile->yearsAsMember()) }}</dd></div>@endif
                        @if ($profile->regionLabel())<div class="flex justify-between gap-3"><dt class="text-warm-gray">Region</dt><dd class="text-right text-ink">{{ $profile->regionLabel() }}</dd></div>@endif
                        @if ($profile->section)<div class="flex justify-between gap-3"><dt class="text-warm-gray">Section</dt><dd class="text-right text-ink">{{ $profile->section }}</dd></div>@endif
                        @if ($stats['avg_rating'])<div class="flex justify-between gap-3"><dt class="text-warm-gray">Organiser rating</dt><dd class="text-right text-ink">{{ $stats['avg_rating'] }} / 5</dd></div>@endif
                        <div class="flex justify-between gap-3"><dt class="text-warm-gray">On the platform since</dt><dd class="text-right text-ink">{{ $user->created_at->format('M Y') }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
@endsection
