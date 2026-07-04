<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    protected $model = SubscriptionInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = now()->startOfDay();
        $periodEnd = $periodStart->copy()->addMonth();

        return [
            'subscription_id' => Subscription::factory(),
            'organization_id' => Organization::factory(),
            'amount_cents' => 9900,
            'currency' => 'BRL',
            'status' => SubscriptionInvoice::STATUS_OPEN,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_at' => now()->addDays(7),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionInvoice::STATUS_OPEN,
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionInvoice::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionInvoice::STATUS_OPEN,
            'due_at' => now()->subDay(),
        ]);
    }
}
