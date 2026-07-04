<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class ExtendTrial
{
    public function execute(Organization $organization, int $days): Subscription
    {
        return DB::transaction(function () use ($organization, $days): Subscription {
            $subscription = $organization->subscriptionOrFail();

            $baseDate = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
                ? $subscription->trial_ends_at
                : now();

            $trialEndsAt = $baseDate->copy()->addDays($days);

            $subscription->update([
                'status' => Subscription::STATUS_TRIALING,
                'trial_ends_at' => $trialEndsAt,
                'current_period_end' => $trialEndsAt,
                'canceled_at' => null,
                'past_due_at' => null,
            ]);

            if ($organization->status !== Organization::STATUS_ACTIVE) {
                $organization->update(['status' => Organization::STATUS_ACTIVE]);
            }

            return $subscription->fresh();
        });
    }
}
