@extends('layouts.admin')
@section('title', $menu->exists ? 'Edit menu item' : 'New menu item')
@section('heading', $menu->exists ? 'Edit menu item' : 'New menu item')

@section('content')
    <form method="POST" action="{{ $menu->exists ? route('admin.menus.update', $menu) : route('admin.menus.store') }}" class="max-w-xl"
          x-data="{ linkType: '{{ old('link_type', $menu->link_type) }}' }">
        @csrf
        @if ($menu->exists) @method('PUT') @endif

        <div class="card space-y-5 p-6">
            <div>
                <label class="label" for="label">Label <span class="text-red-500">*</span></label>
                <input id="label" name="label" value="{{ old('label', $menu->label) }}" required class="input">
                <x-input-error :messages="$errors->get('label')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label" for="location">Menu</label>
                    <select id="location" name="location" class="input">
                        <option value="header" @selected(old('location', $menu->location) === 'header')>Header</option>
                        <option value="footer" @selected(old('location', $menu->location) === 'footer')>Footer</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="link_type">Link type</label>
                    <select id="link_type" name="link_type" x-model="linkType" class="input">
                        <option value="route">Built-in page</option>
                        <option value="page">Custom page</option>
                        <option value="url">External URL</option>
                    </select>
                </div>
            </div>

            {{-- Value: depends on link type --}}
            <div>
                <label class="label">Links to <span class="text-red-500">*</span></label>

                <select name="value" class="input" x-show="linkType === 'route'" x-cloak>
                    @foreach ($routes as $name => $lbl)
                        <option value="{{ $name }}" @selected(old('value', $menu->value) === $name)>{{ $lbl }}</option>
                    @endforeach
                </select>

                <select name="value" class="input" x-show="linkType === 'page'" x-cloak>
                    @foreach ($pages as $slug => $title)
                        <option value="{{ $slug }}" @selected(old('value', $menu->value) === $slug)>{{ $title }}</option>
                    @endforeach
                </select>

                <input type="text" name="value" class="input" x-show="linkType === 'url'" x-cloak
                       value="{{ old('value', $menu->value) }}" placeholder="https://…">
                <x-input-error :messages="$errors->get('value')" class="mt-1" />
                <p class="mt-1 text-xs text-warm-gray">Only the field for the selected link type is submitted.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label" for="display_order">Order</label>
                    <input id="display_order" name="display_order" type="number" min="0" value="{{ old('display_order', $menu->display_order ?? 0) }}" class="input">
                </div>
                <div class="flex flex-col justify-end gap-2 pb-1">
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active ?? true)) class="rounded border-light-gray text-brand focus:ring-brand"><span class="text-sm text-warmer-gray">Visible</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="new_tab" value="1" @checked(old('new_tab', $menu->new_tab ?? false)) class="rounded border-light-gray text-brand focus:ring-brand"><span class="text-sm text-warmer-gray">Open in new tab</span></label>
                </div>
            </div>

            <div class="flex gap-2 border-t border-light-gray pt-5">
                <button class="btn-primary">{{ $menu->exists ? 'Update' : 'Add' }} item</button>
                <a href="{{ route('admin.menus.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </div>
    </form>

    <script>
        // Ensure only the visible value field is submitted (disable the hidden ones).
        document.querySelector('form').addEventListener('submit', function () {
            this.querySelectorAll('[name="value"]').forEach(function (el) {
                if (el.offsetParent === null) el.disabled = true;
            });
        });
    </script>
@endsection
