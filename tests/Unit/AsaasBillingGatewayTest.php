<?php

namespace Tests\Unit;

use App\Billing\Asaas\AsaasClient;
use App\Billing\AsaasBillingGateway;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasBillingGatewayTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'docflow.billing.driver' => 'asaas',
            'docflow.billing.asaas_api_key' => 'test-asaas-key',
            'docflow.billing.asaas_base_url' => 'https://api-sandbox.asaas.com',
            'docflow.billing.asaas_billing_type' => 'BOLETO',
        ]);
    }

    public function test_create_customer_posts_to_asaas_and_returns_id(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_123'], 200),
        ]);

        $organization = Organization::factory()->make([
            'id' => 10,
            'name' => 'Escritorio Teste',
            'document' => '24971563792',
            'email' => 'billing@example.test',
            'phone' => '47999999999',
        ]);

        $gateway = new AsaasBillingGateway(new AsaasClient);
        $customerId = $gateway->createCustomer($organization);

        $this->assertSame('cus_123', $customerId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-sandbox.asaas.com/v3/customers'
                && $request->hasHeader('access_token', 'test-asaas-key')
                && $request['cpfCnpj'] === '24971563792'
                && $request['externalReference'] === 'organization:10';
        });
    }

    public function test_create_subscription_creates_native_asaas_subscription(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/subscriptions' => Http::response(['id' => 'sub_abc'], 200),
        ]);

        $plan = Plan::factory()->create([
            'price_cents' => 9900,
            'billing_interval' => 'month',
            'name' => 'Essencial',
        ]);

        $organization = Organization::factory()->create([
            'document' => '24971563792',
            'plan_id' => $plan->id,
        ]);

        $subscription = $organization->subscription;
        $subscription->update([
            'plan_id' => $plan->id,
            'provider_customer_id' => 'cus_123',
            'current_period_end' => now()->addDay(),
        ]);

        $gateway = new AsaasBillingGateway(new AsaasClient);
        $providerSubscriptionId = $gateway->createSubscription($subscription->fresh(['organization', 'plan']));

        $this->assertSame('sub_abc', $providerSubscriptionId);
        $this->assertSame('sub_abc', $subscription->fresh()->provider_subscription_id);
        $this->assertSame(Subscription::BILLING_PROVIDER_ASAAS, $subscription->fresh()->billing_provider);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/v3/subscriptions')
                && $request['customer'] === 'cus_123'
                && $request['cycle'] === 'MONTHLY'
                && $request['value'] === 99.0;
        });
    }

    public function test_create_invoice_links_pending_payment_id(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/subscriptions/sub_abc/payments*' => Http::response([
                'data' => [
                    ['id' => 'pay_001', 'status' => 'PENDING', 'externalReference' => null],
                ],
            ], 200),
            'api-sandbox.asaas.com/v3/payments/pay_001' => Http::response(['id' => 'pay_001'], 200),
        ]);

        $plan = Plan::factory()->create(['price_cents' => 9900]);
        $organization = Organization::factory()->create([
            'document' => '24971563792',
            'plan_id' => $plan->id,
        ]);

        $subscription = $organization->subscription;
        $subscription->update([
            'plan_id' => $plan->id,
            'provider_customer_id' => 'cus_123',
            'provider_subscription_id' => 'sub_abc',
        ]);

        $invoice = SubscriptionInvoice::factory()->open()->create([
            'subscription_id' => $subscription->id,
            'organization_id' => $organization->id,
            'amount_cents' => 9900,
        ]);

        $gateway = new AsaasBillingGateway(new AsaasClient);
        $paymentId = $gateway->createInvoice($invoice->fresh(['subscription.plan', 'subscription.organization']));

        $this->assertSame('pay_001', $paymentId);
    }

    public function test_cancel_at_period_end_marks_asaas_subscription_inactive(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/subscriptions/sub_abc' => Http::response(['id' => 'sub_abc', 'status' => 'INACTIVE'], 200),
        ]);

        $subscription = Subscription::factory()->make([
            'provider_subscription_id' => 'sub_abc',
        ]);

        $gateway = new AsaasBillingGateway(new AsaasClient);
        $gateway->cancelSubscription($subscription, atPeriodEnd: true);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_ends_with($request->url(), '/v3/subscriptions/sub_abc')
                && $request['status'] === 'INACTIVE';
        });
    }
}
