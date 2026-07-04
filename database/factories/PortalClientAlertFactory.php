<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\PortalClientAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalClientAlert>
 */
class PortalClientAlertFactory extends Factory
{
    protected $model = PortalClientAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => Client::factory(),
            'client_portal_access_id' => ClientPortalAccess::factory(),
            'type' => PortalClientAlert::TYPE_GENERAL,
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(),
            'action_url' => '/client-portal',
        ];
    }
}
