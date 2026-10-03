<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Assembles everything shown on a volunteer's profile/CV page and in the
 * PDF CV: impact figures, volunteering experience, created opportunities,
 * skills (with endorsement counts) and endorsements.
 */
class VolunteerCv
{
    public const SECTIONS = [
        'statement' => 'Personal statement',
        'impact' => 'Impact summary',
        'skills' => 'Skills',
        'experience' => 'Volunteering experience',
        'created' => 'Opportunities created',
        'endorsements' => 'Endorsements',
        'links' => 'Links',
    ];

    public function build(User $user): array
    {
        $user->loadMissing(['profile', 'skills']);

        $experiences = Application::query()
            ->where('user_id', $user->id)
            ->whereIn('status', Application::CONFIRMED)
            ->with(['opportunity.category', 'opportunity.owners', 'endorsement.endorser'])
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->get()
            ->filter(fn ($a) => $a->opportunity)
            ->map(function (Application $a) {
                $o = $a->opportunity;

                return (object) [
                    'application' => $a,
                    'opportunity' => $o,
                    'title' => $o->title,
                    'organisation' => $o->society ?: ($o->section ? $o->section.' Section' : ($o->regionLabel() ?? 'IEEE')),
                    'category' => $o->category?->name,
                    'location' => $o->locationLabel(),
                    'start' => $a->decided_at ?? $o->start_date ?? $a->created_at,
                    'end' => $a->completed_at ?? ($o->end_date && $o->end_date->isPast() ? $o->end_date : null),
                    'in_progress' => $a->status === Application::ACCEPTED,
                    'hours' => round((float) $a->approved_hours, 1),
                    'description' => $o->description,
                    'upskills' => $o->upskills ?? [],
                    'endorsement' => $a->endorsement,
                    'rating' => $a->owner_rating,
                ];
            })
            ->sortByDesc(fn ($e) => [$e->in_progress ? 1 : 0, $e->start?->timestamp])
            ->values();

        $created = Opportunity::ownedBy($user)
            ->whereNot('status', Opportunity::DRAFT)
            ->with('category')
            ->withCount(['applications as confirmed_count' => fn ($q) => $q->whereIn('status', Application::CONFIRMED)])
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->orderByDesc('start_date')
            ->get();

        $endorsements = Endorsement::query()
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->with(['endorser.profile', 'opportunity', 'skills'])
            ->latest()
            ->get();

        $endorsedSkillCounts = DB::table('endorsement_skill')
            ->join('endorsements', 'endorsements.id', '=', 'endorsement_skill.endorsement_id')
            ->where('endorsements.user_id', $user->id)
            ->selectRaw('endorsement_skill.skill_id, COUNT(*) as total')
            ->groupBy('endorsement_skill.skill_id')
            ->pluck('total', 'skill_id');

        $skills = $user->skills
            ->map(fn ($s) => (object) ['name' => $s->name, 'endorsements' => (int) ($endorsedSkillCounts[$s->id] ?? 0)])
            ->sortByDesc('endorsements')
            ->values();

        $upskills = $experiences->where('in_progress', false)->pluck('upskills')->flatten()->countBy()->sortDesc();

        $hours = (float) HourLog::where('user_id', $user->id)->where('status', HourLog::APPROVED)->sum('hours');
        $volunteersLed = $created->sum('confirmed_count');

        return [
            'user' => $user,
            'profile' => $user->profile,
            'experiences' => $experiences,
            'created' => $created,
            'endorsements' => $endorsements,
            'skills' => $skills,
            'upskills' => $upskills,
            'stats' => [
                'hours' => round($hours, 1),
                'completed' => $experiences->where('in_progress', false)->count(),
                'active' => $experiences->where('in_progress', true)->count(),
                'endorsements' => $endorsements->count(),
                'created' => $created->count(),
                'volunteers_led' => (int) $volunteersLed,
                'hours_enabled' => round((float) $created->sum('approved_hours'), 1),
                'avg_rating' => ($r = $experiences->pluck('rating')->filter()->avg()) ? round($r, 1) : null,
            ],
        ];
    }
}
