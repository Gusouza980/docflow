<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'owner_user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('119########'),
            'origin' => Lead::ORIGIN_REFERRAL,
            'stage' => Lead::STAGE_NEW,
            'estimated_value_cents' => fake()->numberBetween(50000, 500000),
            'service_interest' => fake()->words(2, true),
        ];
    }

    public function lost(): static
    {
        return $this->state(fn (): array => [
            'stage' => Lead::STAGE_LOST,
            'lost_reason' => 'Preço',
        ]);
    }
}
