<?php

namespace App\Actions\Organizations;

use App\Actions\Billing\CreateTrialSubscription;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateOrganization
{
    public function __construct(private CreateTrialSubscription $createTrialSubscription) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data): Organization {
            $plan = $this->resolvePlan($data['plan_id'] ?? null);
            unset($data['plan_id']);

            $organization = Organization::create([
                ...$data,
                'plan_id' => $plan?->id,
            ]);

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'role' => OrganizationMember::ROLE_ADMIN,
                'status' => OrganizationMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            setPermissionsTeamId($organization->id);

            $role = Role::findOrCreate(OrganizationMember::ROLE_ADMIN, 'web');
            $owner->assignRole($role);
            $owner->unsetRelation('roles')->unsetRelation('permissions');

            $this->createTrialSubscription->execute($organization, $plan);

            return $organization;
        });
    }

    private function resolvePlan(mixed $planId): ?Plan
    {
        if ($planId) {
            $selectedPlan = Plan::query()
                ->whereKey($planId)
                ->where('is_active', true)
                ->first();

            if ($selectedPlan) {
                return $selectedPlan;
            }
        }

        return Plan::query()
            ->where('slug', config('docflow.default_plan_slug', 'essencial'))
            ->where('is_active', true)
            ->first();
    }
}
