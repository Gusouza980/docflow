<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class ActivateSubscription
{
    public function execute(Organization $organization): Subscription
    {
        return DB::transaction(function () use ($organization): Subscription {
            $subscription = $organization->subscriptionOrFail();

            $subscription->update([
                'status' => Subscription::STATUS_ACTIVE,
                'past_due_at' => null,
                'canceled_at' => null,
                'cancel_at_period_end' => false,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            $organization->update(['status' => Organization::STATUS_ACTIVE]);

            return $subscription->fresh();
        });
    }
}
