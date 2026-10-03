<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Category;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\SearchLog;
use App\Models\User;
use App\Support\DateBucket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide metrics for the admin analytics page. One instance describes
 * one slice — a date range plus an optional IEEE region and opportunity type —
 * and every figure it returns respects that slice:
 *
 *  - Region is the volunteer's profile region for people and activity
 *    metrics (sign-ups, applications, hours…) and the opportunity's region
 *    for opportunity metrics (publishing, fill rate, time to fill…).
 *  - Type limits opportunities and everything that happened on them.
 *    Sign-ups and profiles aren't tied to a type, so they ignore it.
 *
 * All time series are bucketed in SQL through DateBucket (MySQL + SQLite).
 */
class PlatformAnalytics
{
    public const RANGES = [
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        '12m' => 'Last 12 months',
        'all' => 'All time',
    ];

    public const DEFAULT_RANGE = '12m';

    /** Fixed palette slots so each entity keeps its colour across charts. */
    private const SLOT_IEEE = 0;

    private const SLOT_LOCAL = 1;

    private const SLOT_VOLUNTEERS = 2;

    private const SLOT_HOURS = 3;

    private const SLOT_APPLICATIONS = 4;

    private const SLOT_ACCEPTED = 5;

    private const SLOT_SEARCHES = 6;

    private const SLOT_ZERO_RESULTS = 7;

    public readonly Carbon $from;

    public readonly Carbon $to;

    public readonly ?Carbon $previousFrom;

    public readonly ?Carbon $previousTo;

    /** day | week | month */
    public readonly string $unit;

    public function __construct(
        public readonly string $range = self::DEFAULT_RANGE,
        public readonly ?string $region = null,
        public readonly ?Category $category = null,
    ) {
        $this->to = now()->endOfDay();

        $this->from = match ($range) {
            '30d' => now()->subDays(29)->startOfDay(),
            '90d' => now()->subDays(89)->startOfDay(),
            'all' => $this->firstSignup(),
            default => now()->subMonths(11)->startOfMonth(),
        };

        $this->unit = match ($range) {
            '30d' => 'day',
            '90d' => 'week',
            default => 'month',
        };

        // Previous period of equal length, ending just before this one starts.
        if ($range === 'all') {
            $this->previousFrom = null;
            $this->previousTo = null;
        } else {
            $length = (int) $this->from->diffInSeconds($this->to, true);
            $this->previousTo = $this->from->copy()->subSecond();
            $this->previousFrom = $this->previousTo->copy()->subSeconds($length);
        }
    }

    /** Build from the analytics filter form; unknown values fall back to defaults. */
    public static function fromRequest(Request $request): self
    {
        $range = (string) $request->query('range', self::DEFAULT_RANGE);
        $region = (string) $request->query('region', '');
        $category = (string) $request->query('category', '');

        return new self(
            array_key_exists($range, self::RANGES) ? $range : self::DEFAULT_RANGE,
            array_key_exists($region, config('volunteering.regions')) ? $region : null,
            $category !== '' ? Category::query()->where('slug', $category)->first() : null,
        );
    }

    /** Query-string parameters describing this slice (for links and exports). */
    public function params(): array
    {
        return array_filter([
            'range' => $this->range,
            'region' => $this->region,
            'category' => $this->category?->slug,
        ]);
    }

    public function rangeLabel(): string
    {
        return self::RANGES[$this->range];
    }

