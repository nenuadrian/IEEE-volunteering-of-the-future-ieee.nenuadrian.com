@props(['config', 'height' => 'h-64', 'title' => null, 'subtitle' => null])

{{-- Chart.js chart + accessible table twin. See resources/js/charts.js for the config shape. --}}
<figure {{ $attributes->merge(['class' => 'card p-5']) }} data-chart='@json($config)'>
    @if ($title)
        <figcaption class="mb-3">
            <span class="block text-base font-semibold text-ink">{{ $title }}</span>
            @if ($subtitle)<span class="block text-xs text-warm-gray">{{ $subtitle }}</span>@endif
        </figcaption>
    @endif
    <div class="relative {{ $height }}">
        <canvas role="img" aria-label="{{ $title ?? 'Chart' }}"></canvas>
    </div>
    <details class="mt-3 text-xs">
        <summary class="cursor-pointer select-none text-warm-gray hover:text-brand">View as table</summary>
        <div data-chart-table class="max-h-72 overflow-auto"></div>
    </details>
</figure>
