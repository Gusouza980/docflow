<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'primary_responsible_member_id' => OrganizationMember::factory(),
            'type' => Client::TYPE_INDIVIDUAL,
            'display_name' => fake()->name(),
            'document_number' => fake()->unique()->numerify('###########'),
            'status' => Client::STATUS_ACTIVE,
            'priority' => Client::PRIORITY_NORMAL,
            'risk_level' => Client::RISK_LOW,
            'potential_revenue_cents' => fake()->numberBetween(50000, 500000),
            'origin' => 'referral',
            'access_policy' => Client::ACCESS_ALL_MEMBERS,
            'entered_at' => now()->toDateString(),
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Client::TYPE_COMPANY,
            'display_name' => fake()->company(),
            'document_number' => fake()->unique()->numerify('##############'),
        ]);
    }

    public function restricted(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_policy' => Client::ACCESS_RESTRICTED,
        ]);
    }
}