    public function periodLabel(): string
    {
        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    public function previousPeriodLabel(): ?string
    {
        return $this->previousFrom ? $this->previousFrom->format('j M Y').' – '.$this->previousTo->format('j M Y') : null;
    }

    public function regionLabel(): ?string
    {
        return $this->region ? config('volunteering.regions')[$this->region] : null;
    }

    /** Everything the analytics page shows. */
    public function report(): array
    {
        $series = $this->series();
        $funnel = $this->funnel();
        $regions = $this->volunteersByRegion();
        $grades = $this->volunteersByGrade();
        $sizes = $this->opportunitiesBySize();
        $search = $this->searchAnalytics();

        return [
            'kpis' => $this->kpis(),
            'series' => $series,
            'funnel' => $funnel,
            'categories' => $this->categoryPerformance(),
            'regions' => $regions,
            'grades' => $grades,
            'sizes' => $sizes,
            'skills' => $this->skillsSupplyDemand(),
            'search' => $search,
            'leaders' => [
                'volunteers' => $this->topVolunteers(),
                'opportunities' => $this->mostAppliedOpportunities(),
                'creators' => $this->mostActiveCreators(),
            ],
            'cohorts' => $this->cohorts(),
            'charts' => $this->charts($series, $funnel, $regions, $grades, $sizes, $search),
        ];
    }

    // ----- KPIs ----------------------------------------------------------

    /**
     * @return array<string, array{label:string, value:int|float|null, previous:int|float|null, format:string, delta:int|null, hint:string}>
     */
    public function kpis(): array
    {
        $now = $this->measure($this->from, $this->to);
        $before = $this->previousFrom ? $this->measure($this->previousFrom, $this->previousTo) : null;

        $importShare = $now['new_opportunities'] ? (int) round($now['imported_opportunities'] / $now['new_opportunities'] * 100) : 0;

        $definitions = [
            'new_volunteers' => ['New volunteers', 'number', $this->category ? 'All types — sign-ups are not tied to a type' : 'Accounts created in the period'],
            'active_volunteers' => ['Active volunteers', 'number', 'Applied or logged hours in the period'],
            'new_opportunities' => ['New opportunities', 'number', $importShare.'% imported from volunteer.ieee.org'],
            'applications' => ['Applications', 'number', 'Submitted in the period'],
            'acceptance_rate' => ['Acceptance rate', 'percent', 'Accepted ÷ decided'],
            'fill_rate' => ['Fill rate', 'percent', 'Published opportunities that reached their target'],
            'days_to_fill' => ['Median days to fill', 'days', 'Published → volunteer target reached'],
            'days_to_first_applicant' => ['Median days to first applicant', 'days', 'Published → first application'],
            'approved_hours' => ['Approved volunteer hours', 'hours', 'By date worked'],
            'completion_rate' => ['Completion rate', 'percent', 'Confirmed volunteers marked completed'],
            'retention' => ['Retention', 'percent', 'Confirmed volunteers who were confirmed before, or 2+ times'],
            'endorsements' => ['Endorsements given', 'number', 'Written by opportunity owners'],
        ];

        $kpis = [];
        foreach ($definitions as $key => [$label, $format, $hint]) {
            $value = $now[$key];
            $previous = $before[$key] ?? null;

            // Rates and medians: a "% change" badge would mislead (and for
            // medians lower is better), so show the previous value instead.
            $comparable = $format === 'number' || $format === 'hours';
            if (! $comparable && $before !== null) {
                $hint .= ' · previous: '.self::format($previous, $format);
            }

            $kpis[$key] = [
                'label' => $label,
                'value' => $value,
                'previous' => $previous,
                'format' => $format,
                'delta' => $comparable && $before !== null ? self::change($value, $previous) : null,
                'hint' => $hint,
            ];
        }

        return $kpis;
    }

    /** Raw KPI values for one period. */
    private function measure(Carbon $from, Carbon $to): array
    {
        $dates = [$from->toDateString(), $to->toDateString()];

        $published = $this->opportunities()
            ->whereBetween('opportunities.published_at', [$from, $to])
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN source = 'ieee' THEN 1 ELSE 0 END) as imported, SUM(CASE WHEN filled_at IS NOT NULL THEN 1 ELSE 0 END) as filled")
            ->first();

        $decided = $this->applications()
            ->whereIn('applications.status', [Application::ACCEPTED, Application::COMPLETED, Application::REJECTED])
            ->whereBetween('applications.decided_at', [$from, $to])
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN applications.status IN ('accepted', 'completed') THEN 1 ELSE 0 END) as confirmed, SUM(CASE WHEN applications.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        $daysToFill = $this->opportunities()
            ->whereBetween('opportunities.filled_at', [$from, $to])
            ->whereNotNull('opportunities.published_at')
            ->toBase()
            ->get(['opportunities.published_at', 'opportunities.filled_at'])
            ->map(fn ($r) => self::daysBetween($r->published_at, $r->filled_at));

        $daysToFirst = $this->opportunities()
            ->whereBetween('opportunities.published_at', [$from, $to])
            ->toBase()
            ->select('opportunities.published_at')
            ->selectSub(
                DB::table('applications')->selectRaw('MIN(applications.created_at)')->whereColumn('applications.opportunity_id', 'opportunities.id'),
                'first_applied_at',
            )
            ->get()
            ->filter(fn ($r) => $r->first_applied_at)
            ->map(fn ($r) => self::daysBetween($r->published_at, $r->first_applied_at));

        return [
            'new_volunteers' => $this->users()->whereBetween('users.created_at', [$from, $to])->count(),
            'active_volunteers' => $this->activeUsers($from, $to)->count(),
            'new_opportunities' => (int) $published->total,
            'imported_opportunities' => (int) $published->imported,
            'applications' => $this->applications()->whereBetween('applications.created_at', [$from, $to])->count(),
            'acceptance_rate' => self::percent($decided->confirmed, $decided->total),
            'fill_rate' => self::percent($published->filled, $published->total),
            'days_to_fill' => self::median($daysToFill),
            'days_to_first_applicant' => self::median($daysToFirst),
            'approved_hours' => round((float) $this->hourLogs()
                ->where('hour_logs.status', HourLog::APPROVED)
                ->whereBetween('hour_logs.worked_on', $dates)
                ->sum('hour_logs.hours'), 1),
            'completion_rate' => self::percent($decided->completed, $decided->confirmed),
            'retention' => $this->retention($from, $to),
            'endorsements' => $this->endorsements()->whereBetween('endorsements.created_at', [$from, $to])->count(),
        ];
    }

    /**
     * Of the volunteers confirmed in the period, the share who had been
     * confirmed before it or were confirmed at least twice within it.
     */
    private function retention(Carbon $from, Carbon $to): ?float
    {
        $rows = $this->applications()
            ->whereIn('applications.status', Application::CONFIRMED)
            ->where('applications.decided_at', '<=', $to)
            ->toBase()
            ->selectRaw(
                'applications.user_id, SUM(CASE WHEN applications.decided_at < ? THEN 1 ELSE 0 END) as before_count, SUM(CASE WHEN applications.decided_at >= ? THEN 1 ELSE 0 END) as during_count',
                [$from, $from],
            )
            ->groupBy('applications.user_id')
            ->get()
            ->filter(fn ($r) => $r->during_count > 0);

        $returning = $rows->filter(fn ($r) => $r->before_count > 0 || $r->during_count >= 2)->count();

        return self::percent($returning, $rows->count());
    }

