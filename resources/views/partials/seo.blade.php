{{--
    Site-wide SEO meta. Pages override individual values via @section(...):
      title, meta_description, og_image, og_type, canonical_url, robots_noindex
    Each falls back to a sensible default from $siteSettings (admin → Settings).
    Robots stays "noindex" unless the "Allow search indexing" setting is on AND
    the page hasn't opted out — preserving the site's pre-launch private state.
--}}
@php
    $siteName = $siteSettings['site_name'] ?? config('app.name');

    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? $pageTitle.' · '.$siteName : $siteName;
    $ogTitle = $pageTitle !== '' ? $pageTitle : $siteName;

    $description = trim($__env->yieldContent('meta_description'))
        ?: trim($siteSettings['seo_default_description'] ?? '')
        ?: trim($siteSettings['site_tagline'] ?? '');

    $canonical = trim($__env->yieldContent('canonical_url')) ?: url()->current();

    $image = trim($__env->yieldContent('og_image')) ?: trim($siteSettings['social_image'] ?? '');
    if ($image !== '' && ! \Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])) {
        $image = asset(ltrim($image, '/'));
    }

    $ogType = trim($__env->yieldContent('og_type')) ?: 'website';

    $pageNoindex = trim($__env->yieldContent('robots_noindex')) === '1';
    $indexable = ($siteSettings['search_indexable'] ?? false) && ! $pageNoindex;

    $twitter = trim($siteSettings['twitter_handle'] ?? '');
    if ($twitter !== '' && ! \Illuminate\Support\Str::startsWith($twitter, '@')) {
        $twitter = '@'.$twitter;
    }
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $indexable ? 'index, follow' : 'noindex, nofollow' }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
@if ($image !== '')
<meta property="og:image" content="{{ $image }}">
@endif

{{-- Twitter / X --}}
<meta name="twitter:card" content="{{ $image !== '' ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $description }}">
@if ($twitter !== '')
<meta name="twitter:site" content="{{ $twitter }}">
@endif
@if ($image !== '')
<meta name="twitter:image" content="{{ $image }}">
@endif
