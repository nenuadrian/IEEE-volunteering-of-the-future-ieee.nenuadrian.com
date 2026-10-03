@extends('layouts.admin')
@section('title', $user->name)
@section('heading', 'User · '.$user->name)

@php
    $profile = $user->profile;
    $totalApplications = $applicationCounts->sum();
@endphp

@section('content')
    <a href="{{ route('admin.users.index') }}" class="mb-4 inline-flex items-center gap-1 font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">← All users</a>

    {{-- Identity --}}
    <div class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
        <x-avatar :user="$user" size="h-20 w-20" text="text-2xl" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-heading text-2xl font-bold text-charcoal">{{ $user->name }}</h2>
                @if ($user->isAdmin())<span class="chip">Admin</span>@else<span class="chip-muted">User</span>@endif
                @if ($user->isSuspended())
                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 font-ui text-xs font-semibold text-red-700">Suspended {{ $user->suspended_at->format('j M Y') }}</span>
                @endif
                @if ($user->is(auth()->user()))<span class="chip-blue">You</span>@endif
            </div>
            <p class="mt-1 break-all text-sm text-warmer-gray">
                <a href="mailto:{{ $user->email }}" class="hover:text-brand">{{ $user->email }}</a>
                @if ($user->email_verified_at)
                    <span class="text-xs text-accent-green-dark">· verified</span>
                @else
                    <span class="text-xs text-amber-700">· email not verified</span>
                @endif
            </p>
            <p class="mt-1 text-xs text-warm-gray">
                Joined {{ $user->created_at?->format('j M Y') }} ·
                last login {{ $user->last_login_at?->diffForHumans() ?? 'never' }}
                @if ($profile?->headline) · {{ $profile->headline }} @endif
            </p>
        </div>
        @if ($profile)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('volunteers.show', $profile) }}" class="btn-secondary btn-sm" target="_blank" rel="noopener">Public profile ↗</a>
                <a href="{{ route('volunteers.cv', $profile) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">CV (PDF)</a>
            </div>
        @endif
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Headline numbers --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat :value="number_format($totalApplications)" label="Applications" :hint="number_format(($applicationCounts['accepted'] ?? 0) + ($applicationCounts['completed'] ?? 0)).' confirmed'" />
                <x-stat :value="\App\Services\PlatformAnalytics::format($approvedHours, 'hours')" label="Approved hours" :hint="$pendingHours ? \App\Services\PlatformAnalytics::format($pendingHours, 'hours').' awaiting approval' : null" />
                <x-stat :value="number_format($owned->count())" label="Opportunities owned" :hint="$owned->where('status', 'open')->count().' open'" />
                <x-stat :value="number_format($endorsementsReceived)" label="Endorsements received" :hint="number_format($endorsementsGiven).' given'" />
            </div>

            {{-- Applications by status --}}
            <section class="card p-6" aria-labelledby="apps-heading">
                <h3 id="apps-heading" class="text-base font-semibold text-ink">Applications</h3>
                @if ($totalApplications)
                    <ul class="mt-3 flex flex-wrap gap-3">
                        @foreach (config('volunteering.application_statuses') as $status => $label)
                            <li class="inline-flex items-center gap-1.5">
                                <x-status-badge :status="$status" type="application" />
                                <span class="text-sm font-semibold tabular-nums text-ink">{{ number_format($applicationCounts[$status] ?? 0) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <ul class="mt-4 divide-y divide-light-gray">
                        @foreach ($applications as $application)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                                <span class="min-w-0 flex-1">
                                    @if ($application->opportunity && ! $application->opportunity->trashed())
                                        <a href="{{ route('opportunities.show', $application->opportunity) }}" class="font-medium text-ink hover:text-brand">{{ $application->opportunity->title }}</a>
                                    @else
                                        <span class="font-medium text-warm-gray">{{ $application->opportunity?->title ?? 'Deleted opportunity' }}</span>
                                    @endif
                                    <span class="block text-xs text-warm-gray">Applied {{ $application->created_at?->format('j M Y') }}</span>
                                </span>
                                <x-status-badge :status="$application->status" type="application" />
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-warm-gray">No applications yet.</p>
                @endif
            </section>

            {{-- Owned opportunities --}}
            <section class="card p-6" aria-labelledby="owned-heading">
                <h3 id="owned-heading" class="text-base font-semibold text-ink">Opportunities they manage</h3>
                @if ($owned->isEmpty())
                    <p class="mt-2 text-sm text-warm-gray">Not an owner or co-owner of any opportunity.</p>
                @else
                    <ul class="mt-3 divide-y divide-light-gray">
                        @foreach ($owned->take(10) as $opportunity)
                            <li class="flex flex-wrap items-center gap-3 py-2.5 text-sm">
                                <span class="min-w-0 flex-1">
                                    <a href="{{ route('opportunities.show', $opportunity) }}" class="font-medium text-ink hover:text-brand">{{ $opportunity->title }}</a>
                                    <span class="block text-xs text-warm-gray">
                                        {{ $opportunity->pivot->role === 'owner' ? 'Owner' : 'Co-owner' }} ·
                                        {{ number_format($opportunity->applications_count) }} {{ \Illuminate\Support\Str::plural('applicant', $opportunity->applications_count) }}
                                    </span>
                                </span>
                                <x-status-badge :status="$opportunity->status" />
                                <a href="{{ route('opportunities.manage', $opportunity) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Manage</a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($owned->count() > 10)
                        <p class="mt-2 text-xs text-warm-gray">and {{ $owned->count() - 10 }} more.</p>
                    @endif
                @endif
            </section>

            {{-- Recent activity --}}
            <section class="card p-6" aria-labelledby="activity-heading">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="activity-heading" class="text-base font-semibold text-ink">Recent activity</h3>
                    <a href="{{ route('admin.activity.index', ['actor' => $user->email]) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Full log</a>
                </div>
                @if ($activities->isEmpty())
                    <p class="mt-2 text-sm text-warm-gray">No recorded activity.</p>
                @else
                    <ol class="mt-3 divide-y divide-light-gray">
                        @foreach ($activities as $activity)
                            <li class="flex flex-wrap items-baseline justify-between gap-2 py-2.5 text-sm">
                                <span class="min-w-0 flex-1 text-warmer-gray">
                                    {{ ucfirst($activity->label()) }}
                                    @if ($title = $activity->subjectTitle() ?? ($activity->properties['name'] ?? null))
                                        @if ($url = $activity->subjectUrl())
                                            — <a href="{{ $url }}" class="font-medium text-ink hover:text-brand">{{ $title }}</a>
                                        @else
                                            — <span class="font-medium text-ink">{{ $title }}</span>
                                        @endif
                                    @endif
                                </span>
                                <time class="whitespace-nowrap text-xs text-warm-gray" datetime="{{ $activity->created_at?->toIso8601String() }}" title="{{ $activity->created_at?->format('j M Y, H:i') }}">{{ $activity->created_at?->diffForHumans() }}</time>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            {{-- Profile basics --}}
            <section class="card p-6" aria-labelledby="profile-heading">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="profile-heading" class="text-base font-semibold text-ink">Volunteer profile</h3>
                    @if ($completeness)
                        <span class="text-xs font-semibold text-warm-gray">{{ $completeness['percent'] }}% complete</span>
                    @endif
                </div>
                @if ($completeness)
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-light-gray" role="progressbar" aria-valuenow="{{ $completeness['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completeness">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $completeness['percent'] }}%"></div>
                    </div>
                @endif
                <dl class="mt-4 space-y-2.5 text-sm">
                    @foreach ([
                        'Region' => $profile?->regionLabel(),
                        'Section' => $profile?->section,
                        'Location' => $profile?->locationLine(),
                        'Membership grade' => $profile?->gradeLabel(),
                        'IEEE member no.' => $profile?->ieee_member_number,
                        'Member since' => $profile?->member_since,
                        'Availability' => $profile ? $profile->availabilityLabel() : null,
                        'Public profile' => $profile ? ($profile->is_public ? 'Visible in the directory' : 'Hidden') : null,
                    ] as $label => $value)
                        <div class="flex justify-between gap-4">
                            <dt class="text-warm-gray">{{ $label }}</dt>
                            <dd class="text-right text-ink">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($user->skills->isNotEmpty())
                    <ul class="mt-4 flex flex-wrap gap-1.5" aria-label="Skills">
                        @foreach ($user->skills as $skill)
                            <li class="chip-muted">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Admin actions --}}
            <section class="card p-6" aria-labelledby="actions-heading">
                <h3 id="actions-heading" class="text-base font-semibold text-ink">Admin actions</h3>

                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="mt-4">
                    @csrf
                    @method('PATCH')
                    <label for="role" class="label">Role</label>
                    <div class="flex gap-2">
                        <select id="role" name="role" class="input" @disabled($guards['role'])>
                            @foreach (\App\Models\User::ROLES as $key => $label)
                                <option value="{{ $key }}" @selected($user->role === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-secondary" @disabled($guards['role'])>Save</button>
                    </div>
                    <p class="help">{{ $guards['role'] ?? 'Admins can open this panel and manage every opportunity.' }}</p>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </form>

                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="mt-6 border-t border-light-gray pt-5">
                    @csrf
                    @method('PATCH')
                    @if ($user->isSuspended())
                        <p class="text-sm text-warmer-gray">Suspended on {{ $user->suspended_at->format('j M Y') }}. They can’t sign in until reinstated.</p>
                        <button type="submit" class="btn-secondary mt-3">Reinstate account</button>
                    @else
                        <p class="text-sm text-warmer-gray">Suspending signs them out and blocks sign-in. Nothing is deleted.</p>
                        <button type="submit" class="btn-secondary mt-3 border-red-300 text-red-700 hover:bg-red-50 focus:ring-red-600" @disabled($guards['suspend'])
                                onclick="return confirm('Suspend this account? They will be signed out immediately.')">Suspend account</button>
                        @if ($guards['suspend'])<p class="help">{{ $guards['suspend'] }}</p>@endif
                    @endif
                    <x-input-error :messages="$errors->get('suspend')" class="mt-1" />
                </form>
            </section>

            {{-- Danger zone --}}
            <section class="card border-red-200 p-6" aria-labelledby="delete-heading"
                     x-data="{ typed: '', expected: @js(\Illuminate\Support\Str::lower($user->email)) }">
                <h3 id="delete-heading" class="text-base font-semibold text-red-700">Delete account</h3>
                @if ($guards['delete'])
                    <p class="mt-2 text-sm text-warm-gray">{{ $guards['delete'] }}</p>
                @else
                    <p class="mt-2 text-sm text-warmer-gray">
                        Permanently removes {{ $user->name }}, their profile, {{ number_format($totalApplications) }} {{ \Illuminate\Support\Str::plural('application', $totalApplications) }},
                        logged hours and endorsements. This cannot be undone.
                        @if ($soleOwned)
                            <strong class="font-semibold text-ink">{{ $soleOwned }} {{ \Illuminate\Support\Str::plural('opportunity', $soleOwned) }} they solely own will be left without an owner.</strong>
                        @endif
                    </p>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <label for="confirm_email" class="label">Type <span class="font-mono text-xs text-ink">{{ $user->email }}</span> to confirm</label>
                        <input id="confirm_email" name="confirm_email" type="text" autocomplete="off" spellcheck="false" class="input" x-model="typed" required>
                        <x-input-error :messages="$errors->get('confirm_email')" class="mt-1" />
                        <x-input-error :messages="$errors->get('delete')" class="mt-1" />
                        <button type="submit" class="btn-danger mt-3 w-full" :disabled="typed.trim().toLowerCase() !== expected">Delete this account</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection
