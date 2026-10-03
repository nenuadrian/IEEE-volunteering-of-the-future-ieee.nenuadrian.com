@props(['light' => false, 'size' => 'text-xl'])

{{-- "IEEE Volunteering" wordmark, as on volunteer.ieee.org. --}}
<span {{ $attributes->merge(['class' => "inline-flex items-baseline gap-1.5 whitespace-nowrap font-heading leading-none tracking-tight $size"]) }}>
    <span @class(['font-extrabold', 'text-white' => $light, 'text-charcoal' => ! $light])>IEEE</span>
    <span @class(['font-light', 'text-brand-light' => $light, 'text-brand' => ! $light])>Volunteering</span>
</span>
