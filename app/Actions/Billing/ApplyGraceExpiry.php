<?php

namespace App\Actions\Billing;

use App\Actions\Notifications\NotifyOrganizationBillingAdmins;
use App\Models\Organization;
use App\Models\Subscription;
use App\Notifications\SubscriptionSuspendedNotification;
use Illuminate\Support\Facades\DB;

class ApplyGraceExpiry
{
    public function __construct(private NotifyOrganizationBillingAdmins $notifyOrganizationBillingAdmins) {}

    /**
     * @return array{expired: int}
     */
    public function execute(): array
    {
        $graceDays = (int) config('docflow.subscription.grace_days', 7);
        $expired = 0;

        Subscription::query()
            ->with('organization')
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('past_due_at')
            ->each(function (Subscription $subscription) use ($graceDays, &$expired): void {
                $graceEndsAt = $subscription->past_due_at?->copy()->addDays($graceDays);

                if ($graceEndsAt === null || $graceEndsAt->isFuture()) {
                    return;
                }

                DB::transaction(function () use ($subscription, &$expired): void {
                    if ($subscription->status !== Subscription::STATUS_PAST_DUE) {
                        return;
                    }

                    if ($subscription->onGracePeriod()) {
                        return;
                    }

                    $subscription->update([
                        'status' => Subscription::STATUS_CANCELED,
                        'canceled_at' => now(),
                    ]);

                    $subscription->organization?->update([
                        'status' => Organization::STATUS_SUSPENDED,
                    ]);

                    if ($subscription->organization) {
                        $this->notifyOrganizationBillingAdmins->execute(
                            $subscription->organization,
                            new SubscriptionSuspendedNotification($subscription->organization),
                        );
                    }

                    $expired++;
                });
            });

        return ['expired' => $expired];
    }
}
