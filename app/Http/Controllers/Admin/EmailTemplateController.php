<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesEditorBody;
use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplateController extends Controller
{
    use HandlesEditorBody;

    private const COMMON_PLACEHOLDERS = ['name', 'email', 'site_name', 'site_url', 'year'];

    public function index()
    {
        return view('admin.email-templates.index', [
            'templates' => EmailTemplate::orderBy('id')->get(),
        ]);
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        return view('admin.email-templates.form', [
            'emailTemplate' => $emailTemplate,
            'placeholders' => EmailTemplate::PLACEHOLDERS[$emailTemplate->key] ?? self::COMMON_PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'button_label' => ['nullable', 'string', 'max:60'],
        ]);

        $emailTemplate->subject = $data['subject'];
        $emailTemplate->button_label = $data['button_label'] ?? null;
        // Decodes editor_data → sanitised HTML body; no-op if the editor sent
        // nothing, so a JS hiccup can't wipe the saved content.
        $this->applyEditorBody($emailTemplate, $request);
        $emailTemplate->save();

        return redirect()->route('admin.email-templates.index')
            ->with('status', $emailTemplate->name.' email updated.');
    }

    /** Render the email HTML in the browser with sample data, for design preview. */
    public function preview(EmailTemplate $emailTemplate)
    {
        return (new TemplatedMail($emailTemplate->key, $this->sampleData()))->render();
    }

    /** Send the template to the signed-in admin, so they can see it in a real inbox. */
    public function test(Request $request, EmailTemplate $emailTemplate)
    {
        $user = $request->user();

        try {
            Mail::send(TemplatedMail::for($user, $emailTemplate->key, array_merge($this->sampleTokens(), ['action_url' => url('/')])));
        } catch (\Throwable $e) {
            Log::error('Email template test send failed: '.$e->getMessage());

            return back()->with('status', 'Could not send the test email. Check the mail configuration.');
        }

        return back()->with('status', 'Test “'.$emailTemplate->name.'” email sent to '.$user->email.'.');
    }

    private function sampleData(): array
    {
        return array_merge(
            TemplatedMail::baseData('Alex Researcher', 'member@example.org'),
            $this->sampleTokens(),
            ['action_url' => url('/')],
        );
    }

    /** Example values for the workflow placeholders so previews read naturally. */
    private function sampleTokens(): array
    {
        return [
            'opportunity' => 'Social Media Coordinator — IEEE Young Professionals',
            'volunteer' => 'Priya Sharma',
            'added_by' => 'Jordan Lee',
            'note' => 'We are delighted to have you on board — expect an intro email this week.',
        ];
    }
}
