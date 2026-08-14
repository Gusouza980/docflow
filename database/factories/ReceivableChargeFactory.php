<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Receivable;
use App\Models\ReceivableCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivableCharge>
 */
class ReceivableChargeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'receivable_id' => Receivable::factory(),
            'client_id' => Client::factory(),
            'provider' => ReceivableCharge::PROVIDER_ASAAS,
            'provider_payment_id' => 'pay_'.$this->faker->unique()->bothify('????####'),
            'billing_type' => ReceivableCharge::TYPE_PIX,
            'status' => ReceivableCharge::STATUS_PENDING,
            'invoice_url' => 'https://asaas.test/i/abc',
            'pix_payload' => '00020126580014br.gov.bcb.pix',
            'pix_encoded_image' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        ];
    }
}
