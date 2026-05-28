<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Task;
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
            'priority' => Task::PRIORITY_NORMAL,
            'is_active' => true,
        ];
    }
}
