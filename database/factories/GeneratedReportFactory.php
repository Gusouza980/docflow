<?php

namespace Database\Factories;

use App\Models\GeneratedReport;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedReport>
 */
class GeneratedReportFactory extends Factory
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
            'client_id' => Client::factory(),
            'generated_by_user_id' => User::factory(),
            'type' => GeneratedReport::TYPE_CLIENT_MONTHLY,
            'title' => fake()->sentence(4),
            'status' => GeneratedReport::STATUS_DRAFT,
            'filters' => ['month' => now()->format('Y-m')],
            'payload' => ['summary' => []],
        ];
    }
}
