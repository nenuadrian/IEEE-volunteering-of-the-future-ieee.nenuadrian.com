@extends('layouts.admin')
@section('title', 'Opportunity types')
@section('heading', 'Opportunity types')

@php($editingId = (int) old('editing_id'))

@section('content')
    <p class="mb-6 max-w-3xl text-sm text-warm-gray">
        Types group opportunities in search filters, on cards and in the analytics. Imports from volunteer.ieee.org create missing types automatically.
        A type that is in use can’t be deleted — deactivate it to stop it being chosen for new opportunities.
    </p>

    @error('category')
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 xl:col-span-2">
            @if ($categories->isEmpty())
                <x-empty-state title="No opportunity types yet" icon="⊞">Add the first one with the form alongside.</x-empty-state>
            @else
                <div class="card relative overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="table-head">
                            <tr>
                                <th scope="col" class="px-5 py-3">Type</th>
                                <th scope="col" class="px-5 py-3 text-right">Order</th>
                                <th scope="col" class="px-5 py-3 text-right">Opportunities</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        @foreach ($categories as $category)
                            @php($editing = $editingId === $category->id)
                            <tbody x-data="{ editing: {{ $editing ? 'true' : 'false' }} }" class="border-t border-light-gray first-of-type:border-t-0">
                                <tr @class(['opacity-70' => ! $category->is_active])>
                                    <td class="px-5 py-4">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full ring-1 ring-black/10" style="background-color: {{ $category->colorOrDefault() }}" aria-hidden="true"></span>
                                            <div class="min-w-0">
                                                <p class="font-medium text-ink">{{ $category->name }} <span class="font-mono text-xs font-normal text-warm-gray">/{{ $category->slug }}</span></p>
                                                <p class="mt-0.5 text-xs text-warm-gray">{{ $category->description ?: 'No description' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right tabular-nums text-warm-gray">{{ $category->display_order }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums">
                                        <a href="{{ route('admin.opportunities.index', ['category' => $category->slug]) }}" class="font-semibold text-ink hover:text-brand">{{ number_format($category->opportunities_count) }}</a>
                                        <span class="block text-xs text-warm-gray">{{ number_format($category->open_opportunities_count) }} open</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <form method="POST" action="{{ route('admin.categories.update', $category) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="toggle_active" value="1">
                                            <button type="submit" role="switch" aria-checked="{{ $category->is_active ? 'true' : 'false' }}"
                                                    aria-label="{{ $category->name }} active" title="{{ $category->is_active ? 'Deactivate' : 'Activate' }}"
                                                    class="inline-flex items-center gap-2 font-ui text-xs font-semibold {{ $category->is_active ? 'text-accent-green-dark' : 'text-warm-gray' }}">
                                                <span @class(['relative inline-flex h-5 w-9 items-center rounded-full transition', 'bg-accent-green' => $category->is_active, 'bg-light-gray' => ! $category->is_active])>
                                                    <span @class(['inline-block h-4 w-4 rounded-full bg-white shadow transition', 'translate-x-4' => $category->is_active, 'translate-x-0.5' => ! $category->is_active])></span>
                                                </span>
                                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <button type="button" @click="editing = !editing" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark" :aria-expanded="editing.toString()">
                                                <span x-text="editing ? 'Close' : 'Edit'">Edit</span>
                                            </button>
                                            @if ($category->opportunities_count === 0)
                                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline"
                                                      onsubmit="return confirm(@js('Delete the “'.$category->name.'” type?'))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="font-ui text-sm font-medium text-red-600 hover:text-red-700">Delete</button>
                                                </form>
                                            @else
                                                <span class="cursor-help font-ui text-sm text-warm-gray/70" title="In use by {{ $category->opportunities_count }} opportunities — deactivate instead">Delete</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="editing" x-cloak class="bg-brand-50/40">
                                    <td colspan="5" class="px-5 py-4">
                                        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="grid gap-4 md:grid-cols-6">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="editing_id" value="{{ $category->id }}">
                                            <div class="md:col-span-2">
                                                <label for="name-{{ $category->id }}" class="label">Name</label>
                                                <input id="name-{{ $category->id }}" name="name" class="input" maxlength="80" required value="{{ $editing ? old('name') : $category->name }}">
                                                @if ($editing)<x-input-error :messages="$errors->get('name')" class="mt-1" />@endif
                                            </div>
                                            <div class="md:col-span-4">
                                                <label for="description-{{ $category->id }}" class="label">Description</label>
                                                <input id="description-{{ $category->id }}" name="description" class="input" maxlength="500" value="{{ $editing ? old('description') : $category->description }}">
                                            </div>
                                            <div>
                                                <label for="color-{{ $category->id }}" class="label">Colour</label>
                                                <input id="color-{{ $category->id }}" name="color" type="color" class="h-10 w-full cursor-pointer rounded-md border border-light-gray bg-white p-1" value="{{ $editing ? old('color', $category->colorOrDefault()) : $category->colorOrDefault() }}">
                                                @if ($editing)<x-input-error :messages="$errors->get('color')" class="mt-1" />@endif
                                            </div>
                                            <div>
                                                <label for="order-{{ $category->id }}" class="label">Order</label>
                                                <input id="order-{{ $category->id }}" name="display_order" type="number" min="0" max="9999" class="input" value="{{ $editing ? old('display_order') : $category->display_order }}">
                                            </div>
                                            <div class="flex items-end md:col-span-2">
                                                <label class="inline-flex items-center gap-2 pb-2 text-sm text-warmer-gray">
                                                    <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($editing ? old('is_active') : $category->is_active)>
                                                    Active (can be chosen for new opportunities)
                                                </label>
                                            </div>
                                            <div class="flex items-end justify-end gap-2 md:col-span-2">
                                                <button type="button" @click="editing = false" class="btn-ghost">Cancel</button>
                                                <button type="submit" class="btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>

        <aside class="min-w-0">
            <section class="card p-5" aria-labelledby="add-type-heading">
                <h2 id="add-type-heading" class="text-base font-semibold text-ink">Add an opportunity type</h2>
                <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label for="new-name" class="label">Name</label>
                        <input id="new-name" name="name" class="input" maxlength="80" required value="{{ $editingId ? '' : old('name') }}">
                        @unless ($editingId)<x-input-error :messages="$errors->get('name')" class="mt-1" />@endunless
                    </div>
                    <div>
                        <label for="new-description" class="label">Description <span class="font-normal text-warm-gray">(optional)</span></label>
                        <textarea id="new-description" name="description" rows="2" class="input" maxlength="500">{{ $editingId ? '' : old('description') }}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="new-color" class="label">Colour</label>
                            <input id="new-color" name="color" type="color" value="{{ $editingId ? '#e87722' : old('color', '#e87722') }}" class="h-10 w-full cursor-pointer rounded-md border border-light-gray bg-white p-1">
                        </div>
                        <div>
                            <label for="new-order" class="label">Order</label>
                            <input id="new-order" name="display_order" type="number" min="0" max="9999" class="input" value="{{ $editingId ? $nextOrder : old('display_order', $nextOrder) }}">
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-warmer-gray">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($editingId || old('name') === null || old('is_active'))>
                        Active
                    </label>
                    <button type="submit" class="btn-primary w-full">Add type</button>
                </form>
            </section>
        </aside>
    </div>
@endsection
