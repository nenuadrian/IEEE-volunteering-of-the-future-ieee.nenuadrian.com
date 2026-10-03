<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'button_label', 'body', 'editor_data'];

    /**
     * The placeholder tokens each template understands, surfaced in the admin
     * editor as a reference. {{ action_url }} is also rendered automatically as
     * the call-to-action button, so the critical link can never be lost.
     */
    public const PLACEHOLDERS = [
        'verify_email' => ['name', 'email', 'site_name', 'site_url', 'action_url', 'year'],
        'reset_password' => ['name', 'email', 'site_name', 'site_url', 'action_url', 'year'],
        'welcome' => ['name', 'email', 'site_name', 'site_url', 'action_url', 'year'],
        'application_received' => ['name', 'volunteer', 'opportunity', 'action_url', 'site_name', 'site_url', 'year'],
        'application_accepted' => ['name', 'opportunity', 'note', 'action_url', 'site_name', 'site_url', 'year'],
        'application_rejected' => ['name', 'opportunity', 'note', 'action_url', 'site_name', 'site_url', 'year'],
        'application_completed' => ['name', 'opportunity', 'action_url', 'site_name', 'site_url', 'year'],
        'coowner_added' => ['name', 'opportunity', 'added_by', 'action_url', 'site_name', 'site_url', 'year'],
    ];

    /**
     * Last-resort copy used only if a template row is missing, so transactional
     * mail can never break.
     */
    private const FALLBACKS = [
        'verify_email' => [
            'name' => 'Confirm your email',
            'subject' => 'Confirm your email address',
            'button_label' => 'Confirm email address',
            'body' => '<p>Hi {{ name }},</p><p>Thanks for joining {{ site_name }}. Please confirm your email address to finish setting up your account.</p>',
        ],
        'reset_password' => [
            'name' => 'Password reset',
            'subject' => 'Reset your password',
            'button_label' => 'Reset password',
            'body' => '<p>Hi {{ name }},</p><p>We received a request to reset the password for your {{ site_name }} account. This link expires in 60 minutes.</p>',
        ],
        'welcome' => [
            'name' => 'Welcome email',
            'subject' => 'Welcome to {{ site_name }}',
            'button_label' => 'Find an opportunity',
            'body' => '<p>Hi {{ name }},</p><p>Welcome to {{ site_name }}, your email is confirmed and your account is ready.</p>',
        ],
        'application_received' => [
            'name' => 'New application',
            'subject' => '{{ volunteer }} applied to “{{ opportunity }}”',
            'button_label' => 'Review applicants',
            'body' => '<p>Hi {{ name }},</p><p>{{ volunteer }} has applied to volunteer on {{ opportunity }}.</p>',
        ],
        'application_accepted' => [
            'name' => 'Application accepted',
            'subject' => 'You are in! “{{ opportunity }}”',
            'button_label' => 'View the opportunity',
            'body' => '<p>Hi {{ name }},</p><p>You have been accepted to volunteer on {{ opportunity }}.</p><p>{{ note }}</p>',
        ],
        'application_rejected' => [
            'name' => 'Application not selected',
            'subject' => 'Update on your application to “{{ opportunity }}”',
            'button_label' => 'Browse other opportunities',
            'body' => '<p>Hi {{ name }},</p><p>The organisers of {{ opportunity }} were not able to take you on this time.</p><p>{{ note }}</p>',
        ],
        'application_completed' => [
            'name' => 'Contribution completed',
            'subject' => 'Thank you for volunteering on “{{ opportunity }}”',
            'button_label' => 'See your volunteer CV',
            'body' => '<p>Hi {{ name }},</p><p>Your contribution to {{ opportunity }} has been marked as completed. Thank you!</p>',
        ],
        'coowner_added' => [
            'name' => 'Added as co-owner',
            'subject' => 'You can now manage “{{ opportunity }}”',
            'button_label' => 'Open the opportunity workspace',
            'body' => '<p>Hi {{ name }},</p><p>{{ added_by }} added you as a co-owner of {{ opportunity }}.</p>',
        ],
    ];

    protected function casts(): array
    {
        return [
            'editor_data' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * Fetch the stored template for $key, or an unsaved fallback instance so
     * mail rendering never depends on the row existing.
     */
    public static function resolve(string $key): self
    {
        $template = static::query()->where('key', $key)->first();

        if ($template) {
            return $template;
        }

        $fallback = self::FALLBACKS[$key] ?? [
            'name' => $key,
            'subject' => '{{ site_name }}',
            'button_label' => null,
            'body' => '',
        ];

        return new self(['key' => $key] + $fallback);
    }
}
