<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAuditLog>
 */
class PlatformAuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_admin_user_id' => User::factory()->state(['is_platform_admin' => true]),
            'action' => PlatformAuditLog::ACTION_ORGANIZATION_VIEWED,
            'subject_type' => Organization::class,
            'subject_id' => Organization::factory(),
            'metadata' => [],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
