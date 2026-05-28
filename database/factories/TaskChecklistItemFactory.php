<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskChecklistItem>
 */
class TaskChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'task_id' => Task::factory(),
            'title' => fake()->sentence(3),
            'is_required' => false,
            'is_completed' => false,
        ];
    }
}
