<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\PlatformAnalytics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Platform metrics over time (the admin home page). */
class AnalyticsController extends Controller
{
    use StreamsCsv;

    public function index(Request $request)
    {
        $analytics = PlatformAnalytics::fromRequest($request);

        return view('admin.analytics.index', [
            'analytics' => $analytics,
            'report' => $analytics->report(),
            'regions' => config('volunteering.regions'),
            'categories' => Category::query()->ordered()->get(['id', 'name', 'slug']),
        ]);
    }

    /** KPIs and the main time series for the selected slice. */
    public function export(Request $request): StreamedResponse
    {
        $analytics = PlatformAnalytics::fromRequest($request);
        $kpis = $analytics->kpis();
        $series = $analytics->series();
        $filename = 'ieee-volunteering-analytics-'.$analytics->range.'-'.now()->format('Y-m-d').'.csv';

        return $this->streamCsv($filename, function ($row) use ($analytics, $kpis, $series) {
            $row(['IEEE Volunteering analytics']);
            $row(['Period', $analytics->rangeLabel(), $analytics->periodLabel()]);
            $row(['Compared with', $analytics->previousPeriodLabel() ?? 'n/a']);
            $row(['Region', $analytics->regionLabel() ?? 'All regions']);
            $row(['Opportunity type', $analytics->category?->name ?? 'All types']);
            $row([]);

            $row(['Metric', 'Current period', 'Previous period', 'Change %', 'Unit']);
            foreach ($kpis as $kpi) {
                $row([$kpi['label'], $kpi['value'], $kpi['previous'], $kpi['delta'], $kpi['format']]);
            }
            $row([]);

            $row([
                ucfirst($analytics->unit).' starting', 'Label', 'New volunteers', 'Applications', 'Accepted',
                'Approved hours', 'Opportunities published (local)', 'Opportunities published (IEEE import)',
                'Searches', 'Zero-result searches',
            ]);
            foreach ($series['keys'] as $i => $key) {
                $row([
                    $key, $series['labels'][$i], $series['volunteers'][$i], $series['applications'][$i], $series['accepted'][$i],
                    $series['hours'][$i], $series['published_local'][$i], $series['published_ieee'][$i],
                    $series['searches'][$i], $series['zero_results'][$i],
                ]);
            }
        });
    }
}
