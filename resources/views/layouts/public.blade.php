<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    @stack('head')
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    @include('partials.header')

    @include('partials.flash')

    <main id="main-content" tabindex="-1" class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.accessibility')

    @stack('scripts')
</body>
</html>
