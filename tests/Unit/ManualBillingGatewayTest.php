<?php

namespace Tests\Unit;

use App\Billing\ManualBillingGateway;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Tests\TestCase;

class ManualBillingGatewayTest extends TestCase
{
    public function test_manual_gateway_returns_null_for_external_ids(): void
    {
        $gateway = new ManualBillingGateway;
        $organization = Organization::factory()->make();
        $subscription = Subscription::factory()->make();
        $invoice = SubscriptionInvoice::factory()->make();

        $this->assertNull($gateway->createCustomer($organization));
        $this->assertNull($gateway->createSubscription($subscription));
        $this->assertNull($gateway->createInvoice($invoice));
    }

    public function test_manual_gateway_cancel_does_not_throw(): void
    {
        $gateway = new ManualBillingGateway;
        $subscription = Subscription::factory()->make();

        $gateway->cancelSubscription($subscription, atPeriodEnd: true);

        $this->assertTrue(true);
    }
}
