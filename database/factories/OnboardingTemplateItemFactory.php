<?php

namespace Database\Factories;

use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTemplateItem>
 */
class OnboardingTemplateItemFactory extends Factory
{
    protected $model = OnboardingTemplateItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onboarding_template_id' => OnboardingTemplate::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'due_in_days' => fake()->numberBetween(0, 14),
            'sort_order' => 0,
        ];
    }
}
