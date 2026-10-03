@props(['name' => 'skills', 'options' => [], 'selected' => [], 'placeholder' => 'Search skills…', 'allowCreate' => false, 'max' => null])

{{--
    Searchable multi-select with chips. $options: [id => label]. When
    allowCreate is on, unknown entries are submitted as "new:<label>" and the
    controller creates the skill.
--}}
@php
    $opts = collect($options)->map(fn ($label, $id) => ['id' => (string) $id, 'label' => $label])->values();
    $sel = collect($selected)->map(fn ($id) => (string) $id)->values();
@endphp
<div x-data="skillPicker(@js($opts), @js($sel), @js($allowCreate), @js($max))" class="relative" @click.outside="open = false">
    <template x-for="id in selected" :key="id">
        <input type="hidden" name="{{ $name }}[]" :value="id">
    </template>

    <div class="input flex min-h-[42px] flex-wrap items-center gap-1.5 px-2 py-1.5" @click="$refs.search.focus()">
        <template x-for="id in selected" :key="'chip-' + id">
            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 py-0.5 pl-2.5 pr-1 text-xs font-semibold text-brand-700">
                <span x-text="labelFor(id)"></span>
                <button type="button" @click.stop="remove(id)" class="grid h-4 w-4 place-items-center rounded-full hover:bg-brand-100" :aria-label="'Remove ' + labelFor(id)">&times;</button>
            </span>
        </template>
        <input x-ref="search" type="text" x-model="query" @focus="open = true" @input="open = true"
               @keydown.enter.prevent="addFirst()" @keydown.backspace="query === '' && selected.length && remove(selected[selected.length - 1])"
               @keydown.escape="open = false"
               class="min-w-[8rem] flex-1 border-0 p-1 text-sm focus:ring-0" placeholder="{{ $placeholder }}" autocomplete="off"
               role="combobox" :aria-expanded="open">
    </div>

    <div x-show="open && (filtered().length || canCreate())" x-cloak
         class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-light-gray bg-white py-1 shadow-dropdown" role="listbox">
        <template x-for="opt in filtered()" :key="opt.id">
            <button type="button" @click="add(opt.id)" class="block w-full px-3 py-1.5 text-left text-sm text-warmer-gray hover:bg-brand-50 hover:text-brand" x-text="opt.label" role="option"></button>
        </template>
        <button type="button" x-show="canCreate()" @click="create()" class="block w-full px-3 py-1.5 text-left text-sm text-accent-blue hover:bg-brand-50">
            + Add “<span x-text="query.trim()"></span>”
        </button>
    </div>
    <p x-show="max && selected.length >= max" x-cloak class="help">You can choose up to <span x-text="max"></span>.</p>
</div>
