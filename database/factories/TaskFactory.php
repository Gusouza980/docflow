<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'status' => Task::STATUS_PENDING,
            'priority' => TaskPriority::Normal,
            'due_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ];
    }
}
