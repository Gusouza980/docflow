<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\Plan;
use App\Support\Billing\PlanLimitChecker;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlanCrmFeatureTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_crm_feature_available_from_profissional(): void
    {
        $this->seed(PlanSeeder::class);

        $essencial = Organization::factory()->create([
            'plan_id' => Plan::query()->where('slug', 'essencial')->value('id'),
        ]);
        $essencial->subscription->update(['plan_id' => $essencial->plan_id]);

        $profissional = Organization::factory()->create([
            'plan_id' => Plan::query()->where('slug', 'profissional')->value('id'),
        ]);
        $profissional->subscription->update(['plan_id' => $profissional->plan_id]);

        $checker = app(PlanLimitChecker::class);

        $this->assertFalse($checker->hasFeature($essencial, 'crm'));
        $this->assertTrue($checker->hasFeature($profissional, 'crm'));
    }
}
