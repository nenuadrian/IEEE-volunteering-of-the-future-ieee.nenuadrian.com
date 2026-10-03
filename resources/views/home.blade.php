@extends('layouts.public')
@section('title', 'Volunteer with IEEE')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden border-b border-light-gray bg-white">
        <div class="pointer-events-none absolute -right-40 -top-40 h-[28rem] w-[28rem] rounded-full bg-brand-50" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-48 right-1/3 h-80 w-80 rounded-full bg-accent-blue/5" aria-hidden="true"></div>
        <div class="container-x relative grid gap-10 py-14 lg:grid-cols-[1.25fr_1fr] lg:items-center lg:py-20">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-brand">IEEE Volunteering</p>
                <h1 class="mt-3 text-4xl font-bold leading-tight text-ink sm:text-5xl">
                    Share your skills.<br><span class="text-brand">Shape the IEEE community.</span>
                </h1>
                <p class="mt-5 max-w-xl text-lg text-warm-gray">
                    Find volunteering opportunities across every region, section, society and council — from one-hour quick tasks to leadership roles — and build a verified record of the impact you make.
                </p>

                <form method="GET" action="{{ route('opportunities.index') }}" role="search" class="mt-8 flex max-w-xl flex-col gap-3 sm:flex-row">
                    <label class="relative flex-1">
                        <span class="sr-only">Search opportunities</span>
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        <input type="search" name="q" placeholder="Try “paper review”, “social media” or “mentor”" class="input h-12 pl-11 text-base">
                    </label>
                    <button class="btn-primary h-12 px-6 text-base">Find opportunities</button>
                </form>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-warm-gray">Popular:</span>
                    <a href="{{ route('opportunities.index', ['online' => 1, 'accepting' => 1]) }}" class="chip-muted hover:border-brand hover:text-brand">Online</a>
                    <a href="{{ route('opportunities.index', ['size' => 'Quick task', 'accepting' => 1]) }}" class="chip-muted hover:border-brand hover:text-brand">Quick tasks</a>
                    @foreach ($categories->take(3) as $category)
                        <a href="{{ route('opportunities.index', ['category' => $category->slug]) }}" class="chip-muted hover:border-brand hover:text-brand">{{ $category->name }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Live numbers --}}
            <div class="grid grid-cols-2 gap-4">
                @foreach ([
                    [number_format($stats['open']), 'open opportunities', route('opportunities.index', ['accepting' => 1])],
                    [number_format($stats['volunteers']), 'registered volunteers', route('volunteers.index')],
                    [number_format($stats['hours']), 'volunteer hours logged', null],
                    [number_format($stats['countries']), 'countries represented', route('volunteers.index')],
                ] as [$value, $label, $href])
                    <x-stat :value="$value" :label="$label" :href="$href" class="bg-white/90 backdrop-blur" />
                @endforeach
                <div class="col-span-2 flex flex-wrap gap-3">
                    <a href="{{ route('opportunities.create') }}" class="btn-secondary flex-1">Create an opportunity</a>
                    @guest
                        <a href="{{ route('register') }}" class="btn-ghost flex-1">Join in 1 minute</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="btn-ghost flex-1">Your impact dashboard &rarr;</a>
                    @endguest
                </div>
            </div>
        </div>
    </section>

    @if ($recommended->isNotEmpty())
        <section class="container-x pt-14">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="section-title">Recommended for you</h2>
                    <p class="mt-3 text-sm text-warm-gray">Based on your skills, membership grade and region. Hover a match score to see why.</p>
                </div>
                <a href="{{ route('opportunities.index', ['sort' => 'match']) }}" class="hidden text-sm font-semibold text-brand hover:underline sm:block">See all matches &rarr;</a>
            </div>
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach ($recommended as $opportunity)
                    <x-opportunity.tile :opportunity="$opportunity" :match="$opportunity->match" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Latest opportunities --}}
    <section class="container-x pt-14">
        <div class="flex items-end justify-between gap-4">
            <h2 class="section-title">Accepting volunteers now</h2>
            <a href="{{ route('opportunities.index', ['accepting' => 1]) }}" class="text-sm font-semibold text-brand hover:underline">Browse all &rarr;</a>
        </div>
        @if ($featured->isEmpty())
            <x-empty-state class="mt-6" title="No open opportunities yet">Be the first to post one — it takes about five minutes.</x-empty-state>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $opportunity)
                    <x-opportunity.tile :opportunity="$opportunity" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Categories --}}
    <section class="container-x pt-16">
        <h2 class="section-title">Ways to volunteer</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($categories as $category)
                <a href="{{ route('opportunities.index', ['category' => $category->slug]) }}" class="card group flex items-start gap-4 p-5 transition hover:border-brand/40 hover:shadow-dropdown">
                    <span class="mt-1 h-10 w-1.5 shrink-0 rounded-full" style="background-color: {{ $category->colorOrDefault() }}" aria-hidden="true"></span>
                    <span class="flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-ink group-hover:text-brand">{{ $category->name }}</span>
                            <span class="text-xs font-semibold text-warm-gray">{{ $category->open_count }} open</span>
                        </span>
                        <span class="mt-1 block text-sm text-warm-gray">{{ $category->description }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="mt-16 border-y border-light-gray bg-white">
        <div class="container-x grid gap-12 py-14 lg:grid-cols-2">
            @foreach ([
                ['For volunteers', [
                    ['Build your profile', 'Add skills, your IEEE region and section. We use them to match you.'],
                    ['Apply in a click', 'Explain why you are interested; track every application in one place.'],
                    ['Log hours & get endorsed', 'Approved hours and endorsements flow into your downloadable CV.'],
                ], route('opportunities.index'), 'Find an opportunity'],
                ['For opportunity creators', [
                    ['Post or clone an opportunity', 'A guided form with help text — or copy last year’s and update it.'],
                    ['Review with co-owners', 'Share applicant review and hour approvals with up to nine co-owners.'],
                    ['See your volunteers’ impact', 'Hours, completion, ratings and reach for every opportunity you run.'],
                ], route('opportunities.create'), 'Create an opportunity'],
            ] as [$heading, $steps, $href, $cta])
                <div>
                    <h2 class="text-xl font-semibold text-ink">{{ $heading }}</h2>
                    <ol class="mt-6 space-y-5">
                        @foreach ($steps as $i => [$title, $text])
                            <li class="flex gap-4">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand text-sm font-bold text-white">{{ $i + 1 }}</span>
                                <span>
                                    <span class="block font-semibold text-ink">{{ $title }}</span>
                                    <span class="block text-sm text-warm-gray">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                    <a href="{{ $href }}" class="btn-secondary mt-6">{{ $cta }}</a>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Skills in demand --}}
    @if ($skillsInDemand->isNotEmpty())
        <section class="container-x pt-14">
            <h2 class="section-title">Skills in demand right now</h2>
            <p class="mt-3 text-sm text-warm-gray">The skills most requested by opportunities currently accepting volunteers.</p>
            <div class="mt-6 flex flex-wrap gap-2">
                @foreach ($skillsInDemand as $skill)
                    <a href="{{ route('opportunities.index', ['skills' => [$skill->id], 'accepting' => 1]) }}" class="inline-flex items-center gap-2 rounded-full border border-light-gray bg-white px-4 py-2 text-sm text-warmer-gray transition hover:border-brand hover:text-brand">
                        {{ $skill->name }} <span class="rounded-full bg-brand-50 px-2 text-xs font-bold text-brand-700">{{ $skill->total }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Endorsements --}}
    @if ($endorsements->isNotEmpty())
        <section class="container-x pt-16">
            <h2 class="section-title">Recognised by the community</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach ($endorsements as $endorsement)
                    <figure class="card flex flex-col p-6">
                        <blockquote class="flex-1 text-sm leading-relaxed text-warmer-gray">“{{ \Illuminate\Support\Str::limit($endorsement->message, 240) }}”</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3 border-t border-light-gray pt-4">
                            <x-avatar :user="$endorsement->user" size="h-10 w-10" />
                            <span class="min-w-0 text-sm">
                                @if ($endorsement->user->profile?->is_public)
                                    <a href="{{ route('volunteers.show', $endorsement->user->profile) }}" class="block truncate font-semibold text-ink hover:text-brand">{{ $endorsement->user->name }}</a>
                                @else
                                    <span class="block truncate font-semibold text-ink">{{ $endorsement->user->name }}</span>
                                @endif
                                <span class="block truncate text-xs text-warm-gray">endorsed by {{ $endorsement->endorser->name }} · {{ \Illuminate\Support\Str::limit($endorsement->opportunity?->title, 40) }}</span>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    {{-- CTA --}}
    <section class="container-x pt-16">
        <div class="relative overflow-hidden rounded-2xl bg-charcoal-dark px-8 py-12 text-white sm:px-12">
            <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full bg-brand/30 blur-3xl" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-white">Running an event, a committee or a project?</h2>
                    <p class="mt-2 max-w-2xl text-white/75">Post an opportunity and reach {{ number_format($stats['volunteers']) }} volunteers. Add co-owners, review applicants by match score and see the impact your volunteers make.</p>
                </div>
                <a href="{{ route('opportunities.create') }}" class="btn-primary shrink-0 px-6 py-3 text-base">Create an opportunity</a>
            </div>
        </div>
    </section>
@endsection
