<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Application;
use App\Models\Endorsement;
use App\Models\Opportunity;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    /** Volunteer applies (or re-applies after withdrawing). */
    public function store(Request $request, Opportunity $opportunity)
    {
        $user = $request->user();

        if (! $opportunity->acceptsApplications()) {
            return back()->withErrors(['application' => 'This opportunity is not accepting applications right now.']);
        }

        if ($opportunity->isOwnedBy($user)) {
            return back()->withErrors(['application' => 'You manage this opportunity, so you cannot apply to it.']);
        }

        $data = $request->validate([
            'motivation' => ['required', 'string', 'min:20', 'max:3000'],
        ], [
            'motivation.required' => 'Tell the organisers briefly why you are interested and what you can bring.',
            'motivation.min' => 'Please write at least a sentence or two (20+ characters) about your interest.',
        ]);

        $existing = $opportunity->applications()->where('user_id', $user->id)->first();

        if ($existing && $existing->status !== Application::WITHDRAWN) {
            return back()->withErrors(['application' => 'You have already applied to this opportunity.']);
        }

        $application = $existing ?? new Application(['opportunity_id' => $opportunity->id, 'user_id' => $user->id]);
        $application->fill([
            'status' => Application::PENDING,
            'motivation' => $data['motivation'],
            'withdrawn_at' => null,
            'decided_at' => null,
            'decided_by' => null,
        ])->save();

        Activity::record('application.submitted', $application, ['title' => $opportunity->title]);
        Notify::applicationReceived($application->load('opportunity.owners', 'user'));

        return redirect()->route('opportunities.show', $opportunity)
            ->with('status', 'Application sent! The organisers have been notified and you can track it under My Opportunities.');
    }

    public function withdraw(Request $request, Application $application)
    {
        abort_unless($application->user_id === $request->user()->id, 403);
        abort_unless(in_array($application->status, [Application::PENDING, Application::ACCEPTED], true), 422);

        $application->update(['status' => Application::WITHDRAWN, 'withdrawn_at' => now()]);
        Activity::record('application.withdrawn', $application, ['title' => $application->opportunity->title]);

        return back()->with('status', 'Your application has been withdrawn.');
    }

    /** Owner accepts or declines an applicant. */
    public function decide(Request $request, Application $application)
    {
        $this->authorize('manage-opportunity', $application->opportunity);

        $data = $request->validate([
            'decision' => ['required', Rule::in([Application::ACCEPTED, Application::REJECTED])],
            'owner_note' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless(in_array($application->status, [Application::PENDING, Application::ACCEPTED, Application::REJECTED], true), 422);

        $application->update([
            'status' => $data['decision'],
            'owner_note' => $data['owner_note'] ?? $application->owner_note,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        if ($data['decision'] === Application::ACCEPTED) {
            $application->opportunity->refreshFilledState();
        }

        Activity::record('application.'.$data['decision'], $application, ['volunteer' => $application->user->name]);
        Notify::applicationDecided($application);

        return back()->with('status', $data['decision'] === Application::ACCEPTED
            ? $application->user->name.' has been accepted and notified.'
            : $application->user->name.' has been notified that they were not selected this time.');
    }

    /** Owner marks a volunteer's contribution complete, rates it and (optionally) endorses them. */
    public function complete(Request $request, Application $application)
    {
        $this->authorize('manage-opportunity', $application->opportunity);
        abort_unless(in_array($application->status, [Application::ACCEPTED, Application::COMPLETED], true), 422);

        $data = $request->validate([
            'owner_rating' => ['nullable', 'integer', 'between:1,5'],
            'endorsement' => ['nullable', 'string', 'min:20', 'max:2000'],
            'endorsed_skills' => ['array', 'max:10'],
            'endorsed_skills.*' => ['integer', 'exists:skills,id'],
        ]);

        DB::transaction(function () use ($application, $data, $request) {
            $application->update([
                'status' => Application::COMPLETED,
                'completed_at' => $application->completed_at ?? now(),
                'owner_rating' => $data['owner_rating'] ?? $application->owner_rating,
            ]);

            if (! empty($data['endorsement'])) {
                $endorsement = Endorsement::updateOrCreate(
                    ['application_id' => $application->id],
                    [
                        'user_id' => $application->user_id,
                        'endorser_id' => $request->user()->id,
                        'opportunity_id' => $application->opportunity_id,
                        'message' => $data['endorsement'],
                    ],
                );
                $endorsement->skills()->sync($data['endorsed_skills'] ?? []);
                Activity::record('endorsement.given', $endorsement, ['volunteer' => $application->user->name]);
            }
        });

        Activity::record('application.completed', $application, ['volunteer' => $application->user->name]);
        Notify::applicationCompleted($application);

        return back()->with('status', $application->user->name.'’s contribution has been marked as completed. Thank you for recognising their work!');
    }

    /** Volunteer rates their experience after taking part. */
    public function feedback(Request $request, Application $application)
    {
        abort_unless($application->user_id === $request->user()->id, 403);
        abort_unless($application->isConfirmed(), 422);

        $data = $request->validate([
            'volunteer_rating' => ['required', 'integer', 'between:1,5'],
            'volunteer_feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update($data);

        return back()->with('status', 'Thanks for your feedback — it helps organisers improve.');
    }
}
