<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\CalendarEventParticipant;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEventParticipant>
 */
class CalendarEventParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'calendar_event_id' => CalendarEvent::factory(),
            'external_name' => fake()->name(),
            'external_email' => fake()->safeEmail(),
            'status' => 'pending',
        ];
    }
}
