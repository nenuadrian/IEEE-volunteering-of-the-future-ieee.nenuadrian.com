<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    public const DRAFT = 'draft';

    public const OPEN = 'open';

    public const IN_PROGRESS = 'in_progress';

    public const ON_HOLD = 'on_hold';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** Statuses visible to the public (everything except drafts). */
    public const PUBLIC_STATUSES = [self::OPEN, self::IN_PROGRESS, self::ON_HOLD, self::COMPLETED];

    public const SOURCE_LOCAL = 'local';

    public const SOURCE_IEEE = 'ieee';

    protected $fillable = [
        'slug', 'title', 'description', 'details_url', 'category_id', 'status',
        'is_online', 'location', 'city', 'state', 'country', 'latitude', 'longitude',
        'region', 'section', 'organizational_unit', 'society',
        'experience_level', 'project_size', 'membership_grades', 'upskills', 'ideal_traits',
        'hours_estimate', 'hours_frequency', 'volunteers_needed', 'start_date', 'end_date',
        'thumbnail_url', 'thumbnail_path',
        'source', 'external_id', 'external_creator_id', 'external_status', 'external_synced_at',
        'created_by', 'cloned_from_id', 'views_count', 'is_featured',
        'published_at', 'filled_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'is_featured' => 'boolean',
            'membership_grades' => 'array',
            'upskills' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'volunteers_needed' => 'integer',
            'hours_estimate' => 'integer',
            'views_count' => 'integer',
            'published_at' => 'datetime',
            'filled_at' => 'datetime',
            'closed_at' => 'datetime',
            'external_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($title), 80, '') ?: 'opportunity';
        $slug = $base;
        $n = 1;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    // ----- Relationships -------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'cloned_from_id');
    }

    /** Owner + co-owners; all of them can manage the opportunity. */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'opportunity_owners')
            ->withPivot(['role', 'added_by'])
            ->withTimestamps()
            ->orderByRaw("CASE WHEN opportunity_owners.role = 'owner' THEN 0 ELSE 1 END");
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->orderBy('name');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function hourLogs(): HasMany
    {
        return $this->hasMany(HourLog::class);
    }

    public function endorsements(): HasMany
    {
        return $this->hasMany(Endorsement::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_opportunities');
    }

    // ----- Scopes --------------------------------------------------------

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::PUBLIC_STATUSES);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN);
    }

    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return $query->whereHas('owners', fn ($q) => $q->where('users.id', $id));
    }

    // ----- Permissions ---------------------------------------------------

    public function isOwnedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->relationLoaded('owners')) {
            return $this->owners->contains('id', $user->id);
        }

        return $this->owners()->where('users.id', $user->id)->exists();
    }

    public function canBeManagedBy(?User $user): bool
    {
        return $user && ($user->isAdmin() || $this->isOwnedBy($user));
    }

    // ----- State ---------------------------------------------------------

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isImported(): bool
    {
        return $this->source === self::SOURCE_IEEE;
    }

    public function acceptsApplications(): bool
    {
        return $this->isOpen() && (! $this->end_date || $this->end_date->endOfDay()->isFuture());
    }

    public function statusLabel(): string
    {
        return config('volunteering.opportunity_statuses')[$this->status] ?? Str::headline($this->status);
    }

    /** Count of volunteers currently or previously confirmed on this opportunity. */
    public function confirmedCount(): int
    {
        if (array_key_exists('confirmed_count', $this->attributes)) {
            return (int) $this->attributes['confirmed_count'];
        }

        return $this->applications()->whereIn('status', [Application::ACCEPTED, Application::COMPLETED])->count();
    }

    public function spotsLeft(): int
    {
        return max(0, $this->volunteers_needed - $this->confirmedCount());
    }

    /**
     * Stamp filled_at the first time the opportunity reaches its target, which
     * powers the "time to fill" metric in the admin analytics.
     */
    public function refreshFilledState(): void
    {
        if (! $this->filled_at && $this->confirmedCount() >= max(1, $this->volunteers_needed)) {
            $this->forceFill(['filled_at' => now()])->saveQuietly();
        }
    }

    // ----- Presentation --------------------------------------------------

    public function thumbnail(): ?string
    {
        if ($this->thumbnail_path) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        if ($this->thumbnail_url) {
            // volunteer.ieee.org serves 130px thumbnails through an image
            // resizer; ask it for a sharper rendition.
            return str_replace('max-dim/130x130/filters:quality(50)', 'max-dim/640x400/filters:quality(80)', $this->thumbnail_url);
        }

        return null;
    }

    public function excerpt(int $limit = 180): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($this->description))), $limit);
    }

    public function locationLabel(): string
    {
        if ($this->is_online && blank($this->city) && blank($this->country) && blank($this->location)) {
            return 'Online';
        }

        $place = $this->location ?: implode(', ', array_filter([$this->city, $this->state, $this->country]));

        return trim(($this->is_online ? 'Online · ' : '').$place, ' ·') ?: 'Online';
    }

    public function regionLabel(): ?string
    {
        return $this->region ? (config('volunteering.regions')[$this->region] ?? $this->region) : null;
    }

    public function hoursLabel(): ?string
    {
        if (! $this->hours_estimate) {
            return null;
        }

        $freq = config('volunteering.hours_frequencies')[$this->hours_frequency ?? 'overall'] ?? 'in total';

        return $this->hours_estimate.' '.Str::plural('hour', $this->hours_estimate).' '.$freq;
    }

    public function gradeLabels(): array
    {
        $grades = $this->membership_grades ?? [];
        $map = config('volunteering.membership_grades');

        if (in_array('_ALL', $grades, true)) {
            return ['All membership grades'];
        }

        return array_values(array_map(fn ($g) => $map[$g] ?? $g, $grades));
    }

    /** Whether a user with the given grade is eligible (no restriction = everyone). */
    public function acceptsGrade(?string $grade): bool
    {
        $grades = $this->membership_grades ?? [];

        return empty($grades) || in_array('_ALL', $grades, true) || ($grade && in_array($grade, $grades, true));
    }

    public function externalUrl(): ?string
    {
        return $this->external_id
            ? str_replace(':id', $this->external_id, config('volunteering.api.public_opportunity_url'))
            : null;
    }

    // ----- Cloning -------------------------------------------------------

    /**
     * Create a draft copy owned by $user. Applications, hours and endorsements
     * are not copied; skills and all descriptive fields are. Dates are shifted
     * forward so the copy starts today with the same duration.
     */
    public function cloneFor(User $user): self
    {
        return DB::transaction(function () use ($user) {
            $copy = $this->replicate([
                'slug', 'status', 'source', 'external_id', 'external_creator_id', 'external_status',
                'external_synced_at', 'views_count', 'is_featured', 'published_at', 'filled_at',
                'closed_at', 'created_by', 'cloned_from_id',
            ]);

            $copy->title = Str::limit('Copy of '.$this->title, 200, '');
            $copy->slug = static::generateSlug($copy->title);
            $copy->status = self::DRAFT;
            $copy->source = self::SOURCE_LOCAL;
            $copy->created_by = $user->id;
            $copy->cloned_from_id = $this->id;

            if ($this->start_date && $this->end_date && $this->start_date->isPast()) {
                $days = $this->start_date->diffInDays($this->end_date);
                $copy->start_date = now()->startOfDay();
                $copy->end_date = now()->startOfDay()->addDays((int) $days);
            }

            $copy->save();
            $copy->skills()->sync($this->skills()->pluck('skills.id'));
            $copy->owners()->attach($user->id, ['role' => 'owner', 'added_by' => $user->id]);

            Activity::record('opportunity.cloned', $copy, ['from' => $this->id, 'from_title' => $this->title], $user);

            return $copy;
        });
    }
}
