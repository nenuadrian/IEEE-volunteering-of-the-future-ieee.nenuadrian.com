<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        return view('contact.index', [
            'contactEmail' => $this->recipient(),
        ]);
    }

    public function submit(Request $request)
    {
        // Honeypot: real users leave the hidden "website" field empty. Bots tend
        // to fill every input, so silently accept and discard their submission.
        if (filled($request->input('website'))) {
            return redirect()->route('contact.show')
                ->with('status', 'Thank you for your message. We’ll be in touch soon.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $recipient = $this->recipient();

        try {
            Mail::to($recipient)->send(new ContactMessage(
                senderName: $data['name'],
                senderEmail: $data['email'],
                messageSubject: $data['subject'],
                messageBody: $data['message'],
            ));
        } catch (\Throwable $e) {
            Log::error('Contact form delivery failed: '.$e->getMessage());

            return back()->withInput()->withErrors([
                'contact' => 'Sorry, we couldn’t send your message right now. '
                    .'Please try again shortly, or email us directly at '.$recipient.'.',
            ]);
        }

        return redirect()->route('contact.show')
            ->with('status', 'Thank you, '.$data['name'].'! Your message has been sent. We’ll get back to you soon.');
    }

    /**
     * Where contact messages are delivered. Admin-configurable, falling back to
     * the application's default "from" address.
     */
    private function recipient(): string
    {
        return Setting::get('contact_email', config('mail.from.address'));
    }
}
