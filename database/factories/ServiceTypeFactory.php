<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'default_amount_cents' => fake()->numberBetween(10000, 500000),
            'default_billing_interval' => ServiceType::BILLING_MONTH,
        ];
    }
}
