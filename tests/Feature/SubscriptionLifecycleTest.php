<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_creating_organization_generates_trialing_subscription_with_essencial_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/organizations', [
                'name' => 'Nova Org Trial',
                'document' => '12345678901234',
                'email' => 'nova@example.com',
                'timezone' => 'America/Sao_Paulo',
            ])
            ->assertRedirect('/organizations');

        $organization = Organization::query()->where('name', 'Nova Org Trial')->firstOrFail();
        $essencialPlan = Plan::query()->where('slug', 'essencial')->firstOrFail();

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $essencialPlan->id,
            'status' => Subscription::STATUS_TRIALING,
        ]);

        $subscription = $organization->subscription;
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->isFuture());
    }

    public function test_manually_suspended_organization_is_blocked_on_web_and_api(): void
    {
        [$user, $organization] = $this->createAdminContext();
        $organization->update(['status' => Organization::STATUS_SUSPENDED]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertRedirect(route('subscription.required'));

        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', (string) $organization->id)
            ->getJson('/api/v1/clients')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_inaccessible');
    }

    public function test_expired_trial_redirects_to_subscription_required(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertRedirect(route('subscription.required'));
    }

    public function test_past_due_within_grace_allows_access(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_PAST_DUE,
            'past_due_at' => now()->subDays(2),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_past_due_after_grace_blocks_access_and_command_suspends_organization(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_PAST_DUE,
            'past_due_at' => now()->subDays(10),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertRedirect(route('subscription.required'));

        $this->artisan('subscriptions:apply-grace-expiry')->assertSuccessful();

        $organization->refresh();
        $organization->subscription->refresh();

        $this->assertSame(Organization::STATUS_SUSPENDED, $organization->status);
        $this->assertSame(Subscription::STATUS_CANCELED, $organization->subscription->status);
    }

    public function test_platform_admin_extending_trial_restores_access(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertRedirect(route('subscription.required'));

        $this->actingAs($platformAdmin)
            ->post("/platform/organizations/{$organization->id}/subscription/extend-trial", ['days' => 14])
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_platform_admin_change_plan_updates_plan_limit_checker(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        [$user, $organization] = $this->createAdminContext();

        $profissionalPlan = Plan::query()->where('slug', 'profissional')->firstOrFail();

        $this->actingAs($platformAdmin)
            ->post("/platform/organizations/{$organization->id}/subscription/change-plan", [
                'plan_id' => $profissionalPlan->id,
            ])
            ->assertRedirect();

        $organization->refresh();

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/organizations/plan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.plan.slug', 'profissional'));
    }

    public function test_portal_is_blocked_when_organization_is_suspended(): void
    {
        [$user, $organization, $member] = $this->createAdminContext();
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        $access = ClientPortalAccess::factory()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'status' => ClientPortalAccess::STATUS_ACTIVE,
            'password_set_at' => now(),
        ]);

        $organization->update(['status' => Organization::STATUS_SUSPENDED]);

        $this->actingAs($access, 'portal')
            ->get(route('client-portal.dashboard'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_expire_trials_command_is_idempotent(): void
    {
        [$user, $organization] = $this->createAdminContext();

        $organization->subscription->update([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('billing:generate-invoices')->assertSuccessful();

        $this->artisan('subscriptions:expire-trials')->assertSuccessful();
        $this->artisan('subscriptions:expire-trials')->assertSuccessful();

        $organization->refresh();

        $this->assertSame(Subscription::STATUS_TRIALING, $organization->subscription->status);
        $this->assertSame(Organization::STATUS_ACTIVE, $organization->status);
        $this->assertDatabaseHas('subscription_invoices', [
            'organization_id' => $organization->id,
            'status' => SubscriptionInvoice::STATUS_OPEN,
        ]);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createAdminContext(?Organization $organization = null): array
    {
        $organization ??= Organization::factory()->create([
            'plan_id' => Plan::query()->where('slug', 'essencial')->value('id'),
        ]);
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }
}
