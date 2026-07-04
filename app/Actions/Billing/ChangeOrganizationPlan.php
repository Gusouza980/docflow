<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class ChangeOrganizationPlan
{
    public function execute(Organization $organization, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($organization, $plan): Subscription {
            $subscription = $organization->subscription;

            if ($subscription === null) {
                $subscription = app(CreateTrialSubscription::class)->execute($organization, $plan);
            }

            $subscription->update(['plan_id' => $plan->id]);
            $organization->update(['plan_id' => $plan->id]);

            return $subscription->fresh(['plan']);
        });
    }
}
