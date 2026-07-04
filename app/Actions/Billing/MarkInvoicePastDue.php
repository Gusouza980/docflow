<?php

namespace App\Actions\Billing;

use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\DB;

class MarkInvoicePastDue
{
    public function __construct(private MarkSubscriptionPastDue $markSubscriptionPastDue) {}

    public function execute(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        return DB::transaction(function () use ($invoice): SubscriptionInvoice {
            $invoice->loadMissing('subscription.organization');

            if (! $invoice->isOpen() || ! $invoice->isOverdue()) {
                return $invoice;
            }

            $this->markSubscriptionPastDue->execute($invoice->subscription->organization);

            return $invoice->fresh();
        });
    }
}
