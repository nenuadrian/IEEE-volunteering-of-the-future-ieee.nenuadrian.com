@props(['href', 'active' => false, 'icon' => null])

<a href="{{ $href }}"
   @class([
        'flex items-center gap-3 rounded-lg px-3 py-2 font-ui text-sm font-medium transition',
        'bg-brand text-cream' => $active,
        'text-cream/70 hover:bg-white/10 hover:text-cream' => ! $active,
   ])>
    @if ($icon)<span class="text-base leading-none">{{ $icon }}</span>@endif
    <span class="flex-1">{{ $slot }}</span>
    {{ $badge ?? '' }}
</a>
