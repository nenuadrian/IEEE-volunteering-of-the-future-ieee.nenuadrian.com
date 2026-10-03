@extends('layouts.public')
@section('title', $opportunity->exists ? 'Edit opportunity' : 'Create opportunity')
@section('robots_noindex', '1')

@php
    $o = $opportunity;
    $steps = ['Basics', 'Where & who', 'Skills', 'Schedule & team'];
    $fieldSteps = [
        'title' => 0, 'category_id' => 0, 'description' => 0, 'details_url' => 0, 'thumbnail' => 0,
        'is_online' => 1, 'location' => 1, 'city' => 1, 'country' => 1, 'region' => 1, 'section' => 1, 'organizational_unit' => 1, 'society' => 1, 'membership_grades' => 1,
        'skills' => 2, 'experience_level' => 2, 'upskills' => 2, 'ideal_traits' => 2,
        'start_date' => 3, 'end_date' => 3, 'project_size' => 3, 'hours_estimate' => 3, 'hours_frequency' => 3, 'volunteers_needed' => 3, 'co_owners' => 3,
    ];
    $errorStep = collect($errors->keys())->map(fn ($k) => $fieldSteps[explode('.', $k)[0]] ?? null)->filter(fn ($v) => $v !== null)->min() ?? 0;
    $selectedSkills = old('skills', $o->exists ? $o->skills->pluck('id')->map(fn ($id) => (string) $id)->all() : []);
    $grades = old('membership_grades', $o->membership_grades ?? []);
    $upskills = old('upskills', $o->upskills ?? []);
    $sectionsByRegion = collect(config('volunteering.sections'))->map(fn ($s) => array_keys($s));
    $myPast = $o->exists ? collect() : \App\Models\Opportunity::ownedBy(auth()->user())->whereNot('status', 'draft')->latest()->take(5)->get();
@endphp

