<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\OrganizationMember;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        $client = $contract->client;

        if ($client === null) {
            return $this->canManageOrphanContract($user, $contract);
        }

        return app(ClientPolicy::class)->view($user, $client);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, Contract $contract): bool
    {
        $client = $contract->client;

        if ($client === null) {
            return $this->canManageOrphanContract($user, $contract);
        }

        return app(ClientPolicy::class)->update($user, $client);
    }

    public function manage(User $user, Contract $contract): bool
    {
        $membership = $user->activeMembershipFor($contract->organization);

        if (! $membership
            || $membership->organization_id !== $contract->organization_id
            || (! $membership->isAdmin() && ! $membership->isManager())) {
            return false;
        }

        $client = $contract->client;

        if ($client === null) {
            return true;
        }

        return app(ClientPolicy::class)->view($user, $client);
    }

    private function canManageOrphanContract(User $user, Contract $contract): bool
    {
        $membership = $user->activeMembershipFor($contract->organization);

        return $membership !== null
            && ($membership->isAdmin() || $membership->isManager());
    }
}
