<?php

namespace Database\Factories;

use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationLog>
 */
class AutomationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'automation_rule_id' => AutomationRule::factory(),
            'trigger' => AutomationRule::TRIGGER_CLIENT_CREATED,
            'subject_type' => (new Client)->getMorphClass(),
            'subject_id' => Client::factory(),
            'dedupe_key' => fake()->unique()->uuid(),
            'status' => AutomationLog::STATUS_SUCCEEDED,
            'result' => [],
            'ran_at' => now(),
        ];
    }
}
