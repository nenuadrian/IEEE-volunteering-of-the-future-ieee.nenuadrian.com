<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

@include('partials.seo')

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

{{-- Apply saved accessibility preferences before paint (avoids a flash). --}}
<script>
    (function () {
        try {
            var s = JSON.parse(localStorage.getItem('ieee-vol-a11y') || '{}');
            var el = document.documentElement;
            if (s.fontScale) el.style.setProperty('--a11y-font-scale', s.fontScale);
            var map = { contrast: 'a11y-contrast', legible: 'a11y-legible', links: 'a11y-links', spacing: 'a11y-spacing', motion: 'a11y-reduce-motion', cursor: 'a11y-big-cursor', guide: 'a11y-guide' };
            for (var k in map) { if (s[k]) el.classList.add(map[k]); }
        } catch (e) {}
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
