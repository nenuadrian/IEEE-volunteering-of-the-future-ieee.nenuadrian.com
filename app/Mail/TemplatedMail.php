<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Support\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Renders an admin-editable EmailTemplate into the branded email shell.
 *
 * Deliberately NOT ShouldQueue: production runs no queue worker, so all
 * transactional mail is sent synchronously (matching App\Mail\ContactMessage).
 */
class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public EmailTemplate $template;

    /**
     * @param  array<string, mixed>  $data  Substitution values (name, email, action_url, …).
     */
    public function __construct(public string $templateKey, public array $data = [])
    {
        $this->template = EmailTemplate::resolve($templateKey);
    }

    /**
     * Build a TemplatedMail for a notifiable (a User), pre-filling the common
     * data map and the recipient. Used by the verify/reset notifications and
     * the welcome listener.
     *
     * @param  array<string, mixed>  $extra  Per-message values such as action_url.
     */
    public static function for(object $notifiable, string $key, array $extra = []): self
    {
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : ($notifiable->email ?? null);
        $name = $notifiable->name ?? 'there';

        $mail = new self($key, array_merge(self::baseData($name, $email), $extra));

        if ($email) {
            $mail->to($email, is_string($name) ? $name : null);
        }

        return $mail;
    }

    /** Tokens available to every template. */
    public static function baseData(?string $name, ?string $email): array
    {
        return [
            'name' => $name ?: 'there',
            'email' => $email,
            'site_name' => Setting::get('site_name', config('app.name')),
            'site_url' => config('app.url'),
            'year' => date('Y'),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: EmailTemplateRenderer::text($this->template->subject, $this->data),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.templated',
            with: [
                'heading' => EmailTemplateRenderer::text($this->template->subject, $this->data),
                'bodyHtml' => EmailTemplateRenderer::html((string) $this->template->body, $this->data),
                'actionUrl' => $this->data['action_url'] ?? null,
                'actionText' => $this->template->button_label ?: null,
            ],
        );
    }
}
