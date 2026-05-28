<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'type' => CalendarEvent::TYPE_INTERNAL,
            'status' => CalendarEvent::STATUS_SCHEDULED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ];
    }
}
