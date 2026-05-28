<?php

namespace Database\Factories;

use App\Models\CommunicationConsent;
use App\Models\Client;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationConsent>
 */
class CommunicationConsentFactory extends Factory
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
            'recorded_by_user_id' => User::factory(),
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'purpose' => 'general',
            'status' => CommunicationConsent::STATUS_GRANTED,
            'source' => 'manual',
            'granted_at' => now(),
        ];
    }
}
