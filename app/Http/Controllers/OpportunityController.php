<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpportunityRequest;
use App\Models\Activity;
use App\Models\Application;
use App\Models\Category;
use App\Models\Opportunity;
use App\Models\SearchLog;
use App\Models\Skill;
use App\Models\User;
use App\Support\MatchScore;
use App\Support\Notify;
use App\Support\OpportunitySearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = new OpportunitySearch($request, $user);
        $view = $request->query('view') === 'map' ? 'map' : 'list';

        $opportunities = $search->paginate(10);

        SearchLog::capture('opportunities', $search->filters['q'], array_diff_key($search->filters, ['q' => 1]), $opportunities->total());

        $mapPoints = [];
        if ($view === 'map') {
            $mapPoints = $search->query()
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->limit(500)->get()
                ->map(fn ($o) => [
                    'lat' => $o->latitude,
                    'lng' => $o->longitude,
                    'title' => $o->title,
                    'url' => route('opportunities.show', $o),
                    'meta' => trim(($o->category?->name ?? '').' · '.$o->locationLabel(), ' ·'),
                ])->values();
        }

        return view('opportunities.index', [
            'opportunities' => $opportunities,
            'search' => $search,
            'filters' => $search->filters,
            'sort' => $search->sort(),
            'view' => $view,
            'mapPoints' => $mapPoints,
            'savedIds' => $user ? $user->savedOpportunities()->pluck('opportunities.id')->all() : [],
            'options' => $this->filterOptions(),
        ]);
    }

    public function show(Request $request, Opportunity $opportunity)
    {
        $user = $request->user();
        $canManage = $opportunity->canBeManagedBy($user);

        abort_if($opportunity->isDraft() && ! $canManage, 404);

        $opportunity->load(['category', 'skills', 'owners.profile', 'clonedFrom']);

        // Count a view once per session per opportunity, and never for its owners.
        $seenKey = 'viewed_opportunity_'.$opportunity->id;
        if (! $canManage && ! $request->session()->has($seenKey)) {
            $request->session()->put($seenKey, true);
            Opportunity::withoutTimestamps(fn () => $opportunity->increment('views_count'));
        }

        $application = $user ? $opportunity->applications()->where('user_id', $user->id)->first() : null;

        $volunteers = $opportunity->applications()
            ->whereIn('status', Application::CONFIRMED)
            ->with('user.profile')
            ->latest('decided_at')
            ->take(12)
            ->get()
            ->filter(fn ($a) => $a->user?->profile?->is_public);

        $similar = Opportunity::open()
            ->with(['category', 'skills'])
            ->where('id', '!=', $opportunity->id)
            ->where(function ($q) use ($opportunity) {
                $q->where('category_id', $opportunity->category_id)
                    ->orWhereHas('skills', fn ($s) => $s->whereIn('skills.id', $opportunity->skills->pluck('id')));
            })
            ->latest()
            ->take(3)
            ->get();

        return view('opportunities.show', [
            'opportunity' => $opportunity,
            'application' => $application,
            'canManage' => $canManage,
            'match' => $canManage ? null : MatchScore::for($user, $opportunity),
            'saved' => $user?->hasSaved($opportunity) ?? false,
            'volunteers' => $volunteers,
            'confirmedCount' => $opportunity->confirmedCount(),
            'similar' => $similar,
        ]);
    }

    public function create(Request $request)
    {
        $opportunity = new Opportunity([
            'is_online' => true,
            'volunteers_needed' => 1,
            'hours_frequency' => 'overall',
            'status' => Opportunity::OPEN,
        ]);

        return view('opportunities.form', $this->formData($opportunity, $request->user()));
    }

    public function store(OpportunityRequest $request)
    {
        $user = $request->user();

        $opportunity = DB::transaction(function () use ($request, $user) {
            $opportunity = new Opportunity($request->opportunityAttributes());
            $opportunity->slug = Opportunity::generateSlug($opportunity->title);
            $opportunity->created_by = $user->id;
            $opportunity->source = Opportunity::SOURCE_LOCAL;
            $opportunity->status = $request->input('action') === 'draft' ? Opportunity::DRAFT : Opportunity::OPEN;
            $opportunity->published_at = $opportunity->status === Opportunity::OPEN ? now() : null;
            $this->storeThumbnail($request, $opportunity);
            $opportunity->save();

            $opportunity->skills()->sync($request->skillIds());
            $opportunity->owners()->attach($user->id, ['role' => 'owner', 'added_by' => $user->id]);
            $this->syncCoOwners($opportunity, $request->coOwnerIds(), $user);

            Activity::record($opportunity->isDraft() ? 'opportunity.created' : 'opportunity.published', $opportunity);

            return $opportunity;
        });

        return redirect()->route('opportunities.manage', $opportunity)->with('status', $opportunity->isDraft()
            ? 'Draft saved. Publish it when you are ready to accept applicants.'
            : 'Your opportunity is live and accepting applicants.');
    }

    public function edit(Request $request, Opportunity $opportunity)
    {
        $this->authorize('manage-opportunity', $opportunity);

        return view('opportunities.form', $this->formData($opportunity->load(['skills', 'owners.profile']), $request->user()));
    }

    public function update(OpportunityRequest $request, Opportunity $opportunity)
    {
        $this->authorize('manage-opportunity', $opportunity);
        $user = $request->user();

        DB::transaction(function () use ($request, $opportunity, $user) {
            $opportunity->fill($request->opportunityAttributes());

            if ($request->input('action') === 'publish' && $opportunity->isDraft()) {
                $opportunity->status = Opportunity::OPEN;
                $opportunity->published_at ??= now();
            }

            if ($request->boolean('remove_thumbnail') && $opportunity->thumbnail_path) {
                Storage::disk('public')->delete($opportunity->thumbnail_path);
                $opportunity->thumbnail_path = null;
            }
            $this->storeThumbnail($request, $opportunity);
            $opportunity->save();

            $opportunity->skills()->sync($request->skillIds());
            $this->syncCoOwners($opportunity, $request->coOwnerIds(), $user);

            Activity::record('opportunity.updated', $opportunity);
        });

        return redirect()->route('opportunities.manage', $opportunity)->with('status', 'Opportunity updated.');
    }

    public function destroy(Request $request, Opportunity $opportunity)
    {
        $this->authorize('manage-opportunity', $opportunity);

        Activity::record('opportunity.deleted', $opportunity, ['title' => $opportunity->title]);
        $opportunity->delete();

        return redirect()->route('my.opportunities', ['tab' => 'managing'])->with('status', 'Opportunity deleted.');
    }

    /** Any signed-in user may clone an opportunity as a starting template. */
    public function clone(Request $request, Opportunity $opportunity)
    {
        abort_if($opportunity->isDraft() && ! $opportunity->canBeManagedBy($request->user()), 404);

        $copy = $opportunity->cloneFor($request->user());

        return redirect()->route('opportunities.edit', $copy)
            ->with('status', 'Opportunity cloned as a draft. Review the details and publish when ready.');
    }

    public function status(Request $request, Opportunity $opportunity)
    {
        $this->authorize('manage-opportunity', $opportunity);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('volunteering.opportunity_statuses')))],
        ]);

        $from = $opportunity->status;
        $opportunity->status = $data['status'];

        if ($data['status'] === Opportunity::OPEN) {
            $opportunity->published_at ??= now();
            $opportunity->closed_at = null;
        }
        if (in_array($data['status'], [Opportunity::COMPLETED, Opportunity::CANCELLED], true)) {
            $opportunity->closed_at = now();
        }
        $opportunity->save();

        Activity::record('opportunity.status_changed', $opportunity, ['from' => $from, 'to' => $data['status']]);

        return back()->with('status', 'Status changed to “'.$opportunity->statusLabel().'”.');
    }

    public function toggleSave(Request $request, Opportunity $opportunity)
    {
        $user = $request->user();
        $saved = $user->hasSaved($opportunity);

        $saved
            ? $user->savedOpportunities()->detach($opportunity->id)
            : $user->savedOpportunities()->attach($opportunity->id, ['created_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['saved' => ! $saved]);
        }

        return back()->with('status', $saved ? 'Removed from your saved opportunities.' : 'Saved. Find it under My Opportunities → Saved.');
    }

    // ----- Helpers -------------------------------------------------------

    private function formData(Opportunity $opportunity, User $user): array
    {
        $coOwners = $opportunity->exists
            ? $opportunity->owners->where('pivot.role', 'co_owner')->map(fn ($u) => $this->personPayload($u))->values()
            : collect();

        return [
            'opportunity' => $opportunity,
            'categories' => Category::active()->ordered()->get(),
            'skillOptions' => Skill::orderBy('name')->pluck('name', 'id'),
            'coOwners' => $coOwners,
            'ownerId' => $opportunity->exists ? ($opportunity->owners->firstWhere('pivot.role', 'owner')?->id ?? $user->id) : $user->id,
        ];
    }

    private function personPayload(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'meta' => $u->profile?->headline ?: $u->profile?->section ?: '', 'initials' => $u->initials()];
    }

    private function syncCoOwners(Opportunity $opportunity, array $ids, User $actor): void
    {
        $ownerId = $opportunity->owners()->wherePivot('role', 'owner')->value('users.id');
        $ids = array_values(array_diff(array_unique($ids), [$ownerId]));
        $ids = array_slice($ids, 0, config('volunteering.max_owners') - 1);

        $current = $opportunity->owners()->wherePivot('role', 'co_owner')->pluck('users.id')->all();

        foreach (array_diff($current, $ids) as $removed) {
            $opportunity->owners()->detach($removed);
        }

        foreach (array_diff($ids, $current) as $added) {
            $opportunity->owners()->attach($added, ['role' => 'co_owner', 'added_by' => $actor->id]);
            $person = User::find($added);
            if ($person) {
                Activity::record('opportunity.owner_added', $opportunity, ['user_id' => $added, 'name' => $person->name]);
                Notify::coOwnerAdded($opportunity, $person, $actor);
            }
        }
    }

    private function storeThumbnail(Request $request, Opportunity $opportunity): void
    {
        if ($request->hasFile('thumbnail')) {
            if ($opportunity->thumbnail_path) {
                Storage::disk('public')->delete($opportunity->thumbnail_path);
            }
            $opportunity->thumbnail_path = $request->file('thumbnail')->store('opportunities', 'public');
        }
    }

    private function filterOptions(): array
    {
        $societies = Opportunity::query()->visible()->whereNotNull('society')->distinct()->orderBy('society')->pluck('society');

        return [
            'categories' => Category::active()->ordered()->get(),
            'skills' => Skill::query()->whereHas('opportunities', fn ($q) => $q->visible())->orderBy('name')->pluck('name', 'id'),
            'societies' => $societies,
            'regions' => config('volunteering.regions'),
            'sizes' => config('volunteering.project_sizes'),
            'experience' => config('volunteering.experience_levels'),
            'upskills' => config('volunteering.upskills'),
            'sorts' => OpportunitySearch::SORTS,
        ];
    }
}
