<?php

namespace Database\Factories;

use App\Models\InternalReminder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalReminder>
 */
class InternalReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'type' => 'task_assigned',
            'remind_at' => now()->addDay(),
        ];
    }
}
