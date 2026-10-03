<?php

namespace App\Support;

use App\Mail\TemplatedMail;
use App\Models\Application;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Transactional emails for the volunteering workflow, rendered through the
 * admin-editable templates. Delivery failures are logged and never break the
 * action that triggered them.
 */
class Notify
{
    public static function applicationReceived(Application $application): void
    {
        $opportunity = $application->opportunity;

        // Imported opportunities nobody has claimed yet are reviewed by admins.
        $recipients = $opportunity->owners->isNotEmpty() ? $opportunity->owners : User::admins()->active()->get();

        foreach ($recipients as $owner) {
            static::send($owner, 'application_received', [
                'volunteer' => $application->user->name,
                'opportunity' => $opportunity->title,
                'action_url' => route('opportunities.manage', $opportunity),
            ]);
        }
    }

    public static function applicationDecided(Application $application): void
    {
        $key = match ($application->status) {
            Application::ACCEPTED => 'application_accepted',
            Application::REJECTED => 'application_rejected',
            default => null,
        };

        if ($key) {
            static::send($application->user, $key, [
                'opportunity' => $application->opportunity->title,
                'note' => $application->owner_note ?: '',
                'action_url' => route('opportunities.show', $application->opportunity),
            ]);
        }
    }

    public static function applicationCompleted(Application $application): void
    {
        static::send($application->user, 'application_completed', [
            'opportunity' => $application->opportunity->title,
            'action_url' => route('volunteers.show', $application->user->profile),
        ]);
    }

    public static function coOwnerAdded(Opportunity $opportunity, User $person, User $actor): void
    {
        static::send($person, 'coowner_added', [
            'opportunity' => $opportunity->title,
            'added_by' => $actor->name,
            'action_url' => route('opportunities.manage', $opportunity),
        ]);
    }

    private static function send(User $to, string $template, array $data): void
    {
        try {
            Mail::send(TemplatedMail::for($to, $template, $data));
        } catch (Throwable $e) {
            Log::warning("Email '{$template}' to user {$to->id} failed: ".$e->getMessage());
        }
    }
}
