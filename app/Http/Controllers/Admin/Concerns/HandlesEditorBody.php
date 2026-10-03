<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\EditorJsRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesEditorBody
{
    /**
     * Decode the EditorJS payload and render it into the model's body + editor_data.
     * Works for any model exposing `editor_data` + `body` (Post, Page, EmailTemplate…).
     *
     * No-op when the payload is absent, empty, or unparseable, so a JS-disabled
     * submit (or a failed editor boot) can never wipe existing content.
     */
    protected function applyEditorBody(Model $model, Request $request): void
    {
        $raw = $request->input('editor_data');
        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['blocks'])) {
            return;
        }

        $model->editor_data = $data;
        $model->body = clean(EditorJsRenderer::toHtml($data), 'content');
    }
}
