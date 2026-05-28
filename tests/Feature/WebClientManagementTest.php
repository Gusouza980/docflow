<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientTag;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebClientManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_clients_page_lists_only_active_organization_clients(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $otherOrganization = Organization::factory()->create();

        Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'display_name' => 'Visible Client',
        ]);
        Client::factory()->create([
            'organization_id' => $otherOrganization->id,
            'display_name' => 'Hidden Client',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index', false)
                ->has('clients.data', 1)
                ->where('clients.data.0.display_name', 'Visible Client'));
    }

    public function test_admin_can_create_client_from_web(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/clients', [
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => 'Maria Silva',
                'document_number' => '123.456.789-01',
                'status' => Client::STATUS_ACTIVE,
                'priority' => Client::PRIORITY_NORMAL,
                'risk_level' => Client::RISK_LOW,
                'access_policy' => Client::ACCESS_ALL_MEMBERS,
                'responsible_member_ids' => [$member->id],
                'individual_profile' => [
                    'full_name' => 'Maria Silva',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'organization_id' => $organization->id,
            'display_name' => 'Maria Silva',
            'document_number' => '12345678901',
            'primary_responsible_member_id' => $member->id,
        ]);
    }

    public function test_client_show_page_renders_profile_contacts_tags_and_timeline(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'display_name' => 'Acme Ltda',
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get("/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show', false)
                ->where('client.display_name', 'Acme Ltda')
                ->has('client.responsibles', 1));
    }

    public function test_admin_can_add_contact_and_tag_from_web(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/contacts", [
                'name' => 'Ana Financeiro',
                'email' => 'ana@example.com',
                'type' => 'financial',
                'is_primary' => true,
            ])
            ->assertRedirect("/clients/{$client->id}");

        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'name' => 'Ana Financeiro',
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/client-tags', [
                'name' => 'VIP',
                'color' => '#0f766e',
            ])
            ->assertRedirect();

        $tag = ClientTag::firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post("/clients/{$client->id}/tags/{$tag->id}")
            ->assertRedirect("/clients/{$client->id}");

        $this->assertDatabaseHas('client_tag', [
            'client_id' => $client->id,
            'client_tag_id' => $tag->id,
        ]);
    }

    public function test_dashboard_page_exposes_client_metrics(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);

        Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'status' => Client::STATUS_ACTIVE,
        ]);
        Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
            'risk_level' => Client::RISK_HIGH,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('metrics.active', 2)
                ->where('metrics.high_risk', 1));
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
}
