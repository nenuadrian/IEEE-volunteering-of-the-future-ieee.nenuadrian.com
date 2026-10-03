<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Application> */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'user_id' => User::factory(),
            'status' => Application::PENDING,
            'motivation' => $this->faker->sentence(12),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => Application::ACCEPTED, 'decided_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Application::COMPLETED, 'decided_at' => now()->subWeek(), 'completed_at' => now()]);
    }
}
