<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CancelSubscription
{
    public function execute(Organization $organization, bool $immediate = true): Subscription
    {
        return DB::transaction(function () use ($organization, $immediate): Subscription {
            $subscription = $organization->subscriptionOrFail();

            if ($immediate) {
                $subscription->update([
                    'status' => Subscription::STATUS_CANCELED,
                    'canceled_at' => now(),
                    'cancel_at_period_end' => false,
                ]);

                $organization->update(['status' => Organization::STATUS_SUSPENDED]);
            } else {
                $subscription->update(['cancel_at_period_end' => true]);
            }

            return $subscription->fresh();
        });
    }
}