    // ----- Time series ---------------------------------------------------

    /** Gap-filled series for the selected range, keyed by measure. */
    public function series(): array
    {
        $range = DateBucket::range($this->from, $this->to, $this->unit);
        $between = [$this->from, $this->to];

        $count = function (Builder $query, string $column) use ($range): array {
            $values = $query->toBase()
                ->selectRaw(DateBucket::expression($column, $this->unit).' as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn ($v) => (int) $v)
                ->all();

            return DateBucket::fill($range, $values);
        };

        $hours = $this->hourLogs()
            ->where('hour_logs.status', HourLog::APPROVED)
            ->whereBetween('hour_logs.worked_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->toBase()
            ->selectRaw(DateBucket::expression('hour_logs.worked_on', $this->unit).' as bucket, SUM(hour_logs.hours) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($v) => round((float) $v, 1))
            ->all();

        $published = $this->opportunities()
            ->whereBetween('opportunities.published_at', $between)
            ->toBase()
            ->selectRaw(DateBucket::expression('opportunities.published_at', $this->unit).' as bucket, opportunities.source, COUNT(*) as total')
            ->groupBy('bucket', 'opportunities.source')
            ->get();

        $bySource = fn (string $source) => DateBucket::fill($range, $published
            ->where('source', $source)
            ->mapWithKeys(fn ($r) => [$r->bucket => (int) $r->total])
            ->all());

        return [
            'keys' => array_keys($range),
            'labels' => array_values($range),
            'volunteers' => $count($this->users()->whereBetween('users.created_at', $between), 'users.created_at'),
            'applications' => $count($this->applications()->whereBetween('applications.created_at', $between), 'applications.created_at'),
            'accepted' => $count(
                $this->applications()->whereIn('applications.status', Application::CONFIRMED)->whereBetween('applications.decided_at', $between),
                'applications.decided_at',
            ),
            'hours' => DateBucket::fill($range, $hours),
            'published_local' => $bySource(Opportunity::SOURCE_LOCAL),
            'published_ieee' => $bySource(Opportunity::SOURCE_IEEE),
            'searches' => $count($this->searches(), 'search_logs.created_at'),
            'zero_results' => $count($this->searches()->where('search_logs.results_count', 0), 'search_logs.created_at'),
        ];
    }

    // ----- Engagement funnel ---------------------------------------------

    /**
     * Strict funnel for people who joined in the period: each step counts
     * those who also reached every earlier step. Profile completeness mirrors
     * Profile::completeness() (6 of its 8 checks = 75%).
     *
     * @return array{steps: array<int, array{label:string, count:int, of_previous:float|null, of_total:float|null}>, applied_incomplete:int, applied_total:int}
     */
    public function funnel(): array
    {
        $stats = DB::table('applications')
            ->tap(fn ($q) => $this->inCategory($q, 'applications.opportunity_id'))
            ->selectRaw("applications.user_id, COUNT(*) as apps, SUM(CASE WHEN applications.status IN ('accepted', 'completed') THEN 1 ELSE 0 END) as confirmed, SUM(CASE WHEN applications.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->groupBy('applications.user_id');

        $people = DB::table('users')
            ->join('profiles as p', 'p.user_id', '=', 'users.id')
            ->leftJoinSub($stats, 's', 's.user_id', '=', 'users.id')
            ->whereBetween('users.created_at', [$this->from, $this->to])
            ->when($this->region, fn ($q) => $q->where('p.region', $this->region))
            ->selectRaw(self::profileScoreSql('p').' as score, COALESCE(s.apps, 0) as apps, COALESCE(s.confirmed, 0) as confirmed, COALESCE(s.completed, 0) as completed')
            ->get();

        $complete = $people->filter(fn ($p) => $p->score >= 6);
        $applied = $complete->filter(fn ($p) => $p->apps > 0);
        $accepted = $applied->filter(fn ($p) => $p->confirmed > 0);
        $completed = $accepted->filter(fn ($p) => $p->completed > 0);
        $returned = $completed->filter(fn ($p) => $p->confirmed >= 2);

        $counts = [
            'Registered' => $people->count(),
            'Profile ≥ 75% complete' => $complete->count(),
            'Applied' => $applied->count(),
            'Accepted' => $accepted->count(),
            'Completed' => $completed->count(),
            'Returned (2+ confirmed)' => $returned->count(),
        ];

        $steps = [];
        $previous = null;
        foreach ($counts as $label => $count) {
            $steps[] = [
                'label' => $label,
                'count' => $count,
                'of_previous' => $previous === null ? null : self::percent($count, $previous),
                'of_total' => self::percent($count, $counts['Registered']),
            ];
            $previous = $count;
        }

        $allApplicants = $people->filter(fn ($p) => $p->apps > 0);

        return [
            'steps' => $steps,
            'applied_total' => $allApplicants->count(),
            'applied_incomplete' => $allApplicants->filter(fn ($p) => $p->score < 6)->count(),
        ];
    }

    /** SQL expression scoring a profile 0–8, matching Profile::completeness(). */
    private static function profileScoreSql(string $p): string
    {
        $filled = fn (string $column) => "CASE WHEN {$p}.{$column} IS NOT NULL AND {$p}.{$column} <> '' THEN 1 ELSE 0 END";

        return '('.implode(' + ', [
            $filled('avatar_path'),
            $filled('headline'),
            "CASE WHEN {$p}.bio IS NOT NULL AND LENGTH({$p}.bio) >= 60 THEN 1 ELSE 0 END",
            "CASE WHEN (SELECT COUNT(*) FROM skill_user su WHERE su.user_id = {$p}.user_id) >= 3 THEN 1 ELSE 0 END",
            $filled('region'),
            $filled('section'),
            $filled('membership_grade'),
            "CASE WHEN COALESCE({$p}.linkedin_url, '') <> '' OR COALESCE({$p}.website_url, '') <> '' OR COALESCE({$p}.github_url, '') <> '' THEN 1 ELSE 0 END",
        ]).')';
    }

    // ----- Opportunities -------------------------------------------------

    /**
     * Opportunities published in the period, by type, with how their
     * applications went.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: array<string, mixed>}
     */
    public function categoryPerformance(): array
    {
        $between = [$this->from, $this->to];

        $opportunities = $this->opportunities()
            ->whereBetween('opportunities.published_at', $between)
            ->toBase()
            ->selectRaw('opportunities.category_id, COUNT(*) as published, SUM(CASE WHEN opportunities.filled_at IS NOT NULL THEN 1 ELSE 0 END) as filled')
            ->groupBy('opportunities.category_id')
            ->get()
            ->keyBy(fn ($r) => (string) $r->category_id);

        $applications = $this->opportunities()
            ->whereBetween('opportunities.published_at', $between)
            ->join('applications', 'applications.opportunity_id', '=', 'opportunities.id')
            ->toBase()
            ->selectRaw("opportunities.category_id, COUNT(*) as applications, SUM(CASE WHEN applications.status IN ('accepted', 'completed') THEN 1 ELSE 0 END) as confirmed, SUM(CASE WHEN applications.status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(applications.volunteer_rating) as rating_sum, COUNT(applications.volunteer_rating) as rating_count")
            ->groupBy('opportunities.category_id')
            ->get()
            ->keyBy(fn ($r) => (string) $r->category_id);

        $categories = Category::query()->whereIn('id', $opportunities->keys()->filter())->get()->keyBy('id');

        $row = function (string $name, ?string $color, $o, $a) {
            $published = (int) ($o->published ?? 0);
            $apps = (int) ($a->applications ?? 0);

            return [
                'name' => $name,
                'color' => $color,
                'published' => $published,
                'applications' => $apps,
                'avg_applicants' => $published ? round($apps / $published, 1) : null,
                'fill_rate' => self::percent($o->filled ?? 0, $published),
                'completion_rate' => self::percent($a->completed ?? 0, $a->confirmed ?? 0),
                'avg_rating' => ($a->rating_count ?? 0) ? round($a->rating_sum / $a->rating_count, 1) : null,
            ];
        };

        $rows = $opportunities->map(function ($o, $id) use ($categories, $applications, $row) {
            $category = $categories[$id] ?? null;

            return $row($category?->name ?? 'Uncategorised', $category?->colorOrDefault(), $o, $applications[$id] ?? null);
        })->sortByDesc('published')->values()->all();

        $sum = fn ($rows, string $key) => $rows->sum(fn ($r) => (float) ($r->{$key} ?? 0));

        $total = $row('All types', null, (object) [
            'published' => $sum($opportunities, 'published'),
            'filled' => $sum($opportunities, 'filled'),
        ], (object) [
            'applications' => $sum($applications, 'applications'),
            'confirmed' => $sum($applications, 'confirmed'),
            'completed' => $sum($applications, 'completed'),
            'rating_sum' => $sum($applications, 'rating_sum'),
            'rating_count' => $sum($applications, 'rating_count'),
        ]);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Opportunities published in the period by duration, split by source.
     *
     * @return array{labels: array<int,string>, local: array<int,int>, ieee: array<int,int>}
     */
    public function opportunitiesBySize(): array
    {
        $rows = $this->opportunities()
            ->whereBetween('opportunities.published_at', [$this->from, $this->to])
            ->toBase()
            ->selectRaw('opportunities.project_size, opportunities.source, COUNT(*) as total')
            ->groupBy('opportunities.project_size', 'opportunities.source')
            ->get();

        $sizes = array_keys(config('volunteering.project_sizes'));
        $known = array_merge($sizes, $rows->pluck('project_size')->filter()->unique()->diff($sizes)->values()->all());
        $keys = $rows->contains(fn ($r) => blank($r->project_size)) ? array_merge($known, ['']) : $known;

        $pick = fn (string $source) => array_map(
            fn ($size) => (int) $rows->filter(fn ($r) => (string) $r->project_size === $size && $r->source === $source)->sum('total'),
            $keys,
        );

        return [
            'labels' => array_map(fn ($k) => $k === '' ? 'Not specified' : $k, $keys),
            'local' => $pick(Opportunity::SOURCE_LOCAL),
            'ieee' => $pick(Opportunity::SOURCE_IEEE),
        ];
    }

    // ----- Volunteers ----------------------------------------------------

    /**
     * Active volunteers by region — or by section when a region is selected,
     * since a single bar says nothing.
     *
     * @return array{by:string, rows: array<string,int>}
     */
    public function volunteersByRegion(): array
    {
        $active = $this->activeUsers($this->from, $this->to)->select('users.id');

        if ($this->region) {
            $rows = DB::table('profiles')
                ->whereIn('user_id', $active)
                ->selectRaw("COALESCE(NULLIF(section, ''), 'Not specified') as label, COUNT(*) as total")
                ->groupBy('label')
                ->orderByDesc('total')
                ->limit(12)
                ->pluck('total', 'label')
                ->map(fn ($v) => (int) $v)
                ->all();

            return ['by' => 'section', 'rows' => $rows];
        }

        $labels = config('volunteering.regions');
        $counts = DB::table('profiles')
            ->whereIn('user_id', $active)
            ->selectRaw("COALESCE(region, '') as code, COUNT(*) as total")
            ->groupBy('code')
            ->pluck('total', 'code');

        $rows = [];
        foreach ($labels as $code => $label) {
            $rows[str_replace('Region ', 'R', $label)] = (int) ($counts[$code] ?? 0);
        }
        if ($unknown = $counts->except(array_keys($labels))->sum()) {
            $rows['Not specified'] = (int) $unknown;
        }

        return ['by' => 'region', 'rows' => $rows];
    }

    /**
     * Active volunteers by membership grade: the eight largest grades, the
     * rest folded into "Other grades" so every bar keeps a readable label.
     *
     * @return array<string,int>
     */
    public function volunteersByGrade(int $limit = 8): array
    {
        $labels = config('volunteering.membership_grades');

        $rows = DB::table('profiles')
            ->whereIn('user_id', $this->activeUsers($this->from, $this->to)->select('users.id'))
            ->selectRaw("COALESCE(membership_grade, '') as grade, COUNT(*) as total")
            ->groupBy('grade')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($r) => [($labels[$r->grade] ?? ($r->grade ?: 'Not specified')) => (int) $r->total]);

        if ($rows->count() <= $limit + 1) {
            return $rows->all();
        }

        return $rows->take($limit)->put('Other grades', $rows->skip($limit)->sum())->all();
    }

    // ----- Skills --------------------------------------------------------

    /**
     * Top skills by demand (open opportunities asking for them) against
     * supply (volunteers listing them), with a shortage indicator.
     *
     * @return array<int, array<string, mixed>>
     */
    public function skillsSupplyDemand(int $limit = 15): array
    {
        $demand = DB::table('opportunity_skill')
            ->join('opportunities', 'opportunities.id', '=', 'opportunity_skill.opportunity_id')
            ->join('skills', 'skills.id', '=', 'opportunity_skill.skill_id')
            ->whereNull('opportunities.deleted_at')
            ->where('opportunities.status', Opportunity::OPEN)
            ->tap(fn ($q) => $this->whereOpportunityMatches($q))
            ->selectRaw('skills.id, skills.name, skills.category, COUNT(*) as open_opportunities')
            ->groupBy('skills.id', 'skills.name', 'skills.category')
            ->orderByDesc('open_opportunities')
            ->orderBy('skills.name')
            ->limit($limit)
            ->get();

        if ($demand->isEmpty()) {
            return [];
        }

        $ids = $demand->pluck('id');

        $requested = DB::table('opportunity_skill')
            ->join('opportunities', 'opportunities.id', '=', 'opportunity_skill.opportunity_id')
            ->whereNull('opportunities.deleted_at')
            ->whereIn('opportunity_skill.skill_id', $ids)
            ->whereBetween('opportunities.published_at', [$this->from, $this->to])
            ->tap(fn ($q) => $this->whereOpportunityMatches($q))
            ->selectRaw('opportunity_skill.skill_id, COUNT(*) as total')
            ->groupBy('opportunity_skill.skill_id')
            ->pluck('total', 'skill_id');

        $supply = DB::table('skill_user')
            ->whereIn('skill_user.skill_id', $ids)
            ->tap(fn ($q) => $this->inRegion($q, 'skill_user.user_id'))
            ->selectRaw('skill_user.skill_id, COUNT(*) as total')
            ->groupBy('skill_user.skill_id')
            ->pluck('total', 'skill_id');

        $rows = $demand->map(function ($skill) use ($supply, $requested) {
            $volunteers = (int) ($supply[$skill->id] ?? 0);
            $open = (int) $skill->open_opportunities;

            return [
                'name' => $skill->name,
                'category' => $skill->category,
                'open_opportunities' => $open,
                'requested_in_period' => (int) ($requested[$skill->id] ?? 0),
                'volunteers' => $volunteers,
                // Open opportunities per 100 volunteers with the skill.
                'gap' => $volunteers ? round($open / $volunteers * 100, 1) : null,
            ];
        });

        // A shortage is a skill with no volunteers, or a demand-per-volunteer
        // ratio at least twice the median of the listed skills.
        $median = self::median($rows->pluck('gap')->filter(fn ($g) => $g !== null)) ?? 0;

        return $rows->map(fn ($r) => $r + [
            'shortage' => $r['gap'] === null || ($median > 0 && $r['gap'] >= 2 * $median),
        ])->all();
    }

    // ----- Search --------------------------------------------------------

    public function searchAnalytics(): array
    {
        $totals = $this->searches()
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero, SUM(CASE WHEN filters IS NOT NULL THEN 1 ELSE 0 END) as filtered, SUM(CASE WHEN scope = 'volunteers' THEN 1 ELSE 0 END) as volunteers")
            ->first();

        $topTerms = $this->searches()
            ->whereNotNull('search_logs.query')
            ->where('search_logs.query', '<>', '')
            ->toBase()
            ->selectRaw('search_logs.query, search_logs.scope, COUNT(*) as total, AVG(search_logs.results_count) as avg_results')
            ->groupBy('search_logs.query', 'search_logs.scope')
            ->orderByDesc('total')
            ->orderBy('search_logs.query')
            ->limit(10)
            ->get();

        $zeroTerms = $this->searches()
            ->where('search_logs.results_count', 0)
            ->toBase()
            ->selectRaw('search_logs.query, search_logs.scope, COUNT(*) as total, MAX(search_logs.created_at) as last_searched')
            ->groupBy('search_logs.query', 'search_logs.scope')
            ->orderByDesc('total')
            ->orderByDesc('last_searched')
            ->limit(10)
            ->get();

        $total = (int) $totals->total;

        return [
            'total' => $total,
            'zero' => (int) $totals->zero,
            'zero_rate' => self::percent($totals->zero, $total),
            'filtered_rate' => self::percent($totals->filtered, $total),
            'volunteer_searches' => (int) $totals->volunteers,
            'opportunity_searches' => $total - (int) $totals->volunteers,
            'top_terms' => $topTerms,
            'zero_terms' => $zeroTerms,
        ];
    }

    // ----- Leaderboards --------------------------------------------------

    public function topVolunteers(int $limit = 10): Collection
    {
        $rows = $this->hourLogs()
            ->where('hour_logs.status', HourLog::APPROVED)
            ->whereBetween('hour_logs.worked_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->toBase()
            ->selectRaw('hour_logs.user_id, SUM(hour_logs.hours) as total, COUNT(DISTINCT hour_logs.opportunity_id) as opportunities')
            ->groupBy('hour_logs.user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $users = User::with('profile')->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows
            ->map(fn ($r) => ['user' => $users[$r->user_id] ?? null, 'hours' => round((float) $r->total, 1), 'opportunities' => (int) $r->opportunities])
            ->filter(fn ($r) => $r['user'])
            ->values();
    }

    public function mostAppliedOpportunities(int $limit = 10): Collection
    {
        $rows = DB::table('applications')
            ->join('opportunities', 'opportunities.id', '=', 'applications.opportunity_id')
            ->whereBetween('applications.created_at', [$this->from, $this->to])
            ->tap(fn ($q) => $this->whereOpportunityMatches($q))
            ->selectRaw("applications.opportunity_id, COUNT(*) as total, SUM(CASE WHEN applications.status IN ('accepted', 'completed') THEN 1 ELSE 0 END) as confirmed")
            ->groupBy('applications.opportunity_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $opportunities = Opportunity::withTrashed()->with('category')->whereIn('id', $rows->pluck('opportunity_id'))->get()->keyBy('id');

        return $rows
            ->map(fn ($r) => ['opportunity' => $opportunities[$r->opportunity_id] ?? null, 'applications' => (int) $r->total, 'confirmed' => (int) $r->confirmed])
            ->filter(fn ($r) => $r['opportunity'])
            ->values();
    }

    /** People who published the most opportunities in the period. */
    public function mostActiveCreators(int $limit = 10): Collection
    {
        $between = [$this->from, $this->to];

        $rows = $this->opportunities()
            ->whereBetween('opportunities.published_at', $between)
            ->whereNotNull('opportunities.created_by')
            ->toBase()
            ->selectRaw('opportunities.created_by, COUNT(*) as published')
            ->groupBy('opportunities.created_by')
            ->orderByDesc('published')
            ->limit($limit * 3)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $received = $this->opportunities()
            ->join('applications', 'applications.opportunity_id', '=', 'opportunities.id')
            ->whereIn('opportunities.created_by', $rows->pluck('created_by'))
            ->whereBetween('applications.created_at', $between)
            ->toBase()
            ->selectRaw('opportunities.created_by, COUNT(*) as total')
            ->groupBy('opportunities.created_by')
            ->pluck('total', 'created_by');

        $users = User::with('profile')->whereIn('id', $rows->pluck('created_by'))->get()->keyBy('id');

        return $rows
            ->map(fn ($r) => [
                'user' => $users[$r->created_by] ?? null,
                'published' => (int) $r->published,
                'applications' => (int) ($received[$r->created_by] ?? 0),
            ])
            ->filter(fn ($r) => $r['user'])
            ->sortBy([['published', 'desc'], ['applications', 'desc']])
            ->take($limit)
            ->values();
    }

    // ----- Cohorts -------------------------------------------------------

    /**
     * Monthly sign-up cohorts × months since joining; each cell is the share
     * of the cohort that applied or logged hours in that month.
     *
     * @return array{months:int, rows: array<int, array{label:string, size:int, cells: array<int, float|null>}>}
     */
    public function cohorts(): array
    {
        $months = in_array($this->range, ['30d', '90d'], true) ? 6 : 12;
        $start = now()->subMonths($months - 1)->startOfMonth();
        $monthKey = DateBucket::expression('users.created_at', 'month');

        $cohortUsers = fn () => DB::table('users')
            ->where('users.created_at', '>=', $start)
            ->tap(fn ($q) => $this->inRegion($q, 'users.id'));

        $members = $cohortUsers()->selectRaw("users.id, {$monthKey} as cohort")->get();

        $activity = function (string $table, string $column) use ($cohortUsers, $start) {
            return DB::table($table)
                ->whereIn("{$table}.user_id", $cohortUsers()->select('users.id'))
                ->where("{$table}.{$column}", '>=', $column === 'worked_on' ? $start->toDateString() : $start)
                ->tap(fn ($q) => $this->inCategory($q, "{$table}.opportunity_id"))
                ->selectRaw("{$table}.user_id, ".DateBucket::expression("{$table}.{$column}", 'month').' as month')
                ->distinct()
                ->get();
        };

        $active = [];
        foreach ([$activity('applications', 'created_at'), $activity('hour_logs', 'worked_on')] as $rows) {
            foreach ($rows as $row) {
                $active[$row->user_id][$row->month] = true;
            }
        }

        $current = now()->format('Y-m');
        $rows = [];
        foreach (DateBucket::range($start, now(), 'month') as $key => $label) {
            $ids = $members->where('cohort', $key)->pluck('id');
            $cohortStart = Carbon::createFromFormat('Y-m-d', $key.'-01')->startOfMonth();
            $cells = [];

            for ($offset = 0; $offset < $months; $offset++) {
                $month = $cohortStart->copy()->addMonthsNoOverflow($offset)->format('Y-m');
                $cells[] = $month > $current || $ids->isEmpty()
                    ? null
                    : self::percent($ids->filter(fn ($id) => isset($active[$id][$month]))->count(), $ids->count());
            }

            $rows[] = ['label' => $label, 'size' => $ids->count(), 'cells' => $cells];
        }

        return ['months' => $months, 'rows' => $rows];
    }

    /** Heat-map background + text classes for a cohort cell. */
    public static function heatClass(?float $percent): string
    {
        return match (true) {
            $percent === null => 'bg-white text-warm-gray',
            $percent <= 0 => 'bg-warm-white text-warm-gray',
            $percent < 10 => 'bg-brand-50 text-brand-800',
            $percent < 20 => 'bg-brand-100 text-brand-800',
            $percent < 35 => 'bg-brand-200 text-brand-800',
            $percent < 50 => 'bg-brand-light text-ink',
            $percent < 65 => 'bg-brand text-white',
            default => 'bg-brand-700 text-white',
        };
    }

    // ----- Chart configs (see resources/js/charts.js) --------------------

    private function charts(array $series, array $funnel, array $regions, array $grades, array $sizes, array $search): array
    {
        $category = ucfirst($this->unit);

        return [
            'volunteers' => [
                'type' => 'line', 'labels' => $series['labels'], 'categoryLabel' => $category,
                'series' => [['name' => 'New volunteers', 'data' => $series['volunteers'], 'slot' => self::SLOT_VOLUNTEERS]],
            ],
            'applications' => [
                'type' => 'line', 'labels' => $series['labels'], 'categoryLabel' => $category,
                'series' => [
                    ['name' => 'Applications', 'data' => $series['applications'], 'slot' => self::SLOT_APPLICATIONS],
                    ['name' => 'Accepted', 'data' => $series['accepted'], 'slot' => self::SLOT_ACCEPTED],
                ],
            ],
            'hours' => [
                'type' => 'bar', 'labels' => $series['labels'], 'categoryLabel' => $category, 'format' => 'hours',
                'series' => [['name' => 'Approved hours', 'data' => $series['hours'], 'slot' => self::SLOT_HOURS]],
            ],
            'published' => [
                'type' => 'bar', 'stacked' => true, 'labels' => $series['labels'], 'categoryLabel' => $category,
                'series' => [
                    ['name' => 'Local', 'data' => $series['published_local'], 'slot' => self::SLOT_LOCAL],
                    ['name' => 'IEEE import', 'data' => $series['published_ieee'], 'slot' => self::SLOT_IEEE],
                ],
            ],
            'funnel' => [
                'type' => 'hbar', 'labels' => array_column($funnel['steps'], 'label'), 'categoryLabel' => 'Step',
                'series' => [['name' => 'People', 'data' => array_column($funnel['steps'], 'count'), 'slot' => self::SLOT_VOLUNTEERS]],
            ],
            'regions' => [
                'type' => 'hbar', 'labels' => array_keys($regions['rows']), 'categoryLabel' => ucfirst($regions['by']),
                'series' => [['name' => 'Active volunteers', 'data' => array_values($regions['rows']), 'slot' => self::SLOT_VOLUNTEERS]],
            ],
            'grades' => [
                'type' => 'hbar', 'labels' => array_keys($grades), 'categoryLabel' => 'Membership grade',
                'series' => [['name' => 'Active volunteers', 'data' => array_values($grades), 'slot' => self::SLOT_VOLUNTEERS]],
            ],
            'sizes' => [
                'type' => 'hbar', 'stacked' => true, 'labels' => $sizes['labels'], 'categoryLabel' => 'Duration',
                'series' => [
                    ['name' => 'Local', 'data' => $sizes['local'], 'slot' => self::SLOT_LOCAL],
                    ['name' => 'IEEE import', 'data' => $sizes['ieee'], 'slot' => self::SLOT_IEEE],
                ],
            ],
            'searches' => [
                'type' => 'line', 'labels' => $series['labels'], 'categoryLabel' => $category,
                'series' => [
                    ['name' => 'Searches', 'data' => $series['searches'], 'slot' => self::SLOT_SEARCHES],
                    ['name' => 'Zero results', 'data' => $series['zero_results'], 'slot' => self::SLOT_ZERO_RESULTS],
                ],
            ],
        ];
    }

    // ----- Scoped base queries -------------------------------------------

    private function users(): Builder
    {
        return User::query()->tap(fn ($q) => $this->inRegion($q, 'users.id'));
    }

    /** Users who applied or logged hours (any status) between the dates. */
    private function activeUsers(Carbon $from, Carbon $to): Builder
    {
        return $this->users()->where(fn ($q) => $q
            ->whereIn('users.id', $this->applications()->whereBetween('applications.created_at', [$from, $to])->select('applications.user_id'))
            ->orWhereIn('users.id', $this->hourLogs()
                ->whereBetween('hour_logs.worked_on', [$from->toDateString(), $to->toDateString()])
                ->select('hour_logs.user_id')));
    }

    private function opportunities(): Builder
    {
        return Opportunity::query()->tap(fn ($q) => $this->whereOpportunityMatches($q));
    }

    private function applications(): Builder
    {
        return Application::query()
            ->tap(fn ($q) => $this->inRegion($q, 'applications.user_id'))
            ->tap(fn ($q) => $this->inCategory($q, 'applications.opportunity_id'));
    }

    private function hourLogs(): Builder
    {
        return HourLog::query()
            ->tap(fn ($q) => $this->inRegion($q, 'hour_logs.user_id'))
            ->tap(fn ($q) => $this->inCategory($q, 'hour_logs.opportunity_id'));
    }

    private function endorsements(): Builder
    {
        return Endorsement::query()
            ->tap(fn ($q) => $this->inRegion($q, 'endorsements.user_id'))
            ->tap(fn ($q) => $this->inCategory($q, 'endorsements.opportunity_id'));
    }

    /** Searches in the period that were themselves narrowed to this region / type. */
    private function searches(): Builder
    {
        return SearchLog::query()
            ->whereBetween('search_logs.created_at', [$this->from, $this->to])
            ->when($this->region, fn ($q) => $q->where('search_logs.filters->region', $this->region))
            ->when($this->category, fn ($q) => $q->where('search_logs.filters->category', $this->category->slug));
    }

    /** Region/type filter on a query that has the opportunities table. */
    private function whereOpportunityMatches(Builder|QueryBuilder $query): void
    {
        $query->when($this->region, fn ($q) => $q->where('opportunities.region', $this->region))
            ->when($this->category, fn ($q) => $q->where('opportunities.category_id', $this->category->id));
    }

    /** Limit rows whose $userColumn is a volunteer to the selected region. */
    private function inRegion(Builder|QueryBuilder $query, string $userColumn): void
    {
        $query->when($this->region, fn ($q) => $q->whereIn(
            $userColumn,
            DB::table('profiles')->select('profiles.user_id')->where('profiles.region', $this->region),
        ));
    }

    /** Limit rows whose $opportunityColumn is an opportunity to the selected type. */
    private function inCategory(Builder|QueryBuilder $query, string $opportunityColumn): void
    {
        $query->when($this->category, fn ($q) => $q->whereIn(
            $opportunityColumn,
            DB::table('opportunities')->select('opportunities.id')->where('opportunities.category_id', $this->category->id),
        ));
    }

    private function firstSignup(): Carbon
    {
        $first = User::query()->min('created_at');

        return ($first ? Carbon::parse($first) : now()->subYear())->startOfMonth();
    }

    // ----- Maths & formatting --------------------------------------------

    public static function percent(int|float|string|null $part, int|float|string|null $whole): ?float
    {
        return (float) $whole > 0 ? round((float) $part / (float) $whole * 100, 1) : null;
    }

    /** % change vs the previous period; null when there is nothing to compare with. */
    public static function change(int|float|null $current, int|float|null $previous): ?int
    {
        if ($current === null || $previous === null) {
            return null;
        }

        if ((float) $previous == 0.0) {
            return (float) $current == 0.0 ? 0 : null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    public static function median(Collection $values): ?float
    {
        $sorted = $values->map(fn ($v) => (float) $v)->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);
        $median = $count % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2;

        return round($median, 1);
    }

    private static function daysBetween(string $start, string $end): float
    {
        return max(0, Carbon::parse($start)->diffInSeconds(Carbon::parse($end), false) / 86400);
    }

    public static function format(int|float|null $value, string $format): string
    {
        if ($value === null) {
            return '—';
        }

        $decimals = $value == floor($value) ? 0 : 1;

        return match ($format) {
            'percent' => number_format($value, $decimals).'%',
            'days' => number_format($value, $decimals).' '.($value == 1 ? 'day' : 'days'),
            'hours' => number_format($value, $decimals).' h',
            default => number_format($value),
        };
    }
}
