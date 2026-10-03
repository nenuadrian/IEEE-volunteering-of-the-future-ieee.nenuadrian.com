<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'suspended_at', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    public const ROLES = [self::ROLE_USER => 'User', self::ROLE_ADMIN => 'Admin'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every account gets a volunteer profile so directory/CV pages always resolve.
        static::created(function (User $user) {
            if (! $user->profile()->exists()) {
                $user->profile()->create(['slug' => Profile::generateSlug($user->name)]);
            }
        });
    }

    // ----- Roles ---------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('suspended_at');
    }

    // ----- Relationships -------------------------------------------------

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->orderBy('name');
    }

    /** Opportunities this user owns or co-owns. */
    public function ownedOpportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_owners')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function hourLogs(): HasMany
    {
        return $this->hasMany(HourLog::class);
    }

    public function endorsementsReceived(): HasMany
    {
        return $this->hasMany(Endorsement::class)->latest();
    }

    public function endorsementsGiven(): HasMany
    {
        return $this->hasMany(Endorsement::class, 'endorser_id');
    }

    public function savedOpportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'saved_opportunities')
            ->withPivot('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    // ----- Helpers -------------------------------------------------------

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last) ?: '?';
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }

    public function hasSaved(Opportunity $opportunity): bool
    {
        return $this->savedOpportunities()->whereKey($opportunity->id)->exists();
    }

    public function approvedHours(): float
    {
        return (float) $this->hourLogs()->where('status', HourLog::APPROVED)->sum('hours');
    }
}
