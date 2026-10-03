<?php

namespace Tests\Feature\Admin;

use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        return $admin;
    }

    public function test_admin_can_view_the_template_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.email-templates.index'))
            ->assertStatus(200)
            ->assertSee('Welcome');
    }

    public function test_admin_can_open_the_edit_form(): void
    {
        // Renders the EditorJS form view, incl. the literal "{{ token }}" help list.
        $this->actingAs($this->admin())
            ->get(route('admin.email-templates.edit', 'welcome'))
            ->assertStatus(200)
            ->assertSee('Subject line')
            ->assertSee('{{ site_name }}');
    }

    public function test_admin_can_update_a_template(): void
    {
        $editorData = json_encode(['blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => 'Hi {{ name }}, this is the updated welcome copy.']],
        ]]);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.email-templates.update', 'welcome'), [
                'subject' => 'A fresh welcome to {{ site_name }}',
                'button_label' => 'Open my dashboard',
                'editor_data' => $editorData,
            ]);

        $response->assertRedirect(route('admin.email-templates.index'))
            ->assertSessionHasNoErrors();

        $template = EmailTemplate::where('key', 'welcome')->first();
        $this->assertSame('A fresh welcome to {{ site_name }}', $template->subject);
        $this->assertSame('Open my dashboard', $template->button_label);
        $this->assertStringContainsString('updated welcome copy', $template->body);
    }

    public function test_update_requires_a_subject(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.email-templates.update', 'welcome'), [
                'subject' => '',
                'editor_data' => json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'x']]]]),
            ])
            ->assertSessionHasErrors('subject');
    }

    public function test_admin_can_send_a_test_email(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.email-templates.test', 'welcome'))
            ->assertRedirect();

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->templateKey === 'welcome'
            && $mail->hasTo($admin->email));
    }

    public function test_preview_renders_the_email(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.email-templates.preview', 'welcome'))
            ->assertStatus(200)
            ->assertSee('Alex Researcher'); // sample data used in preview
    }

    public function test_users_without_manage_settings_are_blocked(): void
    {
        // Editor has admin access but not the "manage settings" permission.
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        $this->actingAs($editor)
            ->get(route('admin.email-templates.index'))
            ->assertStatus(403);
    }
}
