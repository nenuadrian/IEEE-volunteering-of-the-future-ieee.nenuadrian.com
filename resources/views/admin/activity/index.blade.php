@extends('layouts.admin')
@section('title', 'Activity log')
@section('heading', 'Activity log')

@php
    $hasFilters = $filters['group'] || $filters['actor'] || $filters['from'] || $filters['to'];
    $groupChip = fn (string $type) => match (\Illuminate\Support\Str::before($type, '.')) {
        'admin' => 'bg-brand-50 text-brand-700',
        'opportunity' => 'bg-accent-blue/10 text-accent-blue',
        'application', 'endorsement' => 'bg-accent-green-light/70 text-accent-green-dark',
        'hours' => 'bg-amber-100 text-amber-800',
        default => 'bg-light-gray text-warmer-gray',
    };
    $describe = function ($value): string {
        return match (true) {
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => \Illuminate\Support\Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 60),
            $value === null => '—',
            default => \Illuminate\Support\Str::limit((string) $value, 60),
        };
    };
@endphp

@section('content')
    <form method="GET" action="{{ route('admin.activity.index') }}" class="card mb-6 p-4" aria-label="Filter activity">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="group" class="label">Type</label>
                <select id="group" name="group" class="input">
                    <option value="">All activity</option>
                    @foreach (\App\Http\Controllers\Admin\ActivityController::GROUPS as $key => $group)
                        <option value="{{ $key }}" @selected($filters['group'] === $key)>{{ $group['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-2">
                <label for="actor" class="label">Who</label>
                <input type="search" id="actor" name="actor" value="{{ $filters['actor'] }}" placeholder="Name or email of the person who acted…" class="input">
            </div>
            <div>
                <label for="from" class="label">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from']?->toDateString() }}" class="input">
            </div>
            <div>
                <label for="to" class="label">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to']?->toDateString() }}" class="input">
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="submit" class="btn-secondary">Apply filters</button>
            @if ($hasFilters)<a href="{{ route('admin.activity.index') }}" class="btn-ghost">Clear</a>@endif
        </div>
    </form>

    @if ($activities->isEmpty())
        <x-empty-state title="No activity found" icon="≋">
            {{ $hasFilters ? 'Nothing matches these filters.' : 'Actions people take on the platform will appear here.' }}
        </x-empty-state>
    @else
        <div class="card relative overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="table-head">
                    <tr>
                        <th scope="col" class="px-5 py-3">When</th>
                        <th scope="col" class="px-5 py-3">Who</th>
                        <th scope="col" class="px-5 py-3">What</th>
                        <th scope="col" class="px-5 py-3">On</th>
                        <th scope="col" class="px-5 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                    @foreach ($activities as $activity)
                        @php
                            $subject = $activity->subject;
                            $props = $activity->properties ?? [];
                            $title = $activity->subjectTitle()
                                ?? ($props['name'] ?? null)
                                ?? ($subject instanceof \App\Models\SyncRun ? 'Sync run #'.$subject->id : null);
                            $url = match (true) {
                                $subject instanceof \App\Models\User => route('admin.users.show', $subject),
                                $subject instanceof \App\Models\SyncRun => route('admin.sync.show', $subject),
                                default => $activity->subjectUrl(),
                            };
                            $details = collect($props)->except(['title', 'name'])->take(5);
                        @endphp
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-5 py-3 text-warm-gray">
                                <time datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->format('j M Y, H:i') }}</time>
                                <span class="block text-xs">{{ $activity->created_at?->diffForHumans() }}</span>
                            </td>
                            <td class="px-5 py-3">
                                @if ($activity->user)
                                    <a href="{{ route('admin.users.show', $activity->user) }}" class="group flex items-center gap-2">
                                        <x-avatar :user="$activity->user" size="h-7 w-7" text="text-[10px]" />
                                        <span class="truncate font-medium text-ink group-hover:text-brand">{{ $activity->user->name }}</span>
                                    </a>
                                @else
                                    <span class="flex items-center gap-2 text-warm-gray">
                                        <span class="grid h-7 w-7 place-items-center rounded-full bg-light-gray text-xs" aria-hidden="true">⚙</span>
                                        System
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-warmer-gray">{{ ucfirst($activity->label()) }}</span>
                                <span class="mt-1 block"><span class="inline-flex rounded px-1.5 py-0.5 font-mono text-[11px] {{ $groupChip($activity->type) }}">{{ $activity->type }}</span></span>
                            </td>
                            <td class="max-w-xs px-5 py-3">
                                @if ($title)
                                    @if ($url)
                                        <a href="{{ $url }}" class="font-medium text-ink hover:text-brand">{{ $title }}</a>
                                    @else
                                        <span class="font-medium text-ink">{{ $title }}</span>
                                    @endif
                                    @if (! $subject && $activity->subject_type)
                                        <span class="block text-xs text-warm-gray">(deleted)</span>
                                    @endif
                                @else
                                    <span class="text-warm-gray">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-warm-gray">
                                @forelse ($details as $key => $value)
                                    <span class="mr-2 inline-block"><span class="font-semibold text-warmer-gray">{{ str_replace('_', ' ', $key) }}:</span> {{ $describe($value) }}</span>
                                @empty
                                    —
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $activities->links() }}</div>
    @endif
@endsection
