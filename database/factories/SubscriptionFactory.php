<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodEnd = now()->addMonth();

        return [
            'organization_id' => Organization::factory(),
            'plan_id' => Plan::query()->where('slug', config('docflow.default_plan_slug', 'essencial'))->value('id')
                ?? Plan::factory(),
            'status' => Subscription::STATUS_ACTIVE,
            'billing_provider' => Subscription::BILLING_PROVIDER_MANUAL,
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
        ];
    }

    public function trialing(?int $days = null): static
    {
        $trialDays = $days ?? (int) config('docflow.subscription.default_trial_days', 14);
        $trialEndsAt = now()->addDays($trialDays);

        return $this->state(fn (): array => [
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => now(),
            'current_period_end' => $trialEndsAt,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'past_due_at' => null,
            'canceled_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function pastDue(?int $daysAgo = 0): static
    {
        return $this->state(fn (): array => [
            'status' => Subscription::STATUS_PAST_DUE,
            'past_due_at' => now()->subDays($daysAgo),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (): array => [
            'status' => Subscription::STATUS_CANCELED,
            'canceled_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => Subscription::STATUS_PAUSED,
        ]);
    }

    public function expiredTrial(): static
    {
        return $this->state(fn (): array => [
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
            'current_period_end' => now()->subDay(),
        ]);
    }
}
