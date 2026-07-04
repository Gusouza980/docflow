<?php

namespace App\Actions\Billing;

use App\Models\SubscriptionInvoice;

class MarkOverdueSubscriptionInvoices
{
    public function __construct(private MarkInvoicePastDue $markInvoicePastDue) {}

    /**
     * @return array{marked: int}
     */
    public function execute(): array
    {
        $marked = 0;

        SubscriptionInvoice::query()
            ->with('subscription.organization')
            ->where('status', SubscriptionInvoice::STATUS_OPEN)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->each(function (SubscriptionInvoice $invoice) use (&$marked): void {
                $this->markInvoicePastDue->execute($invoice);
                $marked++;
            });

        return ['marked' => $marked];
    }
}
