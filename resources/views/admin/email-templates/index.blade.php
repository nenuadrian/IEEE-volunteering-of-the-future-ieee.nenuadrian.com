@extends('layouts.admin')
@section('title', 'Email templates')
@section('heading', 'Email templates')

@section('content')
    <p class="mb-6 max-w-2xl text-sm text-warm-gray">
        The automated emails the site sends. Edit the wording, subject line and button text.
        The Society branding and layout are applied automatically.
    </p>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-warm-white font-ui text-xs uppercase tracking-wide text-warm-gray">
                <tr>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Subject</th>
                    <th class="px-6 py-3">Updated</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
                @forelse ($templates as $template)
                    <tr>
                        <td class="px-6 py-4 font-medium text-ink">{{ $template->name }}</td>
                        <td class="px-6 py-4 text-warm-gray">{{ $template->subject }}</td>
                        <td class="px-6 py-4 text-warm-gray">{{ optional($template->updated_at)->format('j M Y') ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.email-templates.preview', $template) }}" target="_blank" rel="noopener" class="font-ui text-sm font-medium text-warm-gray hover:text-brand">Preview</a>
                                <a href="{{ route('admin.email-templates.edit', $template) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-warm-gray">No email templates found. Run <code class="font-mono">php artisan db:seed --class=EmailTemplateSeeder</code> to create them.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
