<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationPaymentGateway;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\ReceivableCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantReceivablePaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'docflow.billing.asaas_api_key' => 'saas-asaas-key',
            'docflow.billing.asaas_webhook_secret' => 'saas-webhook-secret',
            'docflow.billing.asaas_base_url' => 'https://api-sandbox.asaas.com',
        ]);
    }

    public function test_admin_can_save_tenant_asaas_gateway_without_exposing_secrets(): void
    {
        [$user, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->put("/organizations/{$organization->id}/payment-gateway", [
                'api_key' => 'tenant-asaas-key-9999',
                'webhook_token' => 'tenant-webhook-9999',
            ])
            ->assertRedirect('/organizations');

        $gateway = $organization->fresh()->paymentGateway;
        $this->assertNotNull($gateway);
        $this->assertSame('tenant-asaas-key-9999', $gateway->api_key);
        $this->assertSame('tenant-webhook-9999', $gateway->webhook_token);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index', false)
                ->where('organizations.0.payment_gateway.connected', true)
                ->where('organizations.0.payment_gateway.masked_api_key', '••••9999')
                ->missing('organizations.0.payment_gateway.api_key')
                ->missing('organizations.0.payment_gateway.webhook_token'));
    }

    public function test_finance_generates_pix_with_tenant_key_not_saas_key(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_tenant'], 200),
            'https://api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_tenant',
                'invoiceUrl' => 'https://asaas.test/i/pay_tenant',
            ], 200),
            'https://api-sandbox.asaas.com/v3/payments/pay_tenant/pixQrCode' => Http::response([
                'payload' => '00020126580014br.gov.bcb.pix',
                'encodedImage' => 'abc123',
            ], 200),
        ]);

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $this->connectGateway($organization);
        $client = $this->createClient($organization, $member);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 150000,
            'status' => Receivable::STATUS_OPEN,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$receivable->id}/charge", [
                'billing_type' => 'PIX',
            ])
            ->assertRedirect('/finance');

        $charge = ReceivableCharge::query()->where('receivable_id', $receivable->id)->first();
        $this->assertNotNull($charge);
        $this->assertSame('pay_tenant', $charge->provider_payment_id);
        $this->assertSame('00020126580014br.gov.bcb.pix', $charge->pix_payload);
        $this->assertSame('https://asaas.test/i/pay_tenant', $receivable->fresh()->payment_url);
        $this->assertSame('cus_tenant', $client->fresh()->asaas_customer_id);

        Http::assertSent(fn ($request): bool => $request->hasHeader('access_token', 'tenant-asaas-key-0001'));
        Http::assertNotSent(fn ($request): bool => $request->hasHeader('access_token', 'saas-asaas-key'));
    }

    public function test_generate_charge_is_idempotent_and_requires_client_document(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_1'], 200),
            'https://api-sandbox.asaas.com/v3/payments' => Http::response(['id' => 'pay_1', 'invoiceUrl' => 'https://asaas.test/i/1'], 200),
            'https://api-sandbox.asaas.com/v3/payments/pay_1/pixQrCode' => Http::response(['payload' => 'pix', 'encodedImage' => 'img'], 200),
        ]);

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $this->connectGateway($organization);
        $client = $this->createClient($organization, $member);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$receivable->id}/charge")
            ->assertRedirect('/finance');

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$receivable->id}/charge")
            ->assertRedirect('/finance');

        $this->assertSame(1, ReceivableCharge::query()->where('receivable_id', $receivable->id)->count());

        $clientWithoutDocument = $this->createClient($organization, $member);
        $clientWithoutDocument->update(['document_number' => '']);
        $blocked = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $clientWithoutDocument->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$blocked->id}/charge")
            ->assertRedirect('/finance')
            ->assertSessionHas('error', 'Cadastre o CPF/CNPJ do cliente antes de gerar o Pix.');

        $this->assertDatabaseMissing('receivable_charges', ['receivable_id' => $blocked->id]);
    }

    public function test_assistant_cannot_generate_charge_or_save_gateway(): void
    {
        [$assistant, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ASSISTANT);
        $this->connectGateway($organization);
        $client = $this->createClient($organization, $member);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $assistant->id,
        ]);

        $this->actingAs($assistant)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/finance/receivables/{$receivable->id}/charge")
            ->assertForbidden();

        $this->actingAs($assistant)
            ->withSession(['active_organization_id' => $organization->id])
            ->put("/organizations/{$organization->id}/payment-gateway", [
                'api_key' => 'stolen',
                'webhook_token' => 'stolen',
            ])
            ->assertForbidden();
    }

    public function test_portal_shows_pay_now_when_charge_exists(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'status' => Receivable::STATUS_OPEN,
        ]);
        ReceivableCharge::factory()->create([
            'organization_id' => $organization->id,
            'receivable_id' => $receivable->id,
            'client_id' => $client->id,
            'pix_payload' => '00020126pix-copia-e-cola',
        ]);

        $this->actingAs($access, 'portal')
            ->get('/client-portal/finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Finance', false)
                ->where('receivables.0.can_pay', true)
                ->where('receivables.0.charge.pix_payload', '00020126pix-copia-e-cola'));
    }

    public function test_tenant_webhook_marks_receivable_paid_once(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $this->connectGateway($organization);
        $client = $this->createClient($organization, $member);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 150000,
            'status' => Receivable::STATUS_OPEN,
        ]);
        ReceivableCharge::factory()->create([
            'organization_id' => $organization->id,
            'receivable_id' => $receivable->id,
            'client_id' => $client->id,
            'provider_payment_id' => 'pay_live',
        ]);

        $payload = [
            'id' => 'evt_tenant_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_live',
                'value' => 1500.00,
                'externalReference' => 'receivable:'.$receivable->id,
            ],
        ];

        $this->postJson("/webhooks/tenant/asaas/{$organization->id}", $payload, [
            'asaas-access-token' => 'tenant-webhook-0001',
        ])->assertOk();

        $this->postJson("/webhooks/tenant/asaas/{$organization->id}", $payload, [
            'asaas-access-token' => 'tenant-webhook-0001',
        ])->assertOk();

        $this->assertSame(Receivable::STATUS_PAID, $receivable->fresh()->status);
        $this->assertSame(1, Payment::query()->where('receivable_id', $receivable->id)->count());
        $this->assertNull(Payment::query()->where('receivable_id', $receivable->id)->value('received_by_user_id'));
    }

    public function test_tenant_webhook_rejects_saas_secret_and_ignores_unknown_payment(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_FINANCE);
        $this->connectGateway($organization);
        $client = $this->createClient($organization, $member);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'status' => Receivable::STATUS_OPEN,
        ]);

        $this->postJson("/webhooks/tenant/asaas/{$organization->id}", [
            'id' => 'evt_bad',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_saas', 'value' => 99, 'externalReference' => 'invoice:1'],
        ], [
            'asaas-access-token' => 'saas-webhook-secret',
        ])->assertUnauthorized();

        $this->postJson("/webhooks/tenant/asaas/{$organization->id}", [
            'id' => 'evt_unknown',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_saas_invoice', 'value' => 99, 'externalReference' => 'invoice:1'],
        ], [
            'asaas-access-token' => 'tenant-webhook-0001',
        ])->assertOk();

        $this->assertSame(Receivable::STATUS_OPEN, $receivable->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createMember(string $role, ?Organization $organization = null): array
    {
        $organization ??= Organization::factory()->create();
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }

    private function createClient(Organization $organization, OrganizationMember $member): Client
    {
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'access_policy' => Client::ACCESS_ALL_MEMBERS,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        return $client;
    }

    private function createPortalAccess(Organization $organization, Client $client, User $user): ClientPortalAccess
    {
        return ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'portal-pay@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password' => Hash::make('password123'),
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
            'status' => ClientPortalAccess::STATUS_ACTIVE,
        ]);
    }

    private function connectGateway(Organization $organization): OrganizationPaymentGateway
    {
        return OrganizationPaymentGateway::factory()->create([
            'organization_id' => $organization->id,
            'api_key' => 'tenant-asaas-key-0001',
            'webhook_token' => 'tenant-webhook-0001',
        ]);
    }
}
