@props(['opportunity', 'size' => 'h-24 w-24'])

@if ($opportunity->thumbnail())
    <img src="{{ $opportunity->thumbnail() }}" alt="" loading="lazy" {{ $attributes->merge(['class' => "$size shrink-0 rounded-lg object-cover bg-warm-white"]) }}>
@else
    @php($color = $opportunity->category?->colorOrDefault() ?? '#e87722')
    <span {{ $attributes->merge(['class' => "$size grid shrink-0 place-items-center rounded-lg text-white"]) }}
          style="background: linear-gradient(135deg, {{ $color }}, {{ $color }}cc)" aria-hidden="true">
        <svg class="h-1/2 w-1/2 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.09 9.09 0 003.74-.48 3 3 0 00-4.68-2.72m.94 3.2v.03c0 .22-.01.45-.04.67A11.95 11.95 0 0112 21c-2.17 0-4.2-.58-5.96-1.58a6.06 6.06 0 01-.04-.7m12 0a5.97 5.97 0 00-.94-3.2m0 0A5.99 5.99 0 0012 12.75a5.99 5.99 0 00-5.06 2.77m0 0a3 3 0 00-4.68 2.72 8.99 8.99 0 003.74.48m.94-3.2a5.97 5.97 0 00-.94 3.2M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
    </span>
@endif
