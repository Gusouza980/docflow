<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            if ($organization->subscription()->exists()) {
                return;
            }

            $planId = $organization->plan_id
                ?? Plan::query()->where('slug', config('docflow.default_plan_slug', 'essencial'))->value('id');

            if (! $planId) {
                return;
            }

            Subscription::factory()->active()->create([
                'organization_id' => $organization->id,
                'plan_id' => $planId,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'document' => fake()->unique()->numerify('##############'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'timezone' => 'America/Sao_Paulo',
            'status' => Organization::STATUS_ACTIVE,
        ];
    }
}
