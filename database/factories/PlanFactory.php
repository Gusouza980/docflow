<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(5000, 50000),
            'billing_interval' => Plan::BILLING_INTERVAL_MONTH,
            'trial_days' => 14,
            'limits' => [
                'max_members' => 5,
                'max_clients' => 25,
                'max_storage_mb' => 1024,
                'max_portal_accesses' => 5,
            ],
            'features' => [
                'portal' => false,
                'finance_advanced' => false,
                'reports_scheduling' => false,
                'audit' => false,
                'automations' => false,
                'crm' => false,
            ],
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
