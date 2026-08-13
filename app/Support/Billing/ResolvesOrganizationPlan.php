<?php

namespace App\Support\Billing;

use App\Models\Organization;
use App\Models\OrganizationPlanOverride;
use App\Models\Plan;

class ResolvesOrganizationPlan
{
    public function planFor(Organization $organization): Plan
    {
        $organization->loadMissing(['subscription.plan', 'plan']);

        if ($organization->subscription?->plan) {
            return $organization->subscription->plan;
        }

        if ($organization->relationLoaded('plan') && $organization->plan) {
            return $organization->plan;
        }

        $plan = $organization->plan_id
            ? Plan::query()->find($organization->plan_id)
            : null;

        if ($plan) {
            return $plan;
        }

        $defaultPlan = Plan::query()
            ->where('slug', config('docflow.default_plan_slug', 'essencial'))
            ->where('is_active', true)
            ->first();

        if ($defaultPlan) {
            return $defaultPlan;
        }

        return Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->firstOrFail();
    }

    public function activeOverrideFor(Organization $organization): ?OrganizationPlanOverride
    {
        return OrganizationPlanOverride::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, int>
     */
    public function effectiveLimits(Organization $organization): array
    {
        $plan = $this->planFor($organization);
        $limits = $plan->limits ?? [];
        $override = $this->activeOverrideFor($organization);

        if ($override?->limits) {
            foreach ($override->limits as $key => $value) {
                if ($value !== null) {
                    $limits[$key] = (int) $value;
                }
            }
        }

        return $limits;
    }

    /**
     * @return array<string, bool>
     */
    public function effectiveFeatures(Organization $organization): array
    {
        $plan = $this->planFor($organization);
        $features = app(NormalizesPlanFeatures::class)->backfillMissing($plan->features, $plan->slug);
        $override = $this->activeOverrideFor($organization);

        if ($override?->features) {
            foreach ($override->features as $key => $value) {
                if ($value !== null) {
                    $features[$key] = (bool) $value;
                }
            }
        }

        return $features;
    }
}
