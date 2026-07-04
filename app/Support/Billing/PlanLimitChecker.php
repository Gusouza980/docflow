<?php

namespace App\Support\Billing;

use App\Exceptions\PlanFeatureUnavailableException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Organization;
use App\Models\Plan;

class PlanLimitChecker
{
    public function __construct(
        private ResolvesOrganizationPlan $resolvesOrganizationPlan,
        private OrganizationUsage $organizationUsage,
    ) {}

    public function hasFeature(Organization $organization, string $feature): bool
    {
        return (bool) ($this->resolvesOrganizationPlan->effectiveFeatures($organization)[$feature] ?? false);
    }

    public function assertFeature(Organization $organization, string $feature): void
    {
        if (! $this->hasFeature($organization, $feature)) {
            throw new PlanFeatureUnavailableException($feature);
        }
    }

    public function assertWithinLimit(Organization $organization, string $metric, int $increment = 0): void
    {
        $limits = $this->resolvesOrganizationPlan->effectiveLimits($organization);
        $limit = (int) ($limits[$metric] ?? 0);

        if ($limit === Plan::LIMIT_UNLIMITED) {
            return;
        }

        $current = $this->currentUsage($organization, $metric) + $increment;

        if ($current > $limit) {
            throw new PlanLimitExceededException($metric, $limit, $current);
        }
    }

    public function assertStorageWithinLimit(Organization $organization, int $additionalBytes): void
    {
        $limits = $this->resolvesOrganizationPlan->effectiveLimits($organization);
        $limitMb = (int) ($limits['max_storage_mb'] ?? 0);

        if ($limitMb === Plan::LIMIT_UNLIMITED) {
            return;
        }

        $currentMb = $this->organizationUsage->storageMb($organization);
        $additionalMb = (int) ceil($additionalBytes / 1024 / 1024);

        if (($currentMb + $additionalMb) > $limitMb) {
            throw new PlanLimitExceededException('max_storage_mb', $limitMb, $currentMb + $additionalMb);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function usageSummary(Organization $organization): array
    {
        $organization->loadMissing(['subscription.plan', 'plan']);
        $plan = $this->resolvesOrganizationPlan->planFor($organization);
        $limits = $this->resolvesOrganizationPlan->effectiveLimits($organization);
        $features = $this->resolvesOrganizationPlan->effectiveFeatures($organization);
        $usage = $this->organizationUsage->counts($organization);
        $subscriptionSummary = app(OrganizationAccessibility::class)->summaryFor($organization);

        $limitsDetail = collect(config('docflow.plan_limits', []))
            ->map(function (array $meta, string $key) use ($limits, $usage): array {
                $limit = (int) ($limits[$key] ?? 0);
                $current = (int) ($usage[$key] ?? 0);

                return [
                    'key' => $key,
                    'label' => $meta['label'],
                    'unit' => $meta['unit'],
                    'limit' => $limit,
                    'current' => $current,
                    'unlimited' => $limit === Plan::LIMIT_UNLIMITED,
                    'percentage' => $limit > 0 && $limit !== Plan::LIMIT_UNLIMITED
                        ? min(100, (int) round(($current / $limit) * 100))
                        : 0,
                ];
            })
            ->values()
            ->all();

        $featuresDetail = collect(config('docflow.plan_features', []))
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'enabled' => (bool) ($features[$key] ?? false),
            ])
            ->values()
            ->all();

        $warnings = collect($limitsDetail)
            ->filter(fn (array $item): bool => ! $item['unlimited'] && $item['limit'] > 0 && $item['percentage'] >= 80)
            ->pluck('label')
            ->values()
            ->all();

        return [
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_cents' => $plan->price_cents,
                'billing_interval' => $plan->billing_interval,
            ],
            'limits' => $limitsDetail,
            'features' => $featuresDetail,
            'warnings' => $warnings,
            'has_warnings' => count($warnings) > 0,
            'subscription' => $subscriptionSummary,
        ];
    }

    private function currentUsage(Organization $organization, string $metric): int
    {
        return match ($metric) {
            'max_members' => $this->organizationUsage->seatCount($organization),
            'max_clients' => $this->organizationUsage->clientsCount($organization),
            'max_storage_mb' => $this->organizationUsage->storageMb($organization),
            'max_portal_accesses' => $this->organizationUsage->portalAccessesCount($organization),
            default => 0,
        };
    }
}
