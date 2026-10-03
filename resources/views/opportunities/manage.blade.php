@extends('layouts.public')
@section('title', 'Manage: '.$opportunity->title)
@section('robots_noindex', '1')

@php
    use App\Models\Application;
    $o = $opportunity;
    $counts = $applications->countBy('status');
    $filter = request('status');
    $shown = $filter ? $applications->where('status', $filter) : $applications;
    $tabs = [
        'applicants' => ['Applicants', $counts[Application::PENDING] ?? 0],
        'hours' => ['Hours to approve', $pendingHours->count()],
        'team' => ['Owners', null],
        'impact' => ['Impact', null],
    ];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
@endphp

@section('content')
    <section class="border-b border-light-gray bg-white">
        <div class="container-x py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-warm-gray">
                <a href="{{ route('my.opportunities', ['tab' => 'managing']) }}" class="hover:text-brand">My Opportunities</a>
                <span aria-hidden="true" class="mx-1.5">/</span> Manage
            </nav>
            <div class="mt-3 flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$o->status" />
                        @if ($o->category)<span class="text-sm text-warm-gray">{{ $o->category->name }}</span>@endif
                        @if ($o->isImported())<span class="chip-muted">Synced from volunteer.ieee.org</span>@endif
                    </div>
                    <h1 class="mt-2 text-2xl font-semibold text-ink sm:text-3xl">{{ $o->title }}</h1>
                    <p class="mt-1 text-sm text-warm-gray">
                        {{ $o->start_date?->format('j M Y') }}{{ $o->end_date ? ' – '.$o->end_date->format('j M Y') : '' }}
                        · {{ $impact['accepted'] + $impact['completed'] }} of {{ $o->volunteers_needed }} volunteers confirmed
                        · {{ number_format($o->views_count) }} views
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('opportunities.status', $o) }}" class="flex items-center gap-2">
                        @csrf
                        <label for="status" class="sr-only">Status</label>
                        <select id="status" name="status" class="input w-auto py-2 text-sm" onchange="this.form.submit()">
                            @foreach (config('volunteering.opportunity_statuses') as $key => $label)
                                <option value="{{ $key }}" @selected($o->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button class="btn-secondary btn-sm">Update</button></noscript>
                    </form>
                    <a href="{{ route('opportunities.show', $o) }}" class="btn-ghost">View page</a>
                    <a href="{{ route('opportunities.edit', $o) }}" class="btn-secondary">Edit</a>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" @click.outside="open = false" class="btn-ghost" aria-label="More actions" :aria-expanded="open">•••</button>
                        <div x-show="open" x-transition x-cloak class="absolute right-0 top-full z-30 mt-1 w-56 rounded-xl border border-light-gray bg-white p-2 shadow-dropdown">
                            <form method="POST" action="{{ route('opportunities.clone', $o) }}">@csrf<button class="block w-full rounded-lg px-3 py-2 text-left text-sm text-warmer-gray hover:bg-brand-50">Clone as new draft</button></form>
                            <a href="{{ route('opportunities.manage.export', $o) }}" class="block rounded-lg px-3 py-2 text-sm text-warmer-gray hover:bg-brand-50">Export applicants (CSV)</a>
                            <form method="POST" action="{{ route('opportunities.destroy', $o) }}" onsubmit="return confirm('Delete this opportunity? Applicants will no longer see it.')">
                                @csrf @method('DELETE')
                                <button class="block w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">Delete opportunity</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="-mb-8 mt-6 flex gap-1 overflow-x-auto" aria-label="Workspace sections">
                @foreach ($tabs as $key => [$label, $badge])
                    <a href="{{ route('opportunities.manage', [$o, 'tab' => $key]) }}" @class([
                        'relative whitespace-nowrap px-4 py-3 text-sm font-semibold transition',
                        'text-brand after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:bg-brand' => $tab === $key,
                        'text-warm-gray hover:text-brand' => $tab !== $key,
                    ]) @if($tab === $key) aria-current="page" @endif>
                        {{ $label }}
                        @if ($badge)<span class="ml-1 rounded-full bg-brand px-1.5 py-0.5 text-[11px] font-bold text-white">{{ $badge }}</span>@endif
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <div class="container-x py-8">
        @if ($tab === 'applicants')
            <div class="mb-5 flex flex-wrap items-center gap-2">
                <a href="{{ route('opportunities.manage', [$o, 'tab' => 'applicants']) }}" @class(['chip', 'bg-brand text-white' => ! $filter])>All ({{ $applications->count() }})</a>
                @foreach (config('volunteering.application_statuses') as $key => $label)
                    @if ($counts[$key] ?? 0)
                        <a href="{{ route('opportunities.manage', [$o, 'tab' => 'applicants', 'status' => $key]) }}" @class(['chip', 'bg-brand text-white' => $filter === $key])>{{ $label }} ({{ $counts[$key] }})</a>
                    @endif
                @endforeach
            </div>

            @if ($shown->isEmpty())
                <x-empty-state title="No applicants yet" icon="📨">
                    Share the opportunity link with your section or society. Opportunities with a clear description and skills attract more applicants.
                    <x-slot:actions><a href="{{ route('opportunities.show', $o) }}" class="btn-secondary">View public page</a></x-slot:actions>
                </x-empty-state>
            @else
                <div class="space-y-4">
                    @foreach ($shown as $a)
                        @php($p = $a->user->profile)
                        <article class="card p-5" x-data="{ decide: null, complete: false }">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start">
                                <x-avatar :user="$a->user" size="h-12 w-12" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <a href="{{ route('volunteers.show', $p) }}" class="text-base font-semibold text-ink hover:text-brand">{{ $a->user->name }}</a>
                                        <x-status-badge :status="$a->status" type="application" />
                                        <span class="ml-auto"><x-match-badge :match="$a->match" /></span>
                                    </div>
                                    <p class="text-sm text-warm-gray">
                                        {{ collect([$p?->headline, $p?->gradeLabel(), $p?->section ? $p->section.' Section' : null, $p?->country])->filter()->implode(' · ') }}
                                    </p>
                                    @if ($a->motivation)
                                        <p class="mt-3 whitespace-pre-line rounded-lg bg-warm-white p-3 text-sm text-warmer-gray">{{ $a->motivation }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach ($a->user->skills->take(10) as $skill)
                                            @if (in_array($skill->name, $a->match['matched'] ?? [], true))
                                                <span class="chip bg-accent-green-light/60 text-accent-green-dark">✓ {{ $skill->name }}</span>
                                            @else
                                                <span class="chip-muted">{{ $skill->name }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                    <p class="mt-3 text-xs text-warm-gray">
                                        Applied {{ $a->created_at->diffForHumans() }}
                                        @if ($a->isConfirmed()) · <span class="font-semibold text-ink">{{ $fmt($a->approved_hours) }} h</span> approved @endif
                                        @if ($a->owner_rating) · Your rating {{ str_repeat('★', $a->owner_rating) }} @endif
                                        @if ($a->volunteer_rating) · Their rating {{ str_repeat('★', $a->volunteer_rating) }} @endif
                                    </p>
                                    @if ($a->endorsement)
                                        <blockquote class="mt-3 border-l-4 border-brand pl-3 text-sm italic text-warmer-gray">“{{ $a->endorsement->message }}”</blockquote>
                                    @endif
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2 md:flex-col">
                                    @if (in_array($a->status, [Application::PENDING, Application::REJECTED], true))
                                        <button type="button" class="btn-primary btn-sm" @click="decide = decide === 'accepted' ? null : 'accepted'">Accept</button>
                                    @endif
                                    @if (in_array($a->status, [Application::PENDING, Application::ACCEPTED], true))
                                        <button type="button" class="btn-secondary btn-sm" @click="decide = decide === 'rejected' ? null : 'rejected'">Decline</button>
                                    @endif
                                    @if ($a->isConfirmed())
                                        <button type="button" class="btn-primary btn-sm" @click="complete = !complete">{{ $a->status === Application::COMPLETED ? 'Edit endorsement' : 'Mark completed' }}</button>
                                    @endif
                                </div>
                            </div>

                            {{-- Accept / decline with optional note --}}
                            <form x-show="decide" x-cloak method="POST" action="{{ route('applications.decide', $a) }}" class="mt-4 rounded-lg border border-light-gray p-4">
                                @csrf
                                <input type="hidden" name="decision" :value="decide">
                                <label class="label" for="note-{{ $a->id }}">Message to {{ $a->user->firstName() }} <span class="font-normal text-warm-gray">(optional, included in the email)</span></label>
                                <textarea id="note-{{ $a->id }}" name="owner_note" rows="2" class="input" maxlength="2000" :placeholder="decide === 'accepted' ? 'Welcome aboard! Next steps…' : 'Thank you for applying…'"></textarea>
                                <div class="mt-3 flex gap-2">
                                    <button class="btn-primary btn-sm" x-text="decide === 'accepted' ? 'Confirm acceptance' : 'Confirm decline'"></button>
                                    <button type="button" class="btn-ghost btn-sm" @click="decide = null">Cancel</button>
                                </div>
                            </form>

                            {{-- Complete, rate & endorse --}}
                            @if ($a->isConfirmed())
                                <form x-show="complete" x-cloak method="POST" action="{{ route('applications.complete', $a) }}" class="mt-4 space-y-3 rounded-lg border border-light-gray p-4">
                                    @csrf
                                    <fieldset>
                                        <legend class="label">How did {{ $a->user->firstName() }} do? <span class="font-normal text-warm-gray">(private rating)</span></legend>
                                        <div class="flex gap-2">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="owner_rating" value="{{ $i }}" class="peer sr-only" @checked($a->owner_rating === $i)>
                                                    <span class="grid h-9 w-9 place-items-center rounded-full border border-light-gray text-sm peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ $i }}</span>
                                                </label>
                                            @endfor
                                        </div>
                                    </fieldset>
                                    <div>
                                        <label class="label" for="endorse-{{ $a->id }}">Public endorsement <span class="font-normal text-warm-gray">(optional — appears on their profile and CV)</span></label>
                                        <textarea id="endorse-{{ $a->id }}" name="endorsement" rows="3" maxlength="2000" class="input" placeholder="What did they contribute? What stood out?">{{ $a->endorsement?->message }}</textarea>
                                    </div>
                                    @if ($o->skills->isNotEmpty())
                                        <fieldset>
                                            <legend class="label">Endorse skills</legend>
                                            <div class="flex flex-wrap gap-2">
                                                @php($endorsedIds = $a->endorsement?->skills()->pluck('skills.id')->all() ?? [])
                                                @foreach ($o->skills as $skill)
                                                    <label class="cursor-pointer">
                                                        <input type="checkbox" name="endorsed_skills[]" value="{{ $skill->id }}" class="peer sr-only" @checked(in_array($skill->id, $endorsedIds, true))>
                                                        <span class="chip-muted peer-checked:border-brand peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ $skill->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @endif
                                    <div class="flex gap-2">
                                        <button class="btn-primary btn-sm">{{ $a->status === Application::COMPLETED ? 'Save' : 'Mark as completed' }}</button>
                                        <button type="button" class="btn-ghost btn-sm" @click="complete = false">Cancel</button>
                                    </div>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

        @elseif ($tab === 'hours')
            @if ($pendingHours->isEmpty())
                <x-empty-state title="No hours waiting for approval" icon="⏱">Volunteers log hours from their My Opportunities page. You'll see them here to approve.</x-empty-state>
            @else
                <div class="card overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="table-head"><tr><th class="px-5 py-3">Volunteer</th><th class="px-5 py-3">Date</th><th class="px-5 py-3">Hours</th><th class="px-5 py-3">Description</th><th class="px-5 py-3 text-right">Decision</th></tr></thead>
                        <tbody class="divide-y divide-light-gray">
                            @foreach ($pendingHours as $log)
                                <tr>
                                    <td class="px-5 py-3"><span class="flex items-center gap-2"><x-avatar :user="$log->user" size="h-8 w-8" text="text-xs" /> {{ $log->user->name }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-warm-gray">{{ $log->worked_on->format('j M Y') }}</td>
                                    <td class="px-5 py-3 font-semibold tabular-nums">{{ $fmt($log->hours) }}</td>
                                    <td class="px-5 py-3 text-warm-gray">{{ $log->description ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('hours.review', $log) }}" class="inline">@csrf<input type="hidden" name="decision" value="approved"><button class="btn-primary btn-sm">Approve</button></form>
                                        <form method="POST" action="{{ route('hours.review', $log) }}" class="inline">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn-ghost btn-sm text-red-600">Reject</button></form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        @elseif ($tab === 'team')
            <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <section class="card p-6">
                    <h2 class="text-lg font-semibold text-ink">People who manage this opportunity</h2>
                    <p class="mt-1 text-sm text-warm-gray">Every owner can edit the listing, review applicants, approve hours and see impact.</p>
                    <x-input-error :messages="$errors->get('owners')" class="mt-2" />
                    <ul class="mt-5 divide-y divide-light-gray">
                        @foreach ($o->owners as $person)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <span class="flex min-w-0 items-center gap-3">
                                    <x-avatar :user="$person" size="h-10 w-10" />
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-ink">{{ $person->name }} @if ($person->id === auth()->id())<span class="text-xs font-normal text-warm-gray">(you)</span>@endif</span>
                                        <span class="block text-xs text-warm-gray">{{ $person->pivot->role === 'owner' ? 'Owner' : 'Co-owner' }} · added {{ $person->pivot->created_at?->format('j M Y') }}</span>
                                    </span>
                                </span>
                                @if ($o->owners->count() > 1)
                                    <form method="POST" action="{{ route('opportunities.owners.destroy', [$o, $person]) }}" onsubmit="return confirm('Remove {{ addslashes($person->name) }} as an owner?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-red-600 hover:underline">{{ $person->id === auth()->id() ? 'Leave' : 'Remove' }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
                <section class="card p-6" x-data="userPicker(@js(route('lookup.users')), [], 1, @js($o->owners->pluck('id')))">
                    <h2 class="text-base font-semibold text-ink">Add a co-owner</h2>
                    <p class="mt-1 text-sm text-warm-gray">Up to {{ config('volunteering.max_owners') }} owners in total. They'll get an email.</p>
                    <form method="POST" action="{{ route('opportunities.owners.store', $o) }}" class="mt-4">
                        @csrf
                        <template x-if="people.length"><input type="hidden" name="user_id" :value="people[0].id"></template>
                        <div x-show="people.length" x-cloak class="mb-3 flex items-center justify-between rounded-lg border border-brand/40 bg-brand-50 px-3 py-2 text-sm">
                            <span x-text="people[0]?.name"></span>
                            <button type="button" @click="people = []" class="text-warm-gray hover:text-red-600" aria-label="Clear">&times;</button>
                        </div>
                        <div class="relative" x-show="!people.length" @click.outside="open = false">
                            <label for="owner-search" class="sr-only">Search people</label>
                            <input id="owner-search" type="search" x-model="query" @input="search()" class="input" placeholder="Name or exact email…" autocomplete="off">
                            <ul x-show="open && results.length" x-cloak class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-light-gray bg-white shadow-dropdown">
                                <template x-for="r in results" :key="r.id">
                                    <li><button type="button" @click="add(r)" class="flex w-full flex-col px-3 py-2 text-left hover:bg-brand-50"><span class="text-sm font-semibold text-ink" x-text="r.name"></span><span class="text-xs text-warm-gray" x-text="r.meta"></span></button></li>
                                </template>
                            </ul>
                        </div>
                        <x-input-error :messages="$errors->get('user_id')" class="mt-1" />
                        <button class="btn-primary mt-4 w-full" :disabled="!people.length">Add co-owner</button>
                    </form>
                </section>
            </div>

        @else
            {{-- Impact --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat :value="$impact['applicants']" label="Applicants" :hint="$impact['pending'].' awaiting a decision'" />
                <x-stat :value="($impact['accepted'] + $impact['completed']).' / '.$o->volunteers_needed" label="Volunteers confirmed" :hint="$impact['fill_rate'] !== null ? $impact['fill_rate'].'% of positions filled' : null" />
                <x-stat :value="$fmt($impact['hours'])" label="Volunteer hours approved" :hint="$impact['pending_hours'] ? $fmt($impact['pending_hours']).' h awaiting approval' : null" />
                <x-stat :value="$impact['completed']" label="Contributions completed" :hint="$impact['avg_rating'] ? 'Volunteers rate it '.$impact['avg_rating'].' / 5' : null" />
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat :value="$impact['days_to_first_applicant'] !== null ? $impact['days_to_first_applicant'].' d' : '—'" label="Time to first applicant" />
                <x-stat :value="$impact['days_to_fill'] !== null ? $impact['days_to_fill'].' d' : '—'" label="Time to fill all positions" />
                <x-stat :value="count($impact['regions'])" label="IEEE regions reached" />
                <x-stat :value="$impact['applicants'] ? round(($impact['accepted'] + $impact['completed']) / max(1, $impact['applicants']) * 100).'%' : '—'" label="Acceptance rate" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                @if ($impact['hours_series'])
                    <x-chart title="Approved volunteer hours per month" :config="['type' => 'bar', 'labels' => $impact['hours_series']['labels'], 'series' => [['name' => 'Hours', 'data' => $impact['hours_series']['data'], 'slot' => 1]], 'format' => 'hours', 'categoryLabel' => 'Month']" />
                @else
                    <x-empty-state title="No hours logged yet" icon="⏱">Once volunteers log hours and you approve them, monthly totals appear here.</x-empty-state>
                @endif
                @if ($impact['regions'])
                    <x-chart title="Confirmed volunteers by IEEE region" :config="['type' => 'hbar', 'labels' => array_keys($impact['regions']), 'series' => [['name' => 'Volunteers', 'data' => array_values($impact['regions']), 'slot' => 0]], 'categoryLabel' => 'Region']" />
                @endif
            </div>

            <section class="card mt-6 p-6">
                <h2 class="text-base font-semibold text-ink">Recent activity</h2>
                <ul class="mt-3 divide-y divide-light-gray text-sm">
                    @forelse ($activity as $item)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="text-warmer-gray"><span class="font-semibold text-ink">{{ $item->user?->name ?? 'System' }}</span> {{ $item->label() }}
                                @if (! empty($item->properties['volunteer']))<span class="text-warm-gray">({{ $item->properties['volunteer'] }})</span>@endif
                                @if (! empty($item->properties['name']))<span class="text-warm-gray">({{ $item->properties['name'] }})</span>@endif
                            </span>
                            <time class="shrink-0 text-xs text-warm-gray" datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->diffForHumans() }}</time>
                        </li>
                    @empty
                        <li class="py-2 text-warm-gray">Nothing yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
@endsection
