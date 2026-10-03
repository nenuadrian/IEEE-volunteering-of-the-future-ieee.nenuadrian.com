@props(['title', 'subtitle' => null, 'eyebrow' => null])

{{-- Page title band in the volunteer.ieee.org style: white, title with short orange rule. --}}
<section class="border-b border-light-gray bg-white">
    <div class="container-x py-8 lg:py-10">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                @if ($eyebrow)
                    <p class="text-xs font-bold uppercase tracking-widest text-brand">{{ $eyebrow }}</p>
                @endif
                <h1 class="section-title mt-1 text-3xl">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-3 max-w-2xl text-warm-gray">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap gap-3">{{ $actions }}</div>
            @endisset
        </div>
        {{ $slot }}
    </div>
</section>
