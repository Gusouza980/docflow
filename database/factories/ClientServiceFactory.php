<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\Organization;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientService>
 */
class ClientServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'service_type_id' => fn (array $attributes) => ServiceType::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'status' => ClientService::STATUS_ACTIVE,
            'starts_at' => now()->toDateString(),
            'ends_at' => null,
            'assigned_to_member_id' => null,
            'notes' => null,
        ];
    }
}
