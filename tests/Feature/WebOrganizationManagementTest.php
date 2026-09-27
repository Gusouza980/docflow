<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebOrganizationManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_user_without_membership_sees_unassigned_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/plataforma/dashboard')
            ->assertRedirect(route('organizations.unassigned'));

        $this->actingAs($user)
            ->get('/plataforma/clients')
            ->assertRedirect(route('organizations.unassigned'));

        $this->actingAs($user)
            ->get(route('organizations.unassigned'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Organizations/Unassigned', false));
    }

    public function test_tenant_cannot_create_organization_from_web(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/plataforma/organizations', [
                'name' => 'Docflow Web',
                'document' => '12345678901234',
                'email' => 'office@example.com',
                'timezone' => 'America/Sao_Paulo',
            ])
            ->assertMethodNotAllowed();

        $this->assertDatabaseMissing('organizations', [
            'email' => 'office@example.com',
        ]);
    }

    public function test_user_can_switch_active_organization_from_web(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'organization_id' => $first->id,
            'user_id' => $user->id,
        ]);
        OrganizationMember::factory()->create([
            'organization_id' => $second->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $first->id])
            ->post("/plataforma/organizations/{$second->id}/switch")
            ->assertRedirect()
            ->assertSessionHas('active_organization_id', $second->id);
    }

    public function test_organizations_page_exposes_active_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Active Office']);

        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/plataforma/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index', false)
                ->where('activeOrganizationId', $organization->id)
                ->has('organizations', 1)
                ->where('organizations.0.name', 'Active Office')
                ->where('organizations.0.active', true));
    }

    public function test_admin_can_invite_and_suspend_member_from_team_page(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => OrganizationMember::ROLE_ADMIN,
        ]);
        $membership = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => OrganizationMember::ROLE_ASSISTANT,
        ]);

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/plataforma/organization-invitations', [
                'email' => 'invitee@example.com',
                'role' => OrganizationMember::ROLE_ASSISTANT,
            ])
            ->assertRedirect('/plataforma/team');

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'invitee@example.com',
            'status' => OrganizationInvitation::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $organization->id])
            ->patch("/plataforma/organization-members/{$membership->id}/suspend")
            ->assertRedirect('/plataforma/team');

        $this->assertDatabaseHas('organization_members', [
            'id' => $membership->id,
            'status' => OrganizationMember::STATUS_SUSPENDED,
        ]);
    }

    public function test_non_admin_cannot_invite_members_from_team_page(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ASSISTANT,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/plataforma/organization-invitations', [
                'email' => 'invitee@example.com',
                'role' => OrganizationMember::ROLE_ASSISTANT,
            ])
            ->assertForbidden();
    }
}
