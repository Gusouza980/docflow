<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
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
            'created_by_user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'channel' => MessageTemplate::CHANNEL_EMAIL,
            'purpose' => 'general',
            'subject' => fake()->sentence(4),
            'body' => 'Olá {{client_name}}, '.fake()->paragraph(),
            'variables' => ['client_name'],
            'requires_consent' => true,
            'is_active' => true,
        ];
    }
}
