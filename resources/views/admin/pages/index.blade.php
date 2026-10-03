@extends('layouts.admin')
@section('title', 'Pages')
@section('heading', 'Static pages')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-warm-gray">Create custom pages and link them from a menu under <a href="{{ route('admin.menus.index') }}" class="text-accent-blue underline">Menus</a>.</p>
        <a href="{{ route('admin.pages.create') }}" class="btn-primary">+ New page</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-warm-white font-ui text-xs uppercase tracking-wide text-warm-gray">
                <tr><th class="px-6 py-3">Title</th><th class="px-6 py-3">URL</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
                @forelse ($pages as $page)
                    <tr>
                        <td class="px-6 py-4 font-medium text-ink">{{ $page->title }}</td>
                        <td class="px-6 py-4"><a href="{{ url('/'.$page->slug) }}" target="_blank" class="font-mono text-xs text-accent-blue hover:underline">/{{ $page->slug }} ↗</a></td>
                        <td class="px-6 py-4"><x-status-badge :status="$page->status" /></td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.pages.edit', $page) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Edit</a>
                                <x-admin.delete :action="route('admin.pages.destroy', $page)" confirm="Delete this page?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-warm-gray">No pages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $pages->links() }}</div>
@endsection
