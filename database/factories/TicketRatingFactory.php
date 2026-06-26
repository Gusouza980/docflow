<?php

namespace Database\Factories;

use App\Models\ClientPortalAccess;
use App\Models\Ticket;
use App\Models\TicketRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketRating>
 */
class TicketRatingFactory extends Factory
{
    protected $model = TicketRating::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Ticket::factory(),
            'ticket_id' => Ticket::factory(),
            'client_portal_access_id' => ClientPortalAccess::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
