<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Opportunity type, e.g. Technical, Event-based, Humanitarian. */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'color', 'display_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'display_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);

        return static::query()->where('slug', Str::slug($name))->first()
            ?? static::create(['name' => $name, 'display_order' => (int) static::max('display_order') + 1]);
    }

    public function colorOrDefault(): string
    {
        return $this->color ?: '#e87722';
    }
}
