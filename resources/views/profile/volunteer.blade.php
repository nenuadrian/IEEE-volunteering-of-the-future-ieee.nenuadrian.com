@extends('layouts.public')
@section('title', 'Edit volunteer profile')
@section('robots_noindex', '1')

@php
    $p = $profile;
    $selectedSkills = old('skills', $user->skills->pluck('id')->map(fn ($id) => (string) $id)->all());
    $sectionsByRegion = collect(config('volunteering.sections'))->map(fn ($s) => array_keys($s));
@endphp

@section('content')
    <x-page-header title="Volunteer profile" subtitle="This is what organisers see when you apply, what appears in the volunteer directory, and what goes into your CV.">
        <x-slot:actions>
            <a href="{{ route('volunteers.show', $p) }}" class="btn-secondary">View my profile</a>
            <a href="{{ route('profile.edit') }}" class="btn-ghost">Account &amp; password</a>
        </x-slot:actions>
    </x-page-header>

    <div class="container-x grid gap-8 py-8 lg:grid-cols-[1fr_18rem]">
        <form method="POST" action="{{ route('profile.volunteer.update') }}" enctype="multipart/form-data" class="space-y-6"
              x-data="{ region: @js(old('region', $p->region)), sections: @js($sectionsByRegion) }">
            @csrf
            @method('PATCH')

            <section class="card p-6">
                <h2 class="text-lg font-semibold text-ink">About you</h2>
                <div class="mt-5 flex items-center gap-5">
                    <x-avatar :user="$user" size="h-20 w-20" text="text-2xl" />
                    <div>
                        <label class="label" for="avatar">Profile photo</label>
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="block text-sm text-warm-gray file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                        <p class="help">Square JPG, PNG or WebP, up to 3 MB. Shown on your profile and CV.</p>
                        @if ($p->avatar_path)
                            <label class="mt-1 inline-flex items-center gap-2 text-xs text-warm-gray"><input type="checkbox" name="remove_avatar" value="1" class="checkbox"> Remove photo</label>
                        @endif
                        <x-input-error :messages="$errors->get('avatar')" class="mt-1" />
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    <div>
                        <label class="label" for="headline">Headline</label>
                        <input id="headline" name="headline" maxlength="160" value="{{ old('headline', $p->headline) }}" class="input" placeholder="e.g. Power systems engineer & YP volunteer">
                        <x-input-error :messages="$errors->get('headline')" class="mt-1" />
                    </div>
                    <div x-data="{ count: {{ mb_strlen((string) old('bio', $p->bio)) }} }">
                        <label class="label" for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="6" maxlength="3000" class="input" @input="count = $event.target.value.length" placeholder="Who you are, what you work on, what kind of volunteering you enjoy.">{{ old('bio', $p->bio) }}</textarea>
                        <p class="help flex justify-between"><span>Tip: mention your field, your IEEE involvement and what you'd like to learn.</span><span x-text="count + ' / 3000'"></span></p>
                        <x-input-error :messages="$errors->get('bio')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="cv_statement">Personal statement for your CV</label>
                        <textarea id="cv_statement" name="cv_statement" rows="3" maxlength="1500" class="input" placeholder="Two or three sentences summarising your volunteering — shown at the top of your PDF CV.">{{ old('cv_statement', $p->cv_statement) }}</textarea>
                        <x-input-error :messages="$errors->get('cv_statement')" class="mt-1" />
                    </div>
                </div>
            </section>

            <section class="card p-6" id="skills">
                <h2 class="text-lg font-semibold text-ink">Skills</h2>
                <p class="mt-1 text-sm text-warm-gray">Skills drive your match score (60% of it). Add at least three — you can add skills that aren't listed yet.</p>
                <div class="mt-4">
                    <label class="sr-only">Your skills</label>
                    <x-skill-picker name="skills" :options="$skillOptions" :selected="$selectedSkills" :allow-create="true" :max="30" placeholder="Type a skill, e.g. Graphic design…" />
                    <x-input-error :messages="$errors->get('skills')" class="mt-1" />
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-lg font-semibold text-ink">IEEE membership</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="membership_grade">Membership grade</label>
                        <select id="membership_grade" name="membership_grade" class="input">
                            <option value="">Not specified</option>
                            @foreach (config('volunteering.membership_grades') as $code => $label)
                                <option value="{{ $code }}" @selected(old('membership_grade', $p->membership_grade) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="help">Some opportunities are limited to particular grades.</p>
                    </div>
                    <div>
                        <label class="label" for="member_since">Member since (year)</label>
                        <input id="member_since" name="member_since" type="number" min="1963" max="{{ now()->year }}" value="{{ old('member_since', $p->member_since) }}" class="input">
                        <x-input-error :messages="$errors->get('member_since')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="ieee_member_number">IEEE member number</label>
                        <input id="ieee_member_number" name="ieee_member_number" inputmode="numeric" value="{{ old('ieee_member_number', $p->ieee_member_number) }}" class="input" placeholder="8 digits">
                        <p class="help">Private. Links opportunities you created on volunteer.ieee.org to this account on the next sync.</p>
                        <x-input-error :messages="$errors->get('ieee_member_number')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="region">IEEE region</label>
                        <select id="region" name="region" x-model="region" class="input">
                            <option value="">Not specified</option>
                            @foreach (config('volunteering.regions') as $code => $label)
                                <option value="{{ $code }}" @selected(old('region', $p->region) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="section">Section</label>
                        <input id="section" name="section" list="section-list" value="{{ old('section', $p->section) }}" class="input" placeholder="Start typing…">
                        <datalist id="section-list"><template x-for="s in (sections[region] || [])" :key="s"><option :value="s"></option></template></datalist>
                    </div>
                    <div>
                        <label class="label" for="society">Main society, council or affinity group</label>
                        <input id="society" name="society" list="society-list" value="{{ old('society', $p->society) }}" class="input">
                        <datalist id="society-list">@foreach (config('volunteering.societies') as $s)<option value="{{ $s }}"></option>@endforeach</datalist>
                    </div>
                    <div>
                        <label class="label" for="country">Country</label>
                        <input id="country" name="country" value="{{ old('country', $p->country) }}" class="input">
                    </div>
                    <div>
                        <label class="label" for="city">City</label>
                        <input id="city" name="city" value="{{ old('city', $p->city) }}" class="input">
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-lg font-semibold text-ink">Links</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <label class="label" for="linkedin_url">LinkedIn</label>
                        <input id="linkedin_url" name="linkedin_url" type="url" value="{{ old('linkedin_url', $p->linkedin_url) }}" class="input" placeholder="https://www.linkedin.com/in/…">
                        <x-input-error :messages="$errors->get('linkedin_url')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="github_url">GitHub</label>
                        <input id="github_url" name="github_url" type="url" value="{{ old('github_url', $p->github_url) }}" class="input" placeholder="https://github.com/…">
                        <x-input-error :messages="$errors->get('github_url')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="website_url">Website / portfolio</label>
                        <input id="website_url" name="website_url" type="url" value="{{ old('website_url', $p->website_url) }}" class="input" placeholder="https://…">
                        <x-input-error :messages="$errors->get('website_url')" class="mt-1" />
                    </div>
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-lg font-semibold text-ink">Availability &amp; privacy</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <fieldset>
                        <legend class="label">Availability</legend>
                        <div class="space-y-2">
                            @foreach (config('volunteering.availability') as $key => $label)
                                <label class="flex items-center gap-2 text-sm text-warmer-gray"><input type="radio" name="availability" value="{{ $key }}" class="text-brand focus:ring-brand" @checked(old('availability', $p->availability) === $key)> {{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div>
                        <label class="label" for="hours_per_month">Hours per month you can usually give</label>
                        <input id="hours_per_month" name="hours_per_month" type="number" min="1" max="200" value="{{ old('hours_per_month', $p->hours_per_month) }}" class="input w-32">
                    </div>
                </div>
                <div class="mt-5 space-y-3 border-t border-light-gray pt-5">
                    <label class="flex items-start gap-3"><input type="checkbox" name="is_public" value="1" class="checkbox mt-0.5" @checked(old('is_public', $p->is_public))>
                        <span><span class="block text-sm font-semibold text-ink">Show me in the volunteer directory</span><span class="block text-xs text-warm-gray">Your profile page and CV link are public. Organisers of opportunities you apply to can always see your profile.</span></span></label>
                    <label class="flex items-start gap-3"><input type="checkbox" name="show_email" value="1" class="checkbox mt-0.5" @checked(old('show_email', $p->show_email))>
                        <span><span class="block text-sm font-semibold text-ink">Show my email address on my profile and CV</span><span class="block text-xs text-warm-gray">Off by default.</span></span></label>
                </div>
            </section>

            <div class="sticky bottom-4 z-10 flex justify-end">
                <button class="btn-primary px-8 shadow-dropdown">Save profile</button>
            </div>
        </form>

        <aside class="space-y-6 lg:sticky lg:top-32 lg:self-start">
            <section class="card p-5">
                <h2 class="text-base font-semibold text-ink">Profile strength</h2>
                <p class="mt-2 text-3xl font-bold text-brand">{{ $completeness['percent'] }}%</p>
                <div class="mt-2 h-2 rounded-full bg-brand-50"><div class="h-2 rounded-full bg-brand" style="width: {{ $completeness['percent'] }}%"></div></div>
                @if ($completeness['missing'])
                    <ul class="mt-4 space-y-1.5 text-sm text-warmer-gray">
                        @foreach ($completeness['missing'] as $item)<li>○ {{ $item }}</li>@endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-accent-green-dark">Your profile is complete — great!</p>
                @endif
            </section>
            <section class="card p-5 text-sm text-warm-gray">
                <h2 class="text-base font-semibold text-ink">Your CV</h2>
                <p class="mt-2">Your PDF CV is generated from this profile plus your approved hours, completed opportunities and endorsements.</p>
                <a href="{{ route('volunteers.cv', $p) }}" target="_blank" class="btn-secondary btn-sm mt-3">Preview PDF CV</a>
            </section>
        </aside>
    </div>
@endsection
