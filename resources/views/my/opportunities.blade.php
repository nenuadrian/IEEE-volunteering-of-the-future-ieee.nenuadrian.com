@extends('layouts.public')
@section('title', 'My Opportunities')
@section('robots_noindex', '1')

@php
    $pendingDecisions = $managing->sum('pending_count');
    $pendingHourCount = $managing->sum('pending_hours_count');
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $tabs = [
        'volunteering' => ['Volunteering', $volunteering['active']->count() + $volunteering['pending']->count(), 'Opportunities you applied to or take part in'],
        'managing' => ['Managing', $pendingDecisions + $pendingHourCount, 'Opportunities you own or co-own'],
        'saved' => ['Saved', $saved->count(), 'Opportunities you bookmarked'],
    ];
@endphp

@section('content')
    <x-page-header title="My Opportunities" subtitle="Everything you volunteer on and everything you organise, in one place.">
        <x-slot:actions>
            <a href="{{ route('opportunities.index') }}" class="btn-secondary">Find opportunities</a>
            <a href="{{ route('opportunities.create') }}" class="btn-primary">Create opportunity</a>
        </x-slot:actions>
        <nav class="-mb-8 mt-6 flex gap-1 overflow-x-auto" aria-label="My opportunities">
            @foreach ($tabs as $key => [$label, $badge, $hint])
                <a href="{{ route('my.opportunities', ['tab' => $key]) }}" title="{{ $hint }}" @class([
                    'relative whitespace-nowrap px-4 py-3 text-sm font-semibold transition',
                    'text-brand after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:bg-brand' => $tab === $key,
                    'text-warm-gray hover:text-brand' => $tab !== $key,
                ]) @if($tab === $key) aria-current="page" @endif>
                    {{ $label }}
                    @if ($badge)<span @class(['ml-1 rounded-full px-1.5 py-0.5 text-[11px] font-bold', 'bg-brand text-white' => $key === 'managing', 'bg-light-gray text-warmer-gray' => $key !== 'managing'])>{{ $badge }}</span>@endif
                </a>
            @endforeach
        </nav>
    </x-page-header>

    <div class="container-x py-8">
        @if ($tab === 'volunteering')
            @if ($applications->isEmpty())
                <x-empty-state title="You haven't applied to anything yet" icon="🤝">
                    Browse opportunities matched to your skills — many take only a few hours.
                    <x-slot:actions>
                        <a href="{{ route('opportunities.index', ['sort' => 'match']) }}" class="btn-primary">See my best matches</a>
                        <a href="{{ route('profile.volunteer.edit') }}" class="btn-secondary">Improve my profile</a>
                    </x-slot:actions>
                </x-empty-state>
            @else
                @foreach ([
                    'active' => ['In progress', 'Log your hours as you go — approved hours appear on your profile and CV.'],
                    'pending' => ['Awaiting a decision', 'The organisers have been notified.'],
                    'completed' => ['Completed', 'Thank you for volunteering!'],
                    'closed' => ['Withdrawn or not selected', null],
                ] as $group => [$heading, $hint])
                    @if ($volunteering[$group]->isNotEmpty())
                        <section class="mb-10">
                            <h2 class="text-lg font-semibold text-ink">{{ $heading }} <span class="text-sm font-normal text-warm-gray">({{ $volunteering[$group]->count() }})</span></h2>
                            @if ($hint)<p class="mt-1 text-sm text-warm-gray">{{ $hint }}</p>@endif
                            <div class="mt-4 space-y-4">
                                @foreach ($volunteering[$group] as $application)
                                    @if ($group === 'active')
                                        <div>
                                            <a href="{{ route('opportunities.show', $application->opportunity) }}" class="mb-1 inline-block text-xs font-semibold text-brand hover:underline">Open opportunity page &rarr;</a>
                                            @include('opportunities.partials.my-application', ['application' => $application, 'compact' => true])
                                        </div>
                                    @else
                                        <article class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                                            <x-opportunity.thumb :opportunity="$application->opportunity" size="h-14 w-14" class="hidden sm:grid" />
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('opportunities.show', $application->opportunity) }}" class="font-semibold text-ink hover:text-brand">{{ $application->opportunity->title }}</a>
                                                <p class="text-xs text-warm-gray">
                                                    {{ $application->opportunity->category?->name }} · applied {{ $application->created_at->format('j M Y') }}
                                                    @if ($application->approved_hours) · <span class="font-semibold text-ink">{{ $fmt($application->approved_hours) }} h</span> approved @endif
                                                </p>
                                            </div>
                                            <x-status-badge :status="$application->status" type="application" />
                                            @if ($group === 'pending')
                                                <form method="POST" action="{{ route('applications.withdraw', $application) }}" onsubmit="return confirm('Withdraw your application?')">@csrf<button class="text-xs text-warm-gray underline hover:text-red-600">Withdraw</button></form>
                                            @elseif ($group === 'completed' && ! $application->volunteer_rating)
                                                <a href="{{ route('opportunities.show', $application->opportunity) }}#my-application" class="text-xs font-semibold text-brand hover:underline">Rate experience</a>
                                            @endif
                                        </article>
                                    @endif
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            @endif

        @elseif ($tab === 'managing')
            @if ($pendingDecisions || $pendingHourCount)
                <div class="mb-6 rounded-xl border border-brand/40 bg-brand-50 px-5 py-4 text-sm text-brand-800">
                    <strong>Items need your action:</strong>
                    @if ($pendingDecisions){{ $pendingDecisions }} {{ \Illuminate\Support\Str::plural('application', $pendingDecisions) }} to review @endif
                    @if ($pendingDecisions && $pendingHourCount) · @endif
                    @if ($pendingHourCount){{ $pendingHourCount }} hour {{ \Illuminate\Support\Str::plural('entry', $pendingHourCount) }} to approve @endif
                </div>
            @endif

            @if ($managing->isEmpty())
                <x-empty-state title="You don't manage any opportunities yet" icon="📣">
                    Need help with an event, a committee or a project? Post an opportunity — or ask an owner to add you as a co-owner.
                    <x-slot:actions><a href="{{ route('opportunities.create') }}" class="btn-primary">Create an opportunity</a></x-slot:actions>
                </x-empty-state>
            @else
                <div class="card overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-5 py-3">Opportunity</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Applicants</th>
                                <th class="px-5 py-3 text-right">Confirmed</th>
                                <th class="px-5 py-3 text-right">Hours</th>
                                <th class="px-5 py-3">Needs action</th>
                                <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-light-gray">
                            @foreach ($managing as $o)
                                <tr class="align-top">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('opportunities.manage', $o) }}" class="font-semibold text-ink hover:text-brand">{{ $o->title }}</a>
                                        <span class="block text-xs text-warm-gray">{{ $o->category?->name }} · {{ $o->pivot->role === 'owner' ? 'Owner' : 'Co-owner' }} · {{ $o->start_date?->format('j M Y') }}</span>
                                    </td>
                                    <td class="px-5 py-3"><x-status-badge :status="$o->status" /></td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ $o->applications_count }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ $o->confirmed_count }} / {{ $o->volunteers_needed }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ $fmt($o->approved_hours) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($o->pending_count)<a href="{{ route('opportunities.manage', [$o, 'tab' => 'applicants', 'status' => 'pending']) }}" class="chip">{{ $o->pending_count }} to review</a>@endif
                                        @if ($o->pending_hours_count)<a href="{{ route('opportunities.manage', [$o, 'tab' => 'hours']) }}" class="chip">{{ $o->pending_hours_count }} hours</a>@endif
                                        @if ($o->isDraft())<a href="{{ route('opportunities.edit', $o) }}" class="chip-muted">Finish draft</a>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <a href="{{ route('opportunities.manage', $o) }}" class="text-sm font-semibold text-brand hover:underline">Manage</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-warm-gray">See the combined impact of your volunteers on your <a href="{{ route('dashboard') }}#organiser" class="text-brand underline">dashboard</a>.</p>
            @endif

        @else
            @if ($saved->isEmpty())
                <x-empty-state title="No saved opportunities" icon="♡">Tap the heart on any opportunity to save it for later.
                    <x-slot:actions><a href="{{ route('opportunities.index') }}" class="btn-primary">Browse opportunities</a></x-slot:actions>
                </x-empty-state>
            @else
                <div class="space-y-4">
                    @foreach ($saved as $opportunity)
                        <x-opportunity.card :opportunity="$opportunity" :saved="true" />
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endsection
