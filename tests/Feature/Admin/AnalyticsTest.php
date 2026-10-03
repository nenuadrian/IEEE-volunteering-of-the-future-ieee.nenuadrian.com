<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\Category;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\SearchLog;
use App\Models\User;
use App\Services\PlatformAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->freezeTime();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Two opportunities (Technical/R8 local, Event-based/R9 imported), three
     * applications from an R8 and an R9 volunteer, approved hours and an
     * endorsement — all inside the last 30 days.
     */
    private function seedActivity(): array
    {
        $technical = Category::factory()->create(['name' => 'Technical', 'slug' => 'technical']);
        $events = Category::factory()->create(['name' => 'Event-based', 'slug' => 'event-based']);

        $owner = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice R8']);
        $alice->profile->update(['region' => 'R8']);
        $bob = User::factory()->create(['name' => 'Bob R9']);
        $bob->profile->update(['region' => 'R9']);

        $tech = Opportunity::factory()->ownedBy($owner)->create([
            'title' => 'Tech Mentor', 'category_id' => $technical->id, 'region' => 'R8', 'volunteers_needed' => 1,
            'published_at' => now()->subDays(5), 'filled_at' => now()->subDays(2),
        ]);
        $event = Opportunity::factory()->create([
            'title' => 'Event Crew', 'category_id' => $events->id, 'region' => 'R9',
            'source' => Opportunity::SOURCE_IEEE, 'external_id' => 'ext-1', 'published_at' => now()->subDays(4),
        ]);

        $accepted = Application::factory()->create([
            'opportunity_id' => $tech->id, 'user_id' => $alice->id, 'status' => Application::ACCEPTED,
            'created_at' => now()->subDays(3), 'decided_at' => now()->subDays(2),
        ]);
        Application::factory()->create([
            'opportunity_id' => $tech->id, 'user_id' => $bob->id, 'status' => Application::REJECTED,
            'created_at' => now()->subDays(2), 'decided_at' => now()->subDay(),
        ]);
        Application::factory()->create(['opportunity_id' => $event->id, 'user_id' => $bob->id, 'created_at' => now()->subDay()]);

        HourLog::create([
            'application_id' => $accepted->id, 'user_id' => $alice->id, 'opportunity_id' => $tech->id,
            'worked_on' => now()->subDays(2)->toDateString(), 'hours' => 4, 'status' => HourLog::APPROVED,
        ]);
        HourLog::create([
            'application_id' => $accepted->id, 'user_id' => $alice->id, 'opportunity_id' => $tech->id,
            'worked_on' => now()->subDay()->toDateString(), 'hours' => 2, 'status' => HourLog::PENDING,
        ]);
        Endorsement::create(['user_id' => $alice->id, 'endorser_id' => $owner->id, 'opportunity_id' => $tech->id, 'application_id' => $accepted->id, 'message' => 'Superb']);

        SearchLog::create(['scope' => 'opportunities', 'query' => 'mentor', 'filters' => ['region' => 'R8'], 'results_count' => 2, 'created_at' => now()->subDay()]);
        SearchLog::create(['scope' => 'opportunities', 'query' => 'quantum photonics', 'results_count' => 0, 'created_at' => now()->subDay()]);

        return compact('technical', 'alice', 'bob', 'tech', 'event');
    }

    public function test_every_range_preset_renders_with_and_without_filters(): void
    {
        $this->seedActivity();

        foreach (array_keys(PlatformAnalytics::RANGES) as $range) {
            foreach ([[], ['region' => 'R8'], ['category' => 'technical'], ['region' => 'R9', 'category' => 'event-based']] as $filters) {
                $this->actingAs($this->admin)
                    ->get(route('admin.dashboard', ['range' => $range] + $filters))
                    ->assertOk()
                    ->assertSee('New volunteers')
                    ->assertSee('Engagement funnel')
                    ->assertSee('Cohort retention');
            }
        }
    }

    public function test_unknown_filters_fall_back_to_the_defaults(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['range' => 'forever', 'region' => 'R99', 'category' => 'nope']))
            ->assertOk()
            ->assertViewHas('analytics', fn (PlatformAnalytics $a) => $a->range === '12m' && $a->region === null && $a->category === null);
    }

    public function test_kpis_are_computed_for_the_selected_period(): void
    {
        $this->seedActivity();

        $kpis = (new PlatformAnalytics('30d'))->kpis();
        $value = fn (string $key) => $kpis[$key]['value'];

        $this->assertSame(3, $value('applications'));
        $this->assertSame(2, $value('active_volunteers'));
        $this->assertSame(2, $value('new_opportunities'));
        $this->assertStringContainsString('50% imported', $kpis['new_opportunities']['hint']);
        $this->assertSame(50.0, $value('acceptance_rate'));
        $this->assertSame(50.0, $value('fill_rate'));
        $this->assertSame(3.0, $value('days_to_fill'));
        $this->assertSame(2.5, $value('days_to_first_applicant'));
        $this->assertSame(4.0, $value('approved_hours'));
        $this->assertSame(0.0, $value('completion_rate'));
        $this->assertSame(0.0, $value('retention'));
        $this->assertSame(1, $value('endorsements'));

        // Nothing happened in the previous 30 days, so counts can't show a % change.
        $this->assertSame(0, $kpis['applications']['previous']);
        $this->assertNull($kpis['applications']['delta']);
    }

    public function test_region_and_type_filters_slice_every_metric(): void
    {
        $this->seedActivity();

        // Region = the volunteer's region for activity, the opportunity's for opportunities.
        $r8 = (new PlatformAnalytics('30d', 'R8'))->kpis();
        $this->assertSame(1, $r8['applications']['value']);
        $this->assertSame(1, $r8['new_opportunities']['value']);
        $this->assertSame(100.0, $r8['acceptance_rate']['value']);

        $technical = Category::where('slug', 'technical')->first();
        $tech = (new PlatformAnalytics('30d', null, $technical))->kpis();
        $this->assertSame(2, $tech['applications']['value']);
        $this->assertSame(1, $tech['new_opportunities']['value']);
        $this->assertSame(4.0, $tech['approved_hours']['value']);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['range' => '30d', 'category' => 'technical']))
            ->assertOk()
            ->assertSee('Tech Mentor')
            ->assertDontSee('Event Crew');
    }

    public function test_previous_period_deltas(): void
    {
        $this->seedActivity();
        $opportunity = Opportunity::query()->first();
        $earlier = User::factory()->create();
        Application::factory()->create(['opportunity_id' => $opportunity->id, 'user_id' => $earlier->id, 'created_at' => now()->subDays(40)]);

        $kpis = (new PlatformAnalytics('30d'))->kpis();

        $this->assertSame(1, $kpis['applications']['previous']);
        $this->assertSame(200, $kpis['applications']['delta']);
    }

    public function test_series_funnel_and_search_tables(): void
    {
        $this->seedActivity();
        $report = (new PlatformAnalytics('30d'))->report();

        $this->assertCount(30, $report['series']['labels']);
        $this->assertSame(3, array_sum($report['series']['applications']));
        $this->assertSame(1, array_sum($report['series']['accepted']));
        $this->assertSame(4.0, (float) array_sum($report['series']['hours']));
        $this->assertSame(1, array_sum($report['series']['published_local']));
        $this->assertSame(1, array_sum($report['series']['published_ieee']));

        $steps = collect($report['funnel']['steps'])->pluck('count', 'label');
        $this->assertSame(User::count(), $steps['Registered']);

        $this->assertSame(2, $report['search']['total']);
        $this->assertSame('quantum photonics', $report['search']['zero_terms']->first()->query);
        $this->assertSame(50.0, $report['search']['filtered_rate']);

        $this->assertSame('Tech Mentor', $report['leaders']['opportunities']->first()['opportunity']->title);
        $this->assertSame(4.0, $report['leaders']['volunteers']->first()['hours']);
    }

    public function test_export_streams_kpis_and_series_as_csv(): void
    {
        $this->seedActivity();

        $response = $this->actingAs($this->admin)->get(route('admin.analytics.export', ['range' => '30d', 'region' => 'R8']));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Region,"Region 8', $csv);
        $this->assertStringContainsString('Applications,1,0,,number', $csv);
        $this->assertStringContainsString('"Day starting",Label,"New volunteers"', $csv);
    }

    public function test_every_range_exports(): void
    {
        foreach (array_keys(PlatformAnalytics::RANGES) as $range) {
            $this->actingAs($this->admin)->get(route('admin.analytics.export', ['range' => $range]))->assertOk();
        }
    }
}
