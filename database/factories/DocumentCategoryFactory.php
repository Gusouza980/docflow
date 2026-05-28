<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'validity_days' => fake()->optional()->numberBetween(30, 365),
            'sensitivity' => DocumentCategory::SENSITIVITY_NORMAL,
            'is_active' => true,
        ];
    }
}
