@php
    $a11yToggles = [
        ['key' => 'contrast', 'label' => 'High contrast'],
        ['key' => 'legible', 'label' => 'Legible font'],
        ['key' => 'links', 'label' => 'Highlight links'],
        ['key' => 'spacing', 'label' => 'Readable spacing'],
        ['key' => 'motion', 'label' => 'Reduce motion'],
        ['key' => 'cursor', 'label' => 'Large cursor'],
        ['key' => 'guide', 'label' => 'Reading guide'],
    ];
@endphp

<div x-data="{ open: false }" class="fixed bottom-5 right-5 z-50 print:hidden">
    {{-- Panel --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         @keydown.escape.window="open = false"
         @click.outside="open = false"
         role="dialog" aria-modal="false" aria-labelledby="a11y-title"
         class="mb-3 w-72 rounded-xl border border-light-gray bg-white p-4 shadow-dropdown">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="a11y-title" class="font-heading text-base font-bold text-charcoal">Accessibility</h2>
            <button type="button" @click="open = false" aria-label="Close accessibility options"
                    class="grid h-7 w-7 place-items-center rounded-md text-warm-gray hover:bg-warm-white hover:text-ink">✕</button>
        </div>

        {{-- Text size --}}
        <div class="mb-3">
            <p class="mb-1.5 font-ui text-xs font-semibold uppercase tracking-wide text-warm-gray">Text size</p>
            <div class="flex items-center gap-2">
                <button type="button" x-ref="firstControl" @click="$store.a11y.fontDown()"
                        :disabled="$store.a11y.fontScale <= 0.9"
                        class="btn-secondary flex-1 px-0 py-1.5 disabled:opacity-40" aria-label="Decrease text size">A&minus;</button>
                <span class="w-14 text-center font-ui text-sm font-semibold text-ink"
                      x-text="$store.a11y.fontPercent() + '%'">100%</span>
                <button type="button" @click="$store.a11y.fontUp()"
                        :disabled="$store.a11y.fontScale >= 1.6"
                        class="btn-secondary flex-1 px-0 py-1.5 text-lg disabled:opacity-40" aria-label="Increase text size">A+</button>
            </div>
            <button type="button" @click="$store.a11y.fontReset()"
                    class="mt-1.5 text-xs text-accent-blue underline">Reset text size</button>
        </div>

        {{-- Toggles --}}
        <div class="space-y-0.5 border-t border-light-gray pt-2">
            @foreach ($a11yToggles as $t)
                <button type="button" role="switch"
                        @click="$store.a11y.toggle('{{ $t['key'] }}')"
                        :aria-checked="$store.a11y.{{ $t['key'] }} ? 'true' : 'false'"
                        class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                    <span class="text-sm font-medium text-warmer-gray">{{ $t['label'] }}</span>
                    <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition"
                          :class="$store.a11y.{{ $t['key'] }} ? 'bg-brand' : 'bg-light-gray'">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                              :class="$store.a11y.{{ $t['key'] }} ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </span>
                </button>
            @endforeach
        </div>

        <button type="button" @click="$store.a11y.reset()"
                class="mt-3 w-full rounded-lg border border-light-gray px-3 py-2 text-sm font-medium text-warmer-gray hover:bg-warm-white">
            Reset all
        </button>
    </div>

    {{-- Launcher --}}
    <button type="button"
            @click="open = !open; if (open) $nextTick(() => $refs.firstControl?.focus())"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="dialog" aria-label="Accessibility options"
            class="grid h-12 w-12 place-items-center rounded-full bg-brand text-cream shadow-button transition hover:bg-brand-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
        <svg viewBox="0 0 24 24" class="h-7 w-7" fill="currentColor" aria-hidden="true">
            <circle cx="12" cy="3.8" r="2" />
            <path d="M20 8.2c0 .5-.39.92-.88 1l-4.12.52v2.98l1.94 6.42a1 1 0 0 1-1.92.56L13.1 14h-2.2l-1.92 6.2a1 1 0 0 1-1.92-.56L9 12.7V9.72l-4.12-.52A1 1 0 0 1 5.12 7.2l4.86.62c1.34.17 2.7.17 4.04 0l4.86-.62c.6-.08 1.12.4 1.12 1z" />
        </svg>
    </button>

    {{-- Reading guide bar — repositioned by app.js on pointer move. --}}
    <div id="a11y-guide" aria-hidden="true"></div>
</div>
