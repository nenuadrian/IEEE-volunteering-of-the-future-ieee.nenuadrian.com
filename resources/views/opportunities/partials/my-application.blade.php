@php
    use App\Models\Application;
    use App\Models\HourLog;
    $logs = $application->hourLogs;
    $approved = $logs->where('status', HourLog::APPROVED)->sum('hours');
    $pending = $logs->where('status', HourLog::PENDING)->sum('hours');
    $compact = $compact ?? false;
@endphp

<section class="card border-brand/30 p-6" id="my-application">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink">{{ $compact ? $application->opportunity->title : 'Your application' }}</h2>
            <p class="mt-1 text-sm text-warm-gray">
                Applied {{ $application->created_at->format('j M Y') }}
                @if ($application->decided_at) · {{ $application->status === Application::REJECTED ? 'Decided' : 'Accepted' }} {{ $application->decided_at->format('j M Y') }}@endif
                @if ($application->completed_at) · Completed {{ $application->completed_at->format('j M Y') }}@endif
            </p>
        </div>
        <x-status-badge :status="$application->status" type="application" />
    </div>

    @if ($application->status === Application::PENDING)
        <p class="mt-4 text-sm text-warmer-gray">The organisers have your application and will get back to you. You can withdraw it at any time.</p>
    @elseif ($application->status === Application::REJECTED)
        <p class="mt-4 text-sm text-warmer-gray">The organisers were not able to take you on this time.</p>
    @endif

    @if ($application->owner_note && $application->status !== Application::PENDING)
        <blockquote class="mt-4 border-l-4 border-brand pl-4 text-sm italic text-warmer-gray">{{ $application->owner_note }}</blockquote>
    @endif

    @if ($application->isConfirmed())
        <div class="mt-5 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-lg bg-warm-white p-3"><p class="text-xl font-bold text-brand">{{ rtrim(rtrim(number_format($approved, 1), '0'), '.') }}</p><p class="text-xs text-warm-gray">hours approved</p></div>
            <div class="rounded-lg bg-warm-white p-3"><p class="text-xl font-bold text-ink">{{ rtrim(rtrim(number_format($pending, 1), '0'), '.') }}</p><p class="text-xs text-warm-gray">awaiting approval</p></div>
            <div class="rounded-lg bg-warm-white p-3"><p class="text-xl font-bold text-ink">{{ $logs->count() }}</p><p class="text-xs text-warm-gray">entries</p></div>
        </div>

        <form method="POST" action="{{ route('hours.store', $application) }}" class="mt-5 grid gap-3 rounded-lg border border-light-gray p-4 sm:grid-cols-[9rem_6rem_1fr_auto] sm:items-end">
            @csrf
            <div>
                <label class="label" for="worked_on_{{ $application->id }}">Date</label>
                <input id="worked_on_{{ $application->id }}" type="date" name="worked_on" value="{{ old('worked_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input">
            </div>
            <div>
                <label class="label" for="hours_{{ $application->id }}">Hours</label>
                <input id="hours_{{ $application->id }}" type="number" name="hours" step="0.25" min="0.25" max="24" value="{{ old('hours') }}" required class="input">
            </div>
            <div>
                <label class="label" for="desc_{{ $application->id }}">What did you work on? <span class="font-normal text-warm-gray">(optional)</span></label>
                <input id="desc_{{ $application->id }}" name="description" maxlength="500" value="{{ old('description') }}" class="input">
            </div>
            <button class="btn-primary">Log hours</button>
            <x-input-error :messages="$errors->get('hours')" class="sm:col-span-4" />
            <x-input-error :messages="$errors->get('worked_on')" class="sm:col-span-4" />
        </form>

        @if ($logs->isNotEmpty())
            <details class="mt-4 text-sm" @if(! $compact) open @endif>
                <summary class="cursor-pointer font-semibold text-warmer-gray">Hour log ({{ $logs->count() }})</summary>
                <ul class="mt-2 divide-y divide-light-gray">
                    @foreach ($logs->take(12) as $log)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0">
                                <span class="font-medium text-ink">{{ $log->worked_on->format('j M Y') }}</span>
                                <span class="text-warm-gray">· {{ rtrim(rtrim(number_format($log->hours, 2), '0'), '.') }} h</span>
                                @if ($log->description)<span class="block truncate text-xs text-warm-gray">{{ $log->description }}</span>@endif
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <x-status-badge :status="$log->status" type="generic" />
                                @if ($log->status === HourLog::PENDING)
                                    <form method="POST" action="{{ route('hours.destroy', $log) }}">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif

    @if ($application->endorsement)
        <figure class="mt-5 rounded-lg bg-brand-50 p-4">
            <blockquote class="text-sm italic text-warmer-gray">“{{ $application->endorsement->message }}”</blockquote>
            <figcaption class="mt-2 text-xs text-warm-gray">— {{ $application->endorsement->endorser?->name }}, endorsement</figcaption>
        </figure>
    @endif

    @if ($application->isConfirmed())
        <div class="mt-5 border-t border-light-gray pt-4" x-data="{ open: {{ $application->volunteer_rating ? 'false' : 'false' }} }">
            @if ($application->volunteer_rating)
                <p class="text-sm text-warm-gray">You rated this experience <span class="font-semibold text-brand">{{ str_repeat('★', $application->volunteer_rating) }}{{ str_repeat('☆', 5 - $application->volunteer_rating) }}</span>
                    <button type="button" @click="open = !open" class="ml-2 text-xs text-brand underline">Change</button></p>
            @else
                <button type="button" @click="open = !open" class="text-sm font-semibold text-brand hover:underline">Rate your experience</button>
            @endif
            <form x-show="open" x-cloak method="POST" action="{{ route('applications.feedback', $application) }}" class="mt-3 space-y-3">
                @csrf
                <fieldset>
                    <legend class="label">How was volunteering on this opportunity?</legend>
                    <div class="flex gap-2">
                        @for ($i = 1; $i <= 5; $i++)
                            <label class="cursor-pointer">
                                <input type="radio" name="volunteer_rating" value="{{ $i }}" class="peer sr-only" @checked($application->volunteer_rating === $i) required>
                                <span class="grid h-9 w-9 place-items-center rounded-full border border-light-gray text-sm peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ $i }}</span>
                            </label>
                        @endfor
                    </div>
                </fieldset>
                <textarea name="volunteer_feedback" rows="2" maxlength="2000" class="input" placeholder="Anything the organisers should know? (optional)">{{ $application->volunteer_feedback }}</textarea>
                <button class="btn-secondary btn-sm">Save feedback</button>
            </form>
        </div>
    @endif

    @if (in_array($application->status, [Application::PENDING, Application::ACCEPTED], true))
        <form method="POST" action="{{ route('applications.withdraw', $application) }}" class="mt-4" onsubmit="return confirm('Withdraw your application?')">
            @csrf
            <button class="text-xs text-warm-gray underline hover:text-red-600">Withdraw application</button>
        </form>
    @endif
</section>
