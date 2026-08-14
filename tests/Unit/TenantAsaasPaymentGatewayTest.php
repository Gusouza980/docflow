<?php

namespace Tests\Unit;

use App\Billing\Asaas\AsaasClient;
use App\Billing\TenantAsaasPaymentGateway;
use App\Exceptions\AsaasApiException;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationPaymentGateway;
use App\Models\Receivable;
use App\Models\ReceivableCharge;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class TenantAsaasPaymentGatewayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_for_tenant_never_falls_back_to_platform_key(): void
    {
        config(['docflow.billing.asaas_api_key' => 'saas-asaas-key']);

        $this->expectException(AsaasApiException::class);
        AsaasClient::forTenant('');
    }

    public function test_create_payment_posts_receivable_external_reference(): void
    {
        config(['docflow.billing.asaas_base_url' => 'https://api-sandbox.asaas.com']);

        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_99',
                'invoiceUrl' => 'https://asaas.test/i/99',
            ], 200),
            'https://api-sandbox.asaas.com/v3/payments/pay_99/pixQrCode' => Http::response([
                'payload' => 'pix-payload',
                'encodedImage' => 'qr',
            ], 200),
        ]);

        $organization = Organization::factory()->create();
        $member = OrganizationMember::factory()->create(['organization_id' => $organization->id]);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $receivable = Receivable::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'amount_cents' => 25000,
        ]);
        $gateway = OrganizationPaymentGateway::factory()->create([
            'organization_id' => $organization->id,
            'api_key' => 'org-key',
        ]);

        $result = (new TenantAsaasPaymentGateway)->createPayment(
            $gateway,
            $receivable,
            'cus_1',
            ReceivableCharge::TYPE_PIX,
        );

        $this->assertSame('pay_99', $result['provider_payment_id']);
        $this->assertSame('pix-payload', $result['pix_payload']);

        Http::assertSent(function ($request) use ($receivable): bool {
            return str_ends_with($request->url(), '/v3/payments')
                && $request->hasHeader('access_token', 'org-key')
                && $request['externalReference'] === 'receivable:'.$receivable->id
                && $request['value'] === 250.0;
        });
    }

    public function test_ensure_customer_requires_document(): void
    {
        $organization = Organization::factory()->create();
        $member = OrganizationMember::factory()->create(['organization_id' => $organization->id]);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'document_number' => '123',
        ]);
        $gateway = OrganizationPaymentGateway::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cadastre o CPF/CNPJ do cliente');

        (new TenantAsaasPaymentGateway)->ensureCustomer($gateway, $client);
    }
}
