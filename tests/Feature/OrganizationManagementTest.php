<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_cannot_create_organization_via_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizations', [
            'name' => 'Docflow Office',
            'document' => '12345678901234',
            'email' => 'office@example.com',
        ])->assertMethodNotAllowed();

        $this->assertDatabaseMissing('organizations', [
            'email' => 'office@example.com',
        ]);
    }

    public function test_platform_admin_cannot_login_via_tenant_api(): void
    {
        User::factory()->create([
            'email' => 'platform@docflow.test',
            'password' => 'password',
            'is_platform_admin' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'platform@docflow.test',
            'password' => 'password',
            'device_name' => 'Mobile',
        ])->assertUnprocessable();
    }

    public function test_platform_admin_cannot_use_tenant_api_even_with_token(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/organizations')
            ->assertForbidden()
            ->assertJsonPath('message', 'Platform administrators cannot use the tenant API.');
    }

    public function test_user_only_lists_organizations_where_membership_is_active(): void
    {
        $user = User::factory()->create();
        $visible = Organization::factory()->create(['name' => 'Visible']);
        $hidden = Organization::factory()->create(['name' => 'Hidden']);

        OrganizationMember::factory()->create([
            'organization_id' => $visible->id,
            'user_id' => $user->id,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        OrganizationMember::factory()->suspended()->create([
            'organization_id' => $hidden->id,
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Visible'])
            ->assertJsonMissing(['name' => 'Hidden']);
    }

    public function test_active_organization_header_is_required_for_member_routes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/organization-members')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The active organization is required.');
    }

    public function test_admin_can_invite_member_and_member_can_accept_once(): void
    {
        [$admin, $organization] = $this->createOrganizationAdmin();
        $invited = User::factory()->create(['email' => 'invited@example.com']);

        Sanctum::actingAs($admin);

        $invitationResponse = $this
            ->withHeader('X-Organization-Id', (string) $organization->id)
            ->postJson('/api/v1/organization-invitations', [
                'email' => 'invited@example.com',
                'role' => OrganizationMember::ROLE_ASSISTANT,
            ]);

        $invitationResponse->assertCreated();

        $invitation = OrganizationInvitation::firstOrFail();

        Sanctum::actingAs($invited);

        $this->postJson("/api/v1/organization-invitations/{$invitation->token}/accept")
            ->assertOk()
            ->assertJsonPath('data.role', OrganizationMember::ROLE_ASSISTANT);

        $this->postJson("/api/v1/organization-invitations/{$invitation->token}/accept")
            ->assertUnprocessable();
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $user = User::factory()->create(['email' => 'invited@example.com']);
        $invitation = OrganizationInvitation::factory()->expired()->create([
            'email' => 'invited@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/organization-invitations/{$invitation->token}/accept")
            ->assertUnprocessable();
    }

    public function test_admin_cannot_suspend_last_active_admin(): void
    {
        [$admin, $organization, $membership] = $this->createOrganizationAdmin();
        Sanctum::actingAs($admin);

        $this
            ->withHeader('X-Organization-Id', (string) $organization->id)
            ->patchJson("/api/v1/organization-members/{$membership->id}/suspend")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The last active administrator cannot be suspended.');
    }

    public function test_member_from_another_organization_cannot_use_active_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        Sanctum::actingAs($user);

        $this
            ->withHeader('X-Organization-Id', (string) $organization->id)
            ->getJson('/api/v1/organization-members')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createOrganizationAdmin(): array
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();
        $membership = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$admin, $organization, $membership];
    }
}
