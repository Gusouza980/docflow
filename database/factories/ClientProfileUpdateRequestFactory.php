<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\ClientProfileUpdateRequest;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientProfileUpdateRequest>
 */
class ClientProfileUpdateRequestFactory extends Factory
{
    protected $model = ClientProfileUpdateRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'client_id' => Client::factory(),
            'client_portal_access_id' => ClientPortalAccess::factory(),
            'status' => ClientProfileUpdateRequest::STATUS_PENDING,
            'changes' => ['email' => fake()->safeEmail()],
        ];
    }
}
