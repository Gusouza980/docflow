<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'code' => strtoupper(fake()->unique()->bothify('CTR-####')),
            'status' => Contract::STATUS_ACTIVE,
            'amount_cents' => fake()->numberBetween(50000, 1000000),
            'billing_interval' => Contract::BILLING_MONTH,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'auto_renew' => false,
            'scope_included' => fake()->sentence(),
            'scope_excluded' => null,
        ];
    }
}
