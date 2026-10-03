@extends('layouts.admin')
@section('title', 'Media & files')
@section('heading', 'Media & files')

@section('content')
    <div class="mb-6 max-w-2xl">
        <p class="text-sm text-warm-gray">
            Upload files such as PDFs, images and documents. Each file gets a public URL you can paste into a
            page or post, for example to link a policy document.
        </p>
    </div>

    {{-- Upload form --}}
    <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="card mb-8 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
            <div>
                <label for="file" class="mb-1 block font-ui text-sm font-medium text-ink">File</label>
                <input id="file" name="file" type="file" required
                       class="block w-full text-sm text-warm-gray file:mr-4 file:rounded-lg file:border-0 file:bg-brand file:px-4 file:py-2 file:font-ui file:text-sm file:font-semibold file:text-cream hover:file:bg-charcoal" />
                <p class="mt-1 text-xs text-warm-gray">PDF, images, Office documents, CSV, TXT, MD or ZIP. Max 20&nbsp;MB.</p>
            </div>
            <div>
                <label for="name" class="mb-1 block font-ui text-sm font-medium text-ink">Title <span class="text-warm-gray">(optional)</span></label>
                <input id="name" name="name" type="text" maxlength="150" value="{{ old('name') }}" placeholder="Defaults to the file name"
                       class="w-full rounded-lg border-light-gray text-sm focus:border-brand focus:ring-brand sm:w-64" />
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-primary">Upload file</button>
        </div>
    </form>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-warm-white font-ui text-xs uppercase tracking-wide text-warm-gray">
                <tr>
                    <th class="px-6 py-3">File</th>
                    <th class="px-6 py-3">Type</th>
                    <th class="px-6 py-3">Size</th>
                    <th class="px-6 py-3">URL</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
                @forelse ($media as $item)
                    <tr x-data="{ copied: false }">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if ($item->isImage())
                                    <img src="{{ $item->url() }}" alt="" class="h-10 w-10 rounded object-cover ring-1 ring-light-gray" />
                                @else
                                    <span class="grid h-10 w-10 place-items-center rounded bg-brand-50 font-ui text-[10px] font-bold uppercase text-brand">
                                        {{ pathinfo($item->file_name, PATHINFO_EXTENSION) ?: 'file' }}
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-ink">{{ $item->name }}</div>
                                    <div class="truncate font-mono text-xs text-warm-gray">{{ $item->file_name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-warm-gray">{{ $item->mime_type ?? '-' }}</td>
                        <td class="px-6 py-4 text-warm-gray">{{ $item->humanSize() }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $item->url() }}"
                                       class="w-48 rounded border-light-gray bg-warm-white px-2 py-1 font-mono text-xs text-warm-gray focus:border-brand focus:ring-brand"
                                       @click="$el.select()" />
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $item->url() }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="font-ui text-xs font-semibold text-accent-blue hover:text-accent-blue-dark">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied" x-cloak class="text-accent-green-dark">Copied!</span>
                                </button>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">View</a>
                                <x-admin.delete :action="route('admin.media.destroy', $item)" confirm="Delete this file? Pages linking to it will break." />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-warm-gray">No files uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
