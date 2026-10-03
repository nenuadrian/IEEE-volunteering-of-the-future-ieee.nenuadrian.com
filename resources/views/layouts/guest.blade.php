<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="bg-warm-white font-sans text-ink antialiased">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Brand panel --}}
        <div class="relative hidden flex-col justify-between overflow-hidden bg-charcoal-dark p-12 text-white lg:flex">
            <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-40 -left-24 h-96 w-96 rounded-full bg-accent-blue/25 blur-3xl" aria-hidden="true"></div>

            <a href="{{ route('home') }}" class="relative">
                <x-logo-mark light size="text-2xl" />
            </a>
            <div class="relative">
                <h1 class="max-w-lg text-4xl font-bold leading-tight text-white">
                    Give back to the IEEE community — and see the impact you make.
                </h1>
                <ul class="mt-8 space-y-3 text-white/80">
                    <li class="flex gap-3"><span class="text-brand-light" aria-hidden="true">✓</span> Opportunities from every region, section and society</li>
                    <li class="flex gap-3"><span class="text-brand-light" aria-hidden="true">✓</span> Matched to your skills, grade and location</li>
                    <li class="flex gap-3"><span class="text-brand-light" aria-hidden="true">✓</span> Log hours, collect endorsements, download your volunteer CV</li>
                </ul>
            </div>
            <p class="relative text-sm text-white/50">&copy; {{ date('Y') }} IEEE – All rights reserved.</p>
        </div>

        {{-- Form panel --}}
        <div id="main-content" tabindex="-1" class="flex flex-col items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-8 block lg:hidden">
                    <x-logo-mark size="text-2xl" />
                </a>
                <div class="card p-8">
                    {{ $slot }}
                </div>
                <p class="mt-6 text-center text-sm text-warm-gray">
                    <a href="{{ route('opportunities.index') }}" class="text-brand hover:underline">Browse opportunities</a> without an account
                </p>
            </div>
        </div>
    </div>

    @include('partials.accessibility')
</body>
</html>
