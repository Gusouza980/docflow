<?php

namespace App\Support\Billing;

class NormalizesPlanFeatures
{
    /**
     * @param  array<string, mixed>|null  $features
     * @return array<string, bool>
     */
    public function normalize(?array $features, ?string $planSlug = null): array
    {
        $known = array_keys(config('docflow.plan_features', []));
        $normalized = [];

        foreach ($known as $key) {
            $normalized[$key] = (bool) ($features[$key] ?? false);
        }

        if ($this->isAllAccessPlan($planSlug, $features ?? [])) {
            foreach ($known as $key) {
                $normalized[$key] = true;
            }
        }

        return $normalized;
    }

    /**
     * Backfill missing feature keys without forcing all-access plans to lose intentional disables.
     *
     * @param  array<string, mixed>|null  $features
     * @return array<string, bool>
     */
    public function backfillMissing(?array $features, ?string $planSlug = null): array
    {
        $features = $features ?? [];
        $known = array_keys(config('docflow.plan_features', []));

        if ($this->isAllAccessPlan($planSlug, $features)) {
            return $this->normalize($features, $planSlug);
        }

        $defaultsFromPeers = (bool) (($features['portal'] ?? false) && ($features['automations'] ?? false));

        foreach ($known as $key) {
            if (! array_key_exists($key, $features)) {
                $features[$key] = $key === 'crm' ? $defaultsFromPeers : false;
            }
        }

        return $this->normalize($features, $planSlug);
    }

    /**
     * @param  array<string, mixed>  $features
     */
    private function isAllAccessPlan(?string $planSlug, array $features): bool
    {
        if (in_array($planSlug, ['unlimited', 'escritorio'], true)) {
            return true;
        }

        return str_contains(strtolower((string) $planSlug), 'unlimited');
    }
}
