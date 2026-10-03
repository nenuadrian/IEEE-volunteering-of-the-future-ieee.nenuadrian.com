<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'location', 'label', 'link_type', 'value',
        'parent_id', 'display_order', 'is_active', 'new_tab',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'new_tab' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('display_order');
    }

    /** Resolve the destination URL for this item based on its link type. */
    public function url(): string
    {
        return match ($this->link_type) {
            'route' => $this->safeRoute($this->value),
            'page' => url('/'.ltrim((string) $this->value, '/')),
            default => $this->value ?: '#',
        };
    }

    private function safeRoute(?string $name): string
    {
        if ($name && \Illuminate\Support\Facades\Route::has($name)) {
            return route($name);
        }

        return '#';
    }
}
