<?php

namespace App\Actions\Billing;

use App\Actions\Notifications\NotifyOrganizationBillingAdmins;
use App\Models\Subscription;
use App\Notifications\SubscriptionTrialEndingNotification;

class NotifyTrialEndingSubscriptions
{
    public function __construct(private NotifyOrganizationBillingAdmins $notifyOrganizationBillingAdmins) {}

    /**
     * @return array{notified: int}
     */
    public function execute(): array
    {
        $notified = 0;
        $thresholds = [3, 1];

        foreach ($thresholds as $days) {
            Subscription::query()
                ->with('organization')
                ->where('status', Subscription::STATUS_TRIALING)
                ->whereNotNull('trial_ends_at')
                ->whereDate('trial_ends_at', now()->addDays($days)->toDateString())
                ->each(function (Subscription $subscription) use ($days, &$notified): void {
                    $this->notifyOrganizationBillingAdmins->execute(
                        $subscription->organization,
                        new SubscriptionTrialEndingNotification($subscription, $days),
                    );

                    $notified++;
                });
        }

        return ['notified' => $notified];
    }
}
