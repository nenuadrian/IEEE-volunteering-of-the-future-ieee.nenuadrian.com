@extends('layouts.public')
@section('title', 'Contact us')

@section('content')
    <x-page-header title="Contact us &amp; feedback"
        subtitle="Questions about an opportunity, a problem with the platform, or an idea to make volunteering better? Send us a message." />

    <section class="container-x grid gap-10 py-12 lg:grid-cols-3">
        {{-- Form --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('contact.submit') }}" class="card space-y-5 p-8">
                @csrf

                @error('contact')
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $message }}
                    </div>
                @enderror

                {{-- Honeypot: hidden from real users, irresistible to bots. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">Your name <span class="text-red-500">*</span></label>
                        <input id="name" name="name" value="{{ old('name') }}" required class="input" maxlength="120">
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="email">Email <span class="text-red-500">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="input" maxlength="190">
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <label class="label" for="subject">Subject <span class="text-red-500">*</span></label>
                    <input id="subject" name="subject" value="{{ old('subject') }}" required class="input" maxlength="160">
                    <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                </div>

                <div>
                    <label class="label" for="message">Message <span class="text-red-500">*</span></label>
                    <textarea id="message" name="message" rows="7" required class="input" placeholder="How can we help?">{{ old('message') }}</textarea>
                    <x-input-error :messages="$errors->get('message')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-light-gray pt-5">
                    <button class="btn-primary">Send message</button>
                </div>
            </form>
        </div>

        {{-- Aside --}}
        <aside class="space-y-6">
            <div class="card p-6">
                <h2 class="text-lg font-semibold text-ink">Email us directly</h2>
                <p class="mt-2 text-sm text-warmer-gray">Prefer your own mail client? Reach us at:</p>
                <a href="mailto:{{ $contactEmail }}" class="mt-3 inline-block font-ui text-sm font-semibold text-brand hover:text-brand-dark">
                    {{ $contactEmail }}
                </a>
            </div>
            <div class="card p-6">
                <h2 class="text-lg font-semibold text-ink">Looking for something else?</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ url('/faq') }}" class="text-warmer-gray hover:text-brand">Frequently asked questions</a></li>
                    <li><a href="{{ url('/how-it-works') }}" class="text-warmer-gray hover:text-brand">How IEEE Volunteering works</a></li>
                    <li><a href="{{ route('opportunities.index') }}" class="text-warmer-gray hover:text-brand">Browse opportunities</a></li>
                    <li><a href="{{ route('volunteers.index') }}" class="text-warmer-gray hover:text-brand">Volunteer directory</a></li>
                </ul>
            </div>
        </aside>
    </section>
@endsection
