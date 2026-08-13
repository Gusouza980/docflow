<?php

namespace App\Policies;

use App\Models\OrganizationMember;
use App\Models\ServiceType;
use App\Models\User;

class ServiceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceType $serviceType): bool
    {
        return $this->membership($user, $serviceType)?->organization_id === $serviceType->organization_id;
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && ($membership->isAdmin() || $membership->isManager());
    }

    public function update(User $user, ServiceType $serviceType): bool
    {
        $membership = $this->membership($user, $serviceType);

        return $membership
            && $membership->organization_id === $serviceType->organization_id
            && ($membership->isAdmin() || $membership->isManager());
    }

    public function delete(User $user, ServiceType $serviceType): bool
    {
        return $this->update($user, $serviceType);
    }

    private function membership(User $user, ServiceType $serviceType): ?OrganizationMember
    {
        return $user->activeMembershipFor($serviceType->organization);
    }
}
