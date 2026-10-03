@props(['href' => '#', 'active' => false, 'newTab' => false])

<a href="{{ $href }}"
   @if($newTab) target="_blank" rel="noopener" @endif
   {{ $attributes->class([
        'rounded-full px-3 py-2 font-ui text-sm font-medium transition',
        'bg-brand-50 text-brand' => $active,
        'text-warmer-gray hover:bg-brand-50 hover:text-brand' => ! $active,
   ]) }}>
    {{ $slot }}
</a>
