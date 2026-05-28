<?php

namespace Database\Factories;

use App\Models\FinancialCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialCategory>
 */
class FinancialCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => FinancialCategory::TYPE_BOTH,
            'color' => '#0f766e',
            'is_active' => true,
        ];
    }
}
