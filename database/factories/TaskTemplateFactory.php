<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Models\Organization;
use App\Models\TaskTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTemplate>
 */
class TaskTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'priority' => TaskPriority::Normal,
            'is_active' => true,
        ];
    }
}
