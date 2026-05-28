<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => Client::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => Ticket::STATUS_NEW,
            'priority' => Ticket::PRIORITY_NORMAL,
            'visible_to_client' => true,
            'due_at' => now()->addWeek()->toDateString(),
        ];
    }
}
