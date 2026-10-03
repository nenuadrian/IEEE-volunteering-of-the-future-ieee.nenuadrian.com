@props(['opportunity', 'match' => null, 'saved' => false])

{{-- Search-result card, modelled on volunteer.ieee.org with clearer hierarchy. --}}
<article class="card relative flex gap-4 p-4 transition hover:border-brand/40 hover:shadow-dropdown sm:p-5">
    <x-opportunity.thumb :opportunity="$opportunity" class="hidden sm:grid" />

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <x-status-badge :status="$opportunity->status" />
            @if ($opportunity->category)
                <span class="text-sm text-warm-gray">{{ $opportunity->category->name }}</span>
            @endif
            @if ($opportunity->is_featured)
                <span class="chip">★ Featured</span>
            @endif
            <span class="ml-auto">
                <x-match-badge :match="$match" />
            </span>
        </div>

        <h3 class="mt-1.5 text-lg font-semibold leading-snug text-ink">
            <a href="{{ route('opportunities.show', $opportunity) }}" class="after:absolute after:inset-0 hover:text-brand">{{ $opportunity->title }}</a>
        </h3>
        <p class="mt-1 line-clamp-2 text-sm text-warm-gray">{{ $opportunity->excerpt(220) }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            @if ($opportunity->project_size)
                <span class="chip">⏱ {{ $opportunity->project_size }}</span>
            @endif
            <span class="chip">📍 {{ $opportunity->locationLabel() }}</span>
            @if ($opportunity->hoursLabel())
                <span class="chip-muted">{{ $opportunity->hoursLabel() }}</span>
            @endif
            @if ($opportunity->society)
                <span class="chip-muted">{{ \Illuminate\Support\Str::limit($opportunity->society, 40) }}</span>
            @endif
            @foreach ($opportunity->skills->take(3) as $skill)
                <span class="chip-blue">{{ $skill->name }}</span>
            @endforeach
            @if ($opportunity->skills->count() > 3)
                <span class="text-warm-gray">+{{ $opportunity->skills->count() - 3 }} more</span>
            @endif
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-warm-gray">
            @if ($opportunity->start_date)
                <span><span class="font-semibold text-warmer-gray">Starts</span> {{ $opportunity->start_date->format('j M Y') }}</span>
            @endif
            @if ($opportunity->end_date)
                <span><span class="font-semibold text-warmer-gray">Ends</span> {{ $opportunity->end_date->format('j M Y') }}</span>
            @endif
            <span><span class="font-semibold text-warmer-gray">{{ $opportunity->volunteers_needed }}</span> {{ \Illuminate\Support\Str::plural('volunteer', $opportunity->volunteers_needed) }} needed</span>
        </div>
    </div>

    @auth
        <form method="POST" action="{{ route('opportunities.save', $opportunity) }}" class="absolute right-3 top-3 z-10 hidden sm:block">
            @csrf
            <button class="grid h-8 w-8 place-items-center rounded-full text-brand hover:bg-brand-50" aria-label="{{ $saved ? 'Remove from saved' : 'Save opportunity' }}" title="{{ $saved ? 'Saved' : 'Save' }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $saved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </button>
        </form>
    @endauth
</article>
