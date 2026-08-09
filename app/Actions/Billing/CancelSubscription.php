<?php

namespace App\Actions\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CancelSubscription
{
    public function __construct(private BillingGateway $billingGateway) {}

    public function execute(Organization $organization, bool $immediate = true): Subscription
    {
        $subscription = $organization->subscriptionOrFail();

        $this->billingGateway->cancelSubscription($subscription, atPeriodEnd: ! $immediate);

        return DB::transaction(function () use ($organization, $immediate, $subscription): Subscription {
            $subscription->refresh();

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
