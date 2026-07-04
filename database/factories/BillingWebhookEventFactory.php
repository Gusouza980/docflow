<?php

namespace Database\Factories;

use App\Models\BillingWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingWebhookEvent>
 */
class BillingWebhookEventFactory extends Factory
{
    protected $model = BillingWebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'manual',
            'event_id' => fake()->unique()->uuid(),
            'payload' => ['event' => 'invoice.paid'],
        ];
    }
}
