<?php

namespace App\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;

class ManualBillingGateway implements BillingGateway
{
    public function createCustomer(Organization $organization): ?string
    {
        return null;
    }

    public function createSubscription(Subscription $subscription, ?string $nextDueDate = null): ?string
    {
        return null;
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = false): void
    {
        // Manual billing does not call external APIs.
    }

    public function createInvoice(SubscriptionInvoice $invoice): ?string
    {
        return null;
    }
}
