<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Sleep::fake(); // the HTTP client's retry back-off
        $this->admin = User::factory()->admin()->create();
    }

    private function hit(array $overrides = []): array
    {
        return array_merge([
            'opportunityId' => 'api-001',
            'title' => 'Data Dashboard Builder',
            'description' => 'Build a dashboard for section membership data.',
            'category' => 'Technical',
            'isOnline' => 'Y',
            'displayStatus' => 'Accepting Applicants',
            'status' => 'active',
            'startDate' => '01/01/2026',
            'endDate' => '12/31/2026',
            'skillsRequired' => ['Data analysis'],
            'grades' => ['M'],
        ], $overrides);
    }

    private function payload(array $hits): array
    {
        return ['status' => 'success', 'data' => ['totalHits' => count($hits), 'hits' => $hits]];
    }

    private function fakeApi(array $hits): void
    {
        Http::fake([config('volunteering.api.search_url') => Http::response($this->payload($hits))]);
    }

    public function test_admin_can_refresh_opportunities_from_the_api(): void
    {
        $this->fakeApi([$this->hit()]);

        $this->actingAs($this->admin)
            ->post(route('admin.sync.run'))
            ->assertRedirect(route('admin.sync.index'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, '1 fetched, 1 new'));

        $opportunity = Opportunity::where('external_id', 'api-001')->firstOrFail();
        $this->assertSame(Opportunity::SOURCE_IEEE, $opportunity->source);
        $this->assertSame('Data Dashboard Builder', $opportunity->title);
        $this->assertSame(Opportunity::OPEN, $opportunity->status);
        $this->assertSame('Technical', $opportunity->category->name);
        $this->assertSame(['Data analysis'], $opportunity->skills->pluck('name')->all());

        $run = SyncRun::latest('id')->firstOrFail();
        $this->assertSame('success', $run->status);
        $this->assertSame(1, $run->fetched_count);
        $this->assertSame(1, $run->created_count);
        $this->assertSame($this->admin->id, $run->triggered_by);

        $this->assertDatabaseHas('activities', ['type' => 'admin.sync_run', 'user_id' => $this->admin->id, 'subject_id' => $run->id]);

        Http::assertSent(fn (Request $request) => $request->url() === config('volunteering.api.search_url')
            && $request['start'] === 0
            && $request->hasHeader('Origin', config('volunteering.api.origin')));
    }

    public function test_a_second_refresh_reports_unchanged_and_closes_missing_imports(): void
    {
        // First refresh lists two opportunities, the second only one of them.
        Http::fake([config('volunteering.api.search_url') => Http::sequence()
            ->push($this->payload([$this->hit(), $this->hit(['opportunityId' => 'api-002', 'title' => 'Second Role'])]))
            ->push($this->payload([$this->hit()])),
        ]);

        $this->actingAs($this->admin)->post(route('admin.sync.run'));
        $this->actingAs($this->admin)
            ->post(route('admin.sync.run'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, '1 unchanged, 1 closed'));

        $this->assertSame(Opportunity::COMPLETED, Opportunity::where('external_id', 'api-002')->value('status'));

        $run = SyncRun::latest('id')->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.sync.show', $run))
            ->assertOk()
            ->assertSee('Second Role')
            ->assertSee('Closed');
        $this->actingAs($this->admin)->get(route('admin.sync.show', [$run, 'action' => 'created']))
            ->assertOk()
            ->assertDontSee('Second Role');
    }

    public function test_a_failed_refresh_is_recorded_and_changes_nothing(): void
    {
        Http::fake([config('volunteering.api.search_url') => Http::response('Service unavailable', 503)]);

        $this->actingAs($this->admin)
            ->post(route('admin.sync.run'))
            ->assertRedirect(route('admin.sync.index'))
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'HTTP 503'));

        $run = SyncRun::latest('id')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('503', $run->error);
        $this->assertSame(0, Opportunity::count());

        $this->actingAs($this->admin)->get(route('admin.sync.index'))
            ->assertOk()
            ->assertSee('HTTP 503');
        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertSee('Last refresh failed');
    }

    public function test_an_unexpected_payload_fails_cleanly(): void
    {
        Http::fake([config('volunteering.api.search_url') => Http::response(['status' => 'error'])]);

        $this->actingAs($this->admin)->post(route('admin.sync.run'))->assertSessionHas('error');

        $this->assertSame('failed', SyncRun::latest('id')->value('status'));
    }

    public function test_sync_page_explains_the_feed_and_lists_history(): void
    {
        $this->fakeApi([$this->hit()]);
        $this->actingAs($this->admin)->post(route('admin.sync.run'));

        $this->actingAs($this->admin)->get(route('admin.sync.index'))
            ->assertOk()
            ->assertSee(config('volunteering.api.search_url'))
            ->assertSee('Refresh now')
            ->assertSee($this->admin->name);
    }

    public function test_non_admins_cannot_trigger_a_refresh(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->post(route('admin.sync.run'))->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(0, SyncRun::count());
        $this->assertSame(0, Activity::count());
    }
}
