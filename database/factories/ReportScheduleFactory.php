<?php

namespace Database\Factories;

use App\Models\ReportSchedule;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSchedule>
 */
class ReportScheduleFactory extends Factory
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
            'created_by_user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'report_type' => 'overview',
            'frequency' => 'monthly',
            'filters' => ['period' => 'month'],
            'is_active' => true,
            'next_run_at' => now()->addMonth()->startOfMonth()->toDateString(),
        ];
    }
}
