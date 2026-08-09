<?php

namespace Tests\Feature;

use App\Actions\Billing\GenerateSubscriptionInvoice;
use App\Actions\Billing\MarkInvoicePaid;
use App\Actions\Billing\MarkSubscriptionPastDue;
use App\Contracts\Billing\BillingGateway;
use App\Jobs\ProcessBillingWebhook;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsaasBillingWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        config([
            'docflow.billing.driver' => 'asaas',
            'docflow.billing.asaas_api_key' => 'test-asaas-key',
            'docflow.billing.asaas_base_url' => 'https://api-sandbox.asaas.com',
            'docflow.billing.asaas_webhook_secret' => 'asaas-webhook-secret',
            'docflow.billing.asaas_billing_type' => 'BOLETO',
        ]);

        $this->app->forgetInstance(BillingGateway::class);
    }

    public function test_asaas_webhook_with_invalid_secret_is_rejected(): void
    {
        $this->postJson('/webhooks/billing/asaas', [
            'id' => 'evt_invalid',
            'event' => 'PAYMENT_RECEIVED',
        ], [
            'asaas-access-token' => 'wrong-secret',
        ])->assertUnauthorized();
    }

    public function test_asaas_payment_received_marks_invoice_paid_and_is_idempotent(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_PAST_DUE,
            'past_due_at' => now()->subDay(),
            'provider_subscription_id' => 'sub_abc',
        ]);

        $invoice = SubscriptionInvoice::factory()->open()->create([
            'subscription_id' => $organization->subscription->id,
            'organization_id' => $organization->id,
            'provider_invoice_id' => 'pay_001',
            'period_start' => now()->subMonth(),
            'period_end' => now()->addDay(),
            'amount_cents' => 9900,
        ]);

        $payload = [
            'id' => 'evt_asaas_001',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_001',
                'subscription' => 'sub_abc',
                'externalReference' => 'invoice:'.$invoice->id,
                'status' => 'RECEIVED',
                'value' => 99.0,
            ],
        ];

        Queue::fake();

        $this->postJson('/webhooks/billing/asaas', $payload, [
            'asaas-access-token' => 'asaas-webhook-secret',
        ])->assertOk();

        Queue::assertPushed(ProcessBillingWebhook::class);

        (new ProcessBillingWebhook('asaas', 'evt_asaas_001', $payload))
            ->handle(app(MarkInvoicePaid::class), app(MarkSubscriptionPastDue::class));
        (new ProcessBillingWebhook('asaas', 'evt_asaas_001', $payload))
            ->handle(app(MarkInvoicePaid::class), app(MarkSubscriptionPastDue::class));

        $this->assertDatabaseCount('billing_webhook_events', 1);

        $invoice->refresh();
        $organization->refresh();
        $organization->subscription->refresh();

        $this->assertSame(SubscriptionInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $organization->subscription->status);
        $this->assertSame(Organization::STATUS_ACTIVE, $organization->status);
    }

    public function test_generate_invoice_with_asaas_driver_stores_provider_payment_id(): void
    {
        Http::fake([
            'api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_123'], 200),
            'api-sandbox.asaas.com/v3/subscriptions' => Http::response(['id' => 'sub_abc'], 200),
            'api-sandbox.asaas.com/v3/subscriptions/sub_abc/payments*' => Http::response([
                'data' => [
                    ['id' => 'pay_001', 'status' => 'PENDING', 'externalReference' => null, 'value' => 99.0],
                ],
            ], 200),
            'api-sandbox.asaas.com/v3/payments/pay_001' => Http::response(['id' => 'pay_001'], 200),
        ]);

        [$user, $organization] = $this->createAdminContext();

        $organization->update(['document' => '24971563792']);
        $organization->subscription->update([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
            'billing_provider' => Subscription::BILLING_PROVIDER_ASAAS,
        ]);

        $invoice = app(GenerateSubscriptionInvoice::class)->execute($organization->subscription->fresh(['plan', 'organization']));

        $this->assertNotNull($invoice);
        $this->assertSame('pay_001', $invoice->provider_invoice_id);
        $this->assertSame('sub_abc', $organization->subscription->fresh()->provider_subscription_id);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createAdminContext(): array
    {
        $organization = Organization::factory()->create([
            'plan_id' => Plan::query()->where('slug', 'essencial')->value('id'),
            'document' => '24971563792',
        ]);
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }
}
