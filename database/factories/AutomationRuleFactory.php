<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Automação '.fake()->words(2, true),
            'trigger' => AutomationRule::TRIGGER_CLIENT_CREATED,
            'preset_key' => 'client_created_tasks',
            'conditions' => [],
            'actions' => [
                [
                    'type' => AutomationRule::ACTION_NOTIFY_ORGANIZATION_MEMBERS,
                    'params' => [
                        'roles' => ['admin'],
                        'message' => 'Cliente criado',
                    ],
                ],
            ],
            'is_active' => true,
        ];
    }
}
