<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RequestPlanChange
{
    public function __construct(private ChangeOrganizationPlan $changeOrganizationPlan) {}

    public function execute(Organization $organization, Plan $targetPlan): Subscription
    {
        return DB::transaction(function () use ($organization, $targetPlan): Subscription {
            $subscription = $organization->subscriptionOrFail();
            $subscription->loadMissing('plan');

            $currentPlan = $subscription->plan ?? $organization->plan;

            if ($currentPlan === null) {
                throw new InvalidArgumentException('Organização sem plano atual.');
            }

            if ($currentPlan->id === $targetPlan->id) {
                return $subscription;
            }

            if (! $targetPlan->is_public || ! $targetPlan->is_active) {
                throw new InvalidArgumentException('Plano indisponível para self-service.');
            }

            $isUpgrade = (int) $targetPlan->sort_order >= (int) $currentPlan->sort_order
                || (int) $targetPlan->price_cents > (int) $currentPlan->price_cents;

            if ($isUpgrade) {
                return $this->changeOrganizationPlan->execute($organization, $targetPlan);
            }

            $metadata = $subscription->metadata ?? [];
            $metadata['pending_plan_id'] = $targetPlan->id;
            $metadata['pending_plan_effective_at'] = $subscription->current_period_end?->toIso8601String();

            $subscription->update(['metadata' => $metadata]);

            return $subscription->fresh();
        });
    }
}
