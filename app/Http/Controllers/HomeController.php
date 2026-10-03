<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Category;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\Profile;
use App\Models\User;
use App\Support\MatchScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $stats = Cache::remember('home:stats', now()->addMinutes(10), fn () => [
            'open' => Opportunity::open()->count(),
            'volunteers' => User::active()->count(),
            'hours' => (int) round((float) HourLog::where('status', HourLog::APPROVED)->sum('hours')),
            'countries' => Profile::whereNotNull('country')->distinct()->count('country'),
            'placements' => Application::whereIn('status', Application::CONFIRMED)->count(),
        ]);

        $featured = Opportunity::open()
            ->with(['category', 'skills'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        $categories = Category::active()->ordered()
            ->withCount(['opportunities as open_count' => fn ($q) => $q->where('status', Opportunity::OPEN)])
            ->get();

        $skillsInDemand = DB::table('opportunity_skill')
            ->join('opportunities', 'opportunities.id', '=', 'opportunity_skill.opportunity_id')
            ->join('skills', 'skills.id', '=', 'opportunity_skill.skill_id')
            ->where('opportunities.status', Opportunity::OPEN)
            ->whereNull('opportunities.deleted_at')
            ->selectRaw('skills.id, skills.name, COUNT(*) as total')
            ->groupBy('skills.id', 'skills.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        $endorsements = Endorsement::with(['user.profile', 'endorser', 'opportunity'])
            ->where('is_public', true)
            ->whereRaw('LENGTH(message) > 80')
            ->latest()
            ->take(3)
            ->get();

        $recommended = collect();
        if ($user = $request->user()) {
            $skillIds = $user->skills()->pluck('skills.id')->all();
            $applied = $user->applications()->pluck('opportunity_id');
            $recommended = Opportunity::open()->with(['category', 'skills'])
                ->whereNotIn('id', $applied)
                ->latest()->take(60)->get()
                ->each(fn ($o) => $o->match = MatchScore::for($user, $o, $skillIds))
                ->sortByDesc(fn ($o) => $o->match['percent'])
                ->take(3)
                ->values();
        }

        return view('home', compact('stats', 'featured', 'categories', 'skillsInDemand', 'endorsements', 'recommended'));
    }
}
