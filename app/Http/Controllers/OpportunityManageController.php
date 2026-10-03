<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Application;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\ImpactStats;
use App\Support\MatchScore;
use App\Support\Notify;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The owner's workspace for one opportunity. */
class OpportunityManageController extends Controller
{
    public function show(Request $request, Opportunity $opportunity, ImpactStats $stats)
    {
        $this->authorize('manage-opportunity', $opportunity);

        $opportunity->load(['category', 'skills', 'owners.profile']);

        $applications = $opportunity->applications()
            ->with(['user.profile', 'user.skills', 'endorsement'])
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 WHEN 'completed' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END")
            ->latest()
            ->get()
            ->each(fn ($a) => $a->match = MatchScore::for($a->user, $opportunity, $a->user->skills->pluck('id')->all()));

        $pendingHours = $opportunity->hourLogs()
            ->where('status', HourLog::PENDING)
            ->with('user.profile')
            ->orderBy('worked_on')
            ->get();

        $tab = in_array($request->query('tab'), ['applicants', 'hours', 'team', 'impact'], true)
            ? $request->query('tab')
            : 'applicants';

        return view('opportunities.manage', [
            'opportunity' => $opportunity,
            'applications' => $applications,
            'pendingHours' => $pendingHours,
            'impact' => $stats->opportunity($opportunity),
            'tab' => $tab,
            'activity' => Activity::query()
                ->where(function ($q) use ($opportunity, $applications) {
                    $q->where(fn ($s) => $s->where('subject_type', $opportunity->getMorphClass())->where('subject_id', $opportunity->id))
                        ->orWhere(fn ($s) => $s->where('subject_type', (new Application)->getMorphClass())->whereIn('subject_id', $applications->pluck('id')));
                })
                ->with('user')
                ->latest('created_at')
                ->take(15)
                ->get(),
        ]);
    }

    /** CSV of every applicant with status, hours and contact (for owners). */
    public function export(Opportunity $opportunity): StreamedResponse
    {
        $this->authorize('manage-opportunity', $opportunity);

        $rows = $opportunity->applications()
            ->with('user.profile')
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->get();

        $filename = 'applicants-'.$opportunity->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Status', 'Applied', 'Decided', 'Approved hours', 'Region', 'Section', 'Grade']);
            foreach ($rows as $a) {
                fputcsv($out, [
                    $a->user->name, $a->user->email, $a->statusLabel(), $a->created_at?->toDateString(),
                    $a->decided_at?->toDateString(), (float) $a->approved_hours, $a->user->profile?->region,
                    $a->user->profile?->section, $a->user->profile?->gradeLabel(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function addOwner(Request $request, Opportunity $opportunity)
    {
        $this->authorize('manage-opportunity', $opportunity);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $person = User::findOrFail($data['user_id']);

        abort_if($person->isSuspended(), 422, 'That account is suspended.');

        if ($opportunity->owners()->count() >= config('volunteering.max_owners')) {
            return back()->withErrors(['user_id' => 'An opportunity can have at most '.config('volunteering.max_owners').' owners.']);
        }

        if (! $opportunity->isOwnedBy($person)) {
            $opportunity->owners()->attach($person->id, ['role' => 'co_owner', 'added_by' => $request->user()->id]);
            Activity::record('opportunity.owner_added', $opportunity, ['user_id' => $person->id, 'name' => $person->name]);
            Notify::coOwnerAdded($opportunity, $person, $request->user());
        }

        return redirect()->route('opportunities.manage', [$opportunity, 'tab' => 'team'])
            ->with('status', $person->name.' can now manage this opportunity.');
    }

    public function removeOwner(Request $request, Opportunity $opportunity, User $user)
    {
        $this->authorize('manage-opportunity', $opportunity);

        $owners = $opportunity->owners()->get();
        $target = $owners->firstWhere('id', $user->id);

        abort_unless($target, 404);

        if ($owners->count() <= 1) {
            return back()->withErrors(['owners' => 'An opportunity needs at least one owner.']);
        }

        $opportunity->owners()->detach($user->id);

        // Keep exactly one primary owner.
        if ($target->pivot->role === 'owner') {
            $next = $opportunity->owners()->first();
            $opportunity->owners()->updateExistingPivot($next->id, ['role' => 'owner']);
        }

        Activity::record('opportunity.owner_removed', $opportunity, ['user_id' => $user->id, 'name' => $user->name]);

        if ($user->id === $request->user()->id && ! $request->user()->isAdmin()) {
            return redirect()->route('my.opportunities')->with('status', 'You are no longer an owner of “'.$opportunity->title.'”.');
        }

        return redirect()->route('opportunities.manage', [$opportunity, 'tab' => 'team'])->with('status', $user->name.' was removed as an owner.');
    }
}
