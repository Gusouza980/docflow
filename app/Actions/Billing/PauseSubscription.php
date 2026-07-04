<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class PauseSubscription
{
    public function execute(Organization $organization): Subscription
    {
        return DB::transaction(function () use ($organization): Subscription {
            $subscription = $organization->subscriptionOrFail();

            $subscription->update(['status' => Subscription::STATUS_PAUSED]);

            return $subscription->fresh();
        });
    }
}
