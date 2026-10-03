@props(['value', 'label', 'hint' => null, 'href' => null, 'delta' => null])

{{-- KPI tile. $delta: % change vs previous period (null hides it). --}}
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card block p-5 '.($href ? 'transition hover:border-brand/40 hover:shadow-dropdown' : '')]) }}>
    <p class="stat-value">{{ $value }}</p>
    <p class="stat-label">{{ $label }}</p>
    @if ($delta !== null)
        <p @class(['mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                   'bg-accent-green-light/60 text-accent-green-dark' => $delta > 0,
                   'bg-red-50 text-red-700' => $delta < 0,
                   'bg-warm-white text-warm-gray' => $delta == 0])>
            <span aria-hidden="true">{{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '■') }}</span>
            {{ $delta > 0 ? '+' : '' }}{{ $delta }}% <span class="font-normal">vs previous period</span>
        </p>
    @endif
    @if ($hint)
        <p class="mt-2 text-xs text-warm-gray">{{ $hint }}</p>
    @endif
</{{ $tag }}>
