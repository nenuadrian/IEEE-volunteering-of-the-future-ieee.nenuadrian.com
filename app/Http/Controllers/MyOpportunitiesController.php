<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\HourLog;
use Illuminate\Http\Request;

/**
 * "My Opportunities", split the way the UX review asked for:
 *   Volunteering — what I applied to / take part in
 *   Managing     — what I own or co-own
 *   Saved        — bookmarks
 */
class MyOpportunitiesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['volunteering', 'managing', 'saved'], true) ? $request->query('tab') : null;

        $applications = $user->applications()
            ->with(['opportunity.category', 'opportunity.owners', 'hourLogs'])
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->withSum(['hourLogs as pending_hours' => fn ($q) => $q->where('status', HourLog::PENDING)], 'hours')
            ->latest('updated_at')
            ->get()
            ->filter(fn ($a) => $a->opportunity);

        $volunteering = [
            'active' => $applications->where('status', Application::ACCEPTED)->values(),
            'pending' => $applications->where('status', Application::PENDING)->values(),
            'completed' => $applications->where('status', Application::COMPLETED)->values(),
            'closed' => $applications->whereIn('status', [Application::REJECTED, Application::WITHDRAWN])->values(),
        ];

        $managing = $user->ownedOpportunities()
            ->with(['category'])
            ->withCount([
                'applications',
                'applications as pending_count' => fn ($q) => $q->where('status', Application::PENDING),
                'applications as confirmed_count' => fn ($q) => $q->whereIn('status', Application::CONFIRMED),
                'hourLogs as pending_hours_count' => fn ($q) => $q->where('status', HourLog::PENDING),
            ])
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->orderByRaw("CASE opportunities.status WHEN 'draft' THEN 0 WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'on_hold' THEN 3 ELSE 4 END")
            ->latest('opportunities.created_at')
            ->get();

        $saved = $user->savedOpportunities()->with(['category', 'skills'])->orderByPivot('created_at', 'desc')->get();

        // Default to whichever side has something needing attention.
        $tab ??= $managing->sum('pending_count') + $managing->sum('pending_hours_count') > 0
            ? 'managing'
            : ($applications->isNotEmpty() || $managing->isEmpty() ? 'volunteering' : 'managing');

        return view('my.opportunities', compact('tab', 'volunteering', 'managing', 'saved', 'applications'));
    }
}
