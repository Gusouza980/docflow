<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationPaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationPaymentGateway>
 */
class OrganizationPaymentGatewayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider' => OrganizationPaymentGateway::PROVIDER_ASAAS,
            'api_key' => 'tenant-asaas-key-'.$this->faker->unique()->numerify('####'),
            'webhook_token' => 'tenant-webhook-'.$this->faker->unique()->numerify('####'),
            'is_enabled' => true,
            'connected_at' => now(),
        ];
    }
}
