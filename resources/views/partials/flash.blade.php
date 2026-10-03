@php
    // These status values are internal tokens consumed inline by specific
    // partials (profile / password / email-verification), not user-facing copy.
    // Don't surface them as a global toast.
    $internalStatusTokens = ['profile-updated', 'password-updated', 'verification-link-sent'];
    $status = session('status');
    $statusMessage = ($status && ! in_array($status, $internalStatusTokens, true)) ? $status : null;
@endphp

@if ($statusMessage || $errors->any())
    {{-- Floating toast stack: overlays the page instead of pushing the banner down. --}}
    <div class="pointer-events-none fixed inset-x-0 top-[112px] z-50 flex flex-col items-stretch gap-3 px-4 sm:items-end sm:px-6">
        @if ($statusMessage)
            <div x-data="{ show: false }"
                 x-init="$nextTick(() => show = true)"
                 x-show="show"
                 x-cloak
                 x-transition:enter="transition transform ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-y-3 sm:translate-y-0 sm:translate-x-4"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 role="status" aria-live="polite"
                 class="group pointer-events-auto w-full overflow-hidden rounded-xl border border-accent-green/30 bg-white shadow-card sm:w-[26rem]">
                <div class="flex items-start gap-3 px-4 py-3">
                    <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-accent-green-light/60 text-accent-green-dark">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.29 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <p class="flex-1 font-ui text-sm font-medium text-accent-green-dark">{{ $statusMessage }}</p>
                    <button type="button" @click="show = false" aria-label="Dismiss"
                            class="-mr-1 -mt-0.5 shrink-0 rounded-md p-1 text-accent-green-dark/60 transition hover:bg-accent-green-light/40 hover:text-accent-green-dark">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                </div>
                {{-- Auto-dismiss countdown; pauses on hover. animationend drives the close. --}}
                <div class="h-1 w-full origin-left bg-accent-green/40 [animation:toast-countdown_6000ms_linear_forwards] group-hover:[animation-play-state:paused]"
                     @animationend="show = false"></div>
            </div>
        @endif

        @if ($errors->any())
            <div x-data="{ show: false }"
                 x-init="$nextTick(() => show = true)"
                 x-show="show"
                 x-cloak
                 x-transition:enter="transition transform ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-y-3 sm:translate-y-0 sm:translate-x-4"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 role="alert" aria-live="assertive"
                 class="pointer-events-auto w-full overflow-hidden rounded-xl border border-red-200 bg-white shadow-card sm:w-[26rem]">
                <div class="flex items-start gap-3 px-4 py-3">
                    <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 1.5a8.5 8.5 0 100 17 8.5 8.5 0 000-17zM10 5a.9.9 0 01.9.9v4.6a.9.9 0 01-1.8 0V5.9A.9.9 0 0110 5zm0 8.2a1.05 1.05 0 100 2.1 1.05 1.05 0 000-2.1z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <div class="flex-1">
                        <p class="font-ui text-sm font-semibold text-red-700">Please fix the following:</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-red-700/90">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" @click="show = false" aria-label="Dismiss"
                            class="-mr-1 -mt-0.5 shrink-0 rounded-md p-1 text-red-400 transition hover:bg-red-50 hover:text-red-600">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif
    </div>
@endif
