<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTemplateItem>
 */
class TaskTemplateItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'task_template_id' => TaskTemplate::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'due_in_days' => fake()->numberBetween(0, 10),
            'priority' => Task::PRIORITY_NORMAL,
            'checklist_items' => null,
        ];
    }
}
