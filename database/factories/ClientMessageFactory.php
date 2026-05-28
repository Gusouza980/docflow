<?php

namespace Database\Factories;

use App\Models\ClientMessage;
use App\Models\Client;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientMessage>
 */
class ClientMessageFactory extends Factory
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
            'message_template_id' => MessageTemplate::factory(),
            'sent_by_user_id' => User::factory(),
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'direction' => ClientMessage::DIRECTION_OUTBOUND,
            'status' => ClientMessage::STATUS_REGISTERED,
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'sent_at' => now(),
        ];
    }
}
