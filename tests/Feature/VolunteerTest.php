<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_lists_public_active_volunteers_and_filters_by_skill(): void
    {
        $skill = Skill::factory()->create(['name' => 'Robotics']);
        $alice = User::factory()->create(['name' => 'Alice Robot']);
        $alice->skills()->attach($skill);
        User::factory()->create(['name' => 'Bob Builder']);
        $hidden = User::factory()->create(['name' => 'Hidden Person']);
        $hidden->profile->update(['is_public' => false]);
        User::factory()->suspended()->create(['name' => 'Suspended Person']);

        $this->get(route('volunteers.index'))
            ->assertOk()
            ->assertSee('Alice Robot')->assertSee('Bob Builder')
            ->assertDontSee('Hidden Person')->assertDontSee('Suspended Person');

        $this->get(route('volunteers.index', ['skills' => [$skill->id]]))
            ->assertSee('Alice Robot')->assertDontSee('Bob Builder');

        $this->get(route('volunteers.index', ['q' => 'robot', 'sort' => 'name']))->assertSee('Alice Robot');
        $this->get(route('volunteers.index', ['sort' => 'hours', 'available' => 1]))->assertOk();
    }

    public function test_private_profiles_are_only_visible_to_their_owner_and_admins(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['is_public' => false]);

        $this->get(route('volunteers.show', $user->profile))->assertNotFound();
        $this->get(route('volunteers.cv', $user->profile))->assertNotFound();
        $this->actingAs($user)->get(route('volunteers.show', $user->profile))->assertOk()->assertSee('hidden from the directory');
        $this->actingAs(User::factory()->admin()->create())->get(route('volunteers.show', $user->profile))->assertOk();
    }

    public function test_pdf_cv_is_generated_with_selected_sections(): void
    {
        $user = User::factory()->create(['name' => 'Casey Volunteer']);
        $user->profile->update(['headline' => 'Signal processing engineer', 'cv_statement' => 'Ten years of IEEE events.']);
        Application::factory()->completed()->create(['user_id' => $user->id, 'opportunity_id' => Opportunity::factory()->create()->id]);

        $response = $this->get(route('volunteers.cv', [$user->profile, 'download' => 1]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('attachment; filename="casey-volunteer-ieee-volunteer-cv.pdf"', $response->headers->get('content-disposition'));

        $this->get(route('volunteers.cv', [$user->profile, 'sections' => ['skills', 'experience']]))->assertOk();
    }

    public function test_cv_page_hides_email_unless_shared(): void
    {
        $user = User::factory()->create(['email' => 'secret@example.com']);

        $this->get(route('volunteers.show', $user->profile))->assertDontSee('secret@example.com');

        $user->profile->update(['show_email' => true]);
        $this->get(route('volunteers.show', $user->profile))->assertSee('secret@example.com');
    }

    public function test_volunteer_profile_can_be_updated_with_new_skills(): void
    {
        $user = User::factory()->create();
        $existing = Skill::factory()->create();

        $this->actingAs($user)->get(route('profile.volunteer.edit'))->assertOk();

        $this->actingAs($user)->patch(route('profile.volunteer.update'), [
            'headline' => 'Antenna engineer',
            'region' => 'R8',
            'section' => 'Germany',
            'membership_grade' => 'SM',
            'availability' => 'limited',
            'ieee_member_number' => '12345678',
            'linkedin_url' => 'https://www.linkedin.com/in/someone',
            'skills' => [(string) $existing->id, 'new:Phased arrays'],
            'is_public' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.volunteer.edit'));

        $profile = $user->profile->fresh();
        $this->assertSame('Antenna engineer', $profile->headline);
        $this->assertSame('SM', $profile->membership_grade);
        $this->assertFalse($profile->show_email);
        $this->assertEqualsCanonicalizing([$existing->name, 'Phased arrays'], $user->skills()->pluck('name')->all());

        $this->actingAs($user)->patch(route('profile.volunteer.update'), [
            'availability' => 'available',
            'linkedin_url' => 'https://evil.example.com/profile',
            'ieee_member_number' => 'abc',
        ])->assertSessionHasErrors(['linkedin_url', 'ieee_member_number']);
    }

    public function test_registration_creates_a_profile_and_suspended_users_cannot_sign_in(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $active = User::factory()->create();
        $this->actingAs($active)->get(route('dashboard'))->assertOk();
        $active->update(['suspended_at' => now()]);
        $this->actingAs($active)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_site_is_not_indexable(): void
    {
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('noindex, nofollow', false);
        $this->assertStringContainsString('Disallow: /', file_get_contents(public_path('robots.txt')));
    }
}
