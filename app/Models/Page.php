<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Admin-editable static page (About, FAQ, policies…), served at /{slug}. */
class Page extends Model
{
    protected $fillable = ['user_id', 'title', 'slug', 'body', 'editor_data', 'status', 'meta', 'published_at'];

    protected function casts(): array
    {
        return [
            'editor_data' => 'array',
            'meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'page';
        $slug = $base;
        $n = 1;

        while (static::query()->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function meta(string $key, $default = null)
    {
        return data_get($this->meta, $key, $default);
    }

    public function seoTitle(): string
    {
        return $this->meta('seo.title') ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta('seo.description')
            ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->body))), 160);
    }

    public function seoImageUrl(): ?string
    {
        return $this->meta('seo.image');
    }

    public function seoCanonical(): ?string
    {
        return $this->meta('seo.canonical');
    }

    public function seoNoindex(): bool
    {
        return (bool) $this->meta('seo.noindex');
    }
}
