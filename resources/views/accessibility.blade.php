@extends('layouts.public')
@section('title', 'Accessibility statement')
@section('meta_description', 'Accessibility statement for the Society of Research Software Engineering: our commitment to making society-rse.org accessible in line with WCAG and the Public Sector Bodies Accessibility Regulations 2018.')

@section('content')
    <x-page-header
        eyebrow="Information"
        title="Accessibility statement"
        subtitle="Our commitment to making the Society's website usable by everyone.">
    </x-page-header>

    <article class="container-x max-w-3xl py-12">
        <div class="content text-lg">
            <p>This accessibility statement applies to all pages hosted at society-rse.org.</p>

            <p>We use accessibility tools to make your device easier to use if you have a disability and to be compliant with the <a href="https://www.w3.org/WAI/standards-guidelines/wcag/" target="_blank" rel="noopener">Web Content Accessibility Guidelines (WCAG)</a>. We've also made the website text as simple as possible to understand.</p>

            <p>You can switch to high-contrast and large-font modes on all pages hosted by the Society. We actively monitor our web pages for empty links, missing alt-text on images and non-descriptive links, and we are working to resolve any broken links we find. We intend to build our pages to be friendly to screen readers.</p>

            <p>If you find any problems, or would like the contents provided in a different format such as PDF, please contact us at <a href="mailto:edia@society-rse.org">edia@society-rse.org</a>.</p>

            <p>The Equality and Human Rights Commission (EHRC) is responsible for enforcing the Public Sector Bodies (Websites and Mobile Applications) (No.&nbsp;2) Accessibility Regulations 2018 (the 'accessibility regulations'). If you're not happy with how we respond to your complaint, <a href="https://www.equalityadvisoryservice.com/" target="_blank" rel="noopener">contact the Equality Advisory and Support Service (EASS)</a>.</p>

            <p>The Society of Research Software Engineering is committed to making its website accessible, in accordance with the Public Sector Bodies (Websites and Mobile Applications) (No.&nbsp;2) Accessibility Regulations 2018.</p>
        </div>
    </article>
@endsection
