<?php

namespace Database\Factories;

use App\Models\ClientPortalAccess;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPortalAccess>
 */
class ClientPortalAccessFactory extends Factory
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
            'created_by_user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'token_hash' => hash('sha256', fake()->unique()->sha256()),
            'status' => ClientPortalAccess::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30),
        ];
    }
}
