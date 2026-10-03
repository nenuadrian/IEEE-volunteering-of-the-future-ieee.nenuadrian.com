<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One refresh of opportunities from the volunteer.ieee.org public API. */
class SyncRun extends Model
{
    protected $fillable = [
        'source', 'triggered_by', 'status', 'fetched_count', 'created_count', 'updated_count',
        'unchanged_count', 'closed_count', 'error', 'log', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'log' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function durationSeconds(): ?float
    {
        return $this->started_at && $this->finished_at
            ? round($this->started_at->diffInMilliseconds($this->finished_at) / 1000, 1)
            : null;
    }
}
