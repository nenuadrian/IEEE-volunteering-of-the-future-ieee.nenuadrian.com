@extends('layouts.admin')
@section('title', 'IEEE sync')
@section('heading', 'IEEE sync')

@php
    $stale = $latest && $latest->started_at?->lt(now()->subHours(48));
@endphp

@section('content')
    <div class="grid gap-6 xl:grid-cols-3">
        {{-- What it does + trigger --}}
        <section class="card p-6 xl:col-span-2" aria-labelledby="sync-heading">
            <h2 id="sync-heading" class="font-heading text-xl font-bold text-charcoal">Refresh data from volunteer.ieee.org</h2>
            <p class="mt-2 text-sm text-warmer-gray">
                The original IEEE volunteering site publishes its open opportunities through a public API. A refresh reads the whole feed and brings this platform in line with it:
            </p>
            <ul class="mt-3 space-y-1.5 text-sm text-warmer-gray">
                <li class="flex gap-2"><span class="text-accent-green" aria-hidden="true">＋</span> New opportunities are created here, marked as <span class="chip-blue">IEEE</span> imports.</li>
                <li class="flex gap-2"><span class="text-accent-blue" aria-hidden="true">↻</span> Existing imports are updated only when a field (title, dates, skills, status…) actually changed.</li>
                <li class="flex gap-2"><span class="text-warm-gray" aria-hidden="true">■</span> Imports that are no longer listed are marked <em>completed</em>, so nobody applies to stale roles.</li>
                <li class="flex gap-2"><span class="text-brand" aria-hidden="true">◉</span> Owners are linked automatically when a local profile has the creator's IEEE member number; otherwise admins review the applications.</li>
            </ul>
            <p class="mt-3 text-xs text-warm-gray">Applications, hours and endorsements made here are never touched by a refresh.</p>

            <dl class="mt-5 grid gap-3 rounded-lg bg-warm-white p-4 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="font-ui text-xs font-semibold uppercase tracking-wide text-warm-gray">API endpoint</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs text-ink">{{ $api['search_url'] }}</dd>
                </div>
                <div>
                    <dt class="font-ui text-xs font-semibold uppercase tracking-wide text-warm-gray">Daily schedule</dt>
                    <dd class="mt-0.5 text-ink">
                        @if ($api['schedule_daily'])
                            <span class="font-semibold text-accent-green-dark">On</span> · every day at 03:15 ({{ config('app.timezone') }})
                        @else
                            <span class="font-semibold text-warmer-gray">Off</span> · set <code class="font-mono text-xs">IEEE_VOLUNTEER_SYNC_DAILY=true</code> to enable
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-ui text-xs font-semibold uppercase tracking-wide text-warm-gray">From the command line</dt>
                    <dd class="mt-0.5 font-mono text-xs text-ink">php artisan opportunities:sync</dd>
                </div>
            </dl>

            <form method="POST" action="{{ route('admin.sync.run') }}" class="mt-6 flex flex-wrap items-center gap-4"
                  x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button type="submit" class="btn-primary px-6 py-3 text-base" :disabled="busy" :aria-busy="busy.toString()">
                    <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle>
                        <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                    <span x-show="!busy"><span aria-hidden="true">⟳</span> Refresh now</span>
                    <span x-show="busy" x-cloak>Refreshing… this can take ~10s</span>
                </button>
                <p class="text-xs text-warm-gray" aria-live="polite">
                    @if ($lastSuccess)
                        Last successful refresh {{ $lastSuccess->started_at?->diffForHumans() }}.
                    @else
                        No successful refresh yet.
                    @endif
                </p>
            </form>
        </section>

        {{-- Last run --}}
        <section class="card p-6" aria-labelledby="last-run-heading">
            <div class="flex items-start justify-between gap-3">
                <h2 id="last-run-heading" class="font-heading text-lg font-bold text-charcoal">Last run</h2>
                @if ($latest)<x-status-badge :status="$latest->status" type="generic" />@endif
            </div>

            @if ($latest)
                <p class="mt-1 text-sm text-warm-gray">
                    <time datetime="{{ $latest->started_at?->toIso8601String() }}" title="{{ $latest->started_at?->format('j M Y, H:i') }}">{{ $latest->started_at?->diffForHumans() }}</time>
                    @if ($latest->durationSeconds() !== null) · took {{ $latest->durationSeconds() }}s @endif
                    · {{ $latest->trigger?->name ?? 'Scheduled / seeder' }}
                </p>
                @if ($stale)
                    <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">More than 48 hours since the last refresh — check the scheduler is running.</p>
                @endif

                <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                    @foreach (['fetched' => 'Fetched', 'created' => 'New', 'updated' => 'Updated', 'unchanged' => 'Unchanged', 'closed' => 'Closed'] as $key => $label)
                        <div class="rounded-lg bg-warm-white px-2 py-3">
                            <dt class="font-ui text-xs text-warm-gray">{{ $label }}</dt>
                            <dd class="mt-0.5 font-heading text-xl font-bold text-ink tabular-nums">{{ number_format($latest->{$key.'_count'}) }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($latest->error)
                    <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700" role="alert">
                        <p class="font-semibold">Error</p>
                        <p class="mt-1 break-words">{{ $latest->error }}</p>
                    </div>
                @endif

                <a href="{{ route('admin.sync.show', $latest) }}" class="btn-secondary btn-sm mt-4">View changes</a>
            @else
                <p class="mt-3 text-sm text-warm-gray">Nothing has been imported yet. Run a refresh to pull in the current opportunities from volunteer.ieee.org.</p>
            @endif

            <div class="mt-6 border-t border-light-gray pt-4">
                <h3 class="font-ui text-xs font-semibold uppercase tracking-wide text-warm-gray">Imported opportunities</h3>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @forelse ($statusCounts as $status => $total)
                        <li>
                            <a href="{{ route('admin.opportunities.index', ['source' => 'ieee', 'status' => $status]) }}" class="inline-flex items-center gap-1.5 hover:opacity-80">
                                <x-status-badge :status="$status" />
                                <span class="text-sm font-semibold tabular-nums text-ink">{{ number_format($total) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="text-sm text-warm-gray">None yet.</li>
                    @endforelse
                </ul>
                @if ($unclaimed)
                    <p class="mt-3 text-xs text-warm-gray">
                        <a href="{{ route('admin.opportunities.index', ['source' => 'ieee', 'owner' => 'unclaimed']) }}" class="text-accent-blue underline">{{ number_format($unclaimed) }} unclaimed</a>
                        — no local owner yet, so admins review their applications.
                    </p>
                @endif
            </div>
        </section>
    </div>

    {{-- History --}}
    <section class="mt-8" aria-labelledby="history-heading">
        <h2 id="history-heading" class="font-heading text-lg font-bold text-charcoal">History</h2>
        @if ($runs->isEmpty())
            <x-empty-state class="mt-4" title="No refreshes yet" icon="⟳">Every refresh — manual or scheduled — will be listed here with what it changed.</x-empty-state>
        @else
            <div class="card mt-4 overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="table-head">
                        <tr>
                            <th scope="col" class="px-5 py-3">Started</th>
                            <th scope="col" class="px-5 py-3">Status</th>
                            <th scope="col" class="px-5 py-3 text-right">Fetched</th>
                            <th scope="col" class="px-5 py-3 text-right">New</th>
                            <th scope="col" class="px-5 py-3 text-right">Updated</th>
                            <th scope="col" class="px-5 py-3 text-right">Unchanged</th>
                            <th scope="col" class="px-5 py-3 text-right">Closed</th>
                            <th scope="col" class="px-5 py-3 text-right">Duration</th>
                            <th scope="col" class="px-5 py-3">Triggered by</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Details</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-light-gray">
                        @foreach ($runs as $run)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-ink">
                                    {{ $run->started_at?->format('j M Y, H:i') }}
                                    <span class="block text-xs text-warm-gray">{{ $run->started_at?->diffForHumans() }}</span>
                                </td>
                                <td class="px-5 py-3"><x-status-badge :status="$run->status" type="generic" /></td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($run->fetched_count) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($run->created_count) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($run->updated_count) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-warm-gray">{{ number_format($run->unchanged_count) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($run->closed_count) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-warm-gray">{{ $run->durationSeconds() !== null ? $run->durationSeconds().'s' : '—' }}</td>
                                <td class="px-5 py-3">{{ $run->trigger?->name ?? 'Scheduled / seeder' }}</td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('admin.sync.show', $run) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Details</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $runs->links() }}</div>
        @endif
    </section>
@endsection
