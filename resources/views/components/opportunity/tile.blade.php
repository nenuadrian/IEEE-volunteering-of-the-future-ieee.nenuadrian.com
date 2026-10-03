@props(['opportunity', 'match' => null])

{{-- Compact grid card (home page, recommendations, similar opportunities). --}}
@php($color = $opportunity->category?->colorOrDefault() ?? '#e87722')
<article class="card group relative flex h-full flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-dropdown">
    <div class="h-1.5 w-full" style="background-color: {{ $color }}" aria-hidden="true"></div>
    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-start justify-between gap-3">
            <span class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $color }}">{{ $opportunity->category?->name ?? 'Opportunity' }}</span>
            <span class="relative z-10"><x-match-badge :match="$match" /></span>
        </div>
        <h3 class="mt-2 line-clamp-2 text-base font-semibold leading-snug text-ink">
            <a href="{{ route('opportunities.show', $opportunity) }}" class="after:absolute after:inset-0 group-hover:text-brand">{{ $opportunity->title }}</a>
        </h3>
        <p class="mt-2 line-clamp-3 flex-1 text-sm text-warm-gray">{{ $opportunity->excerpt(160) }}</p>
        <div class="mt-4 flex flex-wrap gap-1.5 text-xs">
            @if ($opportunity->project_size)<span class="chip">⏱ {{ $opportunity->project_size }}</span>@endif
            <span class="chip-muted">📍 {{ \Illuminate\Support\Str::limit($opportunity->locationLabel(), 28) }}</span>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-light-gray pt-3 text-xs text-warm-gray">
            <span>{{ $opportunity->start_date ? 'Starts '.$opportunity->start_date->format('j M Y') : 'Flexible start' }}</span>
            <span>{{ $opportunity->volunteers_needed }} needed</span>
        </div>
    </div>
</article>
