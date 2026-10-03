@props(['title', 'icon' => '✦'])

<div {{ $attributes->merge(['class' => 'card flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-2xl text-brand" aria-hidden="true">{{ $icon }}</span>
    <h3 class="mt-4 text-lg font-semibold text-ink">{{ $title }}</h3>
    @if (trim($slot) !== '')
        <div class="mt-2 max-w-md text-sm text-warm-gray">{{ $slot }}</div>
    @endif
    @isset($actions)
        <div class="mt-6 flex flex-wrap justify-center gap-3">{{ $actions }}</div>
    @endisset
</div>
