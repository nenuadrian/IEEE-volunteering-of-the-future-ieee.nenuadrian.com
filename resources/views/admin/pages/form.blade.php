@extends('layouts.admin')
@section('title', $page->exists ? 'Edit page' : 'New page')
@section('heading', $page->exists ? 'Edit page' : 'New page')

@section('content')
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
        @csrf
        @if ($page->exists) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card p-6 lg:col-span-2">
                <label class="label" for="title">Title <span class="text-red-500">*</span></label>
                <input id="title" name="title" value="{{ old('title', $page->title) }}" required class="input">
                <x-input-error :messages="$errors->get('title')" class="mt-1" />

                <label class="label mt-5" for="slug">URL slug</label>
                <div class="flex items-center gap-1">
                    <span class="font-mono text-sm text-warm-gray">/</span>
                    <input id="slug" name="slug" value="{{ old('slug', $page->slug) }}" class="input font-mono text-sm" placeholder="auto-generated from title">
                </div>
                <x-input-error :messages="$errors->get('slug')" class="mt-1" />

                <p class="label mt-5">Body</p>
                @include('admin.partials.editor', ['model' => $page])
            </div>

            <div class="space-y-6">
                <div class="card h-fit p-6">
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="input">
                        @foreach (['draft' => 'Draft', 'published' => 'Published'] as $val => $lbl)
                            <option value="{{ $val }}" @selected(old('status', $page->status ?? 'published') === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>

                    <div class="mt-6 flex gap-2">
                        <button class="btn-primary flex-1">{{ $page->exists ? 'Update' : 'Create' }}</button>
                        <a href="{{ route('admin.pages.index') }}" class="btn-ghost">Cancel</a>
                    </div>
                </div>

                @include('admin.partials.seo-fields', ['model' => $page])
            </div>
        </div>
    </form>
@endsection
