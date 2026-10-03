<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Application;
use App\Models\Category;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Every opportunity on the platform, local and imported. */
class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $opportunities = Opportunity::query()
            ->with(['category', 'owners.profile'])
            ->withCount([
                'applications',
                'applications as confirmed_count' => fn ($q) => $q->whereIn('status', Application::CONFIRMED),
                'applications as pending_count' => fn ($q) => $q->where('status', Application::PENDING),
            ])
            ->when($filters['q'], function ($query, $term) {
                $like = '%'.$term.'%';
                $query->where(fn ($q) => $q
                    ->where('title', 'like', $like)
                    ->orWhere('external_id', $term)
                    ->orWhereHas('owners', fn ($o) => $o->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($filters['source'], fn ($q, $source) => $q->where('source', $source))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category'], fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($filters['region'], fn ($q, $region) => $q->where('region', $region))
            ->when($filters['featured'] === 'yes', fn ($q) => $q->where('is_featured', true))
            ->when($filters['featured'] === 'no', fn ($q) => $q->where('is_featured', false))
            ->when($filters['owner'] === 'unclaimed', fn ($q) => $q->doesntHave('owners'))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.opportunities.index', [
            'opportunities' => $opportunities,
            'filters' => $filters,
            'categories' => Category::query()->ordered()->get(['id', 'name', 'slug']),
            'statusCounts' => Opportunity::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'sourceCounts' => Opportunity::query()->selectRaw('source, COUNT(*) as total')->groupBy('source')->pluck('total', 'source'),
        ]);
    }

    public function feature(Opportunity $opportunity)
    {
        $opportunity->forceFill(['is_featured' => ! $opportunity->is_featured])->saveQuietly();

        return back()->with('status', $opportunity->is_featured
            ? '“'.Str::limit($opportunity->title, 60).'” is now featured on the home page.'
            : '“'.Str::limit($opportunity->title, 60).'” is no longer featured.');
    }

    public function destroy(Opportunity $opportunity)
    {
        Activity::record('opportunity.deleted', $opportunity, ['title' => $opportunity->title, 'by_admin' => true]);
        $opportunity->delete();

        return back()->with('status', 'Opportunity “'.Str::limit($opportunity->title, 60).'” deleted.');
    }

    /** Whitelisted list filters from the query string. */
    private function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed) => in_array($value = (string) $request->query($key, ''), $allowed, true) ? $value : null;

        return [
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
            'source' => $pick('source', [Opportunity::SOURCE_LOCAL, Opportunity::SOURCE_IEEE]),
            'status' => $pick('status', array_keys(config('volunteering.opportunity_statuses'))),
            'category' => $pick('category', Category::query()->pluck('slug')->all()),
            'region' => $pick('region', array_keys(config('volunteering.regions'))),
            'featured' => $pick('featured', ['yes', 'no']),
            'owner' => $pick('owner', ['unclaimed']),
        ];
    }
}
