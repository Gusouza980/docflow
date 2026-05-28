<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_create_individual_client(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->postJson('/api/v1/clients', [
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => 'Maria Silva',
                'document_number' => '123.456.789-01',
                'responsible_member_ids' => [$member->id],
                'individual_profile' => [
                    'full_name' => 'Maria Silva',
                    'rg' => '1234567',
                    'profession' => 'Advogada',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'Maria Silva')
            ->assertJsonPath('data.document_number', '12345678901')
            ->assertJsonPath('data.individual_profile.full_name', 'Maria Silva');

        $this->assertDatabaseHas('clients', [
            'organization_id' => $organization->id,
            'display_name' => 'Maria Silva',
            'document_number' => '12345678901',
            'primary_responsible_member_id' => $member->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'client.created',
        ]);
    }

    public function test_admin_can_create_company_client(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->postJson('/api/v1/clients', [
                'type' => Client::TYPE_COMPANY,
                'display_name' => 'Acme Ltda',
                'document_number' => '12.345.678/0001-90',
                'responsible_member_ids' => [$member->id],
                'company_profile' => [
                    'legal_name' => 'Acme Ltda',
                    'trade_name' => 'Acme',
                    'tax_regime' => 'simples_nacional',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', Client::TYPE_COMPANY)
            ->assertJsonPath('data.company_profile.legal_name', 'Acme Ltda');
    }

    public function test_document_number_must_be_unique_inside_organization(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'document_number' => '12345678901',
        ]);

        $this->withOrganization($organization)
            ->postJson('/api/v1/clients', [
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => 'Duplicate',
                'document_number' => '12345678901',
                'responsible_member_ids' => [$member->id],
                'individual_profile' => ['full_name' => 'Duplicate'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_number');
    }

    public function test_client_requires_at_least_one_responsible_member(): void
    {
        [$admin, $organization] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->postJson('/api/v1/clients', [
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => 'No Responsible',
                'document_number' => '12345678901',
                'individual_profile' => ['full_name' => 'No Responsible'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('responsible_member_ids');
    }

    public function test_clients_are_filtered_and_scoped_to_active_organization(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $otherOrganization = Organization::factory()->create();

        Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'display_name' => 'Visible Client',
            'status' => Client::STATUS_ACTIVE,
        ]);
        Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'display_name' => 'Inactive Client',
            'status' => Client::STATUS_INACTIVE,
        ]);
        Client::factory()->create([
            'organization_id' => $otherOrganization->id,
            'display_name' => 'Other Org Client',
        ]);

        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->getJson('/api/v1/clients?status=active&search=Visible')
            ->assertOk()
            ->assertJsonFragment(['display_name' => 'Visible Client'])
            ->assertJsonMissing(['display_name' => 'Inactive Client'])
            ->assertJsonMissing(['display_name' => 'Other Org Client']);
    }

    public function test_restricted_client_is_visible_only_to_allowed_members_and_managers(): void
    {
        [$admin, $organization, $adminMember] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        [$professional, , $professionalMember] = $this->createMember(OrganizationMember::ROLE_PROFESSIONAL, $organization);
        [$otherProfessional] = $this->createMember(OrganizationMember::ROLE_PROFESSIONAL, $organization);

        $client = Client::factory()->restricted()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $adminMember->id,
            'display_name' => 'Restricted Client',
        ]);
        $client->responsibles()->attach($professionalMember->id, ['is_primary' => false]);

        Sanctum::actingAs($professional);
        $this->withOrganization($organization)
            ->getJson("/api/v1/clients/{$client->id}")
            ->assertOk();

        Sanctum::actingAs($otherProfessional);
        $this->withOrganization($organization)
            ->getJson("/api/v1/clients/{$client->id}")
            ->assertForbidden();

        Sanctum::actingAs($admin);
        $this->withOrganization($organization)
            ->getJson("/api/v1/clients/{$client->id}")
            ->assertOk();
    }

    public function test_status_update_records_audit_event(): void
    {
        [$admin, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'status' => Client::STATUS_ACTIVE,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        Sanctum::actingAs($admin);

        $this->withOrganization($organization)
            ->patchJson("/api/v1/clients/{$client->id}/status", [
                'status' => Client::STATUS_CLOSED,
                'closure_reason' => 'Contract ended',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Client::STATUS_CLOSED);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => 'client.status_updated',
        ]);
    }

    public function test_readonly_member_receives_masked_sensitive_fields(): void
    {
        [, $organization, $adminMember] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        [$readonly] = $this->createMember(OrganizationMember::ROLE_READONLY, $organization);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $adminMember->id,
            'document_number' => '12345678901',
        ]);

        Sanctum::actingAs($readonly);

        $this->withOrganization($organization)
            ->getJson("/api/v1/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.document_number', '*******8901')
            ->assertJsonPath('data.potential_revenue_cents', null);
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

    private function withOrganization(Organization $organization): self
    {
        return $this->withHeader('X-Organization-Id', (string) $organization->id);
    }
}
