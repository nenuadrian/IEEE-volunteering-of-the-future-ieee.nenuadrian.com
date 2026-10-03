<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\HourLog;
use App\Models\Profile;
use App\Models\SearchLog;
use App\Models\Skill;
use App\Services\VolunteerCv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VolunteerController extends Controller
{
    public const SORTS = [
        'active' => 'Most active',
        'hours' => 'Most hours contributed',
        'newest' => 'Newest members',
        'name' => 'Name (A–Z)',
    ];

    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'skills' => array_values(array_filter((array) $request->query('skills', []), 'is_numeric')),
            'region' => $request->query('region'),
            'section' => trim((string) $request->query('section', '')),
            'society' => $request->query('society'),
            'grade' => $request->query('grade'),
            'country' => $request->query('country'),
            'available' => $request->boolean('available'),
            'endorsed' => $request->boolean('endorsed'),
        ];
        $sort = array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'active';

        $query = Profile::query()
            ->where('is_public', true)
            ->whereHas('user', fn ($q) => $q->whereNull('suspended_at'))
            ->with(['user.skills'])
            ->select('profiles.*')
            ->addSelect([
                'approved_hours' => HourLog::query()->selectRaw('COALESCE(SUM(hours), 0)')
                    ->whereColumn('hour_logs.user_id', 'profiles.user_id')
                    ->where('status', HourLog::APPROVED),
                'confirmed_count' => Application::query()->selectRaw('COUNT(*)')
                    ->whereColumn('applications.user_id', 'profiles.user_id')
                    ->whereIn('status', Application::CONFIRMED),
                'completed_count' => Application::query()->selectRaw('COUNT(*)')
                    ->whereColumn('applications.user_id', 'profiles.user_id')
                    ->where('status', Application::COMPLETED),
                'endorsement_count' => \App\Models\Endorsement::query()->selectRaw('COUNT(*)')
                    ->whereColumn('endorsements.user_id', 'profiles.user_id'),
            ]);

        if ($filters['q'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
            $query->where(function (Builder $w) use ($term) {
                $w->whereHas('user', fn ($u) => $u->where('name', 'like', $term))
                    ->orWhere('headline', 'like', $term)
                    ->orWhere('bio', 'like', $term)
                    ->orWhere('section', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhereHas('user.skills', fn ($s) => $s->where('name', 'like', $term));
            });
        }
        if ($filters['skills']) {
            foreach ($filters['skills'] as $skillId) {
                $query->whereHas('user.skills', fn ($s) => $s->where('skills.id', $skillId));
            }
        }
        foreach (['region', 'society', 'country'] as $col) {
            if ($filters[$col]) {
                $query->where($col, $filters[$col]);
            }
        }
        if ($filters['grade']) {
            $query->where('membership_grade', $filters['grade']);
        }
        if ($filters['section'] !== '') {
            $query->where('section', 'like', '%'.$filters['section'].'%');
        }
        if ($filters['available']) {
            $query->where('availability', 'available');
        }
        if ($filters['endorsed']) {
            $query->whereHas('user.endorsementsReceived');
        }

        match ($sort) {
            'hours' => $query->orderByDesc('approved_hours'),
            'newest' => $query->orderByDesc('profiles.created_at'),
            'name' => $query->orderBy(\App\Models\User::select('name')->whereColumn('users.id', 'profiles.user_id')),
            default => $query->orderByDesc('confirmed_count')->orderByDesc('approved_hours'),
        };

        $volunteers = $query->paginate(12)->withQueryString();

        SearchLog::capture('volunteers', $filters['q'], array_diff_key($filters, ['q' => 1]), $volunteers->total());

        $activeFilters = count(array_filter(array_diff_key($filters, ['q' => 1]), fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== false));

        return view('volunteers.index', [
            'volunteers' => $volunteers,
            'filters' => $filters,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'activeFilters' => $activeFilters,
            'skillOptions' => Skill::whereHas('users')->orderBy('name')->pluck('name', 'id'),
            'countries' => Profile::whereNotNull('country')->where('is_public', true)->distinct()->orderBy('country')->pluck('country'),
        ]);
    }

    public function show(Request $request, Profile $profile, VolunteerCv $cv)
    {
        $viewer = $request->user();
        $isOwn = $viewer && $viewer->id === $profile->user_id;

        abort_if((! $profile->is_public || $profile->user?->isSuspended()) && ! $isOwn && ! $viewer?->isAdmin(), 404);

        return view('volunteers.show', [
            'profile' => $profile,
            'cv' => $cv->build($profile->user),
            'isOwn' => $isOwn,
        ]);
    }
}
