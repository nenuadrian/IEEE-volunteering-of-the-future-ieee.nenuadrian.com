@extends('layouts.public')
@section('title', $page->seoTitle())
@section('meta_description', $page->seoDescription())
@if ($page->seoImageUrl())@section('og_image', $page->seoImageUrl())@endif
@if ($page->seoCanonical())@section('canonical_url', $page->seoCanonical())@endif
@if ($page->seoNoindex())@section('robots_noindex', '1')@endif

@section('content')
    <x-page-header :title="$page->title" />

    <section class="container-x max-w-4xl py-10">
        @unless ($page->isPublished())
            <p class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">Draft — only admins can see this page.</p>
        @endunless
        <div class="card content p-8 text-[17px]">
            {!! $page->body !!}
        </div>
    </section>
@endsection
