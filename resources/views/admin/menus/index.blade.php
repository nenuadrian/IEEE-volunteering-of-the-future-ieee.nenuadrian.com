@extends('layouts.admin')
@section('title', 'Menus')
@section('heading', 'Navigation menus')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-warm-gray">Manage the links shown in the site header and footer.</p>
        <a href="{{ route('admin.menus.create') }}" class="btn-primary">+ Add menu item</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['Header' => $header, 'Footer' => $footer] as $label => $items)
            <div class="card overflow-hidden">
                <div class="border-b border-light-gray bg-warm-white px-6 py-3 font-heading font-bold text-charcoal">{{ $label }} menu</div>
                <ul class="divide-y divide-light-gray">
                    @forelse ($items as $item)
                        <li class="flex items-center justify-between px-6 py-3">
                            <div>
                                <p class="font-medium text-ink">{{ $item->label }}
                                    @unless ($item->is_active)<span class="chip ml-2 bg-light-gray text-warm-gray">hidden</span>@endunless
                                </p>
                                <p class="font-mono text-xs text-warm-gray">{{ $item->link_type }}: {{ $item->value }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.menus.edit', $item) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Edit</a>
                                <x-admin.delete :action="route('admin.menus.destroy', $item)" confirm="Remove this menu item?" label="Remove" />
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-center text-sm text-warm-gray">No items.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
@endsection
