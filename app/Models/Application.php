<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** A volunteer's application to (and participation in) an opportunity. */
class Application extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const WITHDRAWN = 'withdrawn';

    public const COMPLETED = 'completed';

    /** Statuses meaning the volunteer was taken on. */
    public const CONFIRMED = [self::ACCEPTED, self::COMPLETED];

    protected $fillable = [
        'opportunity_id', 'user_id', 'status', 'motivation', 'owner_note', 'decided_by',
        'decided_at', 'completed_at', 'withdrawn_at', 'owner_rating', 'volunteer_rating',
        'volunteer_feedback', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'completed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'owner_rating' => 'integer',
            'volunteer_rating' => 'integer',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function hourLogs(): HasMany
    {
        return $this->hasMany(HourLog::class)->orderByDesc('worked_on');
    }

    public function endorsement(): HasOne
    {
        return $this->hasOne(Endorsement::class);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CONFIRMED);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACCEPTED;
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, self::CONFIRMED, true);
    }

    public function canLogHours(): bool
    {
        return $this->isConfirmed();
    }

    public function statusLabel(): string
    {
        return config('volunteering.application_statuses')[$this->status] ?? Str::headline($this->status);
    }

    public function approvedHours(): float
    {
        if (array_key_exists('approved_hours', $this->attributes)) {
            return (float) $this->attributes['approved_hours'];
        }

        return (float) $this->hourLogs()->where('status', HourLog::APPROVED)->sum('hours');
    }
}