@section('content')
    <x-page-header :title="$o->exists ? 'Edit opportunity' : 'Create opportunity'"
                   :subtitle="$o->exists ? $o->title : 'Four short steps. Fields marked * are required — everything else helps volunteers decide.'">
        @if ($o->exists && $o->isImported())
            <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                This opportunity is synced from volunteer.ieee.org. Edits made here may be overwritten by the next daily refresh — change the original listing for permanent updates.
            </p>
        @endif
    </x-page-header>

    <section class="container-x max-w-4xl py-8">
        @if ($myPast->isNotEmpty())
            <details class="card mb-6 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-brand">Running something again? Start from one of your previous opportunities</summary>
                <ul class="mt-3 divide-y divide-light-gray">
                    @foreach ($myPast as $past)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="min-w-0 truncate text-warmer-gray">{{ $past->title }} <span class="text-xs text-warm-gray">· {{ $past->created_at->format('M Y') }}</span></span>
                            <form method="POST" action="{{ route('opportunities.clone', $past) }}">@csrf<button class="btn-secondary btn-sm">Clone</button></form>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <form method="POST" enctype="multipart/form-data" novalidate
              action="{{ $o->exists ? route('opportunities.update', $o) : route('opportunities.store') }}"
              x-data="{ step: {{ $errorStep }}, total: {{ count($steps) }}, online: {{ old('is_online', $o->is_online) ? 'true' : 'false' }}, region: @js(old('region', $o->region)), sections: @js($sectionsByRegion) }"
              @keydown.enter="if ($event.target.tagName === 'INPUT' && $event.target.type !== 'submit') $event.preventDefault()">
            @csrf
            @if ($o->exists) @method('PUT') @endif

            {{-- Progress --}}
            <ol class="mb-6 grid grid-cols-4 gap-2" aria-label="Form steps">
                @foreach ($steps as $i => $label)
                    <li>
                        <button type="button" @click="step = {{ $i }}" class="w-full text-left" :aria-current="step === {{ $i }} ? 'step' : null">
                            <span class="block h-1.5 rounded-full" :class="step >= {{ $i }} ? 'bg-brand' : 'bg-light-gray'"></span>
                            <span class="mt-2 block text-xs font-semibold sm:text-sm" :class="step === {{ $i }} ? 'text-brand' : 'text-warm-gray'">
                                <span class="hidden sm:inline">{{ $i + 1 }}.</span> {{ $label }}
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <div class="card p-6 sm:p-8">
                {{-- Step 1: Basics --}}
                <div x-show="step === 0" class="space-y-5">
                    <div>
                        <label class="label" for="title">Title *</label>
                        <input id="title" name="title" value="{{ old('title', $o->title) }}" maxlength="200" required class="input" placeholder="e.g. Social Media Coordinator — IEEE YP Region 8">
                        <p class="help">Lead with the role, then the IEEE unit or event.</p>
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="category_id">Category *</label>
                        <select id="category_id" name="category_id" required class="input">
                            <option value="">Choose a category…</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $o->category_id) === (string) $category->id)>{{ $category->name }} — {{ \Illuminate\Support\Str::limit($category->description, 70) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="description">Description *</label>
                        <textarea id="description" name="description" rows="9" required minlength="30" maxlength="10000" class="input" placeholder="What is the opportunity, what will the volunteer do, and why does it matter?">{{ old('description', $o->description) }}</textarea>
                        <p class="help">Good descriptions cover the goal, the tasks, who they'll work with and what support you provide. Line breaks are kept.</p>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="label" for="details_url">More details (link)</label>
                            <input id="details_url" name="details_url" type="url" value="{{ old('details_url', $o->details_url) }}" class="input" placeholder="https://…">
                            <x-input-error :messages="$errors->get('details_url')" class="mt-1" />
                        </div>
                        <div>
                            <label class="label" for="thumbnail">Thumbnail image</label>
                            <input id="thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-warm-gray file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                            <p class="help">JPG, PNG or WebP, up to 4 MB. Optional — we show a category graphic otherwise.</p>
                            @if ($o->thumbnail_path)
                                <label class="mt-2 inline-flex items-center gap-2 text-xs text-warm-gray"><input type="checkbox" name="remove_thumbnail" value="1" class="checkbox"> Remove current image</label>
                            @endif
                            <x-input-error :messages="$errors->get('thumbnail')" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- Step 2: Where & who --}}
                <div x-show="step === 1" x-cloak class="space-y-5">
                    <label class="flex items-start gap-3 rounded-lg border border-light-gray p-4">
                        <input type="checkbox" name="is_online" value="1" x-model="online" class="checkbox mt-0.5" @checked(old('is_online', $o->is_online))>
                        <span><span class="block text-sm font-semibold text-ink">This can be done online</span>
                            <span class="block text-xs text-warm-gray">Online opportunities are open to volunteers from every region.</span></span>
                    </label>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <div class="sm:col-span-3">
                            <label class="label" for="location">Location <span x-show="!online">*</span></label>
                            <input id="location" name="location" value="{{ old('location', $o->location) }}" class="input" placeholder="City, state/province, country" :required="!online">
                            <x-input-error :messages="$errors->get('location')" class="mt-1" />
                        </div>
                        <div>
                            <label class="label" for="city">City</label>
                            <input id="city" name="city" value="{{ old('city', $o->city) }}" class="input">
                        </div>
                        <div>
                            <label class="label" for="country">Country</label>
                            <input id="country" name="country" value="{{ old('country', $o->country) }}" class="input">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label" for="latitude">Lat.</label>
                                <input id="latitude" name="latitude" type="number" step="any" value="{{ old('latitude', $o->latitude) }}" class="input">
                            </div>
                            <div>
                                <label class="label" for="longitude">Long.</label>
                                <input id="longitude" name="longitude" type="number" step="any" value="{{ old('longitude', $o->longitude) }}" class="input">
                            </div>
                        </div>
                    </div>
                    <p class="help -mt-3">Coordinates are optional — they place the opportunity on the map view.</p>

                    <div class="rounded-lg bg-warm-white p-4 text-xs text-warm-gray">
                        <strong class="text-warmer-gray">How IEEE is organised:</strong> the world is divided into 10 <em>regions</em>; each region has local <em>sections</em>;
                        sections host chapters and affinity groups (<em>organizational units</em>). <em>Societies, councils and committees</em> are global technical or affinity communities.
                        Fill in whatever applies — all optional.
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="label" for="region">IEEE region</label>
                            <select id="region" name="region" x-model="region" class="input">
                                <option value="">Not region-specific</option>
                                @foreach (config('volunteering.regions') as $code => $label)
                                    <option value="{{ $code }}" @selected(old('region', $o->region) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="section">Section / council</label>
                            <input id="section" name="section" list="section-options" value="{{ old('section', $o->section) }}" class="input" placeholder="e.g. Bangalore">
                            <datalist id="section-options">
                                <template x-for="s in (sections[region] || [])" :key="s"><option :value="s"></option></template>
                            </datalist>
                        </div>
                        <div>
                            <label class="label" for="organizational_unit">Organizational unit</label>
                            <input id="organizational_unit" name="organizational_unit" value="{{ old('organizational_unit', $o->organizational_unit) }}" class="input" placeholder="e.g. Delhi Section YP Affinity Group">
                        </div>
                        <div>
                            <label class="label" for="society">Society, technical council or committee</label>
                            <input id="society" name="society" list="society-options" value="{{ old('society', $o->society) }}" class="input">
                            <datalist id="society-options">
                                @foreach (config('volunteering.societies') as $s)<option value="{{ $s }}"></option>@endforeach
                            </datalist>
                        </div>
                    </div>

                    <fieldset>
                        <legend class="label">Eligible membership grades</legend>
                        <p class="help -mt-1 mb-2">Leave all unticked if anyone can apply.</p>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label class="flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="membership_grades[]" value="_ALL" class="checkbox" @checked(in_array('_ALL', $grades, true))> All grades</label>
                            @foreach (config('volunteering.membership_grades') as $code => $label)
                                <label class="flex items-center gap-2 text-sm text-warmer-gray"><input type="checkbox" name="membership_grades[]" value="{{ $code }}" class="checkbox" @checked(in_array($code, $grades, true))> {{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                {{-- Step 3: Skills --}}
                <div x-show="step === 2" x-cloak class="space-y-5" id="skills">
                    <div>
                        <label class="label">Skills required * <span class="font-normal text-warm-gray">(up to 15)</span></label>
                        <x-skill-picker name="skills" :options="$skillOptions" :selected="$selectedSkills" :allow-create="true" :max="15" placeholder="Type to search, or add a new skill…" />
                        <p class="help">Volunteers with these skills see a higher match score.</p>
                        <x-input-error :messages="$errors->get('skills')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="experience_level">Experience level *</label>
                        <select id="experience_level" name="experience_level" required class="input">
                            @foreach (config('volunteering.experience_levels') as $level => $hint)
                                <option value="{{ $level }}" @selected(old('experience_level', $o->experience_level ?? 'Some experience') === $level)>{{ $level }} — {{ $hint }}</option>
                            @endforeach
                        </select>
                    </div>
                    <fieldset>
                        <legend class="label">Skills volunteers will build</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (config('volunteering.upskills') as $upskill => $hint)
                                <label class="flex items-start gap-3 rounded-lg border border-light-gray p-3">
                                    <input type="checkbox" name="upskills[]" value="{{ $upskill }}" class="checkbox mt-0.5" @checked(in_array($upskill, $upskills, true))>
                                    <span><span class="block text-sm font-semibold text-ink">{{ $upskill }}</span><span class="block text-xs text-warm-gray">{{ $hint }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div>
                        <label class="label" for="ideal_traits">Traits of an ideal candidate</label>
                        <input id="ideal_traits" name="ideal_traits" maxlength="255" value="{{ old('ideal_traits', $o->ideal_traits) }}" class="input" placeholder="e.g. Reliable, detail-oriented, comfortable presenting to large groups">
                        <p class="help">Up to 255 characters.</p>
                    </div>
                </div>

                {{-- Step 4: Schedule & team --}}
                <div x-show="step === 3" x-cloak class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="label" for="start_date">Start date *</label>
                            <input id="start_date" name="start_date" type="date" required value="{{ old('start_date', $o->start_date?->toDateString()) }}" class="input">
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>
                        <div>
                            <label class="label" for="end_date">End date *</label>
                            <input id="end_date" name="end_date" type="date" required value="{{ old('end_date', $o->end_date?->toDateString()) }}" class="input">
                            <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                        </div>
                        <div>
                            <label class="label" for="project_size">Duration *</label>
                            <select id="project_size" name="project_size" required class="input">
                                @foreach (config('volunteering.project_sizes') as $size => $hint)
                                    <option value="{{ $size }}" @selected(old('project_size', $o->project_size ?? 'Small project') === $size)>{{ $size }} — {{ $hint }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="hours_estimate">Estimated time</label>
                            <div class="flex gap-2">
                                <input id="hours_estimate" name="hours_estimate" type="number" min="1" max="5000" value="{{ old('hours_estimate', $o->hours_estimate) }}" class="input w-28" placeholder="Hours">
                                <select name="hours_frequency" aria-label="Frequency" class="input">
                                    @foreach (config('volunteering.hours_frequencies') as $key => $label)
                                        <option value="{{ $key }}" @selected(old('hours_frequency', $o->hours_frequency ?? 'overall') === $key)>hours {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label" for="volunteers_needed">Number of volunteers needed *</label>
                            <input id="volunteers_needed" name="volunteers_needed" type="number" min="1" max="1000" required value="{{ old('volunteers_needed', $o->volunteers_needed ?? 1) }}" class="input">
                        </div>
                    </div>

                    <div class="border-t border-light-gray pt-5"
                         x-data="userPicker(@js(route('lookup.users')), @js($coOwners), {{ config('volunteering.max_owners') - 1 }}, [{{ $ownerId }}])">
                        <label class="label" for="coowner-search">Co-owners <span class="font-normal text-warm-gray">(up to {{ config('volunteering.max_owners') - 1 }})</span></label>
                        <p class="help -mt-1 mb-2">Co-owners can edit, review applicants, approve hours and see impact. They are notified by email.</p>
                        <template x-for="p in people" :key="p.id"><input type="hidden" name="co_owners[]" :value="p.id"></template>
                        <ul class="mb-3 flex flex-wrap gap-2" x-show="people.length">
                            <template x-for="p in people" :key="'chip' + p.id">
                                <li class="inline-flex items-center gap-2 rounded-full border border-light-gray bg-white py-1 pl-1 pr-2 text-sm">
                                    <span class="grid h-7 w-7 place-items-center rounded-full bg-brand text-xs font-bold text-white" x-text="p.initials"></span>
                                    <span x-text="p.name"></span>
                                    <button type="button" @click="remove(p.id)" class="text-warm-gray hover:text-red-600" :aria-label="'Remove ' + p.name">&times;</button>
                                </li>
                            </template>
                        </ul>
                        <div class="relative" @click.outside="open = false">
                            <input id="coowner-search" type="search" x-model="query" @input="search()" @focus="open = results.length > 0" class="input" placeholder="Search people by name or exact email…" autocomplete="off" :disabled="people.length >= max">
                            <ul x-show="open && results.length" x-cloak class="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-light-gray bg-white shadow-dropdown">
                                <template x-for="r in results" :key="r.id">
                                    <li><button type="button" @click="add(r)" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-brand-50">
                                        <span class="grid h-8 w-8 place-items-center rounded-full bg-accent-blue text-xs font-bold text-white" x-text="r.initials"></span>
                                        <span><span class="block text-sm font-semibold text-ink" x-text="r.name"></span><span class="block text-xs text-warm-gray" x-text="r.meta"></span></span>
                                    </button></li>
                                </template>
                            </ul>
                            <p x-show="loading" x-cloak class="help">Searching…</p>
                        </div>
                        <x-input-error :messages="$errors->get('co_owners')" class="mt-1" />
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-light-gray pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-2">
                        <a href="{{ $o->exists ? route('opportunities.manage', $o) : route('opportunities.index') }}" class="btn-ghost">Cancel</a>
                        <button type="button" class="btn-ghost" x-show="step > 0" x-cloak @click="step--">&larr; Back</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if (! $o->exists || $o->isDraft())
                            <button name="action" value="draft" class="btn-secondary">Save draft</button>
                        @endif
                        <button type="button" class="btn-primary" x-show="step < total - 1" @click="step++">Next: <span x-text="['Where & who', 'Skills', 'Schedule & team'][step]"></span> &rarr;</button>
                        <button name="action" value="{{ $o->exists && ! $o->isDraft() ? 'save' : 'publish' }}" class="btn-primary" x-show="step === total - 1" x-cloak>
                            {{ $o->exists && ! $o->isDraft() ? 'Save changes' : 'Publish opportunity' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </section>
@endsection
