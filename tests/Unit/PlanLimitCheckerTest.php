<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationPlanOverride;
use App\Models\Plan;
use App\Support\Billing\OrganizationUsage;
use App\Support\Billing\PlanLimitChecker;
use App\Support\Billing\ResolvesOrganizationPlan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlanLimitCheckerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_effective_limits_merge_active_override(): void
    {
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $organization = Organization::factory()->create(['plan_id' => $plan->id]);

        OrganizationPlanOverride::factory()->create([
            'organization_id' => $organization->id,
            'limits' => ['max_members' => 10],
            'expires_at' => now()->addWeek(),
        ]);

        $resolver = app(ResolvesOrganizationPlan::class);

        $this->assertSame(10, $resolver->effectiveLimits($organization)['max_members']);
    }

    public function test_expired_override_is_ignored(): void
    {
        $plan = Plan::query()->where('slug', 'essencial')->firstOrFail();
        $organization = Organization::factory()->create(['plan_id' => $plan->id]);

        OrganizationPlanOverride::factory()->create([
            'organization_id' => $organization->id,
            'limits' => ['max_members' => 99],
            'expires_at' => now()->subDay(),
        ]);

        $resolver = app(ResolvesOrganizationPlan::class);

        $this->assertSame(3, $resolver->effectiveLimits($organization)['max_members']);
    }

    public function test_organization_usage_counts_members_and_clients(): void
    {
        $organization = Organization::factory()->create();
        $usage = app(OrganizationUsage::class);

        $this->assertSame(0, $usage->clientsCount($organization));
        $this->assertSame(0, $usage->membersCount($organization));
    }

    public function test_usage_summary_includes_plan_name(): void
    {
        $plan = Plan::query()->where('slug', 'profissional')->firstOrFail();
        $organization = Organization::factory()->create(['plan_id' => $plan->id]);

        $summary = app(PlanLimitChecker::class)->usageSummary($organization);

        $this->assertSame('Profissional', $summary['plan']['name']);
        $this->assertTrue(collect($summary['features'])->firstWhere('key', 'portal')['enabled']);
    }
}
