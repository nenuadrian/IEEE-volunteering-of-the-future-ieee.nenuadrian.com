<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Skill;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The skills vocabulary shared by volunteer profiles and opportunities. */
class SkillController extends Controller
{
    use StreamsCsv;

    public const SORTS = [
        'name' => 'Name',
        'volunteers' => 'Most volunteers',
        'demand' => 'Most open opportunities',
        'unused' => 'Least used',
    ];

    public function index(Request $request)
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $categories = $this->categories();
        $category = in_array($request->query('category'), $categories, true) ? $request->query('category') : null;
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'name';

        $skills = $this->withUsage(Skill::query())
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->when($category, fn ($query, $c) => $query->where('category', $c))
            ->tap(fn ($query) => match ($sort) {
                'volunteers' => $query->orderByDesc('users_count'),
                'demand' => $query->orderByDesc('open_opportunities_count'),
                'unused' => $query->orderByRaw('(users_count + opportunities_count) asc'),
                default => null,
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.skills.index', [
            'skills' => $skills,
            'filters' => ['q' => $q, 'category' => $category, 'sort' => $sort],
            'categories' => $categories,
            'allSkills' => Skill::query()->withCount('users')->orderBy('name')->get(['id', 'name']),
            'total' => Skill::query()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $skill = Skill::create(['name' => $data['name'], 'category' => $data['category'] ?? null]);

        return back()->with('status', 'Skill “'.$skill->name.'” added.');
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $data = $request->validate($this->rules($skill));

        $skill->update(['name' => $data['name'], 'category' => $data['category'] ?? null]);

        return back()->with('status', 'Skill “'.$skill->name.'” updated.');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $skill->delete();

        return back()->with('status', 'Skill “'.$skill->name.'” deleted and removed from every profile and opportunity.');
    }

    /** Fold a duplicate into the skill that should remain. */
    public function merge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'exists:skills,id'],
            'target_id' => ['required', 'integer', 'exists:skills,id', 'different:source_id'],
        ], [
            'target_id.different' => 'Pick two different skills to merge.',
        ]);

        $source = Skill::findOrFail($data['source_id']);
        $target = Skill::findOrFail($data['target_id']);
        $moved = DB::table('skill_user')->where('skill_id', $source->id)->count();

        $source->mergeInto($target);

        Activity::record('admin.skill_merged', $target, [
            'title' => $target->name,
            'from' => $source->name,
            'into' => $target->name,
            'volunteers_moved' => $moved,
        ]);

        return back()->with('status', '“'.$source->name.'” merged into “'.$target->name.'”.');
    }

    public function export(): StreamedResponse
    {
        $skills = $this->withUsage(Skill::query())->orderBy('name');

        return $this->streamCsv('ieee-volunteering-skills-'.now()->format('Y-m-d').'.csv', function ($row) use ($skills) {
            $row(['name', 'category', 'volunteers', 'open_opportunities']);

            foreach ($skills->lazy(500) as $skill) {
                $row([$skill->name, $skill->category, $skill->users_count, $skill->open_opportunities_count]);
            }
        });
    }

    /**
     * Import a CSV of name[,category]. Missing skills are created; existing
     * ones get their category updated when the file provides one.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $line = 0;

        while (($cells = fgetcsv($handle, 2000, ',', '"', '')) !== false) {
            $line++;
            $name = trim(preg_replace('/\s+/', ' ', (string) ($cells[0] ?? '')));
            if ($line === 1) {
                $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
                if (Str::lower($name) === 'name') {
                    continue; // header row
                }
            }

            $category = trim((string) ($cells[1] ?? '')) ?: null;

            if ($name === '' || mb_strlen($name) > 120 || ($category && mb_strlen($category) > 60) || Str::slug($name) === '') {
                $counts['skipped']++;

                continue;
            }

            $skill = Skill::query()->where('slug', Str::slug($name))->first();

            if (! $skill) {
                Skill::create(['name' => $name, 'category' => $category]);
                $counts['created']++;
            } elseif ($category && $category !== $skill->category) {
                $skill->update(['category' => $category]);
                $counts['updated']++;
            } else {
                $counts['skipped']++;
            }
        }

        fclose($handle);

        return back()->with('status', sprintf(
            'Import finished: %d created, %d updated, %d skipped (unchanged, blank or invalid).',
            $counts['created'], $counts['updated'], $counts['skipped'],
        ));
    }

    // ----- Helpers -------------------------------------------------------

    private function withUsage(Builder $query): Builder
    {
        return $query
            ->withCount([
                'users',
                'opportunities',
                'opportunities as open_opportunities_count' => fn ($q) => $q->where('status', Opportunity::OPEN),
            ])
            ->addSelect(['endorsements_count' => DB::table('endorsement_skill')
                ->selectRaw('COUNT(*)')
                ->whereColumn('endorsement_skill.skill_id', 'skills.id')]);
    }

    /** @return array<int, string> */
    private function categories(): array
    {
        return Skill::query()->whereNotNull('category')->where('category', '<>', '')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }

    private function rules(?Skill $skill = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120', function (string $attribute, mixed $value, Closure $fail) use ($skill) {
                $slug = Str::slug(trim((string) $value));

                if ($slug === '') {
                    $fail('The skill name needs at least one letter or number.');
                } elseif (Skill::query()->where('slug', $slug)->when($skill, fn ($q) => $q->whereKeyNot($skill->id))->exists()) {
                    $fail('A skill with this name already exists — merge them instead.');
                }
            }],
            'category' => ['nullable', 'string', 'max:60'],
        ];
    }
}
