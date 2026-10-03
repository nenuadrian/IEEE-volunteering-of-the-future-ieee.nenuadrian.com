<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Support\EditorJsRenderer;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Seed the transactional templates. Idempotent: existing rows keep any
     * admin edits (only missing rows are created).
     */
    public function run(): void
    {
        foreach ($this->templates() as $key => $tpl) {
            if (EmailTemplate::query()->where('key', $key)->exists()) {
                continue;
            }

            $editorData = ['blocks' => $tpl['blocks']];

            EmailTemplate::create([
                'key' => $key,
                'name' => $tpl['name'],
                'subject' => $tpl['subject'],
                'button_label' => $tpl['button_label'],
                'editor_data' => $editorData,
                'body' => clean(EditorJsRenderer::toHtml($editorData), 'content'),
            ]);
        }
    }

    private function templates(): array
    {
        return [
            'verify_email' => [
                'name' => 'Email confirmation',
                'subject' => 'Confirm your email address for {{ site_name }}',
                'button_label' => 'Confirm email address',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('Thanks for joining {{ site_name }}. Please confirm this is your email address by clicking the button below.'),
                    $this->paragraph('If you did not create an account, no further action is required.'),
                ],
            ],
            'reset_password' => [
                'name' => 'Password reset',
                'subject' => 'Reset your {{ site_name }} password',
                'button_label' => 'Reset password',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('We received a request to reset the password for your {{ site_name }} account. This link expires in 60 minutes.'),
                    $this->paragraph('If you did not request a password reset, you can safely ignore this email.'),
                ],
            ],
            'welcome' => [
                'name' => 'Welcome',
                'subject' => 'Welcome to {{ site_name }}',
                'button_label' => 'Find an opportunity',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('Your email address is confirmed — welcome to {{ site_name }}! Add your skills and IEEE region to your profile and we will match you with opportunities across sections, societies and councils.'),
                ],
            ],
            'application_received' => [
                'name' => 'New application (to owners)',
                'subject' => '{{ volunteer }} applied to “{{ opportunity }}”',
                'button_label' => 'Review applicants',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('{{ volunteer }} has applied to volunteer on <b>{{ opportunity }}</b>.'),
                    $this->paragraph('Review their profile, skills and motivation, then accept or decline from your opportunity workspace.'),
                ],
            ],
            'application_accepted' => [
                'name' => 'Application accepted (to volunteer)',
                'subject' => 'You are in! “{{ opportunity }}”',
                'button_label' => 'View the opportunity',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('Great news — you have been accepted to volunteer on <b>{{ opportunity }}</b>. The organisers will be in touch with next steps.'),
                    $this->paragraph('{{ note }}'),
                    $this->paragraph('Remember to log your hours as you go so your contribution shows on your profile and CV.'),
                ],
            ],
            'application_rejected' => [
                'name' => 'Application not selected (to volunteer)',
                'subject' => 'Update on your application to “{{ opportunity }}”',
                'button_label' => 'Browse other opportunities',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('Thank you for applying to <b>{{ opportunity }}</b>. The organisers were not able to take you on this time.'),
                    $this->paragraph('{{ note }}'),
                    $this->paragraph('There are many more ways to get involved — new opportunities are posted every week.'),
                ],
            ],
            'application_completed' => [
                'name' => 'Contribution completed (to volunteer)',
                'subject' => 'Thank you for volunteering on “{{ opportunity }}”',
                'button_label' => 'See your volunteer CV',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('The organisers of <b>{{ opportunity }}</b> have marked your contribution as completed. Thank you for giving your time to the IEEE community!'),
                    $this->paragraph('It now appears on your public profile and in your downloadable volunteer CV.'),
                ],
            ],
            'coowner_added' => [
                'name' => 'Added as co-owner',
                'subject' => 'You can now manage “{{ opportunity }}”',
                'button_label' => 'Open the opportunity workspace',
                'blocks' => [
                    $this->paragraph('Hi {{ name }},'),
                    $this->paragraph('{{ added_by }} added you as a co-owner of <b>{{ opportunity }}</b>. You can now review applicants, approve hours and see the impact of its volunteers.'),
                ],
            ],
        ];
    }

    private function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'data' => ['text' => $text]];
    }
}
