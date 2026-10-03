@extends('layouts.public')
@section('title', 'Dashboard')
@section('robots_noindex', '1')

@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $done = collect($checklist)->filter(fn ($c) => $c[1])->count();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

@section('content')
    <section class="border-b border-light-gray bg-white">
        <div class="container-x flex flex-col gap-6 py-8 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <x-avatar :user="$user" size="h-16 w-16" text="text-xl" />
                <div>
                    <h1 class="text-2xl font-semibold text-ink">{{ $greeting }}, {{ $user->firstName() }}</h1>
                    <p class="text-sm text-warm-gray">
                        @if ($owner['empty'] ?? true)
                            Here's the impact you've made as an IEEE volunteer.
                        @else
                            Your impact as a volunteer — and the impact of the volunteers on your opportunities.
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('volunteers.show', $user->profile) }}" class="btn-secondary">View my profile &amp; CV</a>
                <a href="{{ route('opportunities.index', ['sort' => 'match']) }}" class="btn-primary">Find opportunities</a>
            </div>
        </div>
    </section>

    <div class="container-x space-y-10 py-8">
        {{-- Needs action --}}
        @if (! ($owner['empty'] ?? true) && ($owner['pending_decisions'] || $owner['pending_hours']))
            <a href="{{ route('my.opportunities', ['tab' => 'managing']) }}" class="flex items-center justify-between gap-4 rounded-xl border border-brand/40 bg-brand-50 px-5 py-4 text-sm text-brand-800 transition hover:bg-brand-100">
                <span><strong>Items need your action:</strong>
                    {{ $owner['pending_decisions'] }} {{ \Illuminate\Support\Str::plural('applicant', $owner['pending_decisions']) }} to review ·
                    {{ $owner['pending_hours'] }} hour {{ \Illuminate\Support\Str::plural('entry', $owner['pending_hours']) }} to approve</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        @endif

        {{-- Volunteer impact --}}
        <section aria-labelledby="impact-heading">
            <h2 id="impact-heading" class="section-title">My volunteer impact</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat :value="$fmt($volunteer['hours_approved'])" label="Hours contributed" :hint="$volunteer['hours_pending'] ? $fmt($volunteer['hours_pending']).' h awaiting approval' : 'Approved by organisers'" />
                <x-stat :value="$volunteer['completed']" label="Opportunities completed" :hint="$volunteer['active'].' in progress'" :href="route('my.opportunities', ['tab' => 'volunteering'])" />
                <x-stat :value="$volunteer['endorsements']" label="Endorsements received" :hint="$volunteer['avg_rating'] ? 'Average organiser rating '.$volunteer['avg_rating'].' / 5' : null" :href="route('volunteers.show', $user->profile).'#endorsements'" />
                <x-stat :value="$volunteer['pending']" label="Applications pending" :hint="$volunteer['acceptance_rate'] !== null ? $volunteer['acceptance_rate'].'% of your applications accepted' : 'Apply to get started'" :href="route('my.opportunities', ['tab' => 'volunteering'])" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <x-chart title="Approved hours per month" subtitle="Last 12 months"
                         :config="['type' => 'bar', 'labels' => $volunteer['hours_series']['labels'], 'series' => [['name' => 'Hours', 'data' => $volunteer['hours_series']['data'], 'slot' => 1]], 'format' => 'hours', 'categoryLabel' => 'Month']" />
                <div class="card p-5">
                    <h3 class="text-base font-semibold text-ink">Skills put to work</h3>
                    <p class="text-xs text-warm-gray">Skills required by opportunities you took part in</p>
                    <ul class="mt-4 space-y-2.5">
                        @php($maxSkill = max(1, $volunteer['skills_used']->max('total') ?? 1))
                        @forelse ($volunteer['skills_used'] as $skill)
                            <li>
                                <div class="flex justify-between text-sm"><span class="text-warmer-gray">{{ $skill->name }}</span><span class="font-semibold tabular-nums text-ink">{{ $skill->total }}</span></div>
                                <div class="mt-1 h-1.5 rounded-full bg-brand-50"><div class="h-1.5 rounded-full bg-brand" style="width: {{ round($skill->total / $maxSkill * 100) }}%"></div></div>
                            </li>
                        @empty
                            <li class="text-sm text-warm-gray">Take part in an opportunity to see the skills you use.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </section>

        <div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-10">
                {{-- In progress --}}
                @if ($active->isNotEmpty())
                    <section>
                        <h2 class="section-title">In progress</h2>
                        <div class="mt-6 space-y-3">
                            @foreach ($active as $application)
                                <a href="{{ route('my.opportunities', ['tab' => 'volunteering']) }}" class="card flex items-center gap-4 p-4 transition hover:border-brand/40">
                                    <x-opportunity.thumb :opportunity="$application->opportunity" size="h-14 w-14" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs text-warm-gray">{{ $application->opportunity->category?->name }}</span>
                                        <span class="block truncate font-semibold text-ink">{{ $application->opportunity->title }}</span>
                                        <span class="block text-xs text-warm-gray">
                                            {{ $application->opportunity->start_date?->format('j M Y') }} – {{ $application->opportunity->end_date?->format('j M Y') ?? 'ongoing' }}
                                        </span>
                                    </span>
                                    <span class="btn-secondary btn-sm shrink-0">Log hours</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Recommended --}}
                <section>
                    <div class="flex items-end justify-between gap-4">
                        <h2 class="section-title">Recommended for you</h2>
                        <a href="{{ route('opportunities.index', ['sort' => 'match']) }}" class="text-sm font-semibold text-brand hover:underline">See all &rarr;</a>
                    </div>
                    <p class="mt-3 text-sm text-warm-gray">Ranked by your match score — hover the score to see why each one fits.</p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        @forelse ($recommended as $opportunity)
                            <x-opportunity.tile :opportunity="$opportunity" :match="$opportunity->match" />
                        @empty
                            <x-empty-state class="sm:col-span-2" title="You've applied to everything open — impressive!" icon="🎉" />
                        @endforelse
                    </div>
                </section>

                {{-- Organiser impact --}}
                <section id="organiser">
                    <h2 class="section-title">Impact of my volunteers</h2>
                    @if ($owner['empty'] ?? true)
                        <x-empty-state class="mt-6" title="You haven't created an opportunity yet" icon="📣">
                            When you run an opportunity, you'll see how many volunteers it engaged, the hours they contributed and where they come from.
                            <x-slot:actions><a href="{{ route('opportunities.create') }}" class="btn-primary">Create an opportunity</a></x-slot:actions>
                        </x-empty-state>
                    @else
                        <p class="mt-3 text-sm text-warm-gray">Across the {{ $owner['opportunities'] }} {{ \Illuminate\Support\Str::plural('opportunity', $owner['opportunities']) }} you own or co-own.</p>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <x-stat :value="$owner['volunteers']" label="Volunteers engaged" :hint="$owner['countries'].' '.\Illuminate\Support\Str::plural('country', $owner['countries'])" />
                            <x-stat :value="$fmt($owner['hours'])" label="Hours contributed" hint="Approved hours on your opportunities" />
                            <x-stat :value="$owner['applicants']" label="Applications received" :hint="$owner['acceptance_rate'] !== null ? $owner['acceptance_rate'].'% accepted' : null" />
                            <x-stat :value="$owner['completion_rate'] !== null ? $owner['completion_rate'].'%' : '—'" label="Completion rate" :hint="$owner['avg_volunteer_rating'] ? 'Volunteers rate you '.$owner['avg_volunteer_rating'].' / 5' : null" />
                        </div>
                        <div class="mt-5 grid gap-5 xl:grid-cols-2">
                            <x-chart title="Applications received vs. volunteers accepted" subtitle="Per month, last 12 months"
                                     :config="['type' => 'line', 'labels' => $owner['series']['labels'], 'series' => [['name' => 'Applications', 'data' => $owner['series']['applications'], 'slot' => 0], ['name' => 'Accepted', 'data' => $owner['series']['confirmed'], 'slot' => 1]], 'categoryLabel' => 'Month']" />
                            <x-chart title="Hours contributed by your volunteers" subtitle="Per month, last 12 months"
                                     :config="['type' => 'bar', 'labels' => $owner['series']['labels'], 'series' => [['name' => 'Hours', 'data' => $owner['series']['hours'], 'slot' => 1]], 'format' => 'hours', 'categoryLabel' => 'Month']" />
                        </div>
                        <div class="mt-5 grid gap-5 xl:grid-cols-2">
                            <div class="card p-5">
                                <h3 class="text-base font-semibold text-ink">Top volunteers by hours</h3>
                                <ol class="mt-4 space-y-3">
                                    @forelse ($owner['top_volunteers'] as $row)
                                        <li class="flex items-center gap-3">
                                            <x-avatar :user="$row->user" size="h-9 w-9" text="text-xs" />
                                            <a href="{{ route('volunteers.show', $row->user->profile) }}" class="min-w-0 flex-1 truncate text-sm font-medium text-ink hover:text-brand">{{ $row->user->name }}</a>
                                            <span class="text-sm font-semibold tabular-nums text-warmer-gray">{{ $fmt($row->hours) }} h</span>
                                        </li>
                                    @empty
                                        <li class="text-sm text-warm-gray">No approved hours yet.</li>
                                    @endforelse
                                </ol>
                            </div>
                            <div class="card p-5">
                                <h3 class="text-base font-semibold text-ink">Where your volunteers come from</h3>
                                <ul class="mt-4 space-y-2.5">
                                    @php($maxRegion = max(1, max($owner['regions'] ?: [1])))
                                    @forelse ($owner['regions'] as $label => $count)
                                        <li>
                                            <div class="flex justify-between gap-3 text-sm"><span class="truncate text-warmer-gray">{{ $label }}</span><span class="font-semibold tabular-nums text-ink">{{ $count }}</span></div>
                                            <div class="mt-1 h-1.5 rounded-full bg-accent-blue/10"><div class="h-1.5 rounded-full bg-accent-blue" style="width: {{ round($count / $maxRegion * 100) }}%"></div></div>
                                        </li>
                                    @empty
                                        <li class="text-sm text-warm-gray">No confirmed volunteers yet.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                        <div class="card mt-5 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="table-head"><tr><th class="px-5 py-3">Opportunity</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Applicants</th><th class="px-5 py-3 text-right">Confirmed</th><th class="px-5 py-3 text-right">Hours</th></tr></thead>
                                <tbody class="divide-y divide-light-gray">
                                    @foreach ($owner['by_opportunity']->take(8) as $o)
                                        <tr>
                                            <td class="px-5 py-3"><a href="{{ route('opportunities.manage', $o) }}" class="font-medium text-ink hover:text-brand">{{ \Illuminate\Support\Str::limit($o->title, 60) }}</a></td>
                                            <td class="px-5 py-3"><x-status-badge :status="$o->status" /></td>
                                            <td class="px-5 py-3 text-right tabular-nums">{{ $o->applications_count }}</td>
                                            <td class="px-5 py-3 text-right tabular-nums">{{ $o->confirmed_count }} / {{ $o->volunteers_needed }}</td>
                                            <td class="px-5 py-3 text-right tabular-nums">{{ $fmt($o->approved_hours) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="space-y-6">
                {{-- Onboarding --}}
                <section class="card p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-ink">Getting started</h2>
                        <span class="text-xs font-semibold text-warm-gray">{{ $done }}/{{ count($checklist) }}</span>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-brand-50"><div class="h-2 rounded-full bg-brand" style="width: {{ round($done / count($checklist) * 100) }}%"></div></div>
                    <ul class="mt-4 space-y-2">
                        @foreach ($checklist as [$label, $complete, $href])
                            <li>
                                <a href="{{ $href }}" class="flex items-center gap-3 rounded-md px-1 py-1 text-sm hover:bg-warm-white">
                                    <span @class(['grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px] font-bold', 'bg-accent-green text-white' => $complete, 'border-2 border-light-gray' => ! $complete])>{{ $complete ? '✓' : '' }}</span>
                                    <span @class(['text-warm-gray line-through' => $complete, 'text-warmer-gray' => ! $complete])>{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Profile completeness --}}
                <section class="card p-5">
                    <h2 class="text-base font-semibold text-ink">Profile strength</h2>
                    <div class="mt-3 flex items-center gap-4">
                        <svg viewBox="0 0 36 36" class="h-16 w-16 -rotate-90" aria-hidden="true">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#fce4cf" stroke-width="3.2" />
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e87722" stroke-width="3.2" stroke-linecap="round" stroke-dasharray="{{ $completeness['percent'] }} 100" />
                        </svg>
                        <div>
                            <p class="text-2xl font-bold text-ink">{{ $completeness['percent'] }}%</p>
                            <p class="text-xs text-warm-gray">Complete profiles get better matches</p>
                        </div>
                    </div>
                    @if ($completeness['missing'])
                        <ul class="mt-3 space-y-1 text-sm text-warmer-gray">
                            @foreach (array_slice($completeness['missing'], 0, 3) as $item)
                                <li>• {{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('profile.volunteer.edit') }}" class="btn-secondary btn-sm mt-4">Complete my profile</a>
                    @endif
                </section>

                @if ($latestEndorsement)
                    <section class="card p-5">
                        <h2 class="text-base font-semibold text-ink">Latest endorsement</h2>
                        <blockquote class="mt-3 text-sm italic text-warmer-gray">“{{ \Illuminate\Support\Str::limit($latestEndorsement->message, 220) }}”</blockquote>
                        <p class="mt-2 text-xs text-warm-gray">— {{ $latestEndorsement->endorser?->name }} · {{ \Illuminate\Support\Str::limit($latestEndorsement->opportunity?->title, 40) }}</p>
                    </section>
                @endif

                {{-- Activity --}}
                <section class="card p-5">
                    <h2 class="text-base font-semibold text-ink">Recent activity</h2>
                    <ul class="mt-3 space-y-3">
                        @forelse ($feed as $item)
                            <li class="text-sm">
                                <p class="text-warmer-gray">
                                    <span class="font-semibold text-ink">{{ $item->user_id === $user->id ? 'You' : ($item->user?->name ?? 'Someone') }}</span>
                                    {{ $item->label() }}
                                    @if ($item->subjectTitle())
                                        @if ($item->subjectUrl())
                                            — <a href="{{ $item->subjectUrl() }}" class="text-brand hover:underline">{{ \Illuminate\Support\Str::limit($item->subjectTitle(), 50) }}</a>
                                        @else
                                            — {{ \Illuminate\Support\Str::limit($item->subjectTitle(), 50) }}
                                        @endif
                                    @endif
                                </p>
                                <time class="text-xs text-warm-gray" datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->diffForHumans() }}</time>
                            </li>
                        @empty
                            <li class="text-sm text-warm-gray">Your activity will appear here.</li>
                        @endforelse
                    </ul>
                </section>
            </aside>
        </div>
    </div>
@endsection
