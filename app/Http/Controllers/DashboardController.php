<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Application;
use App\Models\Endorsement;
use App\Models\Opportunity;
use App\Services\ImpactStats;
use App\Support\MatchScore;
use Illuminate\Http\Request;

/** Personal engagement hub: impact as a volunteer and as an organiser. */
class DashboardController extends Controller
{
    public function index(Request $request, ImpactStats $stats)
    {
        $user = $request->user()->load('profile', 'skills');

        $volunteer = $stats->volunteer($user);
        $owner = $stats->owner($user);

        $skillIds = $user->skills->pluck('id')->all();
        $appliedIds = $user->applications()->pluck('opportunity_id');

        $recommended = Opportunity::open()
            ->with(['category', 'skills'])
            ->whereNotIn('id', $appliedIds)
            ->whereDoesntHave('owners', fn ($q) => $q->where('users.id', $user->id))
            ->latest()
            ->take(80)
            ->get()
            ->each(fn ($o) => $o->match = MatchScore::for($user, $o, $skillIds))
            ->sortByDesc(fn ($o) => $o->match['percent'] * 1000 + $o->created_at->timestamp / 1e7)
            ->take(4)
            ->values();

        $active = $user->applications()
            ->where('status', Application::ACCEPTED)
            ->with('opportunity.category')
            ->latest('decided_at')
            ->get()
            ->filter(fn ($a) => $a->opportunity);

        // Feed: my own actions plus things that happened to me or my opportunities.
        $ownedIds = Opportunity::ownedBy($user)->pluck('id');
        $myApplicationIds = $user->applications()->pluck('id');
        $feed = Activity::query()
            ->with(['user', 'subject'])
            ->where(function ($q) use ($user, $ownedIds, $myApplicationIds) {
                $q->where('user_id', $user->id)
                    ->orWhere(fn ($s) => $s->where('subject_type', (new Opportunity)->getMorphClass())->whereIn('subject_id', $ownedIds))
                    ->orWhere(fn ($s) => $s->where('subject_type', (new Application)->getMorphClass())->whereIn('subject_id', $myApplicationIds));
            })
            ->where('type', 'not like', 'admin.%')
            ->latest('created_at')
            ->take(12)
            ->get();

        $completeness = $user->profile->completeness();

        $checklist = [
            ['Complete your volunteer profile', $completeness['percent'] >= 75, route('profile.volunteer.edit')],
            ['Add at least 3 skills', count($skillIds) >= 3, route('profile.volunteer.edit').'#skills'],
            ['Apply to your first opportunity', $appliedIds->isNotEmpty(), route('opportunities.index')],
            ['Log your first volunteer hours', $user->hourLogs()->exists(), route('my.opportunities', ['tab' => 'volunteering'])],
            ['Create an opportunity for others', $ownedIds->isNotEmpty(), route('opportunities.create')],
        ];

        $latestEndorsement = Endorsement::with(['endorser', 'opportunity'])->where('user_id', $user->id)->latest()->first();

        return view('dashboard', compact('user', 'volunteer', 'owner', 'recommended', 'active', 'feed', 'completeness', 'checklist', 'latestEndorsement'));
    }
}
