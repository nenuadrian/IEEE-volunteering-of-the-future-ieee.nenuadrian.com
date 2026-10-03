<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Application;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $volunteer;

    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->owner = User::factory()->create();
        $this->volunteer = User::factory()->create();
        $this->opportunity = Opportunity::factory()->ownedBy($this->owner)->create(['volunteers_needed' => 1]);
    }

    private function apply(?User $user = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user ?? $this->volunteer)->post(route('applications.store', $this->opportunity), [
            'motivation' => 'I have organised similar events and would love to help out.',
        ]);
    }

    public function test_volunteer_applies_and_owners_are_notified(): void
    {
        $this->apply()->assertRedirect(route('opportunities.show', $this->opportunity));

        $this->assertDatabaseHas('applications', ['user_id' => $this->volunteer->id, 'status' => 'pending']);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'application_received' && $m->hasTo($this->owner->email));
        $this->assertDatabaseHas('activities', ['type' => 'application.submitted', 'user_id' => $this->volunteer->id]);
    }

    public function test_application_guards(): void
    {
        $this->apply();
        $this->apply()->assertSessionHasErrors('application');                 // twice
        $this->apply($this->owner)->assertSessionHasErrors('application');    // own opportunity

        $closed = Opportunity::factory()->status(Opportunity::IN_PROGRESS)->create();
        $this->actingAs($this->volunteer)->post(route('applications.store', $closed), ['motivation' => str_repeat('a', 30)])
            ->assertSessionHasErrors('application');

        $this->actingAs(User::factory()->create())->post(route('applications.store', $this->opportunity), ['motivation' => 'hi'])
            ->assertSessionHasErrors('motivation');
    }

    public function test_owner_accepts_and_the_opportunity_is_marked_filled(): void
    {
        $this->apply();
        $application = Application::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->post(route('applications.decide', $application), ['decision' => 'accepted'])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post(route('applications.decide', $application), ['decision' => 'accepted', 'owner_note' => 'Welcome aboard!']);

        $application->refresh();
        $this->assertSame(Application::ACCEPTED, $application->status);
        $this->assertSame($this->owner->id, $application->decided_by);
        $this->assertNotNull($this->opportunity->fresh()->filled_at);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'application_accepted' && $m->hasTo($this->volunteer->email));
    }

    public function test_hours_are_logged_by_volunteers_and_approved_by_owners(): void
    {
        $this->apply();
        $application = Application::firstOrFail();

        // Not yet accepted.
        $this->actingAs($this->volunteer)->post(route('hours.store', $application), ['worked_on' => now()->toDateString(), 'hours' => 2])
            ->assertSessionHasErrors('hours');

        $application->update(['status' => Application::ACCEPTED, 'decided_at' => now()]);

        $this->actingAs($this->volunteer)->post(route('hours.store', $application), [
            'worked_on' => now()->toDateString(), 'hours' => 2.5, 'description' => 'Reviewed submissions',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->volunteer)->post(route('hours.store', $application), ['worked_on' => now()->addDay()->toDateString(), 'hours' => 1])
            ->assertSessionHasErrors('worked_on');

        $log = HourLog::firstOrFail();
        $this->assertSame(HourLog::PENDING, $log->status);

        $this->actingAs($this->volunteer)->post(route('hours.review', $log), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->owner)->post(route('hours.review', $log), ['decision' => 'approved']);

        $this->assertSame(HourLog::APPROVED, $log->fresh()->status);
        $this->assertSame(2.5, $this->volunteer->approvedHours());

        // Approved entries can no longer be deleted by the volunteer.
        $this->actingAs($this->volunteer)->delete(route('hours.destroy', $log))->assertForbidden();
    }

    public function test_completion_with_rating_endorsement_and_skills_shows_on_the_cv(): void
    {
        $skill = Skill::factory()->create(['name' => 'Event logistics']);
        $this->opportunity->skills()->attach($skill);
        $application = Application::factory()->accepted()->create(['opportunity_id' => $this->opportunity->id, 'user_id' => $this->volunteer->id]);

        $this->actingAs($this->owner)->post(route('applications.complete', $application), [
            'owner_rating' => 5,
            'endorsement' => 'Brilliant work running the registration desk, calm under pressure.',
            'endorsed_skills' => [$skill->id],
        ])->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(Application::COMPLETED, $application->status);
        $this->assertSame(5, $application->owner_rating);
        $endorsement = Endorsement::firstOrFail();
        $this->assertSame($this->owner->id, $endorsement->endorser_id);
        $this->assertTrue($endorsement->skills->contains($skill));
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'application_completed');

        $this->get(route('volunteers.show', $this->volunteer->profile))
            ->assertOk()
            ->assertSee('Brilliant work running the registration desk')
            ->assertSee($this->opportunity->title);
    }

    public function test_volunteer_can_withdraw_reapply_and_rate(): void
    {
        $this->apply();
        $application = Application::firstOrFail();

        $this->actingAs($this->volunteer)->post(route('applications.withdraw', $application));
        $this->assertSame(Application::WITHDRAWN, $application->fresh()->status);

        $this->apply()->assertSessionHasNoErrors();
        $this->assertSame(Application::PENDING, $application->fresh()->status);
        $this->assertSame(1, Application::count());

        $application->update(['status' => Application::COMPLETED]);
        $this->actingAs($this->volunteer)->post(route('applications.feedback', $application), ['volunteer_rating' => 4, 'volunteer_feedback' => 'Great team']);
        $this->assertSame(4, $application->fresh()->volunteer_rating);

        $this->actingAs(User::factory()->create())->post(route('applications.withdraw', $application))->assertForbidden();
    }

    public function test_my_opportunities_and_dashboard_render_for_both_roles(): void
    {
        Application::factory()->accepted()->create(['opportunity_id' => $this->opportunity->id, 'user_id' => $this->volunteer->id]);
        Application::factory()->create(['opportunity_id' => $this->opportunity->id]);

        foreach ([$this->volunteer, $this->owner, User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('My volunteer impact');
            foreach (['volunteering', 'managing', 'saved'] as $tab) {
                $this->actingAs($user)->get(route('my.opportunities', ['tab' => $tab]))->assertOk();
            }
        }

        $this->actingAs($this->owner)->get(route('my.opportunities'))->assertSee('Items need your action');
        $this->actingAs($this->owner)->get(route('dashboard'))->assertSee('Impact of my volunteers')->assertSee('Volunteers engaged');
        foreach (['applicants', 'hours', 'team', 'impact'] as $tab) {
            $this->actingAs($this->owner)->get(route('opportunities.manage', [$this->opportunity, 'tab' => $tab]))->assertOk();
        }
        $this->actingAs($this->owner)->get(route('opportunities.manage.export', $this->opportunity))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
    }
}
