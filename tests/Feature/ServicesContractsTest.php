<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientService;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\ReceivableRecurrence;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicesContractsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_admin_can_create_service_type_isolated_by_organization(): void
    {
        [$user, $organization] = $this->createContext();
        [$otherUser, $otherOrganization] = $this->createContext();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/service-types', [
                'name' => 'BPO Contábil',
                'default_amount_cents' => '1.500,00',
                'default_billing_interval' => ServiceType::BILLING_MONTH,
            ])
            ->assertRedirect(route('service-types.index'));

        $type = ServiceType::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('BPO Contábil', $type->name);
        $this->assertSame(150000, $type->default_amount_cents);

        $this->actingAs($otherUser)
            ->withSession(['active_organization_id' => $otherOrganization->id])
            ->get('/service-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ServiceTypes/Index', false)
                ->where('serviceTypes', []));
    }

    public function test_can_link_service_to_client(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $type = ServiceType::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/services", [
                'service_type_id' => $type->id,
                'status' => ClientService::STATUS_ACTIVE,
                'starts_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('client_services', [
            'client_id' => $client->id,
            'service_type_id' => $type->id,
            'status' => ClientService::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/clients/{$client->id}?tab=services")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show', false)
                ->has('hub.services', 1));
    }

    public function test_can_create_active_contract_with_period(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/contracts', [
                'client_id' => $client->id,
                'code' => 'CTR-1001',
                'status' => Contract::STATUS_ACTIVE,
                'amount_cents' => '2.500,00',
                'billing_interval' => Contract::BILLING_MONTH,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $contract = Contract::query()->where('code', 'CTR-1001')->firstOrFail();
        $this->assertSame(Contract::STATUS_ACTIVE, $contract->status);
        $this->assertSame(250000, $contract->amount_cents);
        $this->assertNotNull($contract->ends_at);
        $this->assertNull($contract->receivableRecurrence);
    }

    public function test_active_monthly_contract_with_flag_creates_receivable_recurrence(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/contracts', [
                'client_id' => $client->id,
                'code' => 'CTR-REC-1',
                'status' => Contract::STATUS_ACTIVE,
                'amount_cents' => '1.200,00',
                'billing_interval' => Contract::BILLING_MONTH,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
                'create_receivable_recurrence' => true,
            ])
            ->assertRedirect();

        $contract = Contract::query()->where('code', 'CTR-REC-1')->firstOrFail();
        $recurrence = ReceivableRecurrence::query()->where('contract_id', $contract->id)->first();

        $this->assertNotNull($recurrence);
        $this->assertSame($client->id, $recurrence->client_id);
        $this->assertSame($organization->id, $recurrence->organization_id);
        $this->assertSame(120000, $recurrence->amount_cents);
        $this->assertSame(ReceivableRecurrence::FREQUENCY_MONTHLY, $recurrence->frequency);
        $this->assertTrue($recurrence->is_active);
        $this->assertSame(1, ReceivableRecurrence::query()->where('contract_id', $contract->id)->count());
    }

    public function test_once_contract_with_flag_does_not_create_recurrence(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/contracts', [
                'client_id' => $client->id,
                'code' => 'CTR-ONCE',
                'status' => Contract::STATUS_ACTIVE,
                'amount_cents' => '3.000,00',
                'billing_interval' => Contract::BILLING_ONCE,
                'starts_at' => now()->toDateString(),
                'create_receivable_recurrence' => true,
            ])
            ->assertRedirect();

        $contract = Contract::query()->where('code', 'CTR-ONCE')->firstOrFail();
        $this->assertNull($contract->receivableRecurrence);
    }

    public function test_renew_extends_existing_recurrence_end_date(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $endsAt = now()->addMonth()->startOfDay();
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Contract::STATUS_ACTIVE,
            'amount_cents' => 200000,
            'billing_interval' => Contract::BILLING_MONTH,
            'ends_at' => $endsAt->toDateString(),
        ]);
        $recurrence = ReceivableRecurrence::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'created_by_user_id' => $user->id,
            'amount_cents' => 200000,
            'end_date' => $endsAt->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/contracts/{$contract->id}/renew")
            ->assertRedirect();

        $this->assertTrue($recurrence->fresh()->end_date->gt($endsAt));
        $this->assertTrue($recurrence->fresh()->is_active);
        $this->assertSame(1, ReceivableRecurrence::query()->where('contract_id', $contract->id)->count());
    }

    public function test_renew_with_flag_creates_missing_recurrence(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'code' => 'CTR-RENEW-REC',
            'status' => Contract::STATUS_ACTIVE,
            'amount_cents' => 90000,
            'billing_interval' => Contract::BILLING_MONTH,
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/contracts/{$contract->id}/renew", [
                'create_receivable_recurrence' => true,
            ])
            ->assertRedirect();

        $recurrence = ReceivableRecurrence::query()->where('contract_id', $contract->id)->first();
        $this->assertNotNull($recurrence);
        $this->assertSame(90000, $recurrence->amount_cents);
        $this->assertTrue($recurrence->is_active);
    }

    public function test_cancel_pauses_linked_recurrence(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Contract::STATUS_ACTIVE,
            'amount_cents' => 150000,
            'billing_interval' => Contract::BILLING_MONTH,
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $recurrence = ReceivableRecurrence::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'created_by_user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/contracts/{$contract->id}/cancel", [
                'cancel_reason' => 'Cliente pediu encerramento',
            ])
            ->assertRedirect();

        $this->assertFalse($recurrence->fresh()->is_active);
        $this->assertSame(Contract::STATUS_CANCELED, $contract->fresh()->status);
    }

    public function test_assistant_flag_does_not_create_recurrence(): void
    {
        [$user, $organization, $member] = $this->createContext(OrganizationMember::ROLE_ASSISTANT);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'access_policy' => Client::ACCESS_ALL_MEMBERS,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/contracts', [
                'client_id' => $client->id,
                'code' => 'CTR-ASSIST',
                'status' => Contract::STATUS_ACTIVE,
                'amount_cents' => '800,00',
                'billing_interval' => Contract::BILLING_MONTH,
                'starts_at' => now()->toDateString(),
                'create_receivable_recurrence' => true,
            ])
            ->assertRedirect();

        $contract = Contract::query()->where('code', 'CTR-ASSIST')->firstOrFail();
        $this->assertNull($contract->receivableRecurrence);
    }

    public function test_renew_extends_ends_at(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $endsAt = now()->addMonth()->startOfDay();
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Contract::STATUS_ACTIVE,
            'billing_interval' => Contract::BILLING_MONTH,
            'ends_at' => $endsAt->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/contracts/{$contract->id}/renew")
            ->assertRedirect();

        $this->assertTrue($contract->fresh()->ends_at->gt($endsAt));
        $this->assertSame(Contract::STATUS_ACTIVE, $contract->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'web.contract.renewed',
            'auditable_id' => $contract->id,
        ]);
    }

    public function test_lists_contracts_expiring_within_30_days(): void
    {
        [$user, $organization, $member] = $this->createContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'code' => 'CTR-SOON',
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => now()->addDays(10)->toDateString(),
        ]);

        Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'code' => 'CTR-LATER',
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => now()->addDays(90)->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/contracts?expiring_soon=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Index', false)
                ->has('contracts', 1)
                ->where('contracts.0.code', 'CTR-SOON'));
    }

    public function test_assistant_cannot_renew_or_cancel_contract(): void
    {
        [$user, $organization, $member] = $this->createContext(OrganizationMember::ROLE_ASSISTANT);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/contracts/{$contract->id}/renew")
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/contracts/{$contract->id}/cancel")
            ->assertForbidden();
    }

    public function test_assistant_cannot_manage_service_types(): void
    {
        [$user, $organization] = $this->createContext(OrganizationMember::ROLE_ASSISTANT);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/service-types')
            ->assertForbidden();
    }

    public function test_professional_cannot_list_contracts_of_restricted_clients(): void
    {
        [$admin, $organization, $adminMember] = $this->createContext();
        $professional = User::factory()->create();
        $professionalMember = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $professional->id,
            'role' => OrganizationMember::ROLE_PROFESSIONAL,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        $restrictedClient = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $adminMember->id,
            'access_policy' => Client::ACCESS_RESTRICTED,
        ]);
        $openClient = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $professionalMember->id,
            'access_policy' => Client::ACCESS_ALL_MEMBERS,
        ]);

        Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $restrictedClient->id,
            'code' => 'CTR-RESTRICTED',
            'status' => Contract::STATUS_ACTIVE,
        ]);
        Contract::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $openClient->id,
            'code' => 'CTR-OPEN',
            'status' => Contract::STATUS_ACTIVE,
        ]);

        $this->actingAs($professional)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/contracts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Index', false)
                ->has('contracts', 1)
                ->where('contracts.0.code', 'CTR-OPEN'));

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/contracts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Index', false)
                ->has('contracts', 2));
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createContext(string $role = OrganizationMember::ROLE_ADMIN): array
    {
        $planId = Plan::query()->where('slug', 'profissional')->value('id');
        $organization = Organization::factory()->create(['plan_id' => $planId]);
        $organization->subscription?->update(['plan_id' => $planId]);

        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }
}
