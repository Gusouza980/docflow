<?php

namespace App\Actions\Billing;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\DB;

class ExpireTrials
{
    /**
     * @return array{expired: int}
     */
    public function execute(): array
    {
        $expired = 0;

        Subscription::query()
            ->with('organization')
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereDoesntHave('invoices', function ($query): void {
                $query->whereIn('status', [
                    SubscriptionInvoice::STATUS_OPEN,
                    SubscriptionInvoice::STATUS_PAID,
                ]);
            })
            ->each(function (Subscription $subscription) use (&$expired): void {
                DB::transaction(function () use ($subscription, &$expired): void {
                    if ($subscription->status !== Subscription::STATUS_TRIALING) {
                        return;
                    }

                    if ($subscription->trial_ends_at === null || $subscription->trial_ends_at->isFuture()) {
                        return;
                    }

                    $subscription->update([
                        'status' => Subscription::STATUS_CANCELED,
                        'canceled_at' => now(),
                    ]);

                    $subscription->organization?->update([
                        'status' => Organization::STATUS_SUSPENDED,
                    ]);

                    $expired++;
                });
            });

        return ['expired' => $expired];
    }
}
