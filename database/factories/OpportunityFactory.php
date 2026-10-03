<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Opportunity> */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        $title = Str::limit($this->faker->unique()->sentence(5), 120, '');

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'title' => $title,
            'description' => $this->faker->paragraphs(2, true),
            'category_id' => Category::factory(),
            'status' => Opportunity::OPEN,
            'is_online' => true,
            'region' => 'R8',
            'experience_level' => 'Some experience',
            'project_size' => 'Small project',
            'hours_estimate' => 10,
            'hours_frequency' => 'overall',
            'volunteers_needed' => 2,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(40)->toDateString(),
            'source' => Opportunity::SOURCE_LOCAL,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Opportunity::DRAFT, 'published_at' => null]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /** Attach $user as the primary owner after creation. */
    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['created_by' => $user->id])
            ->afterCreating(fn (Opportunity $o) => $o->owners()->attach($user->id, ['role' => 'owner', 'added_by' => $user->id]));
    }
}
