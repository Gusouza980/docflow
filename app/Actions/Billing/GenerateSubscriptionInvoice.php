<?php

namespace App\Actions\Billing;

use App\Actions\Notifications\NotifyOrganizationBillingAdmins;
use App\Contracts\Billing\BillingGateway;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Notifications\SubscriptionInvoiceIssuedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateSubscriptionInvoice
{
    public function __construct(
        private BillingGateway $billingGateway,
        private NotifyOrganizationBillingAdmins $notifyOrganizationBillingAdmins,
    ) {}

    public function execute(Subscription $subscription): ?SubscriptionInvoice
    {
        return DB::transaction(function () use ($subscription): ?SubscriptionInvoice {
            $subscription->loadMissing(['plan', 'organization']);

            if ($subscription->plan === null) {
                return null;
            }

            [$periodStart, $periodEnd] = $this->resolvePeriod($subscription);

            if ($periodStart === null || $periodEnd === null) {
                return null;
            }

            $existing = SubscriptionInvoice::query()
                ->where('subscription_id', $subscription->id)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $dueDays = (int) config('docflow.billing.invoice_due_days', 7);

            $invoice = SubscriptionInvoice::query()->create([
                'subscription_id' => $subscription->id,
                'organization_id' => $subscription->organization_id,
                'amount_cents' => (int) $subscription->plan->price_cents,
                'currency' => 'BRL',
                'status' => SubscriptionInvoice::STATUS_OPEN,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_at' => $periodStart->copy()->addDays($dueDays),
            ]);

            $providerInvoiceId = $this->billingGateway->createInvoice($invoice);

            if ($providerInvoiceId !== null) {
                $invoice->update(['provider_invoice_id' => $providerInvoiceId]);
            }

            if ($subscription->status === Subscription::STATUS_TRIALING) {
                $subscription->update([
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                ]);
            }

            $this->notifyOrganizationBillingAdmins->execute(
                $subscription->organization,
                new SubscriptionInvoiceIssuedNotification($invoice),
            );

            return $invoice->fresh();
        });
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolvePeriod(Subscription $subscription): array
    {
        if ($subscription->status === Subscription::STATUS_TRIALING
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isPast()) {
            $periodStart = $subscription->trial_ends_at->copy();
            $periodEnd = $periodStart->copy()->addMonth();

            return [$periodStart, $periodEnd];
        }

        if ($subscription->status === Subscription::STATUS_ACTIVE
            && $subscription->current_period_end !== null
            && $subscription->current_period_end->isPast()) {
            $periodStart = $subscription->current_period_end->copy();
            $periodEnd = $periodStart->copy()->addMonth();

            return [$periodStart, $periodEnd];
        }

        return [null, null];
    }
}
