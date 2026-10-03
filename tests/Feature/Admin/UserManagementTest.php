<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Application;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin', 'email' => 'ada@example.com']);
    }

    // ----- Listing & export ----------------------------------------------

    public function test_users_can_be_searched_filtered_and_sorted(): void
    {
        $maria = User::factory()->create(['name' => 'Maria Moreno', 'email' => 'maria@example.org']);
        $maria->profile->update(['region' => 'R9']);
        $sam = User::factory()->suspended()->create(['name' => 'Sam Suspended']);
        $sam->profile->update(['region' => 'R8']);

        $opportunity = Opportunity::factory()->create();
        $application = Application::factory()->accepted()->create(['opportunity_id' => $opportunity->id, 'user_id' => $maria->id]);
        HourLog::create([
            'application_id' => $application->id, 'user_id' => $maria->id, 'opportunity_id' => $opportunity->id,
            'worked_on' => now()->toDateString(), 'hours' => 12, 'status' => HourLog::APPROVED,
        ]);

        $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'maria@']))
            ->assertOk()->assertSee('Maria Moreno')->assertDontSee('Sam Suspended');
        $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'suspended']))
            ->assertSee('Sam Suspended')->assertDontSee('Maria Moreno');
        $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'admin']))
            ->assertSee('Ada Admin')->assertDontSee('Maria Moreno');
        $this->actingAs($this->admin)->get(route('admin.users.index', ['region' => 'R9']))
            ->assertSee('Maria Moreno')->assertDontSee('Sam Suspended');
        $this->actingAs($this->admin)->get(route('admin.users.index', ['sort' => 'hours', 'dir' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Maria Moreno', 'Sam Suspended']);
        $this->actingAs($this->admin)->get(route('admin.users.index', ['sort' => 'name', 'dir' => 'asc']))
            ->assertOk()->assertSeeInOrder(['Ada Admin', 'Maria Moreno', 'Sam Suspended']);
        $this->actingAs($this->admin)->get(route('admin.users.index', ['sort' => 'drop table', 'dir' => 'sideways']))
            ->assertOk();
    }

    public function test_the_filtered_list_exports_as_csv(): void
    {
        $maria = User::factory()->create(['name' => 'Maria Moreno', 'email' => 'maria@example.org']);
        $maria->profile->update(['region' => 'R9']);
        User::factory()->create(['name' => '=HYPERLINK("evil")', 'email' => 'other@example.org']);

        $response = $this->actingAs($this->admin)->get(route('admin.users.export', ['region' => 'R9']));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('maria@example.org', $csv);
        $this->assertStringNotContainsString('other@example.org', $csv);

        $all = $this->actingAs($this->admin)->get(route('admin.users.export'))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $all, 'formula-looking cells are neutralised');
    }

    public function test_user_page_summarises_before_any_action(): void
    {
        $user = User::factory()->create(['name' => 'Victor Volunteer']);
        $opportunity = Opportunity::factory()->ownedBy($user)->create(['title' => 'Owned Opportunity']);
        $elsewhere = Opportunity::factory()->create(['title' => 'Applied Opportunity']);
        Application::factory()->accepted()->create(['opportunity_id' => $elsewhere->id, 'user_id' => $user->id]);
        Activity::record('application.submitted', $elsewhere, [], $user);

        $this->actingAs($this->admin)->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Victor Volunteer')
            ->assertSee('Owned Opportunity')
            ->assertSee('Applied Opportunity')
            ->assertSee('Admin actions')
            ->assertSee('Delete this account')
            ->assertSee(route('volunteers.show', $user->profile));
    }

    // ----- Roles ---------------------------------------------------------

    public function test_admin_can_promote_and_demote_another_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->from(route('admin.users.show', $user))
            ->patch(route('admin.users.role', $user), ['role' => 'admin'])
            ->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->isAdmin());

        $activity = Activity::where('type', 'admin.role_changed')->latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $activity->user_id);
        $this->assertSame(['name' => $user->name, 'email' => $user->email, 'from' => 'user', 'to' => 'admin'], $activity->properties);

        $this->actingAs($this->admin)->patch(route('admin.users.role', $user), ['role' => 'user'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_role_must_be_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.users.role', $user), ['role' => 'superuser'])->assertSessionHasErrors('role');
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        // Also the only admin: demoting them would lock everyone out.
        $this->actingAs($this->admin)->patch(route('admin.users.role', $this->admin), ['role' => 'user'])->assertSessionHasErrors('role');

        $this->assertTrue($this->admin->fresh()->isAdmin());
        $this->assertSame(0, Activity::where('type', 'admin.role_changed')->count());
    }

    public function test_the_last_admin_keeps_admin_rights(): void
    {
        $other = User::factory()->admin()->create();

        // With two admins one may demote the other…
        $this->actingAs($this->admin)->patch(route('admin.users.role', $other), ['role' => 'user'])->assertSessionHasNoErrors();
        $this->assertSame(1, User::admins()->count());

        // …but the remaining (last) admin can't be demoted or deleted.
        $this->actingAs($this->admin)->patch(route('admin.users.role', $this->admin), ['role' => 'user'])->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin), ['confirm_email' => $this->admin->email])->assertSessionHasErrors('delete');
        $this->assertSame(1, User::admins()->count());
        $this->assertModelExists($this->admin);
    }

    // ----- Suspension ----------------------------------------------------

    public function test_admin_can_suspend_and_reinstate_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.users.suspend', $user))->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->isSuspended());
        $this->assertDatabaseHas('activities', ['type' => 'admin.user_suspended', 'subject_id' => $user->id, 'user_id' => $this->admin->id]);

        // A suspended user is signed out on their next request.
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($this->admin)->patch(route('admin.users.suspend', $user))->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->isSuspended());
        $this->assertDatabaseHas('activities', ['type' => 'admin.user_unsuspended', 'subject_id' => $user->id]);
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.users.suspend', $this->admin))->assertSessionHasErrors('suspend');

        $this->assertFalse($this->admin->fresh()->isSuspended());
    }

    // ----- Deletion ------------------------------------------------------

    public function test_deleting_a_user_requires_typing_their_email(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.org']);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user), ['confirm_email' => 'wrong@example.org'])
            ->assertSessionHasErrors('confirm_email');
        $this->assertModelExists($user);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))->assertSessionHasErrors('confirm_email');
        $this->assertModelExists($user);
    }

    public function test_admin_can_delete_a_user_and_the_audit_log_keeps_who_it_was(): void
    {
        $user = User::factory()->create(['name' => 'Gone Person', 'email' => 'gone@example.org']);
        $application = Application::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user), ['confirm_email' => ' GONE@example.org '])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($user);
        $this->assertModelMissing($application);
        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);

        $activity = Activity::where('type', 'admin.user_deleted')->firstOrFail();
        $this->assertSame(['name' => 'Gone Person', 'email' => 'gone@example.org'], $activity->properties);

        $this->actingAs($this->admin)->get(route('admin.activity.index', ['group' => 'admin']))
            ->assertOk()
            ->assertSee('Gone Person')
            ->assertSee('Deleted a user');
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        User::factory()->admin()->create();

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin), ['confirm_email' => $this->admin->email])
            ->assertSessionHasErrors('delete');

        $this->assertModelExists($this->admin);
    }
}
