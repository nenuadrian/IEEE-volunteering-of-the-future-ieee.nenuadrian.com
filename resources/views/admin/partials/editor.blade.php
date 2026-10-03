@php
    /**
     * Shared block-editor field. Expects $model (a Post). Seeds the hidden
     * editor_data input with this precedence:
     *   1. old('editor_data')  — preserve content across a failed validation
     *   2. saved editor_data   — re-editing a post authored with EditorJS
     *   3. legacy HTML body     — parse existing HTML into editable blocks
     */
    if (filled(old('editor_data'))) {
        $editorSeed = old('editor_data');
    } elseif (filled($model->editor_data ?? null)) {
        $editorSeed = json_encode($model->editor_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (filled($model->body ?? null)) {
        $editorSeed = json_encode(
            \App\Support\HtmlToEditorJs::convert($model->body),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    } else {
        $editorSeed = '';
    }
@endphp

<input type="hidden" name="editor_data" value="{{ $editorSeed }}">
<div data-editorjs
     data-upload-url="{{ $uploadUrl ?? route('admin.editor.upload') }}"
     data-placeholder="Write the content. Press “/” or click + for headings, lists, images, tables, quotes and embeds…"
     class="editor-holder"></div>
<x-input-error :messages="$errors->get('editor_data')" class="mt-1" />
