@extends('layouts.admin')
@section('title', 'Skills')
@section('heading', 'Skills')

@php
    $hasFilters = $filters['q'] !== '' || $filters['category'];
    $editingId = (int) old('editing_id');
@endphp

@section('content')
    <p class="mb-6 max-w-3xl text-sm text-warm-gray">
        {{ number_format($total) }} skills shared by volunteer profiles and opportunity requirements. Imports from volunteer.ieee.org add new skills automatically — merge duplicates here so matching and the analytics stay accurate.
    </p>

    <div class="grid gap-6 xl:grid-cols-4">
        <div class="space-y-6 xl:col-span-3">
            {{-- Filters --}}
            <form method="GET" action="{{ route('admin.skills.index') }}" class="card flex flex-wrap items-end gap-4 p-4" aria-label="Filter skills">
                <div class="min-w-[12rem] flex-1">
                    <label for="q" class="label">Search</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Skill name…" class="input">
                </div>
                <div class="w-full sm:w-52">
                    <label for="filter-category" class="label">Category</label>
                    <select id="filter-category" name="category" class="input">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-52">
                    <label for="sort" class="label">Sort by</label>
                    <select id="sort" name="sort" class="input">
                        @foreach (\App\Http\Controllers\Admin\SkillController::SORTS as $key => $label)
                            <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">Apply</button>
                    @if ($hasFilters)<a href="{{ route('admin.skills.index') }}" class="btn-ghost">Clear</a>@endif
                </div>
            </form>

            {{-- Table --}}
            @if ($skills->isEmpty())
                <x-empty-state title="No skills found" icon="✚">
                    {{ $hasFilters ? 'Nothing matches these filters.' : 'Add the first skill with the form on the right.' }}
                </x-empty-state>
            @else
                <datalist id="skill-categories">
                    @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
                </datalist>
                <div class="card overflow-x-auto">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <thead class="table-head">
                            <tr>
                                <th scope="col" class="px-5 py-3">Skill</th>
                                <th scope="col" class="px-5 py-3">Category</th>
                                <th scope="col" class="px-5 py-3 text-right">Volunteers</th>
                                <th scope="col" class="px-5 py-3 text-right">Open opportunities</th>
                                <th scope="col" class="px-5 py-3 text-right">All opportunities</th>
                                <th scope="col" class="px-5 py-3 text-right">Endorsements</th>
                                <th scope="col" class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-light-gray">
                            @foreach ($skills as $skill)
                                @php
                                    $editing = $editingId === $skill->id;
                                    $usage = $skill->users_count.' '.\Illuminate\Support\Str::plural('volunteer', $skill->users_count).' and '.$skill->opportunities_count.' '.\Illuminate\Support\Str::plural('opportunity', $skill->opportunities_count);
                                @endphp
                                <tr x-data="{ editing: {{ $editing ? 'true' : 'false' }} }" :class="{ 'bg-brand-50/50': editing }">
                                    <td class="px-5 py-3">
                                        <span x-show="!editing" class="font-medium text-ink">{{ $skill->name }}</span>
                                        <div x-show="editing" x-cloak>
                                            <label for="skill-name-{{ $skill->id }}" class="sr-only">Name</label>
                                            <input id="skill-name-{{ $skill->id }}" name="name" form="edit-skill-{{ $skill->id }}" class="input py-1.5 text-sm"
                                                   value="{{ $editing ? old('name') : $skill->name }}" maxlength="120" required>
                                            @if ($editing)<x-input-error :messages="$errors->get('name')" class="mt-1" />@endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span x-show="!editing" class="text-warmer-gray">{{ $skill->category ?? '—' }}</span>
                                        <div x-show="editing" x-cloak>
                                            <label for="skill-category-{{ $skill->id }}" class="sr-only">Category</label>
                                            <input id="skill-category-{{ $skill->id }}" name="category" form="edit-skill-{{ $skill->id }}" list="skill-categories"
                                                   class="input py-1.5 text-sm" value="{{ $editing ? old('category') : $skill->category }}" maxlength="60">
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ number_format($skill->users_count) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ number_format($skill->open_opportunities_count) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-warm-gray">{{ number_format($skill->opportunities_count) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-warm-gray">{{ number_format($skill->endorsements_count) }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-3" x-show="!editing">
                                            <button type="button" @click="editing = true; $nextTick(() => document.getElementById('skill-name-{{ $skill->id }}').focus())"
                                                    class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Edit</button>
                                            <form method="POST" action="{{ route('admin.skills.destroy', $skill) }}" class="inline"
                                                  onsubmit="return confirm(@js('Delete “'.$skill->name.'”? It is used by '.$usage.' and will be removed from all of them. Merging keeps those links.'))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-ui text-sm font-medium text-red-600 hover:text-red-700">Delete</button>
                                            </form>
                                        </div>
                                        <form id="edit-skill-{{ $skill->id }}" method="POST" action="{{ route('admin.skills.update', $skill) }}"
                                              class="flex items-center justify-end gap-2" x-show="editing" x-cloak>
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="editing_id" value="{{ $skill->id }}">
                                            <button type="submit" class="btn-primary btn-sm">Save</button>
                                            <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div>{{ $skills->links() }}</div>
            @endif
        </div>

        {{-- Side tools --}}
        <aside class="space-y-6">
            <section class="card p-5" aria-labelledby="add-heading">
                <h2 id="add-heading" class="text-base font-semibold text-ink">Add a skill</h2>
                <form method="POST" action="{{ route('admin.skills.store') }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label for="new-name" class="label">Name</label>
                        <input id="new-name" name="name" class="input" maxlength="120" required value="{{ $editingId ? '' : old('name') }}">
                        @unless ($editingId)<x-input-error :messages="$errors->get('name')" class="mt-1" />@endunless
                    </div>
                    <div>
                        <label for="new-category" class="label">Category <span class="font-normal text-warm-gray">(optional)</span></label>
                        <input id="new-category" name="category" class="input" maxlength="60" list="skill-categories-all" value="{{ $editingId ? '' : old('category') }}">
                        <datalist id="skill-categories-all">
                            @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
                        </datalist>
                    </div>
                    <button type="submit" class="btn-primary w-full">Add skill</button>
                </form>
            </section>

            <section class="card p-5" aria-labelledby="merge-heading">
                <h2 id="merge-heading" class="text-base font-semibold text-ink">Merge duplicates</h2>
                <p class="mt-1 text-xs text-warm-gray">Every volunteer, opportunity and endorsement using the first skill moves to the second, then the first is deleted.</p>
                <form method="POST" action="{{ route('admin.skills.merge') }}" class="mt-3 space-y-3"
                      onsubmit="return confirm('Merge these skills? This cannot be undone.')">
                    @csrf
                    <div>
                        <label for="source_id" class="label">Merge this skill…</label>
                        <select id="source_id" name="source_id" class="input" required>
                            <option value="">Choose a skill</option>
                            @foreach ($allSkills as $option)
                                <option value="{{ $option->id }}" @selected((int) old('source_id') === $option->id)>{{ $option->name }} ({{ $option->users_count }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('source_id')" class="mt-1" />
                    </div>
                    <div>
                        <label for="target_id" class="label">…into this one</label>
                        <select id="target_id" name="target_id" class="input" required>
                            <option value="">Choose a skill</option>
                            @foreach ($allSkills as $option)
                                <option value="{{ $option->id }}" @selected((int) old('target_id') === $option->id)>{{ $option->name }} ({{ $option->users_count }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('target_id')" class="mt-1" />
                    </div>
                    <button type="submit" class="btn-secondary w-full">Merge skills</button>
                </form>
            </section>

            <section class="card p-5" aria-labelledby="csv-heading">
                <h2 id="csv-heading" class="text-base font-semibold text-ink">CSV import &amp; export</h2>
                <a href="{{ route('admin.skills.export') }}" class="btn-secondary mt-3 w-full"><span aria-hidden="true">⤓</span> Export CSV</a>
                <form method="POST" action="{{ route('admin.skills.import') }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-light-gray pt-4">
                    @csrf
                    <div>
                        <label for="file" class="label">Import CSV</label>
                        <input id="file" name="file" type="file" accept=".csv,text/csv" required
                               class="block w-full text-sm text-warmer-gray file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-ui file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                        <p class="help">Columns: <code class="font-mono">name</code>, optional <code class="font-mono">category</code>. Missing skills are created; existing ones get the category from the file.</p>
                        <x-input-error :messages="$errors->get('file')" class="mt-1" />
                    </div>
                    <button type="submit" class="btn-secondary w-full">Import</button>
                </form>
            </section>
        </aside>
    </div>
@endsection
