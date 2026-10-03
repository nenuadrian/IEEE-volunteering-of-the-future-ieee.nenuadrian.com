<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="font-sans text-ink antialiased">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Brand panel --}}
        <div class="relative hidden flex-col justify-between bg-charcoal p-12 text-cream lg:flex">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <x-logo-mark chip size="h-12" />
                <span class="font-heading text-lg font-extrabold">Society of RSE</span>
            </a>
            <div>
                <h1 class="font-heading text-4xl font-extrabold leading-tight text-cream">
                    Championing the people behind research software.
                </h1>
                <p class="mt-4 max-w-md text-cream/70">
                    Join a growing community of research software engineers. Share jobs, discover
                    resources and help shape the future of the profession.
                </p>
            </div>
            <p class="text-sm text-cream/50">&copy; {{ date('Y') }} Society of Research Software Engineering</p>
        </div>

        {{-- Form panel --}}
        <div id="main-content" tabindex="-1" class="flex flex-col items-center justify-center bg-cream px-6 py-12">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-8 flex items-center gap-3 lg:hidden">
                    <x-logo-mark size="h-11" />
                    <span class="font-heading text-lg font-extrabold text-charcoal">Society of RSE</span>
                </a>
                <div class="card p-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    @include('partials.accessibility')
</body>
</html>
