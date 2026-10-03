@props(['match'])

{{-- Match percentage with an explanation popover (what drives the score). --}}
@if ($match)
    @php($tone = $match['percent'] >= 80 ? 'text-accent-green' : ($match['percent'] >= 50 ? 'text-brand-700' : 'text-warm-gray'))
    <span x-data="{ open: false }" class="relative inline-flex" @mouseenter="open = true" @mouseleave="open = false">
        <button type="button" @click.prevent.stop="open = !open" @click.outside="open = false"
                class="inline-flex items-center gap-1 text-sm font-semibold {{ $tone }}" :aria-expanded="open"
                aria-label="{{ $match['percent'] }}% match — show why">
            {{ $match['percent'] }}% Match
            <svg class="h-3.5 w-3.5 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        </button>
        <span x-show="open" x-transition x-cloak role="tooltip"
              class="absolute right-0 top-full z-30 mt-2 w-72 rounded-lg border border-light-gray bg-white p-3 text-left text-xs font-normal text-warmer-gray shadow-dropdown">
            <span class="mb-1.5 block font-semibold text-ink">Why this match?</span>
            @foreach ($match['reasons'] as $reason)
                <span class="block">• {{ $reason }}</span>
            @endforeach
            @if ($match['matched'])
                <span class="mt-2 block"><span class="font-semibold text-accent-green-dark">You have:</span> {{ implode(', ', $match['matched']) }}</span>
            @endif
            @if ($match['missing'])
                <span class="mt-1 block"><span class="font-semibold text-brand-700">You'd learn:</span> {{ implode(', ', $match['missing']) }}</span>
            @endif
            <span class="mt-2 block text-[11px] text-warm-gray">Skills 60% · membership grade 20% · location 20%</span>
        </span>
    </span>
@endif
