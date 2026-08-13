<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Support\Billing\NormalizesPlanFeatures;
use App\Support\Billing\PlanLimitChecker;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlanFeaturesBackfillTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unlimited_plan_missing_crm_key_gets_all_features(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'unlimited',
            'name' => 'Unlimited',
            'features' => [
                'portal' => true,
                'finance_advanced' => true,
                'reports_scheduling' => true,
                'audit' => true,
                'automations' => true,
                // crm ausente de propósito
            ],
        ]);

        $organization = Organization::factory()->create(['plan_id' => $plan->id]);
        $organization->subscription?->update(['plan_id' => $plan->id]);

        $this->assertTrue(app(PlanLimitChecker::class)->hasFeature($organization, 'crm'));
        $this->assertTrue(app(PlanLimitChecker::class)->hasFeature($organization, 'automations'));
    }

    public function test_backfill_persists_crm_on_unlimited_and_premium_like_plans(): void
    {
        $unlimited = Plan::factory()->create([
            'slug' => 'unlimited',
            'features' => [
                'portal' => true,
                'finance_advanced' => true,
                'reports_scheduling' => true,
                'audit' => true,
                'automations' => true,
            ],
        ]);
        $premium = Plan::factory()->create([
            'slug' => 'premium',
            'features' => [
                'portal' => true,
                'finance_advanced' => true,
                'reports_scheduling' => true,
                'audit' => true,
                'automations' => true,
            ],
        ]);
        $basic = Plan::factory()->create([
            'slug' => 'basico',
            'features' => [
                'portal' => false,
                'finance_advanced' => false,
                'reports_scheduling' => false,
                'audit' => false,
                'automations' => false,
            ],
        ]);

        $normalizer = app(NormalizesPlanFeatures::class);

        foreach ([$unlimited, $premium, $basic] as $plan) {
            $plan->forceFill([
                'features' => $normalizer->backfillMissing($plan->features, $plan->slug),
            ])->save();
        }

        $this->assertTrue($unlimited->fresh()->features['crm']);
        $this->assertTrue($premium->fresh()->features['crm']);
        $this->assertFalse($basic->fresh()->features['crm']);
    }

    public function test_platform_update_keeps_crm_on_unlimited_even_if_omitted(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $plan = Plan::factory()->create([
            'slug' => 'unlimited',
            'features' => [
                'portal' => true,
                'finance_advanced' => true,
                'reports_scheduling' => true,
                'audit' => true,
                'automations' => true,
                'crm' => true,
            ],
        ]);

        $this->actingAs($admin)
            ->patch("/platform/plans/{$plan->id}", [
                'slug' => 'unlimited',
                'name' => 'Unlimited',
                'description' => 'Tudo incluso',
                'price_cents' => '999,00',
                'billing_interval' => Plan::BILLING_INTERVAL_MONTH,
                'trial_days' => 14,
                'limits' => [
                    'max_members' => -1,
                    'max_clients' => -1,
                    'max_storage_mb' => 5000,
                    'max_portal_accesses' => -1,
                ],
                'features' => [
                    'portal' => true,
                    'finance_advanced' => true,
                    'reports_scheduling' => true,
                    'audit' => true,
                    'automations' => true,
                    // crm omitido no payload (bug antigo do form)
                ],
                'is_public' => true,
                'is_active' => true,
                'sort_order' => 3,
            ])
            ->assertRedirect();

        $this->assertTrue($plan->fresh()->features['crm']);
    }

    public function test_normalizer_marks_escritorio_as_all_access(): void
    {
        $features = app(NormalizesPlanFeatures::class)->normalize([
            'portal' => true,
            'crm' => false,
        ], 'escritorio');

        $this->assertTrue($features['crm']);
        $this->assertTrue($features['automations']);
    }
}
