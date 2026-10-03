@extends('layouts.admin')
@section('title', 'Sync run #'.$run->id)
@section('heading', 'IEEE sync · run #'.$run->id)

@php
    $chips = [
        'created' => 'bg-accent-green-light/70 text-accent-green-dark',
        'updated' => 'bg-accent-blue/10 text-accent-blue',
        'closed' => 'bg-light-gray text-warmer-gray',
    ];
@endphp

@section('content')
    <a href="{{ route('admin.sync.index') }}" class="mb-4 inline-flex items-center gap-1 font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">← All refreshes</a>

    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-warm-gray">
                    Started {{ $run->started_at?->format('j M Y, H:i:s') }}
                    @if ($run->durationSeconds() !== null) · took {{ $run->durationSeconds() }}s @endif
                    · {{ $run->trigger?->name ?? 'Scheduled / seeder' }}
                </p>
            </div>
            <x-status-badge :status="$run->status" type="generic" />
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-center sm:grid-cols-5">
            @foreach (['fetched' => 'Fetched', 'created' => 'New', 'updated' => 'Updated', 'unchanged' => 'Unchanged', 'closed' => 'Closed'] as $key => $label)
                <div class="rounded-lg bg-warm-white px-2 py-3">
                    <dt class="font-ui text-xs text-warm-gray">{{ $label }}</dt>
                    <dd class="mt-0.5 font-heading text-xl font-bold text-ink tabular-nums">{{ number_format($run->{$key.'_count'}) }}</dd>
                </div>
            @endforeach
        </dl>

        @foreach ($notes as $note)
            <p class="mt-4 rounded-md bg-warm-white px-3 py-2 text-sm text-warmer-gray"><span aria-hidden="true">ℹ</span> {{ $note }}</p>
        @endforeach

        @if ($run->error)
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                <p class="font-semibold">The refresh failed</p>
                <p class="mt-1 break-words font-mono text-xs">{{ $run->error }}</p>
            </div>
        @endif
    </div>

    <section class="mt-8" aria-labelledby="changes-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="changes-heading" class="font-heading text-lg font-bold text-charcoal">Changes</h2>
            <nav class="flex flex-wrap gap-2" aria-label="Filter changes">
                <a href="{{ route('admin.sync.show', $run) }}" @class(['chip', 'ring-2 ring-brand' => ! $action]) @if(! $action) aria-current="true" @endif>All ({{ number_format($actionCounts->sum()) }})</a>
                @foreach ($chips as $key => $class)
                    <a href="{{ route('admin.sync.show', [$run, 'action' => $key]) }}"
                       class="inline-flex items-center rounded-full px-3 py-1 font-ui text-xs font-semibold {{ $class }} {{ $action === $key ? 'ring-2 ring-brand' : '' }}"
                       @if($action === $key) aria-current="true" @endif>
                        {{ ucfirst($key) }} ({{ number_format($actionCounts[$key] ?? 0) }})
                    </a>
                @endforeach
            </nav>
        </div>

        @if ($entries->isEmpty())
            <x-empty-state class="mt-4" title="{{ $action ? 'No '.$action.' opportunities in this run' : 'Nothing changed' }}" icon="✓">
                Unchanged opportunities are not listed individually.
            </x-empty-state>
        @else
            <div class="card relative mt-4 overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="table-head">
                        <tr>
                            <th scope="col" class="px-5 py-3">Change</th>
                            <th scope="col" class="px-5 py-3">Opportunity</th>
                            <th scope="col" class="px-5 py-3">Now</th>
                            <th scope="col" class="px-5 py-3 text-right">IEEE id</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-light-gray">
                        @foreach ($entries as $entry)
                            @php($opportunity = $local[$entry['id'] ?? ''] ?? null)
                            <tr>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-ui text-xs font-semibold {{ $chips[$entry['action'] ?? ''] ?? 'bg-light-gray text-warmer-gray' }}">{{ ucfirst($entry['action'] ?? 'changed') }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($opportunity && ! $opportunity->trashed())
                                        <a href="{{ route('opportunities.show', $opportunity) }}" class="font-medium text-ink hover:text-brand">{{ $entry['title'] ?? $opportunity->title }}</a>
                                    @else
                                        <span class="font-medium text-ink">{{ $entry['title'] ?? 'Untitled' }}</span>
                                        <span class="block text-xs text-warm-gray">{{ $opportunity ? 'Deleted locally' : 'Not on this platform any more' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($opportunity)<x-status-badge :status="$opportunity->status" />@else<span class="text-warm-gray">—</span>@endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if (! empty($entry['id']))
                                        <a href="{{ str_replace(':id', $entry['id'], config('volunteering.api.public_opportunity_url')) }}" target="_blank" rel="noopener"
                                           class="whitespace-nowrap font-mono text-xs text-accent-blue hover:underline" title="Open on volunteer.ieee.org">{{ \Illuminate\Support\Str::limit($entry['id'], 14) }} ↗</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
