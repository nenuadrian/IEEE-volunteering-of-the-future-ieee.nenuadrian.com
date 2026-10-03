<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesSeoMeta
{
    /** Validation rules for the per-page SEO override fields. */
    protected function seoRules(): array
    {
        return [
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:200'],
            'seo_image' => ['nullable', 'url', 'max:500'],
            'seo_canonical' => ['nullable', 'url', 'max:500'],
            'seo_noindex' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Merge the submitted SEO override fields into the model's meta['seo'],
     * preserving any other meta keys. Drops the seo block entirely when every
     * field is blank so empty overrides don't linger in the JSON.
     */
    protected function applySeoMeta(Model $model, Request $request): void
    {
        $seo = array_filter([
            'title' => trim((string) $request->input('seo_title')) ?: null,
            'description' => trim((string) $request->input('seo_description')) ?: null,
            'image' => trim((string) $request->input('seo_image')) ?: null,
            'canonical' => trim((string) $request->input('seo_canonical')) ?: null,
            'noindex' => $request->boolean('seo_noindex') ?: null,
        ], fn ($v) => $v !== null);

        $meta = $model->meta ?? [];

        if ($seo === []) {
            unset($meta['seo']);
        } else {
            $meta['seo'] = $seo;
        }

        $model->meta = $meta;
    }
}
