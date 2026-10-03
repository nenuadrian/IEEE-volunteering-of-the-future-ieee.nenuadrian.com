<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only activity stream. Powers the personal dashboard feed and the
 * admin audit log. Record with Activity::record('application.accepted', $subject).
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'subject_type', 'subject_id', 'properties', 'created_at'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    /** Human-readable verbs for each activity type. */
    public const LABELS = [
        'user.registered' => 'joined IEEE Volunteering',
        'profile.updated' => 'updated their volunteer profile',
        'opportunity.created' => 'created an opportunity',
        'opportunity.published' => 'published an opportunity',
        'opportunity.updated' => 'updated an opportunity',
        'opportunity.status_changed' => 'changed the status of an opportunity',
        'opportunity.cloned' => 'cloned an opportunity',
        'opportunity.deleted' => 'deleted an opportunity',
        'opportunity.owner_added' => 'added a co-owner to an opportunity',
        'opportunity.owner_removed' => 'removed a co-owner from an opportunity',
        'application.submitted' => 'applied to an opportunity',
        'application.accepted' => 'accepted a volunteer',
        'application.rejected' => 'declined an application',
        'application.withdrawn' => 'withdrew an application',
        'application.completed' => 'marked a volunteer as completed',
        'hours.logged' => 'logged volunteer hours',
        'hours.approved' => 'approved volunteer hours',
        'hours.rejected' => 'rejected volunteer hours',
        'endorsement.given' => 'endorsed a volunteer',
        'admin.role_changed' => 'changed a user role',
        'admin.user_suspended' => 'suspended a user',
        'admin.user_unsuspended' => 'reinstated a user',
        'admin.user_deleted' => 'deleted a user',
        'admin.sync_run' => 'refreshed opportunities from volunteer.ieee.org',
        'admin.skill_merged' => 'merged two skills',
    ];

    public static function record(string $type, ?Model $subject = null, array $properties = [], ?User $actor = null): self
    {
        return static::create([
            'user_id' => $actor?->id ?? auth()->id(),
            'type' => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? str_replace(['.', '_'], ' ', $this->type);
    }

    /** Title of the thing acted upon, falling back to the stored snapshot. */
    public function subjectTitle(): ?string
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof Opportunity => $subject->title,
            $subject instanceof Application => $subject->opportunity?->title,
            $subject instanceof HourLog => $subject->opportunity?->title,
            $subject instanceof Endorsement => $subject->opportunity?->title,
            $subject instanceof User => $subject->name,
            default => $this->properties['title'] ?? null,
        };
    }

    public function subjectUrl(): ?string
    {
        $subject = $this->subject;

        $opportunity = match (true) {
            $subject instanceof Opportunity => $subject,
            $subject instanceof Application, $subject instanceof HourLog, $subject instanceof Endorsement => $subject->opportunity,
            default => null,
        };

        if ($opportunity && ! $opportunity->trashed()) {
            return route('opportunities.show', $opportunity);
        }

        if ($subject instanceof User && $subject->profile) {
            return route('volunteers.show', $subject->profile);
        }

        return null;
    }
}
