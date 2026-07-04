<?php

namespace App\Actions\Billing;

use App\Actions\Notifications\NotifyOrganizationBillingAdmins;
use App\Actions\Platform\RecordPlatformAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Notifications\SubscriptionPaymentConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarkInvoicePaid
{
    public function __construct(
        private ActivateSubscription $activateSubscription,
        private NotifyOrganizationBillingAdmins $notifyOrganizationBillingAdmins,
    ) {}

    public function execute(
        SubscriptionInvoice $invoice,
        ?User $platformAdmin = null,
        ?Request $request = null,
        ?RecordPlatformAuditLog $recordPlatformAuditLog = null,
        ?string $paymentMethod = 'manual',
    ): SubscriptionInvoice {
        return DB::transaction(function () use ($invoice, $platformAdmin, $request, $recordPlatformAuditLog, $paymentMethod): SubscriptionInvoice {
            $invoice->loadMissing(['subscription.organization', 'subscription.plan']);

            if ($invoice->status === SubscriptionInvoice::STATUS_PAID) {
                return $invoice;
            }

            $invoice->update([
                'status' => SubscriptionInvoice::STATUS_PAID,
                'paid_at' => now(),
                'payment_method' => $paymentMethod,
            ]);

            $subscription = $invoice->subscription;
            $organization = $subscription->organization;

            $this->activateSubscription->execute($organization);

            $subscription->refresh()->update([
                'current_period_start' => $invoice->period_start,
                'current_period_end' => $invoice->period_end,
                'cancel_at_period_end' => false,
            ]);

            $this->applyPendingPlanChange($subscription);

            $this->notifyOrganizationBillingAdmins->execute(
                $organization,
                new SubscriptionPaymentConfirmedNotification($invoice),
            );

            if ($platformAdmin !== null && $recordPlatformAuditLog !== null) {
                $recordPlatformAuditLog->execute(
                    action: 'platform.invoice.marked_paid',
                    platformAdmin: $platformAdmin,
                    subject: $invoice,
                    metadata: ['invoice_id' => $invoice->id, 'organization_id' => $organization->id],
                    request: $request,
                );
            }

            return $invoice->fresh();
        });
    }

    private function applyPendingPlanChange(Subscription $subscription): void
    {
        $pendingPlanId = $subscription->metadata['pending_plan_id'] ?? null;

        if (! $pendingPlanId) {
            return;
        }

        app(ChangeOrganizationPlan::class)->execute(
            $subscription->organization,
            Plan::query()->findOrFail($pendingPlanId),
        );

        $metadata = $subscription->metadata ?? [];
        unset($metadata['pending_plan_id'], $metadata['pending_plan_effective_at']);

        $subscription->update(['metadata' => $metadata]);
    }
}
