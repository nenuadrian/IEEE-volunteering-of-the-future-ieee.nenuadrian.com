<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_types_are_listed_with_usage(): void
    {
        $category = Category::factory()->create(['name' => 'Humanitarian', 'slug' => 'humanitarian']);
        Opportunity::factory()->create(['category_id' => $category->id]);
        Opportunity::factory()->status(Opportunity::COMPLETED)->create(['category_id' => $category->id]);

        $this->actingAs($this->admin)->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Humanitarian')
            ->assertViewHas('categories', fn ($categories) => $categories->first()->opportunities_count === 2
                && $categories->first()->open_opportunities_count === 1);
    }

    public function test_admin_can_create_a_type(): void
    {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'name' => 'Standards Work',
            'description' => 'Help write and review IEEE standards.',
            'color' => '#00629B',
            'display_order' => 7,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Standards Work', 'slug' => 'standards-work', 'color' => '#00629b', 'display_order' => 7, 'is_active' => true,
        ]);
    }

    public function test_type_names_must_be_unique_and_colours_valid(): void
    {
        Category::factory()->create(['name' => 'Technical', 'slug' => 'technical']);

        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'technical'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Outreach', 'color' => 'orange'])->assertSessionHasErrors('color');
        $this->assertSame(1, Category::count());
    }

    public function test_admin_can_edit_a_type_and_its_slug_stays_stable(): void
    {
        $category = Category::factory()->create(['name' => 'Event', 'slug' => 'event-based', 'is_active' => true]);

        $this->actingAs($this->admin)->put(route('admin.categories.update', $category), [
            'name' => 'Events & conferences',
            'description' => 'Run or support events.',
            'color' => '#e87722',
            'display_order' => 2,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('Events & conferences', $category->name);
        $this->assertSame('event-based', $category->slug);
        $this->assertSame(2, $category->display_order);
    }

    public function test_admin_can_toggle_a_type_active(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->patch(route('admin.categories.update', $category), ['toggle_active' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($category->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.categories.update', $category), ['toggle_active' => 1]);
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_an_unused_type_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $category))->assertSessionHasNoErrors();

        $this->assertModelMissing($category);
    }

    public function test_a_type_in_use_cannot_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Technical', 'slug' => 'technical']);
        $opportunity = Opportunity::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin)->from(route('admin.categories.index'))
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasErrors('category');

        $this->assertStringContainsString('Deactivate it instead', session('errors')->first('category'));
        $this->assertModelExists($category);
        $this->assertSame($category->id, $opportunity->fresh()->category_id);
    }
}
