@extends('layouts.admin')
@section('title', 'Edit '.$emailTemplate->name.' email')
@section('heading', 'Edit the “'.$emailTemplate->name.'” email')

@php
    $lb = '{'.'{';
    $rb = '}'.'}';
@endphp

@section('content')
    <form method="POST" action="{{ route('admin.email-templates.update', $emailTemplate) }}">
        @csrf
        @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="card p-6">
                    <label class="label" for="subject">Subject line <span class="text-red-500">*</span></label>
                    <input id="subject" name="subject" value="{{ old('subject', $emailTemplate->subject) }}" required maxlength="200" class="input">
                    <x-input-error :messages="$errors->get('subject')" class="mt-1" />

                    <p class="label mt-5">Body</p>
                    @include('admin.partials.editor', ['model' => $emailTemplate])

                    <label class="label mt-5" for="button_label">Button label</label>
                    <input id="button_label" name="button_label" value="{{ old('button_label', $emailTemplate->button_label) }}" maxlength="60" class="input">
                    <p class="mt-1 text-xs text-warm-gray">Text on the call-to-action button. The button and its link are added automatically below your message.</p>
                    <x-input-error :messages="$errors->get('button_label')" class="mt-1" />
                </div>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <div class="flex flex-col gap-2">
                        <button class="btn-primary w-full">Save changes</button>
                        <a href="{{ route('admin.email-templates.preview', $emailTemplate) }}" target="_blank" rel="noopener" class="btn-ghost w-full text-center">Preview in new tab</a>
                        <a href="{{ route('admin.email-templates.index') }}" class="btn-ghost w-full text-center">Cancel</a>
                    </div>
                </div>

                <div class="card p-6">
                    <p class="label">Available placeholders</p>
                    <p class="mt-1 text-xs text-warm-gray">Use these in the subject or body: each is replaced when the email is sent.</p>
                    <ul class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($placeholders as $token)
                            <li><code class="rounded bg-brand-50 px-1.5 py-0.5 font-mono text-xs text-brand">{{ $lb.' '.$token.' '.$rb }}</code></li>
                        @endforeach
                    </ul>
                    @if (in_array('action_url', $placeholders, true))
                        <p class="mt-3 text-xs text-warm-gray"><code class="font-mono text-brand">{{ $lb.' action_url '.$rb }}</code> is also rendered as the button, so the essential link is always included even if you don’t add it to the text.</p>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- Separate form: nested forms aren't allowed. --}}
    <form method="POST" action="{{ route('admin.email-templates.test', $emailTemplate) }}" class="mt-6">
        @csrf
        <button class="btn-secondary">Send a test to {{ auth()->user()->email }}</button>
    </form>
@endsection
