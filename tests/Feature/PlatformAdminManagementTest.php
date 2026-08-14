<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAdminManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_admin_can_access_platform_dashboard(): void
    {
        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin)
            ->get('/platform')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Dashboard/Index', false)
                ->has('metrics.total_organizations'));
    }

    public function test_tenant_admin_cannot_access_platform(): void
    {
        [$user, $organization] = $this->createTenantMember(OrganizationMember::ROLE_ADMIN);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/platform')
            ->assertForbidden();
    }

    public function test_assistant_cannot_access_platform(): void
    {
        [$user, $organization] = $this->createTenantMember(OrganizationMember::ROLE_ASSISTANT);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/platform')
            ->assertForbidden();
    }

    public function test_platform_admin_can_suspend_organization_and_audit(): void
    {
        $admin = $this->createPlatformAdmin();
        $organization = Organization::factory()->create(['status' => Organization::STATUS_ACTIVE]);

        $this->actingAs($admin)
            ->post("/platform/organizations/{$organization->id}/suspend", [
                'reason' => 'Inadimplência',
            ])
            ->assertRedirect(route('platform.organizations.show', $organization));

        $this->assertSame(Organization::STATUS_SUSPENDED, $organization->fresh()->status);

        $this->assertDatabaseHas('platform_audit_logs', [
            'platform_admin_user_id' => $admin->id,
            'action' => PlatformAuditLog::ACTION_ORGANIZATION_SUSPENDED,
            'subject_type' => $organization->getMorphClass(),
            'subject_id' => $organization->id,
        ]);
    }

    public function test_platform_admin_can_reactivate_organization(): void
    {
        $admin = $this->createPlatformAdmin();
        $organization = Organization::factory()->create(['status' => Organization::STATUS_SUSPENDED]);

        $this->actingAs($admin)
            ->post("/platform/organizations/{$organization->id}/reactivate")
            ->assertRedirect(route('platform.organizations.show', $organization));

        $this->assertSame(Organization::STATUS_ACTIVE, $organization->fresh()->status);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditLog::ACTION_ORGANIZATION_REACTIVATED,
            'subject_id' => $organization->id,
        ]);
    }

    public function test_platform_admin_can_update_platform_notes_with_audit(): void
    {
        $admin = $this->createPlatformAdmin();
        $organization = Organization::factory()->create();

        $this->actingAs($admin)
            ->patch("/platform/organizations/{$organization->id}/notes", [
                'platform_notes' => 'Cliente enterprise — tratar com prioridade.',
            ])
            ->assertRedirect(route('platform.organizations.show', $organization));

        $this->assertSame('Cliente enterprise — tratar com prioridade.', $organization->fresh()->platform_notes);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditLog::ACTION_ORGANIZATION_NOTES_UPDATED,
            'subject_id' => $organization->id,
        ]);
    }

    public function test_platform_organization_index_lists_multiple_tenants(): void
    {
        $admin = $this->createPlatformAdmin();

        Organization::factory()->count(3)->create(['status' => Organization::STATUS_ACTIVE]);
        Organization::factory()->create(['status' => Organization::STATUS_SUSPENDED, 'name' => 'Org Suspensa']);

        $this->actingAs($admin)
            ->get('/platform/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Organizations/Index', false)
                ->where('organizations.meta.total', 4)
                ->has('organizations.data', 4));

        $this->actingAs($admin)
            ->get('/platform/organizations?status=suspended')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('organizations.meta.total', 1)
                ->where('organizations.data.0.name', 'Org Suspensa'));
    }

    public function test_platform_admin_can_provision_tenant_user_and_organization(): void
    {
        $this->seed(PlanSeeder::class);
        Notification::fake();

        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin)
            ->post('/platform/organizations', [
                'owner_name' => 'Ana Escritório',
                'owner_email' => 'ana@escritorio.test',
                'name' => 'Escritório Ana',
                'document' => '11222333000181',
                'timezone' => 'America/Sao_Paulo',
            ])
            ->assertRedirect()
            ->assertSessionHas('reset_url')
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'ana@escritorio.test')->firstOrFail();
        $organization = Organization::query()->where('name', 'Escritório Ana')->firstOrFail();

        $this->assertFalse($user->isPlatformAdmin());
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'status' => Subscription::STATUS_TRIALING,
        ]);
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => PlatformAuditLog::ACTION_TENANT_PROVISIONED,
            'subject_id' => $organization->id,
            'platform_admin_user_id' => $admin->id,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_tenant_cannot_provision_from_platform(): void
    {
        [$user, $organization] = $this->createTenantMember(OrganizationMember::ROLE_ADMIN);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/platform/organizations', [
                'owner_name' => 'Outro',
                'owner_email' => 'outro@test.com',
                'name' => 'Outra Org',
            ])
            ->assertForbidden();
    }

    public function test_provisioning_rejects_duplicate_owner_email(): void
    {
        $this->seed(PlanSeeder::class);
        $admin = $this->createPlatformAdmin();
        User::factory()->create(['email' => 'ana@escritorio.test']);

        $this->actingAs($admin)
            ->from('/platform/organizations')
            ->post('/platform/organizations', [
                'owner_name' => 'Ana',
                'owner_email' => 'ana@escritorio.test',
                'name' => 'Escritório Ana',
            ])
            ->assertRedirect('/platform/organizations')
            ->assertSessionHasErrors('owner_email');
    }

    public function test_provisioned_client_reaches_dashboard_after_login(): void
    {
        $this->seed(PlanSeeder::class);
        Notification::fake();

        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin)
            ->post('/platform/organizations', [
                'owner_name' => 'Ana Escritório',
                'owner_email' => 'ana@escritorio.test',
                'name' => 'Escritório Ana',
            ]);

        $this->post('/logout');

        $user = User::query()->where('email', 'ana@escritorio.test')->firstOrFail();
        $user->update(['password' => 'password']);

        $this->post('/login', [
            'email' => 'ana@escritorio.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index', false));
    }

    public function test_organization_is_operational_helper(): void
    {
        $active = Organization::factory()->create(['status' => Organization::STATUS_ACTIVE]);
        $suspended = Organization::factory()->create(['status' => Organization::STATUS_SUSPENDED]);

        $this->assertTrue($active->isOperational());
        $this->assertFalse($suspended->isOperational());
    }

    private function createPlatformAdmin(): User
    {
        return User::factory()->create(['is_platform_admin' => true]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function createTenantMember(string $role): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization];
    }
}
