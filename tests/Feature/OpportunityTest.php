<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Category;
use App\Models\Opportunity;
use App\Models\SearchLog;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Paper reviewer for IEEE ICC',
            'description' => 'Review three to five papers in signal processing and submit structured reviews.',
            'category_id' => Category::factory()->create()->id,
            'is_online' => '1',
            'experience_level' => 'Some experience',
            'project_size' => 'Small project',
            'hours_estimate' => 12,
            'hours_frequency' => 'overall',
            'volunteers_needed' => 3,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(6)->toDateString(),
            'skills' => [(string) Skill::factory()->create()->id],
            'upskills' => ['Communication', 'Problem Solving', 'Leadership Qualities'],
            'membership_grades' => ['M', 'SM'],
            'action' => 'publish',
        ], $overrides);
    }

    public function test_guests_can_browse_and_view_opportunities(): void
    {
        $opportunity = Opportunity::factory()->create(['title' => 'Webinar host for YP']);

        $this->get(route('opportunities.index'))->assertOk()->assertSee('Webinar host for YP');
        $this->get(route('opportunities.show', $opportunity))->assertOk()->assertSee('Sign in to apply');
    }

    public function test_drafts_are_only_visible_to_their_owners(): void
    {
        $owner = User::factory()->create();
        $draft = Opportunity::factory()->draft()->ownedBy($owner)->create();

        $this->get(route('opportunities.show', $draft))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('opportunities.show', $draft))->assertNotFound();
        $this->actingAs($owner)->get(route('opportunities.show', $draft))->assertOk()->assertSee('draft');
        $this->get(route('opportunities.index'))->assertDontSee($draft->title);
    }

    public function test_search_filters_and_logs_queries(): void
    {
        $skill = Skill::factory()->create(['name' => 'Graphic design']);
        $match = Opportunity::factory()->create(['title' => 'Poster designer', 'is_online' => true]);
        $match->skills()->attach($skill);
        $other = Opportunity::factory()->create(['title' => 'Registration desk', 'is_online' => false]);

        $this->get(route('opportunities.index', ['q' => 'poster']))
            ->assertSee('Poster designer')->assertDontSee('Registration desk');

        $this->get(route('opportunities.index', ['skills' => [$skill->id]]))
            ->assertSee('Poster designer')->assertDontSee('Registration desk');

        $this->get(route('opportunities.index', ['online' => 1]))
            ->assertSee('Poster designer')->assertDontSee('Registration desk');

        $this->get(route('opportunities.index', ['category' => $other->category->slug]))
            ->assertSee('Registration desk')->assertDontSee('Poster designer');

        $this->assertDatabaseHas('search_logs', ['query' => 'poster', 'scope' => 'opportunities', 'results_count' => 1]);
        $this->assertSame(4, SearchLog::count());
    }

    public function test_signed_in_users_see_explained_match_scores(): void
    {
        $user = User::factory()->create();
        $skill = Skill::factory()->create();
        $user->skills()->attach($skill);
        $user->profile->update(['membership_grade' => 'M']);

        $opportunity = Opportunity::factory()->create(['membership_grades' => ['M']]);
        $opportunity->skills()->attach($skill);

        $this->actingAs($user)->get(route('opportunities.index'))
            ->assertSee('100% Match')
            ->assertSee('You have 1 of 1 required skills');
    }

    public function test_any_user_can_create_and_publish_an_opportunity_with_co_owners(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $coOwner = User::factory()->create();

        $response = $this->actingAs($user)->post(route('opportunities.store'), $this->validPayload([
            'skills' => ['new:Quantum widgets'],
            'co_owners' => [(string) $coOwner->id],
        ]));

        $opportunity = Opportunity::firstOrFail();
        $response->assertRedirect(route('opportunities.manage', $opportunity));

        $this->assertSame(Opportunity::OPEN, $opportunity->status);
        $this->assertNotNull($opportunity->published_at);
        $this->assertSame(['Communication', 'Problem Solving', 'Leadership Qualities'], $opportunity->upskills);
        $this->assertTrue($opportunity->skills->contains('name', 'Quantum widgets'));
        $this->assertTrue($opportunity->isOwnedBy($user));
        $this->assertTrue($opportunity->isOwnedBy($coOwner));
        $this->assertSame('owner', $opportunity->owners()->where('users.id', $user->id)->first()->pivot->role);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->templateKey === 'coowner_added' && $m->hasTo($coOwner->email));
    }

    public function test_drafts_only_need_a_title_but_publishing_validates_everything(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('opportunities.store'), ['title' => 'Half-baked idea', 'action' => 'draft'])
            ->assertSessionHasNoErrors();
        $draft = Opportunity::firstOrFail();
        $this->assertSame(Opportunity::DRAFT, $draft->status);

        $this->actingAs($user)->put(route('opportunities.update', $draft), ['title' => 'Half-baked idea', 'action' => 'publish'])
            ->assertSessionHasErrors(['description', 'category_id', 'skills', 'start_date']);
    }

    public function test_only_owners_and_admins_can_edit(): void
    {
        $owner = User::factory()->create();
        $opportunity = Opportunity::factory()->ownedBy($owner)->create();

        $this->actingAs(User::factory()->create())->get(route('opportunities.edit', $opportunity))->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('opportunities.update', $opportunity), $this->validPayload())->assertForbidden();
        $this->actingAs($owner)->get(route('opportunities.edit', $opportunity))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('opportunities.manage', $opportunity))->assertOk();

        $this->actingAs($owner)->put(route('opportunities.update', $opportunity), $this->validPayload(['title' => 'Renamed role', 'action' => 'save']))
            ->assertRedirect(route('opportunities.manage', $opportunity));
        $this->assertSame('Renamed role', $opportunity->fresh()->title);
    }

    public function test_anyone_signed_in_can_clone_an_opportunity_as_a_draft(): void
    {
        $opportunity = Opportunity::factory()->create(['start_date' => now()->subMonths(3), 'end_date' => now()->subMonths(2)]);
        $opportunity->skills()->attach(Skill::factory()->count(2)->create());
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('opportunities.clone', $opportunity))->assertRedirect();

        $copy = Opportunity::where('cloned_from_id', $opportunity->id)->firstOrFail();
        $this->assertSame(Opportunity::DRAFT, $copy->status);
        $this->assertStringStartsWith('Copy of ', $copy->title);
        $this->assertTrue($copy->isOwnedBy($user));
        $this->assertCount(2, $copy->skills);
        $this->assertTrue($copy->start_date->isToday(), 'Past dates shift to start today');
        $this->assertSame(0, $copy->applications()->count());
    }

    public function test_owner_can_change_status_save_and_delete(): void
    {
        $owner = User::factory()->create();
        $opportunity = Opportunity::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->post(route('opportunities.status', $opportunity), ['status' => 'completed']);
        $this->assertSame('completed', $opportunity->fresh()->status);
        $this->assertNotNull($opportunity->fresh()->closed_at);

        $this->actingAs($owner)->post(route('opportunities.save', $opportunity));
        $this->assertTrue($owner->hasSaved($opportunity));
        $this->actingAs($owner)->post(route('opportunities.save', $opportunity));
        $this->assertFalse($owner->hasSaved($opportunity));

        $this->actingAs($owner)->delete(route('opportunities.destroy', $opportunity))->assertRedirect();
        $this->assertSoftDeleted($opportunity);
    }

    public function test_owners_can_be_added_and_removed_but_never_the_last_one(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $friend = User::factory()->create();
        $opportunity = Opportunity::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->post(route('opportunities.owners.store', $opportunity), ['user_id' => $friend->id]);
        $this->assertTrue($opportunity->isOwnedBy($friend));

        $this->actingAs($friend)->delete(route('opportunities.owners.destroy', [$opportunity, $owner]));
        $this->assertFalse($opportunity->fresh()->isOwnedBy($owner));
        $this->assertSame('owner', $opportunity->owners()->first()->pivot->role, 'Remaining co-owner is promoted');

        $this->actingAs($friend)->delete(route('opportunities.owners.destroy', [$opportunity, $friend]))
            ->assertSessionHasErrors('owners');
    }

    public function test_user_lookup_returns_active_people_only(): void
    {
        $me = User::factory()->create();
        User::factory()->create(['name' => 'Priya Sharma']);
        User::factory()->suspended()->create(['name' => 'Priya Suspended']);

        $this->actingAs($me)->getJson(route('lookup.users', ['q' => 'Priya']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Priya Sharma');
    }
}
