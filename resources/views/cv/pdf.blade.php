@php
    $user = $cv['user'];
    $profile = $cv['profile'];
    $stats = $cv['stats'];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $has = fn ($s) => in_array($s, $sections, true);
    $avatar = null;
    if ($profile->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->avatar_path)) {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $avatar = 'data:'.$disk->mimeType($profile->avatar_path).';base64,'.base64_encode($disk->get($profile->avatar_path));
    }
    $contact = collect([
        $showEmail ? $user->email : null,
        $profile->gradeLabel(),
        $profile->ieee_member_number && $showEmail ? 'IEEE # '.$profile->ieee_member_number : null,
        $profile->regionLabel(),
        $profile->section ? $profile->section.' Section' : null,
        $profile->locationLine(),
    ])->filter();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $user->name }} — IEEE Volunteer CV</title>
<style>
    @page { margin: 34mm 16mm 20mm 16mm; }
    * { font-family: "DejaVu Sans", Helvetica, Arial, sans-serif; }
    body { font-size: 9.5pt; color: #2b2b2b; line-height: 1.45; }
    header { position: fixed; top: -26mm; left: 0; right: 0; height: 20mm; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; font-size: 7.5pt; color: #8a8a8a; border-top: 0.6pt solid #e4e4e4; padding-top: 2mm; }
    .pagenum:before { content: counter(page); }
    .brand { font-size: 15pt; letter-spacing: -0.2pt; }
    .brand b { color: #3a3a3a; font-weight: bold; }
    .brand span { color: #e87722; }
    .rule { height: 2.2pt; background: #e87722; margin-top: 3mm; }
    h1 { font-size: 22pt; margin: 0; color: #1f1f1f; font-weight: bold; }
    .headline { font-size: 11pt; color: #555; margin-top: 1mm; }
    .contact { font-size: 8.5pt; color: #666; margin-top: 2mm; }
    .links { font-size: 8.5pt; color: #00629b; margin-top: 1mm; }
    h2 { font-size: 10.5pt; text-transform: uppercase; letter-spacing: 0.8pt; color: #e87722; margin: 7mm 0 2.5mm; padding-bottom: 1.2mm; border-bottom: 0.8pt solid #f3d3b8; }
    .statement { border-left: 2.2pt solid #e87722; padding-left: 3mm; font-size: 10pt; color: #3d3d3d; }
    table { border-collapse: collapse; width: 100%; }
    .stats td { width: 16.66%; text-align: center; border: 0.6pt solid #eeeeee; padding: 2.5mm 1mm; background: #fdf7f2; }
    .stats .v { font-size: 15pt; font-weight: bold; color: #e87722; }
    .stats .l { font-size: 7pt; color: #777; text-transform: uppercase; letter-spacing: 0.3pt; }
    .chip { display: inline-block; background: #fef1e6; color: #8a4210; border-radius: 8pt; padding: 0.8mm 2.4mm; margin: 0 1.2mm 1.4mm 0; font-size: 8pt; }
    .chip .n { color: #e87722; font-weight: bold; }
    .exp { margin-bottom: 4.5mm; page-break-inside: avoid; }
    .exp-title { font-weight: bold; font-size: 10pt; color: #1f1f1f; }
    .exp-meta { font-size: 8pt; color: #777; margin-top: 0.5mm; }
    .exp-dates { font-size: 8pt; color: #555; text-align: right; white-space: nowrap; }
    .exp-desc { margin-top: 1.2mm; color: #444; font-size: 8.8pt; }
    .quote { margin-top: 1.5mm; background: #f6f6f6; padding: 2mm 3mm; font-style: italic; color: #444; font-size: 8.5pt; }
    .quote .by { font-style: normal; color: #777; }
    .badge { display: inline-block; font-size: 7pt; padding: 0.4mm 1.8mm; border-radius: 6pt; background: #e6f0f6; color: #00629b; }
    .badge-live { background: #fef1e6; color: #b35614; }
    .muted { color: #888; }
    .created td { padding: 1.6mm 0; border-bottom: 0.5pt solid #eeeeee; vertical-align: top; }
    .avatar { width: 24mm; height: 24mm; border-radius: 12mm; }
</style>
</head>
<body>
<header>
    <table><tr>
        <td class="brand"><b>IEEE</b> <span>Volunteering</span></td>
        <td style="text-align:right; font-size:8pt; color:#888;">Volunteer CV · generated {{ now()->format('j F Y') }}</td>
    </tr></table>
    <div class="rule"></div>
</header>
<footer>
    <table><tr>
        <td>{{ $user->name }} · verified volunteering record from IEEE Volunteering · {{ route('volunteers.show', $profile) }}</td>
        <td style="text-align:right;">Page <span class="pagenum"></span></td>
    </tr></table>
</footer>

<main>
    <table>
        <tr>
            @if ($avatar)
                <td style="width:28mm; vertical-align:top;"><img src="{{ $avatar }}" class="avatar" alt=""></td>
            @endif
            <td style="vertical-align:top;">
                <h1>{{ $user->name }}</h1>
                @if ($profile->headline)<div class="headline">{{ $profile->headline }}</div>@endif
                @if ($contact->isNotEmpty())<div class="contact">{{ $contact->implode('  ·  ') }}</div>@endif
                @if ($has('links'))
                    @php($links = collect([$profile->linkedin_url, $profile->github_url, $profile->website_url])->filter())
                    @if ($links->isNotEmpty())<div class="links">{{ $links->implode('   ') }}</div>@endif
                @endif
            </td>
        </tr>
    </table>

    @if ($has('statement') && ($profile->cv_statement || $profile->bio))
        <h2>Personal statement</h2>
        <div class="statement">{{ $profile->cv_statement ?: \Illuminate\Support\Str::limit($profile->bio, 900) }}</div>
    @endif

    @if ($has('impact'))
        <h2>Impact summary</h2>
        <table class="stats"><tr>
            <td><div class="v">{{ $fmt($stats['hours']) }}</div><div class="l">Volunteer hours</div></td>
            <td><div class="v">{{ $stats['completed'] }}</div><div class="l">Completed</div></td>
            <td><div class="v">{{ $stats['active'] }}</div><div class="l">In progress</div></td>
            <td><div class="v">{{ $stats['endorsements'] }}</div><div class="l">Endorsements</div></td>
            <td><div class="v">{{ $stats['created'] }}</div><div class="l">Opportunities created</div></td>
            <td><div class="v">{{ $stats['volunteers_led'] }}</div><div class="l">Volunteers engaged</div></td>
        </tr></table>
        @if ($stats['avg_rating'] || $stats['hours_enabled'])
            <p class="muted" style="font-size:8pt; margin-top:2mm;">
                @if ($stats['avg_rating'])Average organiser rating {{ $stats['avg_rating'] }}/5. @endif
                @if ($stats['hours_enabled'])Opportunities created by {{ $user->firstName() }} enabled {{ $fmt($stats['hours_enabled']) }} volunteer hours from others.@endif
                Hours are approved by opportunity organisers.
            </p>
        @endif
    @endif

    @if ($has('skills') && $cv['skills']->isNotEmpty())
        <h2>Skills</h2>
        <div>
            @foreach ($cv['skills'] as $skill)
                <span class="chip">{{ $skill->name }}@if ($skill->endorsements) <span class="n">· {{ $skill->endorsements }} endorsed</span>@endif</span>
            @endforeach
        </div>
        @if ($cv['upskills']->isNotEmpty())
            <p class="muted" style="font-size:8pt; margin-top:1mm;">Developed through volunteering: {{ $cv['upskills']->keys()->implode(', ') }}</p>
        @endif
    @endif

    @if ($has('experience') && $cv['experiences']->isNotEmpty())
        @foreach ($cv['experiences'] as $i => $e)
            <div class="exp">
                {{-- Heading travels with the first entry so it is never orphaned at a page end. --}}
                @if ($i === 0)<h2>Volunteering experience</h2>@endif
                <table><tr>
                    <td>
                        <div class="exp-title">{{ $e->title }}</div>
                        <div class="exp-meta">{{ $e->organisation }} · {{ $e->location }}@if ($e->category) · {{ $e->category }}@endif @if ($e->hours) · {{ $fmt($e->hours) }} hours @endif</div>
                    </td>
                    <td class="exp-dates" style="width:42mm;">
                        {{ $e->start?->format('M Y') }} – {{ $e->in_progress ? 'present' : ($e->end?->format('M Y') ?? '') }}<br>
                        <span class="badge {{ $e->in_progress ? 'badge-live' : '' }}">{{ $e->in_progress ? 'In progress' : 'Completed' }}</span>
                    </td>
                </tr></table>
                <div class="exp-desc">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $e->description), 700) }}</div>
                @if ($e->endorsement && $has('endorsements'))
                    <div class="quote">“{{ $e->endorsement->message }}” <span class="by">— {{ $e->endorsement->endorser?->name }}</span></div>
                @endif
            </div>
        @endforeach
    @endif

    @if ($has('created') && $cv['created']->isNotEmpty())
        <div style="page-break-inside: avoid;"><h2>Opportunities created</h2></div>
        <table class="created">
            @foreach ($cv['created'] as $o)
                <tr>
                    <td><b>{{ $o->title }}</b><br><span class="muted" style="font-size:8pt;">{{ $o->category?->name }}@if ($o->society) · {{ $o->society }}@endif</span></td>
                    <td style="width:30mm; font-size:8pt; color:#555;">{{ $o->start_date?->format('M Y') }}@if ($o->end_date) – {{ $o->end_date->format('M Y') }}@endif</td>
                    <td style="width:34mm; text-align:right; font-size:8pt; color:#555;">{{ $o->confirmed_count }} {{ \Illuminate\Support\Str::plural('volunteer', $o->confirmed_count) }} · {{ $fmt($o->approved_hours) }} h</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($has('endorsements'))
        @php($standalone = $cv['endorsements']->filter(fn ($en) => ! $has('experience') || ! $cv['experiences']->contains(fn ($e) => $e->endorsement?->id === $en->id)))
        @if ($standalone->isNotEmpty())
            <h2>Endorsements</h2>
            @foreach ($standalone as $endorsement)
                <div class="quote" style="margin-bottom:2.5mm;">“{{ $endorsement->message }}” <span class="by">— {{ $endorsement->endorser?->name }}, {{ $endorsement->opportunity?->title }}</span></div>
            @endforeach
        @endif
    @endif
</main>
</body>
</html>
