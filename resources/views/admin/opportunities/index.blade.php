@extends('layouts.admin')
@section('title', 'Opportunities')
@section('heading', 'Opportunities')

@php($hasFilters = collect($filters)->filter()->isNotEmpty())

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-warm-gray">
            {{ number_format($sourceCounts->sum()) }} opportunities ·
            {{ number_format($sourceCounts['local'] ?? 0) }} created here ·
            {{ number_format($sourceCounts['ieee'] ?? 0) }} imported from <a href="{{ route('admin.sync.index') }}" class="text-accent-blue underline">volunteer.ieee.org</a>
        </p>
        <a href="{{ route('opportunities.create') }}" class="btn-primary">+ New opportunity</a>
    </div>

    <form method="GET" action="{{ route('admin.opportunities.index') }}" class="card mb-6 p-4" aria-label="Filter opportunities">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <div class="sm:col-span-2 lg:col-span-4 xl:col-span-2">
                <label for="q" class="label">Search</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Title, owner name or email, IEEE id…" class="input">
            </div>
            <div>
                <label for="source" class="label">Source</label>
                <select id="source" name="source" class="input">
                    <option value="">Any source</option>
                    <option value="local" @selected($filters['source'] === 'local')>Created here</option>
                    <option value="ieee" @selected($filters['source'] === 'ieee')>IEEE import</option>
                </select>
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <option value="">Any status</option>
                    @foreach (config('volunteering.opportunity_statuses') as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }} ({{ number_format($statusCounts[$key] ?? 0) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category" class="label">Type</label>
                <select id="category" name="category" class="input">
                    <option value="">Any type</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="region" class="label">Region</label>
                <select id="region" name="region" class="input">
                    <option value="">Any region</option>
                    @foreach (config('volunteering.regions') as $code => $label)
                        <option value="{{ $code }}" @selected($filters['region'] === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="featured" class="label">Featured</label>
                <select id="featured" name="featured" class="input">
                    <option value="">Any</option>
                    <option value="yes" @selected($filters['featured'] === 'yes')>Featured</option>
                    <option value="no" @selected($filters['featured'] === 'no')>Not featured</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-secondary">Apply filters</button>
            <label class="inline-flex items-center gap-2 text-sm text-warmer-gray">
                <input type="checkbox" name="owner" value="unclaimed" class="checkbox" @checked($filters['owner'] === 'unclaimed')>
                Only unclaimed (no local owner)
            </label>
            @if ($hasFilters)
                <a href="{{ route('admin.opportunities.index') }}" class="btn-ghost">Clear</a>
            @endif
        </div>
    </form>

    @if ($opportunities->isEmpty())
        <x-empty-state title="No opportunities match" icon="✦">
            Try a broader search or clear the filters.
            @if ($hasFilters)
                <x-slot:actions><a href="{{ route('admin.opportunities.index') }}" class="btn-secondary">Clear filters</a></x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="card relative overflow-x-auto">
            <table class="w-full min-w-[1040px] text-left text-sm">
                <thead class="table-head">
                    <tr>
                        <th scope="col" class="px-5 py-3">Opportunity</th>
                        <th scope="col" class="px-5 py-3">Owners</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3">Source</th>
                        <th scope="col" class="px-5 py-3 text-right">Applicants</th>
                        <th scope="col" class="px-5 py-3">Created</th>
                        <th scope="col" class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                    @foreach ($opportunities as $opportunity)
                        <tr class="align-top">
                            <td class="max-w-sm px-5 py-4">
                                <a href="{{ route('opportunities.show', $opportunity) }}" class="font-medium text-ink hover:text-brand">{{ $opportunity->title }}</a>
                                <p class="mt-0.5 text-xs text-warm-gray">
                                    {{ $opportunity->category?->name ?? 'Uncategorised' }}
                                    @if ($opportunity->region) · {{ $opportunity->region }} @endif
                                    · {{ $opportunity->locationLabel() }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                @if ($opportunity->owners->isNotEmpty())
                                    <ul class="space-y-1.5">
                                        @foreach ($opportunity->owners->take(3) as $owner)
                                            <li class="flex items-center gap-2">
                                                <x-avatar :user="$owner" size="h-6 w-6" text="text-[10px]" />
                                                <a href="{{ route('admin.users.show', $owner) }}" class="truncate text-sm text-ink hover:text-brand">{{ $owner->name }}</a>
                                                @if ($owner->pivot->role === 'co_owner')<span class="text-xs text-warm-gray">co-owner</span>@endif
                                            </li>
                                        @endforeach
                                        @if ($opportunity->owners->count() > 3)
                                            <li class="text-xs text-warm-gray">+{{ $opportunity->owners->count() - 3 }} more</li>
                                        @endif
                                    </ul>
                                @elseif ($opportunity->isImported())
                                    <span class="text-xs italic text-warm-gray">Unclaimed — admins review applications</span>
                                @else
                                    <span class="text-xs italic text-warm-gray">No owner</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <x-status-badge :status="$opportunity->status" />
                                @if ($opportunity->is_featured)
                                    <span class="mt-1 block font-ui text-xs font-semibold text-brand"><span aria-hidden="true">★</span> Featured</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if ($opportunity->isImported())
                                    @if ($opportunity->externalUrl())
                                        <a href="{{ $opportunity->externalUrl() }}" target="_blank" rel="noopener" class="chip-blue whitespace-nowrap hover:underline" title="Open on volunteer.ieee.org">IEEE ↗</a>
                                    @else
                                        <span class="chip-blue">IEEE</span>
                                    @endif
                                @else
                                    <span class="chip-muted">Local</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums">
                                <span class="font-semibold text-ink">{{ number_format($opportunity->applications_count) }}</span>
                                <span class="block text-xs text-warm-gray">{{ number_format($opportunity->confirmed_count) }}/{{ $opportunity->volunteers_needed }} confirmed</span>
                                @if ($opportunity->pending_count)
                                    <span class="block text-xs font-semibold text-amber-700">{{ $opportunity->pending_count }} pending</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-warm-gray">
                                <time datetime="{{ $opportunity->created_at?->toIso8601String() }}">{{ $opportunity->created_at?->format('j M Y') }}</time>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
                                    <a href="{{ route('opportunities.manage', $opportunity) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Manage</a>
                                    <a href="{{ route('opportunities.edit', $opportunity) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Edit</a>
                                    <form method="POST" action="{{ route('admin.opportunities.feature', $opportunity) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="font-ui text-sm font-medium text-brand hover:text-brand-dark" aria-pressed="{{ $opportunity->is_featured ? 'true' : 'false' }}">
                                            {{ $opportunity->is_featured ? 'Unfeature' : 'Feature' }}
                                        </button>
                                    </form>
                                    <x-admin.delete :action="route('admin.opportunities.destroy', $opportunity)"
                                                    confirm="Delete this opportunity? It disappears from the site; its applications and hours are kept." />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $opportunities->links() }}</div>
    @endif
@endsection
