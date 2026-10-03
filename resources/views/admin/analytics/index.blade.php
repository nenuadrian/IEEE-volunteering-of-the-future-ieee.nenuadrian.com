@extends('layouts.admin')
@section('title', 'Analytics')
@section('heading', 'Analytics')

@php
    $PA = \App\Services\PlatformAnalytics::class;
    $kpis = $report['kpis'];
    $charts = $report['charts'];
    $per = ['day' => 'per day', 'week' => 'per week', 'month' => 'per month'][$analytics->unit];
    $rangeShort = ['30d' => '30 days', '90d' => '90 days', '12m' => '12 months', 'all' => 'All time'];
    $sliced = $analytics->region || $analytics->category;
@endphp

@section('content')
    {{-- Filters: one slice for everything below. --}}
    <form method="GET" action="{{ route('admin.dashboard') }}" class="card mb-6 p-4 sm:p-5" aria-label="Analytics filters">
        <div class="flex flex-wrap items-end gap-4">
            <fieldset>
                <legend class="label">Period</legend>
                <div class="inline-flex flex-wrap rounded-lg border border-light-gray bg-warm-white p-0.5">
                    @foreach ($PA::RANGES as $key => $label)
                        <label class="cursor-pointer" title="{{ $label }}">
                            <input type="radio" name="range" value="{{ $key }}" class="peer sr-only" @checked($analytics->range === $key) onchange="this.form.submit()">
                            <span class="block rounded-md px-3 py-1.5 font-ui text-sm font-semibold text-warm-gray transition hover:text-ink peer-checked:bg-white peer-checked:text-brand peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-accent-blue">{{ $rangeShort[$key] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="w-full sm:w-64">
                <label for="filter-region" class="label">Region</label>
                <select id="filter-region" name="region" class="input" onchange="this.form.submit()">
                    <option value="">All regions</option>
                    @foreach ($regions as $code => $label)
                        <option value="{{ $code }}" @selected($analytics->region === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-52">
                <label for="filter-category" class="label">Opportunity type</label>
                <select id="filter-category" name="category" class="input" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($analytics->category?->id === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn-secondary">Apply</button>
                @if ($sliced)
                    <a href="{{ route('admin.dashboard', ['range' => $analytics->range]) }}" class="btn-ghost">Clear filters</a>
                @endif
            </div>

            <a href="{{ route('admin.analytics.export', $analytics->params()) }}" class="btn-primary sm:ml-auto">
                <span aria-hidden="true">⤓</span> Export CSV
            </a>
        </div>

        <p class="mt-4 text-xs text-warm-gray">
            <strong class="font-semibold text-warmer-gray">{{ $analytics->periodLabel() }}</strong>
            · charts {{ $per }}
            @if ($analytics->previousPeriodLabel())
                · changes compare with {{ $analytics->previousPeriodLabel() }}
            @endif
            @if ($analytics->region)
                · region matches the volunteer's profile for people &amp; activity metrics, and the opportunity's region for opportunity metrics
            @endif
        </p>
    </form>

    {{-- KPIs --}}
    <section aria-labelledby="kpi-heading">
        <h2 id="kpi-heading" class="sr-only">Key metrics</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <x-stat :value="$PA::format($kpi['value'], $kpi['format'])" :label="$kpi['label']" :hint="$kpi['hint']" :delta="$kpi['delta']" />
            @endforeach
        </div>
    </section>

    {{-- Trends --}}
    <section class="mt-10" aria-labelledby="trends-heading">
        <h2 id="trends-heading" class="font-heading text-lg font-bold text-charcoal">Trends</h2>
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <x-chart :config="$charts['volunteers']" title="Volunteers joining" :subtitle="'New accounts '.$per" />
            <x-chart :config="$charts['applications']" title="Applications vs accepted" :subtitle="'Applications submitted and volunteers accepted, '.$per" />
            <x-chart :config="$charts['hours']" title="Approved volunteer hours" :subtitle="'By date worked, '.$per" />
            <x-chart :config="$charts['published']" title="Opportunities published" subtitle="Created on this platform vs imported from volunteer.ieee.org" />
        </div>
    </section>

    {{-- Funnel --}}
    @php($funnel = $report['funnel'])
    <section class="mt-10" aria-labelledby="funnel-heading">
        <h2 id="funnel-heading" class="font-heading text-lg font-bold text-charcoal">Engagement funnel</h2>
        <p class="mt-1 text-sm text-warm-gray">People who joined in the period. Each step counts those who also reached every earlier step.</p>
        <div class="mt-4 grid gap-6 lg:grid-cols-5">
            <x-chart class="lg:col-span-3" :config="$charts['funnel']" title="From sign-up to returning volunteer" height="h-72" />
            <div class="card relative overflow-x-auto lg:col-span-2">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Engagement funnel steps</caption>
                    <thead class="table-head">
                        <tr>
                            <th scope="col" class="px-4 py-3">Step</th>
                            <th scope="col" class="px-4 py-3 text-right">People</th>
                            <th scope="col" class="px-4 py-3 text-right">% of previous</th>
                            <th scope="col" class="px-4 py-3 text-right">% of sign-ups</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-light-gray">
                        @foreach ($funnel['steps'] as $step)
                            <tr>
                                <th scope="row" class="px-4 py-2.5 font-medium text-ink">{{ $step['label'] }}</th>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($step['count']) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-warm-gray">{{ $step['of_previous'] === null ? '—' : $PA::format($step['of_previous'], 'percent') }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-warm-gray">{{ $PA::format($step['of_total'], 'percent') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($funnel['applied_incomplete'])
                    <p class="border-t border-light-gray px-4 py-3 text-xs text-warm-gray">
                        {{ number_format($funnel['applied_incomplete']) }} of {{ number_format($funnel['applied_total']) }} applicants never reached 75% profile completeness, so they stop at the profile step.
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- Opportunity performance --}}
    @php($perf = $report['categories'])
    <section class="mt-10" aria-labelledby="perf-heading">
        <h2 id="perf-heading" class="font-heading text-lg font-bold text-charcoal">Opportunity performance by type</h2>
        <p class="mt-1 text-sm text-warm-gray">Opportunities published in the period and how their applications went.</p>
        <div class="card relative mt-4 overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="table-head">
                    <tr>
                        <th scope="col" class="px-5 py-3">Type</th>
                        <th scope="col" class="px-5 py-3 text-right">Published</th>
                        <th scope="col" class="px-5 py-3 text-right">Applications</th>
                        <th scope="col" class="px-5 py-3 text-right">Avg applicants</th>
                        <th scope="col" class="px-5 py-3 text-right">Fill rate</th>
                        <th scope="col" class="px-5 py-3 text-right">Completion rate</th>
                        <th scope="col" class="px-5 py-3 text-right">Avg volunteer rating</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                    @forelse ($perf['rows'] as $row)
                        <tr>
                            <th scope="row" class="px-5 py-3 font-medium text-ink">
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $row['color'] ?? '#898781' }}" aria-hidden="true"></span>
                                    {{ $row['name'] }}
                                </span>
                            </th>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($row['published']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($row['applications']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $row['avg_applicants'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $PA::format($row['fill_rate'], 'percent') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $PA::format($row['completion_rate'], 'percent') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{!! $row['avg_rating'] ? '<span class="text-brand" aria-hidden="true">★</span> '.e($row['avg_rating']) : '—' !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-warm-gray">No opportunities were published in this slice.</td></tr>
                    @endforelse
                </tbody>
                @if (count($perf['rows']) > 1)
                    @php($t = $perf['total'])
                    <tfoot class="border-t-2 border-light-gray bg-warm-white font-semibold">
                        <tr>
                            <th scope="row" class="px-5 py-3 text-ink">{{ $t['name'] }}</th>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($t['published']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($t['applications']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $t['avg_applicants'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $PA::format($t['fill_rate'], 'percent') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $PA::format($t['completion_rate'], 'percent') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{!! $t['avg_rating'] ? '<span class="text-brand" aria-hidden="true">★</span> '.e($t['avg_rating']) : '—' !!}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    {{-- Who volunteers --}}
    <section class="mt-10" aria-labelledby="who-heading">
        <h2 id="who-heading" class="font-heading text-lg font-bold text-charcoal">Who volunteers, and for what</h2>
        <div class="mt-4 grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
            <x-chart :config="$charts['regions']"
                     :title="$report['regions']['by'] === 'section' ? 'Active volunteers by section' : 'Active volunteers by region'"
                     :subtitle="$report['regions']['by'] === 'section' ? 'Top sections in '.$analytics->regionLabel() : 'Applied or logged hours in the period'"
                     height="h-96" />
            <x-chart :config="$charts['grades']" title="Active volunteers by membership grade" subtitle="Applied or logged hours in the period" height="h-96" />
            <x-chart class="lg:col-span-2 2xl:col-span-1" :config="$charts['sizes']" title="Opportunities by duration" subtitle="Published in the period, by source" height="h-96" />
        </div>
    </section>

    {{-- Skills supply vs demand --}}
    <section class="mt-10" aria-labelledby="skills-heading">
        <h2 id="skills-heading" class="font-heading text-lg font-bold text-charcoal">Skills: supply vs demand</h2>
        <p class="mt-1 text-sm text-warm-gray">
            Demand is the opportunities open right now that ask for the skill; supply is volunteers listing it on their profile.
            Skills with no volunteers, or at least twice the typical demand per volunteer, are flagged as shortages.
        </p>
        <div class="card relative mt-4 overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="table-head">
                    <tr>
                        <th scope="col" class="px-5 py-3">Skill</th>
                        <th scope="col" class="px-5 py-3 text-right">Open opportunities</th>
                        <th scope="col" class="px-5 py-3 text-right">Requested in period</th>
                        <th scope="col" class="px-5 py-3 text-right">Volunteers</th>
                        <th scope="col" class="px-5 py-3 text-right">Open per 100 volunteers</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Status</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                    @forelse ($report['skills'] as $skill)
                        <tr @class(['bg-red-50/60' => $skill['shortage']])>
                            <th scope="row" class="px-5 py-3 font-medium text-ink">
                                {{ $skill['name'] }}
                                @if ($skill['category'])<span class="block text-xs font-normal text-warm-gray">{{ $skill['category'] }}</span>@endif
                            </th>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($skill['open_opportunities']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-warm-gray">{{ number_format($skill['requested_in_period']) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($skill['volunteers']) }}</td>
                            <td @class(['px-5 py-3 text-right tabular-nums', 'font-semibold text-red-700' => $skill['shortage']])>{{ $skill['gap'] ?? '∞' }}</td>
                            <td class="px-5 py-3">
                                @if ($skill['shortage'])
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 font-ui text-xs font-semibold text-red-700">Shortage</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-warm-gray">No open opportunities list skills in this slice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Search --}}
    @php($search = $report['search'])
    <section class="mt-10" aria-labelledby="search-heading">
        <h2 id="search-heading" class="font-heading text-lg font-bold text-charcoal">Search</h2>
        <p class="mt-1 text-sm text-warm-gray">
            What people look for in the opportunity and volunteer directories.
            @if ($sliced)
                Only searches that were themselves narrowed to the selected region or type are included.
            @endif
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat :value="number_format($search['total'])" label="Searches" :hint="number_format($search['opportunity_searches']).' opportunity · '.number_format($search['volunteer_searches']).' volunteer'" />
            <x-stat :value="$PA::format($search['filtered_rate'], 'percent')" label="Used filters" hint="Narrowed by region, skills, type…" />
            <x-stat :value="number_format($search['zero'])" label="Zero-result searches" :hint="$PA::format($search['zero_rate'], 'percent').' of all searches'" />
            <x-stat :value="$search['top_terms']->first()->query ?? '—'" label="Top search term" :hint="$search['top_terms']->isNotEmpty() ? number_format($search['top_terms']->first()->total).' searches' : null" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <x-chart class="xl:col-span-3" :config="$charts['searches']" title="Searches over time" :subtitle="'All searches and those that found nothing, '.$per" />

            <div class="card relative overflow-x-auto xl:col-span-2">
                <h3 class="px-5 pt-4 text-base font-semibold text-ink">Top search terms</h3>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="table-head">
                        <tr>
                            <th scope="col" class="px-5 py-2.5">Term</th>
                            <th scope="col" class="px-5 py-2.5">Where</th>
                            <th scope="col" class="px-5 py-2.5 text-right">Searches</th>
                            <th scope="col" class="px-5 py-2.5 text-right">Avg results</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-light-gray">
                        @forelse ($search['top_terms'] as $term)
                            <tr>
                                <th scope="row" class="px-5 py-2.5 font-medium text-ink">{{ $term->query }}</th>
                                <td class="px-5 py-2.5"><span class="{{ $term->scope === 'volunteers' ? 'chip-blue' : 'chip-muted' }}">{{ ucfirst($term->scope) }}</span></td>
                                <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($term->total) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-warm-gray">{{ number_format((float) $term->avg_results, 1) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-warm-gray">No keyword searches in this slice.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card relative overflow-x-auto">
                <h3 class="px-5 pt-4 text-base font-semibold text-ink">Searches with no results</h3>
                <p class="px-5 text-xs text-warm-gray">Content gaps worth filling.</p>
                <table class="mt-2 w-full text-left text-sm">
                    <thead class="table-head">
                        <tr>
                            <th scope="col" class="px-5 py-2.5">Term</th>
                            <th scope="col" class="px-5 py-2.5 text-right">Times</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-light-gray">
                        @forelse ($search['zero_terms'] as $term)
                            <tr>
                                <th scope="row" class="px-5 py-2.5 font-medium text-ink">
                                    {{ $term->query ?: '(filters only)' }}
                                    <span class="block text-xs font-normal text-warm-gray">{{ ucfirst($term->scope) }} · last {{ \Illuminate\Support\Carbon::parse($term->last_searched)->diffForHumans() }}</span>
                                </th>
                                <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($term->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-5 py-6 text-center text-warm-gray">Every search found something.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Leaderboards --}}
    @php($leaders = $report['leaders'])
    <section class="mt-10" aria-labelledby="leaders-heading">
        <h2 id="leaders-heading" class="font-heading text-lg font-bold text-charcoal">Leaderboards</h2>
        <div class="mt-4 grid gap-6 lg:grid-cols-3">
            <div class="card min-w-0 p-5">
                <h3 class="text-base font-semibold text-ink">Top volunteers by approved hours</h3>
                <ol class="mt-3 divide-y divide-light-gray">
                    @forelse ($leaders['volunteers'] as $i => $row)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="w-5 text-right font-ui text-xs font-semibold text-warm-gray">{{ $i + 1 }}</span>
                            <x-avatar :user="$row['user']" size="h-8 w-8" text="text-xs" />
                            <a href="{{ route('admin.users.show', $row['user']) }}" class="min-w-0 flex-1 truncate text-sm font-medium text-ink hover:text-brand">{{ $row['user']->name }}</a>
                            <span class="text-right text-sm tabular-nums">
                                <span class="font-semibold text-ink">{{ $PA::format($row['hours'], 'hours') }}</span>
                                <span class="block text-xs text-warm-gray">{{ $row['opportunities'] }} {{ \Illuminate\Support\Str::plural('opportunity', $row['opportunities']) }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-warm-gray">No approved hours in this slice.</li>
                    @endforelse
                </ol>
            </div>

            <div class="card min-w-0 p-5">
                <h3 class="text-base font-semibold text-ink">Most applied-to opportunities</h3>
                <ol class="mt-3 divide-y divide-light-gray">
                    @forelse ($leaders['opportunities'] as $i => $row)
                        @php($o = $row['opportunity'])
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="w-5 text-right font-ui text-xs font-semibold text-warm-gray">{{ $i + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                @if ($o->trashed())
                                    <span class="block truncate text-sm font-medium text-warm-gray">{{ $o->title }} (deleted)</span>
                                @else
                                    <a href="{{ route('opportunities.show', $o) }}" class="block truncate text-sm font-medium text-ink hover:text-brand" title="{{ $o->title }}">{{ $o->title }}</a>
                                @endif
                                <span class="block truncate text-xs text-warm-gray">{{ $o->category?->name ?? 'Uncategorised' }}{{ $o->isImported() ? ' · IEEE import' : '' }}</span>
                            </span>
                            <span class="text-right text-sm tabular-nums">
                                <span class="font-semibold text-ink">{{ number_format($row['applications']) }}</span>
                                <span class="block text-xs text-warm-gray">{{ $row['confirmed'] }} confirmed</span>
                            </span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-warm-gray">No applications in this slice.</li>
                    @endforelse
                </ol>
            </div>

            <div class="card min-w-0 p-5">
                <h3 class="text-base font-semibold text-ink">Most active creators</h3>
                <ol class="mt-3 divide-y divide-light-gray">
                    @forelse ($leaders['creators'] as $i => $row)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="w-5 text-right font-ui text-xs font-semibold text-warm-gray">{{ $i + 1 }}</span>
                            <x-avatar :user="$row['user']" size="h-8 w-8" text="text-xs" />
                            <a href="{{ route('admin.users.show', $row['user']) }}" class="min-w-0 flex-1 truncate text-sm font-medium text-ink hover:text-brand">{{ $row['user']->name }}</a>
                            <span class="text-right text-sm tabular-nums">
                                <span class="font-semibold text-ink">{{ $row['published'] }} published</span>
                                <span class="block text-xs text-warm-gray">{{ number_format($row['applications']) }} applications</span>
                            </span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-warm-gray">Nobody published an opportunity in this slice.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </section>

    {{-- Cohorts --}}
    @php($cohorts = $report['cohorts'])
    <section class="mt-10" aria-labelledby="cohort-heading">
        <h2 id="cohort-heading" class="font-heading text-lg font-bold text-charcoal">Cohort retention</h2>
        <p class="mt-1 text-sm text-warm-gray">
            Monthly sign-up cohorts (last {{ $cohorts['months'] }} months). Each cell is the share of the cohort that applied or logged hours in that month after joining; month 0 is the month they joined.
        </p>
        <div class="card relative mt-4 overflow-x-auto p-4">
            <table class="w-full min-w-[640px] border-separate border-spacing-0.5 text-center text-xs">
                <thead>
                    <tr class="font-ui text-warm-gray">
                        <th scope="col" class="px-2 py-1.5 text-left font-semibold">Cohort</th>
                        <th scope="col" class="px-2 py-1.5 text-right font-semibold">People</th>
                        @for ($m = 0; $m < $cohorts['months']; $m++)
                            <th scope="col" class="px-2 py-1.5 font-semibold">M{{ $m }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cohorts['rows'] as $row)
                        <tr>
                            <th scope="row" class="whitespace-nowrap px-2 py-1.5 text-left font-medium text-ink">{{ $row['label'] }}</th>
                            <td class="px-2 py-1.5 text-right tabular-nums text-warm-gray">{{ number_format($row['size']) }}</td>
                            @foreach ($row['cells'] as $cell)
                                <td class="{{ $PA::heatClass($cell) }} rounded px-2 py-1.5 tabular-nums">
                                    @if ($cell !== null)
                                        {{ round($cell) }}%
                                    @else
                                        <span class="sr-only">No data</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-warm-gray" aria-hidden="true">
                <span>Less</span>
                @foreach ([0, 5, 15, 25, 40, 55, 70] as $sample)
                    <span class="{{ $PA::heatClass($sample) }} h-4 w-6 rounded"></span>
                @endforeach
                <span>More</span>
            </div>
        </div>
    </section>
@endsection
