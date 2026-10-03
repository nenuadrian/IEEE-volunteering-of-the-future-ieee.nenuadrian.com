<?php

namespace App\Support;

use App\Models\Application;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Filters, sorting and match scoring for the opportunity search page.
 * Every filter mirrors one on volunteer.ieee.org, plus a few the UX review
 * asked for (experience level, past opportunities, best-match sort).
 */
class OpportunitySearch
{
    public const SORTS = [
        'relevance' => 'Most relevant',
        'match' => 'Best match for me',
        'newest' => 'Newest',
        'closing' => 'Ending soonest',
        'starting' => 'Starting soonest',
        'needed' => 'Most volunteers needed',
    ];

    public array $filters;

    public function __construct(private Request $request, private ?User $user = null)
    {
        $this->filters = [
            'q' => trim((string) $request->query('q', '')),
            'skills' => array_values(array_filter((array) $request->query('skills', []), 'is_numeric')),
            'upskills' => array_values(array_filter((array) $request->query('upskills', []))),
            'category' => $request->query('category'),
            'region' => $request->query('region'),
            'section' => trim((string) $request->query('section', '')),
            'society' => $request->query('society'),
            'size' => $request->query('size'),
            'experience' => $request->query('experience'),
            'online' => $request->boolean('online'),
            'accepting' => $request->boolean('accepting'),
            'my_grade' => $request->boolean('my_grade') && $user,
            'past' => $request->boolean('past'),
        ];
    }

    public function sort(): string
    {
        $sort = (string) $this->request->query('sort', $this->user ? 'match' : 'relevance');

        if ($sort === 'match' && ! $this->user) {
            return 'relevance';
        }

        return array_key_exists($sort, self::SORTS) ? $sort : 'relevance';
    }

    /** Number of active filters (excluding the keyword), for the filter badge. */
    public function activeFilterCount(): int
    {
        $f = $this->filters;
        unset($f['q']);

        return count(array_filter($f, fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== false));
    }

    public function query(): Builder
    {
        $f = $this->filters;

        $query = Opportunity::query()
            ->with(['category', 'skills'])
            ->withCount(['applications as confirmed_count' => fn ($q) => $q->whereIn('status', Application::CONFIRMED)]);

        $f['past']
            ? $query->whereIn('status', Opportunity::PUBLIC_STATUSES)
            : $query->whereIn('status', [Opportunity::OPEN, Opportunity::IN_PROGRESS, Opportunity::ON_HOLD]);

        if ($f['q'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['q']).'%';
            $query->where(function (Builder $w) use ($term) {
                $w->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('society', 'like', $term)
                    ->orWhere('section', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhereHas('skills', fn ($s) => $s->where('name', 'like', $term));
            });
        }

        if ($f['skills']) {
            $query->whereHas('skills', fn ($s) => $s->whereIn('skills.id', $f['skills']));
        }

        foreach ($f['upskills'] as $upskill) {
            $query->whereJsonContains('upskills', $upskill);
        }

        if ($f['category']) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $f['category']));
        }
        if ($f['region']) {
            $query->where('region', $f['region']);
        }
        if ($f['section'] !== '') {
            $query->where('section', 'like', '%'.$f['section'].'%');
        }
        if ($f['society']) {
            $query->where('society', $f['society']);
        }
        if ($f['size']) {
            $query->where('project_size', $f['size']);
        }
        if ($f['experience']) {
            $query->where('experience_level', $f['experience']);
        }
        if ($f['online']) {
            $query->where('is_online', true);
        }
        if ($f['accepting']) {
            $query->where('status', Opportunity::OPEN);
        }
        if ($f['my_grade'] && ($grade = $this->user?->profile?->membership_grade)) {
            $query->where(function (Builder $w) use ($grade) {
                $w->whereNull('membership_grades')
                    ->orWhereJsonContains('membership_grades', $grade)
                    ->orWhereJsonContains('membership_grades', '_ALL');
            });
        }

        return $query;
    }

    /**
     * Run the search and return a paginator with match scores attached
     * ($opportunity->match) when a user is signed in.
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->query();
        $sort = $this->sort();
        $userSkillIds = $this->user?->skills()->pluck('skills.id')->all();

        if ($sort === 'match') {
            // Scores are computed in PHP (explainable), so rank the whole
            // filtered set and paginate the collection.
            $all = $query->get()->each(fn ($o) => $o->match = MatchScore::for($this->user, $o, $userSkillIds));
            $sorted = $all->sortBy([
                fn ($a, $b) => ($b->status === Opportunity::OPEN) <=> ($a->status === Opportunity::OPEN),
                fn ($a, $b) => $b->match['percent'] <=> $a->match['percent'],
                fn ($a, $b) => $b->created_at <=> $a->created_at,
            ])->values();

            $page = LengthAwarePaginator::resolveCurrentPage();

            return new LengthAwarePaginator(
                $sorted->forPage($page, $perPage)->values(),
                $sorted->count(),
                $perPage,
                $page,
                ['path' => $this->request->url(), 'query' => $this->request->query()],
            );
        }

        match ($sort) {
            'newest' => $query->orderByDesc('created_at'),
            'closing' => $query->orderByRaw('end_date IS NULL')->orderBy('end_date'),
            'starting' => $query->orderByRaw('start_date IS NULL')->orderBy('start_date'),
            'needed' => $query->orderByDesc('volunteers_needed'),
            default => $this->relevance($query),
        };

        $paginator = $query->paginate($perPage)->withQueryString();

        if ($this->user) {
            $paginator->getCollection()->each(fn ($o) => $o->match = MatchScore::for($this->user, $o, $userSkillIds));
        }

        return $paginator;
    }

    private function relevance(Builder $query): void
    {
        $query->orderByDesc('is_featured')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'in_progress' THEN 1 ELSE 2 END");

        if ($this->filters['q'] !== '') {
            $query->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ['%'.$this->filters['q'].'%']);
        }

        $query->orderByDesc('created_at');
    }
}
