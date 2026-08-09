<?php

namespace App\Contracts\Billing;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;

interface BillingGateway
{
    public function createCustomer(Organization $organization): ?string;

    public function createSubscription(Subscription $subscription, ?string $nextDueDate = null): ?string;

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = false): void;

    public function createInvoice(SubscriptionInvoice $invoice): ?string;
}
