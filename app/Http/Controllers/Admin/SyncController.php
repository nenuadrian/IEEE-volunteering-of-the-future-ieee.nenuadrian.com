<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\SyncRun;
use App\Services\IeeeOpportunitySync;
use Illuminate\Http\Request;

/** Refreshing imported opportunities from the volunteer.ieee.org public API. */
class SyncController extends Controller
{
    public const LOG_ACTIONS = ['created', 'updated', 'closed'];

    public function index()
    {
        $imported = Opportunity::query()->where('source', Opportunity::SOURCE_IEEE);

        return view('admin.sync.index', [
            'latest' => SyncRun::query()->with('trigger')->latest('started_at')->latest('id')->first(),
            'lastSuccess' => SyncRun::query()->where('status', 'success')->latest('started_at')->latest('id')->first(),
            'runs' => SyncRun::query()->with('trigger')->latest('started_at')->latest('id')->paginate(15),
            'statusCounts' => (clone $imported)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'unclaimed' => (clone $imported)->doesntHave('owners')->count(),
            'api' => config('volunteering.api'),
        ]);
    }

    public function run(Request $request, IeeeOpportunitySync $sync)
    {
        // A full refresh pages through the whole public feed.
        @set_time_limit(180);

        $run = $sync->run($request->user());

        if ($run->status !== 'success') {
            return redirect()->route('admin.sync.index')
                ->with('error', 'The refresh failed: '.($run->error ?: 'unknown error').' Existing opportunities were left as they were.');
        }

        return redirect()->route('admin.sync.index')->with('status', sprintf(
            'Refreshed from volunteer.ieee.org in %ss: %s fetched, %s new, %s updated, %s unchanged, %s closed.',
            $run->durationSeconds() ?? 0,
            number_format($run->fetched_count),
            number_format($run->created_count),
            number_format($run->updated_count),
            number_format($run->unchanged_count),
            number_format($run->closed_count),
        ));
    }

    public function show(Request $request, SyncRun $syncRun)
    {
        $syncRun->load('trigger');
        $entries = collect($syncRun->log ?? [])->filter(fn ($e) => is_array($e));
        $action = in_array($request->query('action'), self::LOG_ACTIONS, true) ? $request->query('action') : null;

        $local = Opportunity::withTrashed()
            ->whereIn('external_id', $entries->pluck('id')->filter()->unique()->values())
            ->get(['id', 'slug', 'title', 'status', 'external_id', 'deleted_at'])
            ->keyBy('external_id');

        return view('admin.sync.show', [
            'run' => $syncRun,
            'entries' => $action ? $entries->where('action', $action)->values() : $entries->values(),
            'actionCounts' => $entries->countBy('action'),
            'action' => $action,
            'local' => $local,
        ]);
    }
}
