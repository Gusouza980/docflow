<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Deadline;
use App\Models\OrganizationMember;
use App\Models\User;

class DeadlinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Deadline $deadline): bool
    {
        $membership = $user->activeMembershipFor($deadline->organization);

        if (! $membership) {
            return false;
        }

        return ! $deadline->client || $this->canAccessClient($user, $deadline->client);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, Deadline $deadline): bool
    {
        $membership = $user->activeMembershipFor($deadline->organization);

        if (! $membership || $membership->role === OrganizationMember::ROLE_READONLY) {
            return false;
        }

        return ! $deadline->client || $this->canAccessClient($user, $deadline->client);
    }

    private function canAccessClient(User $user, Client $client): bool
    {
        return app(ClientPolicy::class)->view($user, $client);
    }
}
