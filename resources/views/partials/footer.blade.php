<footer class="mt-20 border-t border-light-gray bg-white print:hidden">
    <div class="container-x py-10">
        <div class="grid gap-10 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-logo-mark size="text-2xl" />
                <p class="mt-4 max-w-md text-sm leading-relaxed text-warm-gray">
                    {{ $siteSettings['site_tagline'] ?? 'Find your next way to give back to the IEEE community.' }}
                    IEEE Volunteering connects members across every region, section and society with
                    opportunities to share their skills, grow, and make an impact.
                </p>
            </div>
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Volunteer</h2>
                <ul class="mt-3 space-y-2 text-sm text-warm-gray">
                    <li><a href="{{ route('opportunities.index') }}" class="hover:text-brand">Browse opportunities</a></li>
                    <li><a href="{{ route('opportunities.create') }}" class="hover:text-brand">Create an opportunity</a></li>
                    <li><a href="{{ route('volunteers.index') }}" class="hover:text-brand">Volunteer directory</a></li>
                    <li><a href="{{ route('dashboard') }}" class="hover:text-brand">Your impact dashboard</a></li>
                </ul>
            </div>
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-ink">Help</h2>
                <ul class="mt-3 space-y-2 text-sm text-warm-gray">
                    @forelse ($footerMenu as $item)
                        <li><a href="{{ $item->url() }}" @if($item->new_tab) target="_blank" rel="noopener" @endif class="hover:text-brand">{{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ route('contact.show') }}" class="hover:text-brand">Contact us</a></li>
                    @endforelse
                    <li><a href="{{ route('accessibility') }}" class="hover:text-brand">Accessibility</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-10 flex flex-wrap items-center gap-3 border-t border-light-gray pt-6">
            <span class="text-sm text-warmer-gray">Follow us</span>
            @include('partials.social-links')
        </div>

        <p class="mt-6 text-xs leading-relaxed text-warm-gray">
            &copy; Copyright {{ date('Y') }} IEEE – All rights reserved. Use of this website signifies your agreement to the
            <a href="https://www.ieee.org/about/help/site-terms-conditions.html" class="text-brand hover:underline" rel="noopener">IEEE Terms and Conditions</a>.
            A public charity, IEEE is the world's largest technical professional organization dedicated to advancing technology for the benefit of humanity.
        </p>
    </div>
</footer>
