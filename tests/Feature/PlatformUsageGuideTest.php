<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Platform\UsageGuideCatalog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformUsageGuideTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_admin_can_view_guides_index(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get('/platform/guides')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Guides/Index', false)
                ->has('guides')
                ->where('guides.0.slug', 'visao-geral'));
    }

    public function test_platform_admin_can_view_portal_guide_with_client_flows(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get('/platform/guides/portal-do-cliente')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Guides/Show', false)
                ->where('guide.slug', 'portal-do-cliente')
                ->where('guide.title', 'Portal do cliente (fluxo completo)')
                ->has('guide.sections')
                ->has('guides'));
    }

    public function test_platform_admin_can_view_plans_guide_with_feature_matrix(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get('/platform/guides/planos-limites-features')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Guides/Show', false)
                ->where('guide.slug', 'planos-limites-features')
                ->has('guide.sections.0.tables', 2));
    }

    public function test_unknown_guide_returns_not_found(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get('/platform/guides/nao-existe')
            ->assertNotFound();
    }

    public function test_tenant_admin_cannot_access_platform_guides(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['is_platform_admin' => false]);
        OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get('/platform/guides')
            ->assertForbidden();
    }

    public function test_catalog_exposes_all_expected_slugs(): void
    {
        $slugs = app(UsageGuideCatalog::class)->slugs();

        $this->assertContains('visao-geral', $slugs);
        $this->assertContains('portal-do-cliente', $slugs);
        $this->assertContains('crm-onboarding', $slugs);
        $this->assertContains('automacoes', $slugs);
        $this->assertContains('platform-admin', $slugs);
        $this->assertCount(14, $slugs);
    }
}
