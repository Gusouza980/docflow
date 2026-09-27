<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_platform_admin_can_impersonate_organization_owner(): void
    {
        [$admin, $organization, $owner] = $this->provisionTenant();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner, 'web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertSame($organization->id, session('active_organization_id'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('auth.impersonation.active', true)
                ->where('auth.impersonation.owner_name', $owner->name)
                ->where('auth.user.id', $owner->id));

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditLog::ACTION_IMPERSONATION_STARTED,
            'platform_admin_user_id' => $admin->id,
            'subject_id' => $organization->id,
        ]);
    }

    public function test_stop_impersonation_returns_to_admin_and_keeps_admin_session(): void
    {
        [$admin, $organization, $owner] = $this->provisionTenant();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('dashboard'));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('admin.organizations.show', $organization));

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNull(session('impersonator_id'));

        $this->get(route('admin.organizations.show', $organization))->assertOk();

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditLog::ACTION_IMPERSONATION_STOPPED,
            'platform_admin_user_id' => $admin->id,
            'subject_id' => $organization->id,
        ]);
    }

    public function test_impersonation_cannot_accept_invitations(): void
    {
        [$admin, $organization, $owner] = $this->provisionTenant();
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'email' => $owner->email,
            'status' => OrganizationInvitation::STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('dashboard'));

        $this->post(route('web.invitations.accept', $invitation->token))
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_impersonate(): void
    {
        [, $organization, $owner] = $this->provisionTenant();

        $this->actingAs($owner)
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('admin.login'));
    }

    public function test_impersonation_is_rejected_without_owner(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = Organization::factory()->create();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.organizations.show', $organization))
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('admin.organizations.show', $organization))
            ->assertSessionHasErrors('organization');
    }

    public function test_impersonation_works_when_tenant_is_suspended(): void
    {
        [$admin, $organization, $owner] = $this->provisionTenant();
        $organization->update(['status' => Organization::STATUS_SUSPENDED]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.impersonation.organization_suspended', true));
    }

    public function test_stop_impersonation_is_forbidden_without_active_session(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $organization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post(route('impersonation.stop'))
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_impersonation_cannot_switch_to_another_organization(): void
    {
        [$admin, $organization, $owner] = $this->provisionTenant();
        $other = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $other->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('dashboard'));

        $this->post(route('organizations.switch', $other))->assertForbidden();
        $this->assertSame($organization->id, session('active_organization_id'));
    }

    public function test_admin_logout_during_impersonation_records_stop_audit(): void
    {
        [$admin, $organization] = $this->provisionTenant();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.organizations.impersonate', $organization))
            ->assertRedirect(route('dashboard'));

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest('web');
        $this->assertGuest('admin');
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditLog::ACTION_IMPERSONATION_STOPPED,
            'platform_admin_user_id' => $admin->id,
            'subject_id' => $organization->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Organization, 2: User}
     */
    private function provisionTenant(): array
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create(['is_platform_admin' => false]);
        $organization = Organization::factory()->create();

        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$admin, $organization, $owner];
    }
}
