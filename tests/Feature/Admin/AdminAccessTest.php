<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Application;
use App\Models\Category;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\SearchLog;
use App\Models\Skill;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** A small but complete data set so every admin page has something to render. */
    private function seedPlatform(): array
    {
        $owner = User::factory()->create(['name' => 'Olivia Owner']);
        $owner->profile->update(['region' => 'R8', 'section' => 'Germany', 'membership_grade' => 'SM']);
        $volunteer = User::factory()->create(['name' => 'Victor Volunteer']);
        $volunteer->profile->update(['region' => 'R10', 'membership_grade' => 'StM']);

        $skill = Skill::factory()->create(['name' => 'Data analysis']);
        $volunteer->skills()->attach($skill);

        $local = Opportunity::factory()->ownedBy($owner)->create(['title' => 'Local Workshop Mentor', 'project_size' => 'Full day']);
        $local->skills()->attach($skill);
        $imported = Opportunity::factory()->create([
            'title' => 'Imported Newsletter Editor', 'source' => Opportunity::SOURCE_IEEE, 'external_id' => 'ext-1', 'region' => 'R10',
        ]);

        $application = Application::factory()->accepted()->create(['opportunity_id' => $local->id, 'user_id' => $volunteer->id, 'volunteer_rating' => 5]);
        Application::factory()->create(['opportunity_id' => $imported->id, 'user_id' => $volunteer->id]);
        HourLog::create([
            'application_id' => $application->id, 'user_id' => $volunteer->id, 'opportunity_id' => $local->id,
            'worked_on' => now()->subDay()->toDateString(), 'hours' => 3.5, 'status' => HourLog::APPROVED,
        ]);
        Endorsement::create(['user_id' => $volunteer->id, 'endorser_id' => $owner->id, 'opportunity_id' => $local->id, 'application_id' => $application->id, 'message' => 'Great work']);

        SearchLog::create(['scope' => 'opportunities', 'query' => 'mentor', 'filters' => ['region' => 'R8'], 'results_count' => 1, 'created_at' => now()]);
        SearchLog::create(['scope' => 'opportunities', 'query' => 'quantum', 'results_count' => 0, 'created_at' => now()]);

        Activity::record('application.accepted', $application, [], $owner);
        Activity::record('admin.role_changed', $owner, ['name' => $owner->name, 'email' => $owner->email, 'from' => 'user', 'to' => 'admin'], $owner);

        $run = SyncRun::create([
            'status' => 'success', 'fetched_count' => 1, 'created_count' => 1,
            'log' => [['action' => 'created', 'title' => 'Imported Newsletter Editor', 'id' => 'ext-1']],
            'started_at' => now()->subMinute(), 'finished_at' => now(),
        ]);

        return compact('owner', 'volunteer', 'run');
    }

    /** @return array<int, string> */
    private function adminPages(array $data): array
    {
        return [
            route('admin.dashboard'),
            route('admin.dashboard', ['range' => '30d', 'region' => 'R8']),
            route('admin.analytics.export'),
            route('admin.opportunities.index'),
            route('admin.sync.index'),
            route('admin.sync.show', $data['run']),
            route('admin.users.index'),
            route('admin.users.export'),
            route('admin.users.show', $data['volunteer']),
            route('admin.skills.index'),
            route('admin.skills.export'),
            route('admin.categories.index'),
            route('admin.activity.index'),
            route('admin.pages.index'),
            route('admin.menus.index'),
            route('admin.media.index'),
            route('admin.settings.edit'),
            route('admin.email-templates.index'),
        ];
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_non_admins_get_403_on_every_admin_page(): void
    {
        $data = $this->seedPlatform();
        $user = User::factory()->create();

        foreach ($this->adminPages($data) as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_non_admins_cannot_use_admin_actions(): void
    {
        $data = $this->seedPlatform();
        $user = User::factory()->create();
        $opportunity = Opportunity::query()->first();

        $this->actingAs($user)->patch(route('admin.users.role', $data['volunteer']), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.opportunities.feature', $opportunity))->assertForbidden();
        $this->actingAs($user)->post(route('admin.skills.store'), ['name' => 'Hacking'])->assertForbidden();

        $this->assertFalse($data['volunteer']->fresh()->isAdmin());
        $this->assertFalse($opportunity->fresh()->is_featured);
    }

    public function test_admins_can_open_every_admin_page(): void
    {
        $data = $this->seedPlatform();
        $admin = User::factory()->admin()->create();

        foreach ($this->adminPages($data) as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_pages_render_their_data(): void
    {
        $data = $this->seedPlatform();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.opportunities.index'))
            ->assertSee('Local Workshop Mentor')
            ->assertSee('Imported Newsletter Editor')
            ->assertSee('Unclaimed — admins review applications');

        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('Victor Volunteer');
        $this->actingAs($admin)->get(route('admin.skills.index'))->assertSee('Data analysis');
        $this->actingAs($admin)->get(route('admin.activity.index'))->assertSee('Accepted a volunteer')->assertSee('Olivia Owner');
        $this->actingAs($admin)->get(route('admin.sync.show', $data['run']))->assertSee('Imported Newsletter Editor');
    }

    public function test_sidebar_flags_a_stale_sync(): void
    {
        $admin = User::factory()->admin()->create();
        SyncRun::create(['status' => 'success', 'started_at' => now()->subDays(3), 'finished_at' => now()->subDays(3)]);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Last refresh is older than 48 hours');
    }

    public function test_admin_can_feature_and_delete_an_opportunity(): void
    {
        $admin = User::factory()->admin()->create();
        $opportunity = Opportunity::factory()->create();

        $this->actingAs($admin)->from(route('admin.opportunities.index'))
            ->patch(route('admin.opportunities.feature', $opportunity))
            ->assertRedirect(route('admin.opportunities.index'));
        $this->assertTrue($opportunity->fresh()->is_featured);

        $this->actingAs($admin)->patch(route('admin.opportunities.feature', $opportunity));
        $this->assertFalse($opportunity->fresh()->is_featured);

        $this->actingAs($admin)->from(route('admin.opportunities.index'))
            ->delete(route('admin.opportunities.destroy', $opportunity))
            ->assertRedirect(route('admin.opportunities.index'));
        $this->assertSoftDeleted($opportunity);
        $this->assertDatabaseHas('activities', ['type' => 'opportunity.deleted', 'subject_id' => $opportunity->id, 'user_id' => $admin->id]);
    }

    public function test_opportunity_list_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $technical = Category::factory()->create(['name' => 'Technical', 'slug' => 'technical']);
        Opportunity::factory()->create(['title' => 'Local Thing', 'category_id' => $technical->id]);
        Opportunity::factory()->create(['title' => 'Imported Thing', 'source' => Opportunity::SOURCE_IEEE, 'external_id' => 'e-9', 'is_featured' => true]);

        $this->actingAs($admin)->get(route('admin.opportunities.index', ['source' => 'ieee']))
            ->assertSee('Imported Thing')->assertDontSee('Local Thing');
        $this->actingAs($admin)->get(route('admin.opportunities.index', ['category' => 'technical']))
            ->assertSee('Local Thing')->assertDontSee('Imported Thing');
        $this->actingAs($admin)->get(route('admin.opportunities.index', ['featured' => 'yes']))
            ->assertSee('Imported Thing')->assertDontSee('Local Thing');
        $this->actingAs($admin)->get(route('admin.opportunities.index', ['q' => 'Local']))
            ->assertSee('Local Thing')->assertDontSee('Imported Thing');
    }

    public function test_activity_log_filters_by_group_actor_and_date(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $other = User::factory()->create(['name' => 'Otto Other']);
        $opportunity = Opportunity::factory()->create(['title' => 'Filtered Opportunity']);

        Activity::record('admin.skill_merged', null, ['title' => 'Merged Skill Marker'], $admin);
        Activity::record('opportunity.published', $opportunity, [], $other);
        Activity::create(['user_id' => $other->id, 'type' => 'hours.logged', 'properties' => ['title' => 'Old Hours Marker'], 'created_at' => now()->subYear()]);

        $this->actingAs($admin)->get(route('admin.activity.index', ['group' => 'admin']))
            ->assertSee('Merged Skill Marker')->assertDontSee('Filtered Opportunity');
        $this->actingAs($admin)->get(route('admin.activity.index', ['actor' => 'Otto']))
            ->assertSee('Filtered Opportunity')->assertDontSee('Merged Skill Marker');
        $this->actingAs($admin)->get(route('admin.activity.index', ['from' => now()->subMonth()->toDateString()]))
            ->assertDontSee('Old Hours Marker')->assertSee('Filtered Opportunity');
        $this->actingAs($admin)->get(route('admin.activity.index', ['from' => 'not-a-date']))
            ->assertOk()->assertSee('Old Hours Marker');
    }
}
