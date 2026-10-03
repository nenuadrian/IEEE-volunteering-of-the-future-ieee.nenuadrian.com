@extends('layouts.admin')
@section('title', 'Settings')
@section('heading', 'Site settings')

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-xl space-y-6">
        @csrf
        @method('PUT')

        <div class="card space-y-5 p-6">
            <div>
                <label class="label" for="site_name">Site name <span class="text-red-500">*</span></label>
                <input id="site_name" name="site_name" value="{{ old('site_name', $settings['site_name']) }}" required class="input">
                <x-input-error :messages="$errors->get('site_name')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="site_tagline">Tagline</label>
                <input id="site_tagline" name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline']) }}" class="input">
            </div>
            <div>
                <label class="label" for="footer_text">Footer text</label>
                <textarea id="footer_text" name="footer_text" rows="2" class="input">{{ old('footer_text', $settings['footer_text']) }}</textarea>
            </div>
            <div>
                <label class="label" for="contact_email">Contact form recipient <span class="text-red-500">*</span></label>
                <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $settings['contact_email']) }}" required class="input">
                <p class="mt-1 text-xs text-warm-gray">Messages from the public contact page are delivered to this address.</p>
                <x-input-error :messages="$errors->get('contact_email')" class="mt-1" />
            </div>
        </div>

        <div class="card space-y-5 p-6">
            <div>
                <h2 class="font-heading text-lg font-bold text-charcoal">SEO &amp; social</h2>
                <p class="mt-1 text-xs text-warm-gray">Defaults for search engines and social shares, used when a page doesn't set its own.</p>
            </div>
            <div>
                <label class="label" for="seo_default_description">Default meta description</label>
                <textarea id="seo_default_description" name="seo_default_description" rows="2" maxlength="255" class="input">{{ old('seo_default_description', $settings['seo_default_description']) }}</textarea>
                <p class="mt-1 text-xs text-warm-gray">Falls back to the tagline if left blank.</p>
                <x-input-error :messages="$errors->get('seo_default_description')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="social_image">Social share image URL</label>
                <input id="social_image" name="social_image" value="{{ old('social_image', $settings['social_image']) }}" placeholder="https://… or /images/og.png" class="input">
                <p class="mt-1 text-xs text-warm-gray">Open Graph / Twitter card image (~1200×630). A page's own image overrides this.</p>
                <x-input-error :messages="$errors->get('social_image')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="twitter_handle">Twitter / X handle</label>
                <input id="twitter_handle" name="twitter_handle" value="{{ old('twitter_handle', $settings['twitter_handle']) }}" placeholder="@IEEEorg" class="input">
                <x-input-error :messages="$errors->get('twitter_handle')" class="mt-1" />
            </div>
            <div class="border-t border-light-gray pt-5">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="search_indexable" value="1" @checked(old('search_indexable', $settings['search_indexable'])) class="mt-0.5 rounded border-light-gray text-brand focus:ring-brand">
                    <span>
                        <span class="block font-ui text-sm font-medium text-warmer-gray">Allow search engine indexing</span>
                        <span class="mt-0.5 block text-xs text-warm-gray">Off by default. The site is hidden from search engines during development. Turn on to let Google &amp; others index public pages. Remember to also relax <code>public/robots.txt</code> when going live.</span>
                    </span>
                </label>
            </div>
        </div>

        <div>
            <button class="btn-primary">Save settings</button>
        </div>
    </form>
@endsection
