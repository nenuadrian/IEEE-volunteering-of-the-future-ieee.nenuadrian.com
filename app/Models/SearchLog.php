<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'scope', 'query', 'filters', 'results_count', 'created_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'created_at' => 'datetime', 'results_count' => 'integer'];
    }

    /**
     * Log a search when the visitor actually searched or filtered. Bare page
     * loads and pagination clicks are skipped so the analytics stay meaningful.
     */
    public static function capture(string $scope, ?string $query, array $filters, int $results): void
    {
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== []);

        if (blank($query) && $filters === []) {
            return;
        }

        if ((int) request()->query('page', 1) > 1) {
            return;
        }

        static::create([
            'user_id' => auth()->id(),
            'scope' => $scope,
            'query' => $query ? mb_substr(mb_strtolower(trim($query)), 0, 200) : null,
            'filters' => $filters ?: null,
            'results_count' => $results,
            'created_at' => now(),
        ]);
    }
}
