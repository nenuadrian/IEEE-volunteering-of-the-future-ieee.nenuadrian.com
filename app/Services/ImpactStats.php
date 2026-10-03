<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\Profile;
use App\Models\User;
use App\Support\DateBucket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Impact figures for a volunteer, for an opportunity creator ("the impact of
 * my volunteers") and for a single opportunity. All series are monthly and
 * gap-filled so charts never skip a month.
 */
class ImpactStats
{
    // ----- Volunteer -----------------------------------------------------

    public function volunteer(User $user, int $months = 12): array
    {
        $apps = Application::query()->where('user_id', $user->id);
        $counts = (clone $apps)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $accepted = (int) ($counts[Application::ACCEPTED] ?? 0);
        $completed = (int) ($counts[Application::COMPLETED] ?? 0);
        $rejected = (int) ($counts[Application::REJECTED] ?? 0);
        $decided = $accepted + $completed + $rejected;

        $hours = HourLog::query()->where('user_id', $user->id);
        $approvedHours = (float) (clone $hours)->where('status', HourLog::APPROVED)->sum('hours');
        $pendingHours = (float) (clone $hours)->where('status', HourLog::PENDING)->sum('hours');

        $confirmedOppIds = (clone $apps)->whereIn('status', Application::CONFIRMED)->pluck('opportunity_id');

        $range = DateBucket::range(now()->subMonths($months - 1), now());
        $hoursByMonth = HourLog::query()
            ->where('user_id', $user->id)
            ->where('status', HourLog::APPROVED)
            ->where('worked_on', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->selectRaw(DateBucket::expression('worked_on').' as bucket, SUM(hours) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($v) => round((float) $v, 1))
            ->all();

        return [
            'hours_approved' => round($approvedHours, 1),
            'hours_pending' => round($pendingHours, 1),
            'applications' => (int) $counts->sum(),
            'pending' => (int) ($counts[Application::PENDING] ?? 0),
            'active' => $accepted,
            'completed' => $completed,
            'acceptance_rate' => $decided ? (int) round(($accepted + $completed) / $decided * 100) : null,
            'endorsements' => Endorsement::query()->where('user_id', $user->id)->count(),
            'avg_rating' => ($r = (clone $apps)->whereNotNull('owner_rating')->avg('owner_rating')) ? round((float) $r, 1) : null,
            'opportunities_created' => Opportunity::ownedBy($user)->count(),
            'skills_used' => $this->skillCounts($confirmedOppIds, 8),
            'categories' => $this->categoryCounts($confirmedOppIds),
            'hours_series' => [
                'labels' => array_values($range),
                'data' => DateBucket::fill($range, $hoursByMonth),
            ],
        ];
    }

    // ----- Opportunity creator -------------------------------------------

    public function owner(User $user, int $months = 12): array
    {
        $oppIds = Opportunity::ownedBy($user)->pluck('id');

        if ($oppIds->isEmpty()) {
            return ['empty' => true];
        }

        $apps = Application::query()->whereIn('opportunity_id', $oppIds);
        $counts = (clone $apps)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $accepted = (int) ($counts[Application::ACCEPTED] ?? 0);
        $completed = (int) ($counts[Application::COMPLETED] ?? 0);
        $rejected = (int) ($counts[Application::REJECTED] ?? 0);

        $volunteerIds = (clone $apps)->whereIn('status', Application::CONFIRMED)->distinct()->pluck('user_id');
        $hours = HourLog::query()->whereIn('opportunity_id', $oppIds);

        $from = now()->subMonths($months - 1)->startOfMonth();
        $range = DateBucket::range($from, now());

        $hoursByMonth = (clone $hours)->where('status', HourLog::APPROVED)->where('worked_on', '>=', $from)
            ->selectRaw(DateBucket::expression('worked_on').' as bucket, SUM(hours) as total')
            ->groupBy('bucket')->pluck('total', 'bucket')->map(fn ($v) => round((float) $v, 1))->all();

        $appsByMonth = (clone $apps)->where('created_at', '>=', $from)
            ->selectRaw(DateBucket::expression('created_at').' as bucket, COUNT(*) as total')
            ->groupBy('bucket')->pluck('total', 'bucket')->all();

        $confirmedByMonth = (clone $apps)->whereIn('status', Application::CONFIRMED)->where('decided_at', '>=', $from)
            ->selectRaw(DateBucket::expression('decided_at').' as bucket, COUNT(*) as total')
            ->groupBy('bucket')->pluck('total', 'bucket')->all();

        $opportunities = Opportunity::query()
            ->whereIn('id', $oppIds)
            ->withCount([
                'applications',
                'applications as confirmed_count' => fn ($q) => $q->whereIn('status', Application::CONFIRMED),
                'applications as pending_count' => fn ($q) => $q->where('status', Application::PENDING),
            ])
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->orderByDesc('created_at')
            ->get();

        return [
            'empty' => false,
            'opportunities' => $oppIds->count(),
            'open' => $opportunities->where('status', Opportunity::OPEN)->count(),
            'applicants' => (int) $counts->sum(),
            'pending_decisions' => (int) ($counts[Application::PENDING] ?? 0),
            'pending_hours' => (clone $hours)->where('status', HourLog::PENDING)->count(),
            'volunteers' => $volunteerIds->count(),
            'completed' => $completed,
            'hours' => round((float) (clone $hours)->where('status', HourLog::APPROVED)->sum('hours'), 1),
            'acceptance_rate' => ($accepted + $completed + $rejected) ? (int) round(($accepted + $completed) / ($accepted + $completed + $rejected) * 100) : null,
            'completion_rate' => ($accepted + $completed) ? (int) round($completed / ($accepted + $completed) * 100) : null,
            'avg_volunteer_rating' => ($r = (clone $apps)->whereNotNull('volunteer_rating')->avg('volunteer_rating')) ? round((float) $r, 1) : null,
            'endorsements_given' => Endorsement::query()->whereIn('opportunity_id', $oppIds)->count(),
            'regions' => $this->regionCounts($volunteerIds),
            'countries' => Profile::query()->whereIn('user_id', $volunteerIds)->whereNotNull('country')->distinct()->count('country'),
            'top_volunteers' => $this->topVolunteers($oppIds),
            'by_opportunity' => $opportunities,
            'series' => [
                'labels' => array_values($range),
                'hours' => DateBucket::fill($range, $hoursByMonth),
                'applications' => DateBucket::fill($range, $appsByMonth),
                'confirmed' => DateBucket::fill($range, $confirmedByMonth),
            ],
        ];
    }

    // ----- Single opportunity --------------------------------------------

    public function opportunity(Opportunity $opportunity): array
    {
        $apps = $opportunity->applications();
        $counts = (clone $apps)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $accepted = (int) ($counts[Application::ACCEPTED] ?? 0);
        $completed = (int) ($counts[Application::COMPLETED] ?? 0);
        $confirmedIds = (clone $apps)->whereIn('status', Application::CONFIRMED)->pluck('user_id');

        $firstApplication = (clone $apps)->min('created_at');
        $start = $opportunity->published_at ?? $opportunity->created_at;

        $hoursByMonth = $opportunity->hourLogs()->where('status', HourLog::APPROVED)
            ->selectRaw(DateBucket::expression('worked_on').' as bucket, SUM(hours) as total')
            ->groupBy('bucket')->pluck('total', 'bucket')->map(fn ($v) => round((float) $v, 1))->all();

        $series = null;
        if ($hoursByMonth) {
            $keys = array_keys($hoursByMonth);
            sort($keys);
            $range = DateBucket::range(Carbon::parse($keys[0].'-01'), now()->max(Carbon::parse(end($keys).'-01')));
            $series = ['labels' => array_values($range), 'data' => DateBucket::fill($range, $hoursByMonth)];
        }

        return [
            'applicants' => (int) $counts->sum(),
            'pending' => (int) ($counts[Application::PENDING] ?? 0),
            'accepted' => $accepted,
            'completed' => $completed,
            'rejected' => (int) ($counts[Application::REJECTED] ?? 0),
            'withdrawn' => (int) ($counts[Application::WITHDRAWN] ?? 0),
            'fill_rate' => $opportunity->volunteers_needed ? min(100, (int) round(($accepted + $completed) / $opportunity->volunteers_needed * 100)) : null,
            'hours' => round((float) $opportunity->hourLogs()->where('status', HourLog::APPROVED)->sum('hours'), 1),
            'pending_hours' => round((float) $opportunity->hourLogs()->where('status', HourLog::PENDING)->sum('hours'), 1),
            'avg_rating' => ($r = (clone $apps)->whereNotNull('volunteer_rating')->avg('volunteer_rating')) ? round((float) $r, 1) : null,
            'days_to_first_applicant' => $firstApplication && $start ? max(0, (int) $start->diffInDays(Carbon::parse($firstApplication))) : null,
            'days_to_fill' => $opportunity->filled_at && $start ? max(0, (int) $start->diffInDays($opportunity->filled_at)) : null,
            'regions' => $this->regionCounts($confirmedIds),
            'hours_series' => $series,
        ];
    }

    // ----- Helpers -------------------------------------------------------

    /** @return Collection<int, object{name:string, total:int}> */
    private function skillCounts(Collection $opportunityIds, int $limit): Collection
    {
        if ($opportunityIds->isEmpty()) {
            return collect();
        }

        return DB::table('opportunity_skill')
            ->join('skills', 'skills.id', '=', 'opportunity_skill.skill_id')
            ->whereIn('opportunity_skill.opportunity_id', $opportunityIds)
            ->selectRaw('skills.name, COUNT(*) as total')
            ->groupBy('skills.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    private function categoryCounts(Collection $opportunityIds): Collection
    {
        if ($opportunityIds->isEmpty()) {
            return collect();
        }

        return DB::table('opportunities')
            ->join('categories', 'categories.id', '=', 'opportunities.category_id')
            ->whereIn('opportunities.id', $opportunityIds)
            ->selectRaw('categories.name, COUNT(*) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();
    }

    /** @return array<string,int> region label => volunteers */
    private function regionCounts(Collection $userIds): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        $labels = config('volunteering.regions');

        return Profile::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw("COALESCE(region, 'Unknown') as r, COUNT(*) as c")
            ->groupBy('r')
            ->orderByDesc('c')
            ->pluck('c', 'r')
            ->mapWithKeys(fn ($c, $r) => [($labels[$r] ?? 'Not specified') => (int) $c])
            ->all();
    }

    private function topVolunteers(Collection $opportunityIds, int $limit = 5): Collection
    {
        $rows = HourLog::query()
            ->whereIn('opportunity_id', $opportunityIds)
            ->where('status', HourLog::APPROVED)
            ->selectRaw('user_id, SUM(hours) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $users = User::with('profile')->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => (object) [
            'user' => $users[$row->user_id] ?? null,
            'hours' => round((float) $row->total, 1),
        ])->filter(fn ($row) => $row->user);
    }
}
