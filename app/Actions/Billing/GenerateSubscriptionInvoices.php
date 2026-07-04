<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class GenerateSubscriptionInvoices
{
    public function __construct(
        private GenerateSubscriptionInvoice $generateSubscriptionInvoice,
        private CancelSubscription $cancelSubscription,
    ) {}

    /**
     * @return array{generated: int, canceled: int}
     */
    public function execute(): array
    {
        $generated = 0;
        $canceled = 0;

        Subscription::query()
            ->with(['plan', 'organization'])
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
            ->each(function (Subscription $subscription) use (&$generated, &$canceled): void {
                if ($subscription->cancel_at_period_end
                    && $subscription->current_period_end !== null
                    && $subscription->current_period_end->isPast()) {
                    DB::transaction(function () use ($subscription, &$canceled): void {
                        $this->cancelSubscription->execute($subscription->organization, immediate: true);
                        $canceled++;
                    });

                    return;
                }

                $invoice = $this->generateSubscriptionInvoice->execute($subscription);

                if ($invoice !== null && $invoice->wasRecentlyCreated) {
                    $generated++;
                }
            });

        return ['generated' => $generated, 'canceled' => $canceled];
    }
}
