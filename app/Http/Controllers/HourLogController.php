<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Application;
use App\Models\HourLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HourLogController extends Controller
{
    public function store(Request $request, Application $application)
    {
        abort_unless($application->user_id === $request->user()->id, 403);

        if (! $application->canLogHours()) {
            return back()->withErrors(['hours' => 'You can log hours once you have been accepted onto the opportunity.']);
        }

        $data = $request->validate([
            'worked_on' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(2)->toDateString()],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $log = $application->hourLogs()->create($data + [
            'user_id' => $application->user_id,
            'opportunity_id' => $application->opportunity_id,
            'status' => HourLog::PENDING,
        ]);

        Activity::record('hours.logged', $log, ['hours' => (float) $data['hours']]);

        return back()->with('status', 'Logged '.rtrim(rtrim(number_format((float) $data['hours'], 2), '0'), '.').' hours. The organisers will review them shortly.');
    }

    public function review(Request $request, HourLog $hourLog)
    {
        $this->authorize('manage-opportunity', $hourLog->opportunity);

        $data = $request->validate([
            'decision' => ['required', Rule::in([HourLog::APPROVED, HourLog::REJECTED])],
        ]);

        $hourLog->update([
            'status' => $data['decision'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Activity::record('hours.'.$data['decision'], $hourLog, ['hours' => $hourLog->hours, 'volunteer' => $hourLog->user->name]);

        return back()->with('status', $data['decision'] === HourLog::APPROVED ? 'Hours approved.' : 'Hours rejected.');
    }

    public function destroy(Request $request, HourLog $hourLog)
    {
        abort_unless($hourLog->user_id === $request->user()->id && $hourLog->status === HourLog::PENDING, 403);

        $hourLog->delete();

        return back()->with('status', 'Hour entry removed.');
    }
}
