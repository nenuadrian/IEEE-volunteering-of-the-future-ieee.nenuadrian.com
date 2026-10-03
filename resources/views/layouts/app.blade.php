<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    @include('partials.header')

    @isset($header)
        <header class="border-b border-light-gray bg-warm-white">
            <div class="container-x py-8">
                {{ $header }}
            </div>
        </header>
    @endisset

    @include('partials.flash')

    <main id="main-content" tabindex="-1" class="flex-1">
        {{ $slot }}
    </main>

    @include('partials.footer')

    @include('partials.accessibility')
</body>
</html>
