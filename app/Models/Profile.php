<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Public volunteer profile (1:1 with User). Holds everything shown in the
 * volunteer directory, on the profile/CV page and in the PDF CV.
 */
class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'slug', 'headline', 'bio', 'avatar_path', 'ieee_member_number',
        'membership_grade', 'member_since', 'region', 'section', 'society', 'country', 'city',
        'linkedin_url', 'website_url', 'github_url', 'availability', 'hours_per_month',
        'cv_statement', 'is_public', 'show_email',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'show_email' => 'boolean',
            'member_since' => 'integer',
            'hours_per_month' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'volunteer';
        $slug = $base;
        $n = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ----- Presentation --------------------------------------------------

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function regionLabel(): ?string
    {
        return $this->region ? (config('volunteering.regions')[$this->region] ?? $this->region) : null;
    }

    public function gradeLabel(): ?string
    {
        return $this->membership_grade
            ? (config('volunteering.membership_grades')[$this->membership_grade] ?? $this->membership_grade)
            : null;
    }

    public function availabilityLabel(): string
    {
        return config('volunteering.availability')[$this->availability] ?? 'Available';
    }

    public function locationLine(): ?string
    {
        $parts = array_filter([$this->city, $this->country]);

        return $parts ? implode(', ', $parts) : null;
    }

    public function yearsAsMember(): ?int
    {
        return $this->member_since ? max(0, (int) now()->year - $this->member_since) : null;
    }

    /**
     * Profile completeness (0–100) and the missing items, used for the
     * onboarding nudges on the dashboard and profile editor.
     *
     * @return array{percent:int, missing:array<int,string>}
     */
    public function completeness(): array
    {
        $checks = [
            'Add a photo' => filled($this->avatar_path),
            'Write a headline' => filled($this->headline),
            'Write a short bio' => filled($this->bio) && mb_strlen((string) $this->bio) >= 60,
            'Add at least 3 skills' => $this->user?->skills()->count() >= 3,
            'Choose your IEEE region' => filled($this->region),
            'Add your section' => filled($this->section),
            'Set your membership grade' => filled($this->membership_grade),
            'Add a LinkedIn or website link' => filled($this->linkedin_url) || filled($this->website_url) || filled($this->github_url),
        ];

        $done = count(array_filter($checks));

        return [
            'percent' => (int) round($done / count($checks) * 100),
            'missing' => array_keys(array_filter($checks, fn ($ok) => ! $ok)),
        ];
    }

    /** Deterministic pastel avatar colour so initials avatars are easy to tell apart. */
    public function avatarColor(): string
    {
        $palette = ['#e87722', '#00629b', '#00843d', '#7a3e9d', '#c8102e', '#0083a9', '#6c6f2b', '#b35614', '#3f5b8c', '#a4508b'];

        return $palette[crc32((string) $this->user_id) % count($palette)];
    }
}
