<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationPlanOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationPlanOverride>
 */
class OrganizationPlanOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'limits' => ['max_members' => 10],
            'features' => null,
            'reason' => fake()->sentence(),
            'expires_at' => now()->addMonth(),
            'created_by_user_id' => User::factory()->state(['is_platform_admin' => true]),
        ];
    }
}
