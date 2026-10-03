<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SkillManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_skills_list_shows_supply_and_demand(): void
    {
        $skill = Skill::factory()->create(['name' => 'Signal processing', 'category' => 'Technical']);
        User::factory()->count(2)->create()->each(fn ($u) => $u->skills()->attach($skill));
        Opportunity::factory()->create()->skills()->attach($skill);
        Opportunity::factory()->status(Opportunity::COMPLETED)->create()->skills()->attach($skill);
        Skill::factory()->create(['name' => 'Public speaking', 'category' => 'Communication']);

        $this->actingAs($this->admin)->get(route('admin.skills.index', ['q' => 'signal']))
            ->assertOk()
            ->assertViewHas('skills', function ($skills) {
                $row = $skills->first();

                return $skills->count() === 1 && $row->users_count === 2 && $row->open_opportunities_count === 1 && $row->opportunities_count === 2;
            });

        $this->actingAs($this->admin)->get(route('admin.skills.index', ['category' => 'Communication']))
            ->assertViewHas('skills', fn ($skills) => $skills->pluck('name')->all() === ['Public speaking']);

        foreach (['volunteers', 'demand', 'unused', 'name'] as $sort) {
            $this->actingAs($this->admin)->get(route('admin.skills.index', ['sort' => $sort]))->assertOk();
        }
    }

    public function test_admin_can_add_a_skill_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.skills.store'), ['name' => '  Machine   learning ', 'category' => 'Technical'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('skills', ['name' => 'Machine learning', 'slug' => 'machine-learning', 'category' => 'Technical']);

        $this->actingAs($this->admin)->post(route('admin.skills.store'), ['name' => 'MACHINE LEARNING'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('admin.skills.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(1, Skill::count());
    }

    public function test_admin_can_rename_a_skill_inline(): void
    {
        $skill = Skill::factory()->create(['name' => 'Pubic speaking']);
        Skill::factory()->create(['name' => 'Teaching']);

        $this->actingAs($this->admin)->put(route('admin.skills.update', $skill), ['name' => 'Public speaking', 'category' => 'Communication'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Public speaking', $skill->fresh()->name);
        $this->assertSame('Communication', $skill->fresh()->category);

        // Renaming onto another skill's name is refused (merge instead).
        $this->actingAs($this->admin)->put(route('admin.skills.update', $skill), ['name' => 'teaching', 'editing_id' => $skill->id])
            ->assertSessionHasErrors('name');
        $this->assertSame('Public speaking', $skill->fresh()->name);

        // Keeping its own name is fine.
        $this->actingAs($this->admin)->put(route('admin.skills.update', $skill), ['name' => 'Public speaking'])->assertSessionHasNoErrors();
    }

    public function test_merging_moves_every_link_to_the_target(): void
    {
        $duplicate = Skill::factory()->create(['name' => 'ML']);
        $target = Skill::factory()->create(['name' => 'Machine learning']);

        $onlyDuplicate = User::factory()->create();
        $onlyDuplicate->skills()->attach($duplicate);
        $both = User::factory()->create();
        $both->skills()->attach([$duplicate->id, $target->id]);
        $opportunity = Opportunity::factory()->create();
        $opportunity->skills()->attach($duplicate);

        $this->actingAs($this->admin)
            ->post(route('admin.skills.merge'), ['source_id' => $duplicate->id, 'target_id' => $target->id])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($duplicate);
        $this->assertSame([$target->id], $onlyDuplicate->skills()->pluck('skills.id')->all());
        $this->assertSame([$target->id], $both->skills()->pluck('skills.id')->all());
        $this->assertSame([$target->id], $opportunity->skills()->pluck('skills.id')->all());
        $this->assertDatabaseMissing('skill_user', ['skill_id' => $duplicate->id]);

        $activity = Activity::where('type', 'admin.skill_merged')->firstOrFail();
        $this->assertSame('ML', $activity->properties['from']);
        $this->assertSame('Machine learning', $activity->properties['into']);
    }

    public function test_a_skill_cannot_be_merged_into_itself(): void
    {
        $skill = Skill::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.skills.merge'), ['source_id' => $skill->id, 'target_id' => $skill->id])
            ->assertSessionHasErrors('target_id');
        $this->assertModelExists($skill);
    }

    public function test_deleting_a_skill_removes_its_links(): void
    {
        $skill = Skill::factory()->create();
        $user = User::factory()->create();
        $user->skills()->attach($skill);

        $this->actingAs($this->admin)->delete(route('admin.skills.destroy', $skill))->assertSessionHasNoErrors();

        $this->assertModelMissing($skill);
        $this->assertDatabaseMissing('skill_user', ['skill_id' => $skill->id]);
    }

    public function test_csv_import_creates_updates_and_skips(): void
    {
        Skill::factory()->create(['name' => 'Data analysis', 'category' => null]);
        Skill::factory()->create(['name' => 'Teaching', 'category' => 'Education']);

        $csv = "\xEF\xBB\xBFname,category\nData analysis,Research\nRobotics,Technical\nteaching,\n,Orphan category\n\"Quoted, skill\",\n";
        $file = UploadedFile::fake()->createWithContent('skills.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.skills.import'), ['file' => $file])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Import finished: 2 created, 1 updated, 2 skipped (unchanged, blank or invalid).');

        $this->assertSame('Research', Skill::where('slug', 'data-analysis')->value('category'));
        $this->assertSame('Technical', Skill::where('slug', 'robotics')->value('category'));
        $this->assertSame('Education', Skill::where('slug', 'teaching')->value('category'));
        $this->assertTrue(Skill::where('name', 'Quoted, skill')->exists());
        $this->assertSame(4, Skill::count());
    }

    public function test_import_requires_a_csv_file(): void
    {
        $this->actingAs($this->admin)->post(route('admin.skills.import'), [])->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post(route('admin.skills.import'), ['file' => UploadedFile::fake()->createWithContent('skills.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n")])
            ->assertSessionHasErrors('file');
    }

    public function test_export_lists_skills_with_usage(): void
    {
        $skill = Skill::factory()->create(['name' => 'Cloud Computing', 'category' => 'Technical']);
        User::factory()->create()->skills()->attach($skill);
        Opportunity::factory()->create()->skills()->attach($skill);

        $response = $this->actingAs($this->admin)->get(route('admin.skills.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("name,category,volunteers,open_opportunities\n", $csv);
        $this->assertStringContainsString('"Cloud Computing",Technical,1,1', $csv);
    }
}
