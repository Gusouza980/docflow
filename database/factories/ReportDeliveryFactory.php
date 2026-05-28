<?php

namespace Database\Factories;

use App\Models\ReportDelivery;
use App\Models\GeneratedReport;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportDelivery>
 */
class ReportDeliveryFactory extends Factory
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
            'generated_report_id' => GeneratedReport::factory(),
            'channel' => 'portal',
            'status' => 'released',
            'delivered_at' => now(),
        ];
    }
}
