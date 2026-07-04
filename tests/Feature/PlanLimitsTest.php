<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_invite_is_blocked_when_member_limit_reached(): void
    {
        [$user, $organization, $member] = $this->createAdminMember();
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $organization->update(['plan_id' => $plan->id]);

        OrganizationMember::factory()->count(2)->create([
            'organization_id' => $organization->id,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/organization-invitations', [
                'email' => 'novo@example.com',
                'role' => OrganizationMember::ROLE_ASSISTANT,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'novo@example.com',
        ]);
    }

    public function test_client_creation_is_blocked_when_client_limit_reached(): void
    {
        [$user, $organization, $member] = $this->createAdminMember();
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $organization->update(['plan_id' => $plan->id]);

        Client::factory()->count(50)->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/clients', [
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => 'Cliente Extra',
                'access_policy' => Client::ACCESS_ALL_MEMBERS,
                'responsible_member_ids' => [$member->id],
                'individual_profile' => [
                    'full_name' => 'Cliente Extra',
                    'cpf' => '52998224725',
                ],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('clients', [
            'organization_id' => $organization->id,
            'display_name' => 'Cliente Extra',
        ]);
    }

    public function test_portal_access_is_blocked_without_portal_feature(): void
    {
        [$user, $organization, $member] = $this->createAdminMember();
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $organization->update(['plan_id' => $plan->id]);

        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/portal/accesses', [
                'client_id' => $client->id,
                'name' => 'Maria Cliente',
                'email' => 'maria@example.com',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('client_portal_accesses', 0);
    }

    public function test_assistant_cannot_access_organization_plan_page(): void
    {
        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ASSISTANT);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/organizations/plan')
            ->assertForbidden();
    }

    public function test_admin_can_view_organization_plan_page(): void
    {
        [$user, $organization] = $this->createAdminMember();
        $profissionalPlan = Plan::query()->where('slug', 'profissional')->firstOrFail();
        $organization->update(['plan_id' => $profissionalPlan->id]);
        $organization->subscription->update(['plan_id' => $profissionalPlan->id]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/organizations/plan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Plan', false)
                ->where('summary.plan.name', 'Profissional'));
    }

    public function test_platform_override_allows_additional_member_invite(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        [$user, $organization, $member] = $this->createAdminMember();
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $organization->update(['plan_id' => $plan->id]);

        OrganizationMember::factory()->count(2)->create([
            'organization_id' => $organization->id,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($platformAdmin)
            ->post("/platform/organizations/{$organization->id}/overrides", [
                'reason' => 'Cortesia comercial',
                'limits' => ['max_members' => 10],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->post('/organization-invitations', [
                'email' => 'novo@example.com',
                'role' => OrganizationMember::ROLE_ASSISTANT,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'email' => 'novo@example.com',
        ]);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createAdminMember(?Organization $organization = null): array
    {
        return $this->createMember(OrganizationMember::ROLE_ADMIN, $organization);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createMember(string $role, ?Organization $organization = null): array
    {
        $organization ??= Organization::factory()->create([
            'plan_id' => Plan::query()->where('slug', 'essencial')->value('id'),
        ]);
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
