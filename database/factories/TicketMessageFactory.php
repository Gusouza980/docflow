<?php

namespace Database\Factories;

use App\Models\TicketMessage;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
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
            'ticket_id' => Ticket::factory(),
            'sender_type' => TicketMessage::SENDER_INTERNAL,
            'body' => fake()->paragraph(),
            'visible_to_client' => true,
        ];
    }
}
