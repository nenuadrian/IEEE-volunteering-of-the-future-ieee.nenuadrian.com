@extends('layouts.admin')
@section('title', 'Users')
@section('heading', 'Users')

@php
    $hasFilters = $filters['q'] || $filters['role'] || $filters['status'] || $filters['region'];
    $sortable = function (string $key) use ($filters) {
        $active = $filters['sort'] === $key;
        $next = $active ? ($filters['dir'] === 'desc' ? 'asc' : 'desc') : ($key === 'name' ? 'asc' : 'desc');

        return [
            'url' => request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]),
            'aria' => $active ? ($filters['dir'] === 'asc' ? 'ascending' : 'descending') : 'none',
            'arrow' => $active ? ($filters['dir'] === 'asc' ? '▲' : '▼') : '',
            'active' => $active,
        ];
    };
    $columns = [
        ['key' => 'name', 'label' => 'User'],
        ['label' => 'Role'],
        ['label' => 'Region'],
        ['key' => 'joined', 'label' => 'Joined'],
        ['key' => 'last_login', 'label' => 'Last login'],
        ['label' => 'Applications', 'right' => true],
        ['key' => 'hours', 'label' => 'Approved hours', 'right' => true],
        ['label' => 'Status'],
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-warm-gray">
            {{ number_format($totals['all']) }} accounts ·
            <a href="{{ route('admin.users.index', ['role' => 'admin']) }}" class="text-accent-blue underline">{{ number_format($totals['admins']) }} {{ \Illuminate\Support\Str::plural('admin', $totals['admins']) }}</a> ·
            <a href="{{ route('admin.users.index', ['status' => 'suspended']) }}" class="text-accent-blue underline">{{ number_format($totals['suspended']) }} suspended</a>
        </p>
        <a href="{{ route('admin.users.export', request()->except('page')) }}" class="btn-secondary"><span aria-hidden="true">⤓</span> Export CSV</a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="card mb-6 p-4" aria-label="Filter users">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="sm:col-span-2">
                <label for="q" class="label">Search</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Name or email…" class="input">
            </div>
            <div>
                <label for="role" class="label">Role</label>
                <select id="role" name="role" class="input">
                    <option value="">Any role</option>
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['role'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <option value="">Any status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="suspended" @selected($filters['status'] === 'suspended')>Suspended</option>
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
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="submit" class="btn-secondary">Apply filters</button>
            @if ($hasFilters)
                <a href="{{ route('admin.users.index') }}" class="btn-ghost">Clear</a>
            @endif
        </div>
    </form>

    @if ($users->isEmpty())
        <x-empty-state title="No users match" icon="◉">
            Try a different name or email, or clear the filters.
            @if ($hasFilters)
                <x-slot:actions><a href="{{ route('admin.users.index') }}" class="btn-secondary">Clear filters</a></x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <div class="card relative overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead class="table-head">
                    <tr>
                        @foreach ($columns as $column)
                            @if (isset($column['key']))
                                @php($s = $sortable($column['key']))
                                <th scope="col" class="px-5 py-3 {{ ($column['right'] ?? false) ? 'text-right' : '' }}" aria-sort="{{ $s['aria'] }}">
                                    <a href="{{ $s['url'] }}" @class(['inline-flex items-center gap-1 hover:text-ink', 'text-ink' => $s['active']])>
                                        <span class="underline decoration-dotted underline-offset-4">{{ $column['label'] }}</span>
                                        @if ($s['arrow'])<span aria-hidden="true" class="text-[10px]">{{ $s['arrow'] }}</span>@endif
                                    </a>
                                </th>
                            @else
                                <th scope="col" class="px-5 py-3 {{ ($column['right'] ?? false) ? 'text-right' : '' }}">{{ $column['label'] }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                    @foreach ($users as $user)
                        <tr @class(['bg-red-50/40' => $user->isSuspended()])>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.users.show', $user) }}" class="group flex items-center gap-3">
                                    <x-avatar :user="$user" size="h-9 w-9" text="text-xs" />
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-ink group-hover:text-brand">{{ $user->name }}</span>
                                        <span class="block truncate text-xs text-warm-gray">{{ $user->email }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                @if ($user->isAdmin())
                                    <span class="chip">Admin</span>
                                @else
                                    <span class="chip-muted">User</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-warmer-gray" title="{{ $user->profile?->regionLabel() }}">{{ $user->profile?->region ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-warm-gray">{{ $user->created_at?->format('j M Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-warm-gray" title="{{ $user->last_login_at?->format('j M Y, H:i') }}">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($user->applications_count) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ \App\Services\PlatformAnalytics::format(round((float) $user->approved_hours, 1), 'hours') }}</td>
                            <td class="px-5 py-3">
                                @if ($user->isSuspended())
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 font-ui text-xs font-semibold text-red-700" title="Since {{ $user->suspended_at->format('j M Y') }}">Suspended</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-accent-green-light/70 px-2.5 py-0.5 font-ui text-xs font-semibold text-accent-green-dark">Active</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $users->links() }}</div>
    @endif
@endsection
