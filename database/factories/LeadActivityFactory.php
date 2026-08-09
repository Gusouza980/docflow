<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadActivity>
 */
class LeadActivityFactory extends Factory
{
    protected $model = LeadActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'created_by_user_id' => User::factory(),
            'type' => LeadActivity::TYPE_NOTE,
            'body' => fake()->sentence(),
            'happened_at' => now(),
        ];
    }
}
