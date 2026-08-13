<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CreateTrialSubscription
{
    public function execute(Organization $organization, ?Plan $plan = null): Subscription
    {
        return DB::transaction(function () use ($organization, $plan): Subscription {
            if ($organization->subscription()->exists()) {
                return $organization->subscription()->firstOrFail();
            }

            $plan ??= Plan::query()
                ->where('slug', config('docflow.default_plan_slug', 'essencial'))
                ->where('is_active', true)
                ->firstOrFail();

            $trialDays = (int) config('docflow.subscription.default_trial_days', 14);
            $trialEndsAt = now()->addDays($trialDays);

            $billingProvider = config('docflow.billing.driver') === 'asaas'
                ? Subscription::BILLING_PROVIDER_ASAAS
                : Subscription::BILLING_PROVIDER_MANUAL;

            $subscription = Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_TRIALING,
                'billing_provider' => $billingProvider,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt,
            ]);

            if ($organization->plan_id !== $plan->id) {
                $organization->update(['plan_id' => $plan->id]);
            }

            return $subscription;
        });
    }
}
