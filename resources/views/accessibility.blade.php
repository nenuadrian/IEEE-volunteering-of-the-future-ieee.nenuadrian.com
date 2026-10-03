@extends('layouts.public')
@section('title', 'Accessibility')
@section('meta_description', 'Accessibility statement for IEEE Volunteering.')

@section('content')
    <x-page-header title="Accessibility" subtitle="We want everyone in the IEEE community to be able to find and take part in volunteering." />

    <section class="container-x max-w-4xl py-10">
        <div class="card content p-8">
            <h2>Our commitment</h2>
            <p>IEEE Volunteering aims to meet the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA. We design with keyboard navigation, screen readers, sufficient colour contrast and responsive layouts in mind, and we test new features against these principles.</p>

            <h2>Accessibility tools on every page</h2>
            <p>Use the accessibility button in the bottom-right corner of any page to:</p>
            <ul>
                <li>increase or decrease text size,</li>
                <li>switch to a high-contrast colour scheme,</li>
                <li>use a more legible font and readable text spacing,</li>
                <li>highlight links, reduce motion, enlarge the cursor or show a reading guide.</li>
            </ul>
            <p>Your choices are remembered on this device.</p>

            <h2>Charts and data</h2>
            <p>Every chart on the platform has a “View as table” option with the same numbers, so no information depends on colour or on hovering with a mouse. Chart colours are checked for colour-vision-deficiency safety.</p>

            <h2>Known limitations</h2>
            <ul>
                <li>Opportunity descriptions and images imported from volunteer.ieee.org are shown as provided by their authors and may lack alternative text.</li>
                <li>The opportunity map uses OpenStreetMap tiles; every opportunity on the map is also available in the list view.</li>
                <li>PDF CVs are generated automatically and may not be fully tagged for screen readers. The same information is available on each volunteer's profile page.</li>
            </ul>

            <h2>Feedback</h2>
            <p>If you find something that is difficult to use, please <a href="{{ route('contact.show') }}">contact us</a> and tell us the page and the problem. We aim to respond within five working days.</p>
        </div>
    </section>
@endsection
